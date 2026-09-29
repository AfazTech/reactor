<?php

namespace App\Providers;

use Reactor\Core\Container;
use Reactor\Contracts\ServiceProviderInterface;
use Reactor\Contracts\DatabaseManagerInterface;
use Reactor\Contracts\LanguageInterface;
use Reactor\Contracts\CacheInterface;
use Reactor\Contracts\QueueManagerInterface;
use Reactor\Contracts\UserProviderInterface;
use Reactor\Queue\QueueManager;
use Reactor\Queue\DatabaseQueue;
use App\Contracts\Repository\UserRepositoryInterface;
use App\Database\EloquentManager;
use App\Repositories\EloquentUserRepository;
use Reactor\Core\Config;
use Reactor\Cache\CacheManager;

/**
 * Application service provider.
 *
 * The user repository is registered as a single singleton and both the
 * framework-level UserProviderInterface and the application-level
 * UserRepositoryInterface are aliased to that same binding. This
 * guarantees a single shared instance across the whole request
 * lifecycle, so any future internal state (caching, request-scoped
 * memoization, transactions) behaves consistently regardless of which
 * interface consumers depend on.
 */
class AppServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->singleton(DatabaseManagerInterface::class, function ($c) {
            return new EloquentManager(
                $c->get(Config::class),
                $c->get(\Reactor\Contracts\LoggerInterface::class)
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
    }

    public function boot(Container $container): void
    {
        // Nothing to boot
    }
}
