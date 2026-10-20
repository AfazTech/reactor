<?php

namespace App\Providers;

use Reactor\Console\Commands\MigrateRefreshCommand as BaseMigrateRefreshCommand;
use Reactor\Console\Commands\MigrateResetCommand as BaseMigrateResetCommand;
use Reactor\Console\Commands\MigrateRollbackCommand as BaseMigrateRollbackCommand;
use Reactor\Console\Commands\QueueFlushCommand as BaseQueueFlushCommand;
use Reactor\Console\Commands\StartCommand as BaseStartCommand;
use Reactor\Console\Support\BotProcessManager;
use Reactor\Contracts\LoggerInterface;
use Reactor\Core\Config;
use Reactor\Core\Container;
use Reactor\Core\Paths;
use Reactor\Contracts\ServiceProviderInterface;
use Reactor\Contracts\DatabaseManagerInterface;
use Reactor\Contracts\LanguageInterface;
use Reactor\Contracts\CacheInterface;
use Reactor\Contracts\QueueManagerInterface;
use Reactor\Contracts\UserProviderInterface;
use Reactor\Database\Migrations\Migrator;
use Reactor\Queue\FailedJobRepository;
use Reactor\Queue\QueueManager;
use Reactor\Queue\DatabaseQueue;
use App\Console\Commands\MigrateRefreshCommand;
use App\Console\Commands\MigrateResetCommand;
use App\Console\Commands\MigrateRollbackCommand;
use App\Console\Commands\QueueFlushCommand;
use App\Console\Commands\StartCommand;
use App\Console\ReactorProcessManager;
use App\Contracts\Repository\UserRepositoryInterface;
use App\Database\EloquentManager;
use App\Repositories\EloquentUserRepository;
use Reactor\Cache\CacheManager;

/**
 * Application service provider.
 *
 * The user repository is registered as a single singleton and both the
 * framework-level UserProviderInterface and the application-level
 * UserRepositoryInterface are aliased to that same binding.
 *
 * The following framework classes are overridden via their abstract
 * bindings:
 *   - BotProcessManager, so `./reactor restart` spawns the new polling
 *     process through ./reactor instead of the removed reactor.php.
 *   - StartCommand, so `./reactor start` refuses to poll while a
 *     webhook is still registered.
 *   - MigrateRefreshCommand / MigrateResetCommand / MigrateRollbackCommand,
 *     so destructive migration operations require explicit
 *     confirmation (or --force).
 *   - QueueFlushCommand, so flushing every failed queue job requires
 *     explicit confirmation (or --force).
 */
class AppServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->singleton(DatabaseManagerInterface::class, function ($c) {
            return new EloquentManager(
                $c->get(Config::class),
                $c->get(LoggerInterface::class)
            );
        });

        // Single concrete repository bound once; both interfaces alias it.
        $container->singleton(EloquentUserRepository::class, function ($c) {
            return new EloquentUserRepository($c->get(LanguageInterface::class));
        });

        $container->alias(UserProviderInterface::class, EloquentUserRepository::class);
        $container->alias(UserRepositoryInterface::class, EloquentUserRepository::class);

        $container->singleton(CacheInterface::class, function ($c) {
            $config = $c->get(Config::class);
            $default = $config->get('cache.default', 'file');
            $storeConfig = $config->get('cache.stores.' . $default, []);
            return new CacheManager(array_merge(['driver' => $default], $storeConfig));
        });

        $container->singleton(QueueManagerInterface::class, function ($c) {
            return new DatabaseQueue($c->get(DatabaseManagerInterface::class));
        });

        $container->singleton(QueueManager::class, function ($c) {
            return new QueueManager(
                $c->get(Container::class),
                $c->get(QueueManagerInterface::class)
            );
        });

        // Override BotProcessManager so `restart` uses ./reactor.
        $container->singleton(BotProcessManager::class, function ($c) {
            return new ReactorProcessManager(
                $c->get(Paths::class),
                $c->get(Config::class)
            );
        });

        // Override StartCommand so `start` checks for an active webhook.
        $container->singleton(BaseStartCommand::class, function ($c) {
            return new StartCommand(
                $c->get(\Reactor\Core\App::class),
                $c->get(LoggerInterface::class),
                $c->get(Container::class),
            );
        });

        // Destructive migration operations require confirmation.
        $container->singleton(BaseMigrateRefreshCommand::class, function ($c) {
            return new MigrateRefreshCommand(
                $c->get(LoggerInterface::class),
                $c->get(Migrator::class)
            );
        });

        $container->singleton(BaseMigrateResetCommand::class, function ($c) {
            return new MigrateResetCommand(
                $c->get(LoggerInterface::class),
                $c->get(Migrator::class)
            );
        });

        $container->singleton(BaseMigrateRollbackCommand::class, function ($c) {
            return new MigrateRollbackCommand(
                $c->get(LoggerInterface::class),
                $c->get(Migrator::class)
            );
        });

        // Flushing failed jobs requires confirmation.
        $container->singleton(BaseQueueFlushCommand::class, function ($c) {
            return new QueueFlushCommand(
                $c->get(FailedJobRepository::class)
            );
        });
    }

    public function boot(Container $container): void
    {
        // Nothing to boot
    }
}
