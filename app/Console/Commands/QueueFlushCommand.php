<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ConfirmsDestructiveCommands;
use Reactor\Console\Commands\QueueFlushCommand as BaseQueueFlushCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Overrides the framework's queue:flush command so that it asks for
 * explicit confirmation before deleting every failed queue job.
 * Pass --force to skip the prompt.
 */
#[AsCommand(name: 'queue:flush', description: 'Delete all failed queue jobs (destructive)')]
class QueueFlushCommand extends BaseQueueFlushCommand
{
    use ConfirmsDestructiveCommands;

    protected function configure(): void
    {
        parent::configure();
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Skip the confirmation prompt (required in non-interactive mode)'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $warning = 'queue:flush permanently deletes every record from the failed_jobs table. '
            . 'This action cannot be undone.';

        if (!$this->confirmDestructive($input, $output, $warning)) {
            return Command::FAILURE;
        }

        return parent::execute($input, $output);
    }
}
