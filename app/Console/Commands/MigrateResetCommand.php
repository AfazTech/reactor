<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ConfirmsDestructiveCommands;
use Reactor\Console\Commands\MigrateResetCommand as BaseMigrateResetCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Overrides the framework's migrate:reset command so that it asks for
 * explicit confirmation before rolling back every migration. Pass
 * --force to skip the prompt.
 */
#[AsCommand(name: 'migrate:reset', description: 'Rollback all migrations (destructive)')]
class MigrateResetCommand extends BaseMigrateResetCommand
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
        $warning = 'migrate:reset rolls back ALL migrations, dropping every migrated table. '
            . 'Every row in every migrated table will be permanently lost.';

        if (!$this->confirmDestructive($input, $output, $warning)) {
            return Command::FAILURE;
        }

        return parent::execute($input, $output);
    }
}
