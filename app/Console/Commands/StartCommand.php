<?php

namespace App\Console\Commands;

use Neili\Client;
use Reactor\Console\Commands\StartCommand as BaseStartCommand;
use Reactor\Contracts\LoggerInterface;
use Reactor\Core\App;
use Reactor\Core\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Application-level override of the framework's start command.
 *
 * In polling mode it verifies that no webhook is registered before
 * the poller loop starts. Polling (getUpdates) and webhooks are
 * mutually exclusive in the Telegram Bot API: while a webhook is
 * registered, getUpdates returns a 409 Conflict and the bot appears
 * silently dead. This guard turns that opaque failure into a clear,
 * actionable prompt.
 *
 * Behaviour:
 *   - No webhook: proceed as usual.
 *   - Webhook + --keep-webhook: abort with a failure code.
 *   - Webhook + --delete-webhook: delete it and proceed.
 *   - Webhook + interactive terminal: ask "Delete the webhook and
 *     continue with polling? [y/N]".
 *   - Webhook + non-interactive (spawned via ./reactor restart,
 *     systemd, cron, ...): delete automatically, because BOT_MODE=polling
 *     was an explicit operator choice and aborting would leave the
 *     detached process dead with only a confusing log entry.
 *   - Telegram unreachable: warn and let polling proceed so a
 *     transient outage does not prevent start.
 *
 * Webhook mode is unaffected: parent::execute() runs directly.
 *
 * The #[AsCommand] attribute is repeated here because Symfony does not
 * inherit attributes from parent classes; without it the command is
 * registered with an empty name and Symfony's Application::add()
 * throws a LogicException.
 */
#[AsCommand(name: 'start', description: 'Start the bot (polling or webhook mode)')]
class StartCommand extends BaseStartCommand
{
    public function __construct(
        private App $app,
        private LoggerInterface $logger,
        private Container $container,
    ) {
        parent::__construct($app, $logger);
    }

    protected function configure(): void
    {
        parent::configure();

        $this->addOption(
            'delete-webhook',
            null,
            InputOption::VALUE_NONE,
            'Automatically delete any active webhook before polling starts'
        );
        $this->addOption(
            'keep-webhook',
            null,
            InputOption::VALUE_NONE,
            'Abort when an active webhook is detected instead of prompting to delete it'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Webhook mode does not use getUpdates, so no conflict can exist.
        if ($this->app->getConfig()->getBotMode() !== 'polling') {
            return parent::execute($input, $output);
        }

        $abort = $this->ensureNoWebhook($input, $output);
        if ($abort !== null) {
            return $abort;
        }

        return parent::execute($input, $output);
    }

    /**
     * Refuse to start polling while a webhook is active.
     *
     * @return int|null Null to continue, an exit code to abort.
     */
    private function ensureNoWebhook(InputInterface $input, OutputInterface $output): ?int
    {
        if (!$this->container->has(Client::class)) {
            // No token configured; parent::execute() reports that clearly.
            return null;
        }

        try {
            $client = $this->container->get(Client::class);
            $info   = $client->getWebhookInfo()->await();
        } catch (\Throwable $e) {
            $output->writeln('<comment>⚠  Could not verify webhook status: ' . $e->getMessage() . '</comment>');
            $this->logger->warning('Webhook check failed, continuing with polling', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        $url = (string) ($info['result']['url'] ?? '');
        if ($url === '') {
            $this->logger->debug('Webhook check: no active webhook, proceeding with polling');
            return null;
        }

        $output->writeln('');
        $output->writeln('<comment>⚠  An active webhook is configured for this bot:</comment>');
        $output->writeln('   ' . $url);
        $output->writeln('');
        $output->writeln('   Polling (getUpdates) and webhooks are mutually exclusive in');
        $output->writeln('   the Telegram Bot API. Polling will fail with a 409 Conflict');
        $output->writeln('   unless the webhook is removed first.');
        $output->writeln('');

        if ($input->getOption('keep-webhook')) {
            $output->writeln('<error>Aborted (--keep-webhook): webhook is still active.</error>');
            return Command::FAILURE;
        }

        $shouldDelete = false;

        if ($input->getOption('delete-webhook')) {
            $shouldDelete = true;
        } elseif ($input->isInteractive()) {
            $question = new ConfirmationQuestion(
                'Delete the webhook and continue with polling? [y/N] ',
                false
            );
            $shouldDelete = (bool) (new QuestionHelper())->ask($input, $output, $question);
        } else {
            $output->writeln('<comment>Non-interactive mode: deleting webhook automatically.</comment>');
            $shouldDelete = true;
        }

        if (!$shouldDelete) {
            $output->writeln('<error>Aborted. Webhook is still active.</error>');
            return Command::FAILURE;
        }

        try {
            $client->deleteWebhook(true)->await();
        } catch (\Throwable $e) {
            $output->writeln('<error>Failed to delete webhook: ' . $e->getMessage() . '</error>');
            $this->logger->error('Failed to delete webhook', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }

        $output->writeln('<info>✓ Webhook deleted.</info>');
        $output->writeln('');
        $this->logger->warning('Webhook deleted to enable polling', ['previous_url' => $url]);

        return null;
    }
}
