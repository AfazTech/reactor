<?php

namespace Tests;

use App\Contracts\Repository\UserRepositoryInterface;
use App\Database\EloquentManager;
use App\Handlers\LanguageSelectionHandler;
use App\Middleware\SyncUserMiddleware;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Repositories\AsyncUserRepository;
use App\Repositories\UpdateCachedUserRepository;
use App\Repositories\EloquentUserRepository;
use Neili\Client;
use Amp\Future;
use PHPUnit\Framework\TestCase;
use Reactor\Contracts\AsyncDatabaseManagerInterface;
use Reactor\Contracts\DatabaseManagerInterface;
use Reactor\Contracts\LanguageInterface;
use Reactor\Contracts\LoggerInterface;
use Reactor\Contracts\UserProviderInterface;
use Reactor\Core\Config;
use Reactor\Core\Container;
use Reactor\Core\EventDispatcher;
use Reactor\Core\Paths;
use Reactor\Events;
use Reactor\Queue\QueueManager;

class UserRepositoryTest extends TestCase
{
    private Container $container;
    private Config $config;
    private string $database;

    protected function setUp(): void
    {
        $this->database = sys_get_temp_dir() . '/reactor-users-' . bin2hex(random_bytes(6)) . '.sqlite';
        $this->config = new Config();
        $this->config->override('database.default', 'sqlite');
        $this->config->override('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => $this->database, 'prefix' => '',
        ]);
        $this->container = new Container();
        $this->container->instance(Config::class, $this->config);
        $this->container->instance(Paths::class, new Paths(dirname(__DIR__)));
        $this->container->instance(LoggerInterface::class, $this->createMock(LoggerInterface::class));
        $language = $this->createMock(LanguageInterface::class);
        $language->method('getAvailableLanguages')->willReturn(['en', 'fa']);
        $language->method('get')->willReturnCallback(fn ($key, $lang = null) => $key);
        $language->method('getLanguageName')->willReturnCallback(fn ($code) => $code);
        $this->container->instance(LanguageInterface::class, $language);
        (new AppServiceProvider())->register($this->container);
        // Only use Eloquent to create the schema, as the migrate command does.
        $this->container->get(DatabaseManagerInterface::class);
        require_once dirname(__DIR__) . '/database/migrations/2026_07_16_000002_create_users_table.php';
        (new \App\Migrations\CreateUsersTable($this->container->get(DatabaseManagerInterface::class)))->up();
    }

    protected function tearDown(): void
    {
        $this->container = new Container();
        \Illuminate\Database\Capsule\Manager::connection()->disconnect();
        gc_collect_cycles();
        foreach (glob($this->database . '*') as $file) {
            @unlink($file);
        }
    }

    public function testDefaultRepositoryUsesAsyncQueriesAndPreservesUserState(): void
    {
        $repo = $this->container->get(UserRepositoryInterface::class);
        self::assertInstanceOf(UpdateCachedUserRepository::class, $repo);
        self::assertSame($repo, $this->container->get(UserProviderInterface::class));
        self::assertNull($repo->getUser(123));
        self::assertSame('', $repo->getLanguage(123));
        $repo->syncUser(123, 'first', 'First', null);
        $repo->setLanguage(123, 'fa');
        $repo->setStep(123, 'checkout');
        $repo->setTemp(123, ['text' => 'سلام', 'count' => 2]);
        $repo->setStatus(123, 1);
        $repo->setLastInteraction(123);
        $createdAt = $repo->getUser(123)['created_at'];
        $repo->syncUser(123, 'updated', 'New', 'Name');
        self::assertSame('fa', $repo->getLanguage(123));
        self::assertSame('checkout', $repo->getStep(123));
        self::assertSame(['text' => 'سلام', 'count' => 2], $repo->getTemp(123));
        $user = $repo->getUser(123);
        self::assertSame('updated', $user['username']);
        self::assertTrue($user['status']);
        self::assertSame($createdAt, $user['created_at']);
        self::assertNotEmpty($user['last_interaction_at']);
        self::assertSame(1, User::count());
        $repo->setStep(123, null);
        $repo->clearTemp(123);
        self::assertNull($repo->getStep(123));
        self::assertNull($repo->getTemp(123));
    }

    public function testConcurrentUpdatesRegisterTheUserOnlyOnceAcrossIndependentConnections(): void
    {
        $first = $this->container->get(UserRepositoryInterface::class);
        $second = new AsyncUserRepository(new \Reactor\Database\Async\AsyncSqliteManager([
            'database' => $this->database,
        ]));
        $events = new EventDispatcher();
        $registered = 0;
        $events->on(Events::USER_REGISTERED, function () use (&$registered) { $registered++; });
        $logger = $this->container->get(LoggerInterface::class);
        $middlewares = [new SyncUserMiddleware($first, $logger, $events), new SyncUserMiddleware($second, $logger, $events)];
        $update = ['message' => ['from' => ['id' => 4321, 'first_name' => 'Concurrent']]];
        $futures = [];
        for ($i = 0; $i < 10; $i++) {
            $middleware = $middlewares[$i % 2];
            $futures[] = \Amp\async(fn () => $middleware->handle($update));
        }
        \Amp\Future\await($futures, new \Amp\TimeoutCancellation(5));
        self::assertSame(1, $registered);
        self::assertSame(1, User::where('user_id', 4321)->count());
        self::assertSame('Concurrent', $first->getUser(4321)['first_name']);
        self::assertTrue($first->getUser(4321)['status']);
    }

    public function testBothRepositoryImplementationsReportCreationOnlyOnce(): void
    {
        foreach ([AsyncUserRepository::class, EloquentUserRepository::class] as $index => $class) {
            $repo = $this->container->get($class);
            $id = 10000 + $index;
            self::assertTrue($repo->syncUser($id, 'first', 'First', null));
            $repo->setLanguage($id, 'fa');
            self::assertFalse($repo->syncUser($id, 'updated', 'Updated', null));
            self::assertSame('fa', $repo->getLanguage($id));
            self::assertSame('updated', $repo->getUser($id)['username']);
        }
    }

    public function testCustomQueueTableIsSharedByDispatchAndWorker(): void
    {
        $this->config->override('queue.table', 'pending_jobs');
        $db = $this->container->get(AsyncDatabaseManagerInterface::class);
        $db->execute('CREATE TABLE pending_jobs (id INTEGER PRIMARY KEY AUTOINCREMENT, queue TEXT, payload TEXT,
            attempts INTEGER, reserved_at INTEGER, available_at INTEGER, created_at INTEGER)')->await();
        $queue = $this->container->get(QueueManager::class);
        $queue->push('ExampleJob', ['value' => 42], 'notifications')->await();
        $job = $queue->getNextJob('notifications');
        self::assertNotNull($job);
        self::assertSame(['job' => 'ExampleJob', 'data' => ['value' => 42]], $job['payload']);
        $queue->delete($job);
        self::assertNull($queue->getNextJob('notifications'));
    }

    public function testBothRepositoriesCanBeInjectedAndUsedAtTheSameTime(): void
    {
        $consumer = $this->container->get(DualUserRepositoryConsumer::class);
        self::assertInstanceOf(UpdateCachedUserRepository::class, $this->container->get(UserRepositoryInterface::class));
        self::assertSame($this->container->get(UserRepositoryInterface::class), $this->container->get(UserProviderInterface::class));
        self::assertNotSame($consumer->async, $consumer->eloquent);
        $consumer->async->syncUser(456, null, 'Async', null);
        $consumer->eloquent->setLanguage(456, 'en');
        self::assertSame('en', $consumer->async->getLanguage(456));
        $consumer->async->setLanguage(456, 'fa');
        self::assertSame('fa', $consumer->eloquent->getLanguage(456));
        self::assertSame('fa', User::where('user_id', 456)->first()->language);
    }

    public function testMiddlewareAndLanguageHandlerUseTheAsyncRepositoryWithoutChangingTheirAPI(): void
    {
        $repo = $this->container->get(UserRepositoryInterface::class);
        $events = new EventDispatcher();
        $registered = 0;
        $events->on(Events::USER_REGISTERED, function () use (&$registered) { $registered++; });
        $middleware = new SyncUserMiddleware($repo, $this->container->get(LoggerInterface::class), $events);
        $update = ['message' => ['from' => ['id' => 789, 'first_name' => 'Test']]];
        self::assertFalse($middleware->handle($update));
        self::assertFalse($middleware->handle($update));
        self::assertSame(1, $registered);
        self::assertTrue($repo->getUser(789)['status']);
        $client = $this->createMock(Client::class);
        $client->expects(self::once())->method('answerCallbackQuery')->willReturn(Future::complete([]));
        $client->expects(self::once())->method('sendMessage')->willReturn(Future::complete([]));
        $handler = new LanguageSelectionHandler($client,
            $this->container->get(LanguageInterface::class), $this->container->get(LoggerInterface::class), $repo);
        $handler->execute(['callback_query' => [
            'id' => 'callback', 'from' => ['id' => 789], 'data' => 'lang:fa',
            'message' => ['chat' => ['id' => 789]],
        ]], ['fa']);
        self::assertSame('fa', $repo->getLanguage(789));
    }

    public function testTheDefaultBotProcessesStartAndLanguageSelectionEndToEnd(): void
    {
        $keys = ['TOKEN', 'DB_DATABASE'];
        $before = [];
        foreach ($keys as $key) {
            $before[$key] = $_ENV[$key] ?? null;
        }
        $_ENV['TOKEN'] = '123456:test-only';
        $_ENV['DB_DATABASE'] = $this->database;
        try {
            $app = new \Reactor\Core\App(dirname(__DIR__), [], [], [AppServiceProvider::class]);
            $transport = new class {
                public array $methods = [];
                public function request(\Amp\Http\Client\Request $request): \Amp\Http\Client\Response
                {
                    $this->methods[] = basename($request->getUri()->getPath());
                    return new \Amp\Http\Client\Response('1.1', 200, 'OK',
                        ['content-type' => 'application/json'],
                        '{"ok":true,"result":{"message_id":1}}', $request);
                }
            };
            $app->getClient()->setHttpClient($transport);
            $scope = $app->getContainer()->get(\Reactor\Core\UpdateScope::class);
            $scopeReads = 0;
            $seen = [];
            $probe = function () use (&$scopeReads) { return ++$scopeReads; };
            foreach ([Events::UPDATE_PROCESSING_STARTED, Events::UPDATE_PROCESSING_FINISHED] as $event) {
                $app->getContainer()->get(EventDispatcher::class)->on($event,
                    function () use ($scope, $probe, &$seen) { $seen[] = $scope->remember('probe', $probe); });
            }
            $app->processUpdate(['update_id' => 1, 'message' => [
                'message_id' => 1, 'from' => ['id' => 999, 'first_name' => 'Bot test'],
                'chat' => ['id' => 999, 'type' => 'private'], 'text' => '/start',
            ]]);
            $app->processUpdate(['update_id' => 2, 'callback_query' => [
                'id' => 'choose-language', 'from' => ['id' => 999, 'first_name' => 'Bot test'],
                'message' => ['message_id' => 1, 'chat' => ['id' => 999, 'type' => 'private']],
                'data' => 'lang:fa',
            ]]);
            $app->getClient()->flush();
            $repo = $app->getContainer()->get(UserRepositoryInterface::class);
            self::assertInstanceOf(UpdateCachedUserRepository::class, $repo);
            self::assertSame('fa', $repo->getLanguage(999));
            self::assertTrue($repo->getUser(999)['status']);
            self::assertSame(['sendMessage', 'answerCallbackQuery', 'sendMessage'], $transport->methods);
            self::assertSame([1, 1, 2, 2], $seen, 'Each update gets exactly one independent scope.');
            self::assertSame(3, $scope->remember('probe', $probe), 'No frame survives processUpdate().');
        } finally {
            foreach ($before as $key => $value) {
                if ($value === null) {
                    unset($_ENV[$key]);
                } else {
                    $_ENV[$key] = $value;
                }
            }
        }
    }

    public function testWebhookModeKeepsTheRawAsyncRepository(): void
    {
        $this->config->override('bot.mode', 'webhook');
        self::assertInstanceOf(AsyncUserRepository::class, $this->container->get(UserRepositoryInterface::class));
        self::assertSame($this->container->get(UserRepositoryInterface::class),
            $this->container->get(UserProviderInterface::class));
    }

    public function testQueueBindingReceivesItsAsyncDatabaseDependency(): void
    {
        self::assertInstanceOf(QueueManager::class, $this->container->get(QueueManager::class));
        self::assertSame('sqlite', $this->container->get(AsyncDatabaseManagerInterface::class)->getDriver());
    }
}

class DualUserRepositoryConsumer
{
    public function __construct(public AsyncUserRepository $async, public EloquentUserRepository $eloquent)
    {
    }
}
