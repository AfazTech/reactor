<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ConfirmsDestructiveCommands;
use Reactor\Console\Commands\MigrateRefreshCommand as BaseMigrateRefreshCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Overrides the framework's migrate:refresh command so that it asks
 * for explicit confirmation before dropping every table and
 * re-running all migrations. Pass --force to skip the prompt.
 */
#[AsCommand(name: 'migrate:refresh', description: 'Reset and re-run all migrations (destructive)')]
class MigrateRefreshCommand extends BaseMigrateRefreshCommand
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
        $warning = 'migrate:refresh rolls back ALL migrations (dropping every migrated table) '
            . 'and then re-runs them. Every row in every migrated table will be permanently lost.';

        if (!$this->confirmDestructive($input, $output, $warning)) {
            return Command::FAILURE;
        }

        return parent::execute($input, $output);
    }
}
