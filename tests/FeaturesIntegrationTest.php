<?php

namespace Tests;

use App\Providers\AppServiceProvider;
use PHPUnit\Framework\TestCase;
use Reactor\Callbacks\CallbackStore;
use Reactor\Cache\Drivers\ArrayCache;
use Reactor\Contracts\CacheInterface;
use Reactor\Contracts\QueuedEventListenerInterface;
use Reactor\Conversation\ConversationManager;
use Reactor\Conversation\Flow;
use Reactor\Core\App;
use Reactor\Core\Container;
use Reactor\Core\EventDispatcher;
use Reactor\Core\Paths;
use Reactor\Diagnostics\Doctor;
use Reactor\Diagnostics\LoopLagMonitor;
use Reactor\Diagnostics\RuntimeMetrics;
use Reactor\Events;
use Reactor\Queue\Worker;
use Reactor\Queue\WorkerOptions;
use Reactor\Testing\BotTestHarness;
use Reactor\Testing\UpdateFactory;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Fixtures\FeatureHandlers\LimitedHandler;
use Tests\Fixtures\FeatureHandlers\PayloadHandler;

require_once __DIR__ . '/Fixtures/FeatureHandlers/LimitedHandler.php';
require_once __DIR__ . '/Fixtures/FeatureHandlers/PayloadHandler.php';
require_once __DIR__ . '/Fixtures/FeatureHandlers/FailingHandler.php';

class FeatureAppProvider extends AppServiceProvider
{
    public function register(Container $container): void
    {
        parent::register($container);
        $container->instance(CacheInterface::class, new ArrayCache());
    }
}
class FeatureListener implements QueuedEventListenerInterface
{
    public static array $received = [];
    public function handle(string $event, array $arguments): void { self::$received[] = [$event, $arguments]; }
}

