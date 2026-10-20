<?php

namespace App\Console\Commands\Concerns;

use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Shared confirmation flow for destructive console commands.
 *
 * Interactive terminal: shows a warning and a "Continue? [y/N]" prompt.
 * Non-interactive (spawned by systemd, cron, CI, ...): aborts unless the
 * caller passed --force, so a misconfigured automation cannot silently
 * wipe the database or the failed-jobs table.
 */
trait ConfirmsDestructiveCommands
{
    protected function confirmDestructive(
        InputInterface $input,
        OutputInterface $output,
        string $warning
    ): bool {
        if ($input->getOption('force')) {
            return true;
        }

        if (!$input->isInteractive()) {
            $output->writeln(
                '<error>Aborted: this is a destructive command and requires '
                . '--force in non-interactive mode.</error>'
            );
            return false;
        }

        $output->writeln('');
        $output->writeln('<comment>⚠  ' . $warning . '</comment>');
        $output->writeln('');

        $question = new ConfirmationQuestion('Continue? [y/N] ', false);

        return (bool) (new QuestionHelper())->ask($input, $output, $question);
    }
}
