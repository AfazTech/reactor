<?php

namespace App\Providers;

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
use Reactor\Queue\QueueManager;
use Reactor\Queue\DatabaseQueue;
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
 * Two framework classes are overridden via their abstract bindings:
 *   - BotProcessManager, so `./reactor restart` spawns the new polling
 *     process through ./reactor instead of the removed reactor.php.
 *   - StartCommand, so `./reactor start` refuses to poll while a
 *     webhook is still registered (a state that otherwise produces a
 *     silent 409 Conflict and a dead-looking bot).
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

        // Override the framework's BotProcessManager so `restart` uses
        // the ./reactor entry point.
        $container->singleton(BotProcessManager::class, function ($c) {
            return new ReactorProcessManager(
                $c->get(Paths::class),
                $c->get(Config::class)
            );
        });

        // Override the framework's StartCommand so `start` checks for an
        // active webhook before polling.
        $container->singleton(BaseStartCommand::class, function ($c) {
            return new StartCommand(
                $c->get(\Reactor\Core\App::class),
                $c->get(LoggerInterface::class),
                $c->get(Container::class),
            );
        });
    }

    public function boot(Container $container): void
    {
        // Nothing to boot
    }
}
