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
use Reactor\Contracts\AsyncDatabaseManagerInterface;
use Reactor\Contracts\LanguageInterface;
use Reactor\Contracts\CacheInterface;
use Reactor\Contracts\QueueManagerInterface;
use Reactor\Contracts\UserProviderInterface;
use Reactor\Database\Async\AsyncMysqlManager;
use Reactor\Database\Async\AsyncPostgresManager;
use Reactor\Database\Async\AsyncSqliteManager;
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
 * Both the synchronous Eloquent-based database manager and the
 * asynchronous AMPHP-backed driver are bound here. They share the same
 * connection settings (config/database.php), so operators only have
 * one place to configure database credentials. Handlers that need
 * non-blocking database access type-hint AsyncDatabaseManagerInterface;
 * everything else keeps using Eloquent through DatabaseManagerInterface.
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

        // Asynchronous database manager. Reuses the exact connection
        // settings that the Eloquent manager reads, so there is no
        // separate configuration block to maintain.
        $container->singleton(AsyncDatabaseManagerInterface::class, function ($c) {
            $config = $c->get(Config::class);
            $connection = $config->getDatabaseConfig();
            $driver = strtolower((string) ($connection['driver'] ?? 'sqlite'));

            return match ($driver) {
                'mysql', 'mariadb'               => new AsyncMysqlManager($connection),
                'pgsql', 'postgres', 'postgresql' => new AsyncPostgresManager($connection),
                'sqlite'                         => $this->createAsyncSqliteManager($connection),
                default                          => throw new \InvalidArgumentException(
                    "No async database driver available for driver: {$driver}"
                ),
            };
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
            $config = $c->get(Config::class);
            $retryAfter = (int) $config->get('queue.retry_after', 90);
            $table = (string) $config->get('queue.table', 'jobs');

            return new DatabaseQueue(
                $c->get(DatabaseManagerInterface::class),
                $table,
                $retryAfter
            );
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

    /**
     * Create the async SQLite driver, ensuring the database file
     * exists with the same restrictive permissions used by the
     * Eloquent manager (0640). The :memory: path is left untouched.
     */
    private function createAsyncSqliteManager(array $connection): AsyncSqliteManager
    {
        $databasePath = (string) ($connection['database'] ?? ':memory:');

        if ($databasePath !== ':memory:') {
            $directory = dirname($databasePath);

            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            if (!file_exists($databasePath)) {
                touch($databasePath);
                chmod($databasePath, 0640);
            }
        }

        return new AsyncSqliteManager($connection);
    }
}