class FeaturesIntegrationTest extends TestCase
{
    private App $app;
    private BotTestHarness $bot;
    private string $database;
    private array $environment;
    protected function setUp(): void
    {
        $this->database = sys_get_temp_dir() . '/reactor-features-' . bin2hex(random_bytes(6)) . '.sqlite';
        $this->environment = [];
        foreach (['TOKEN', 'DB_DATABASE'] as $key) { $this->environment[$key] = $_ENV[$key] ?? null; }
        $_ENV['TOKEN'] = '123456:feature-test-only'; $_ENV['DB_DATABASE'] = $this->database;
        $this->app = new App(dirname(__DIR__), [], [], [new FeatureAppProvider()]);
        $this->app->getMigrator()->run();
        $this->app->getRouter()->discover(__DIR__ . '/Fixtures/FeatureHandlers', 'Tests\\Fixtures\\FeatureHandlers');
        $this->bot = new BotTestHarness($this->app);
        LimitedHandler::$runs = 0; FeatureListener::$received = [];
    }
    protected function tearDown(): void
    {
        $this->app->getClient()->flush();
        \Illuminate\Database\Capsule\Manager::connection()->disconnect();
        unset($this->bot, $this->app); gc_collect_cycles();
        foreach (glob($this->database . '*') as $file) { @unlink($file); }
        foreach ($this->environment as $key => $value) {
            if ($value === null) { unset($_ENV[$key]); } else { $_ENV[$key] = $value; }
        }
    }
    public function testAttributeThrottleAndMetricsInTheRealPipeline(): void
    {
        for ($i = 0; $i < 3; $i++) { $this->bot->receive(UpdateFactory::message(10, '/limited')); }
        $this->bot->receive(UpdateFactory::message(11, '/limited'));
        self::assertSame(3, LimitedHandler::$runs);
        $this->bot->telegram->assertSent('sendMessage', fn ($p) => $p['text'] === 'limited', 3);
        $this->bot->assertHandledBy(LimitedHandler::class);
        $snapshot = $this->app->getContainer()->get(RuntimeMetrics::class)->snapshot();
        self::assertSame(1, $snapshot['updates']['throttled']['count']);
        self::assertSame(3, $snapshot['updates']['success']['count']);
        self::assertGreaterThan(0, $snapshot['queries']['total']);
    }
    public function testOpaqueCallbacksRouteToTheHandlerAndRejectOtherUsers(): void
    {
        $token = $this->app->getContainer()->get(CallbackStore::class)->issue('order.view', ['id' => 42], 10);
        $this->bot->receive(UpdateFactory::callback(10, $token));
        $this->bot->receive(UpdateFactory::callback(11, $token));
        $this->bot->telegram->assertSent('sendMessage', fn ($p) => $p['text'] === 'order:42');
        $this->bot->telegram->assertSent('answerCallbackQuery', fn ($p) => str_contains($p['text'], 'expired'));
        $this->bot->assertHandledBy(PayloadHandler::class);
    }
    public function testConversationMiddlewareCompletesValidatedPersistentFlow(): void
    {
        $manager = $this->app->getContainer()->get(ConversationManager::class);
        $manager->define((new Flow('signup'))->ask('name', 'Name?', fn ($v) => strlen($v) > 1)->ask('age', 'Age?', fn ($v) => ctype_digit($v)));
        $completed = [];
        $this->app->getContainer()->get(EventDispatcher::class)->on(Events::CONVERSATION_COMPLETED,
            function ($user, $flow, $answers) use (&$completed) { $completed = [$user, $flow, $answers]; });
        $manager->start(10, 'signup');
        foreach (['x', 'Alice', '/back', 'Bob', '20'] as $text) { $this->bot->receive(UpdateFactory::message(10, $text)); }
        self::assertSame([10, 'signup', ['name' => 'Bob', 'age' => '20']], $completed);
        self::assertFalse($manager->active(10));
        $this->bot->telegram->assertSent('sendMessage', fn ($p) => $p['text'] === 'Age?', 2);
    }
    public function testQueuedListenerUsesTheDatabaseQueueAndExistingWorker(): void
    {
        $events = $this->app->getContainer()->get(EventDispatcher::class);
        $events->onQueued('feature.event', FeatureListener::class, 'listeners');
        $events->dispatch('feature.event', 10, ['name' => 'test']);
        self::assertSame([], FeatureListener::$received);
        $processed = $this->app->getContainer()->get(Worker::class)->work(new WorkerOptions(queue: 'listeners', once: true, timeout: 0));
        self::assertSame(1, $processed);
        self::assertSame([['feature.event', [10, ['name' => 'test']]]], FeatureListener::$received);
    }
    public function testDoctorMetricsExportAndHandlerFailureAccounting(): void
    {
        $this->bot->receive(UpdateFactory::message(10, '/fail'));
        $metrics = $this->app->getContainer()->get(RuntimeMetrics::class);
        self::assertSame(1, $metrics->snapshot()['updates']['handler_error']['count']);
        $doctor = new CommandTester(new \Reactor\Console\Commands\DoctorCommand($this->app->getContainer()->get(Doctor::class)));
        self::assertSame(0, $doctor->execute(['--json' => true, '--telegram' => true]));
        $checks = json_decode($doctor->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame([], array_values(array_filter($checks, fn ($c) => $c['status'] !== 'pass')));
        $path = sys_get_temp_dir() . '/reactor-metrics-' . bin2hex(random_bytes(6)) . '.json';
        $monitor = new LoopLagMonitor($metrics, $path); $monitor->start(0.01);
        try {
            \Amp\delay(0.1);
            $command = new CommandTester(new \Reactor\Console\Commands\MetricsCommand(new Paths(dirname(__DIR__))));
            self::assertSame(0, $command->execute(['--file' => $path]));
            $snapshot = json_decode($command->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame(getmypid(), $snapshot['pid']); self::assertArrayHasKey('snapshot_age_seconds', $snapshot);
        } finally { $monitor->stop(); if (is_file($path)) { unlink($path); } }
    }
}
