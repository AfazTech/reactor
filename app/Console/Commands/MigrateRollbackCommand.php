<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ConfirmsDestructiveCommands;
use Reactor\Console\Commands\MigrateRollbackCommand as BaseMigrateRollbackCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Overrides the framework's migrate:rollback command so that it asks
 * for explicit confirmation before rolling back migration batches.
 * Pass --force to skip the prompt.
 */
#[AsCommand(name: 'migrate:rollback', description: 'Rollback the last migration batch (destructive)')]
class MigrateRollbackCommand extends BaseMigrateRollbackCommand
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
        $step = (int) $input->getOption('step');
        $warning = "migrate:rollback will roll back the last {$step} migration batch(es). "
            . 'Every row in the tables belonging to those batches will be permanently lost.';

        if (!$this->confirmDestructive($input, $output, $warning)) {
            return Command::FAILURE;
        }

        return parent::execute($input, $output);
    }
}
