<?php

namespace Tests;

use Amp\Future;
use App\Console\Commands\StartCommand;
use Neili\Client;
use PHPUnit\Framework\TestCase;
use Reactor\Contracts\LoggerInterface;
use Reactor\Core\App;
use Reactor\Core\Container;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class StartCommandTest extends TestCase
{
    /** @dataProvider pendingUpdatePolicy */
    public function testDeletingWebhookOnlyDropsUpdatesWhenExplicitlyRequested(bool $drop): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getWebhookInfo')->willReturn(Future::complete([
            'result' => ['url' => 'https://example.test/webhook'],
        ]));
        $client->expects(self::once())->method('deleteWebhook')->with($drop)
            ->willReturn(Future::complete(['ok' => true]));
        $command = $this->command($client);
        $options = $drop ? ['--drop-pending-updates' => true] : [];
        $input = new ArrayInput($options, $command->getDefinition());
        $input->setInteractive(false);
        $result = (new \ReflectionMethod($command, 'ensureNoWebhook'))
            ->invoke($command, $input, new BufferedOutput());
        self::assertNull($result);
    }

    public static function pendingUpdatePolicy(): array
    {
        return [[false], [true]];
    }

    public function testKeepWebhookNeverDeletesIt(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('getWebhookInfo')->willReturn(Future::complete([
            'result' => ['url' => 'https://example.test/webhook'],
        ]));
        $client->expects(self::never())->method('deleteWebhook');
        $command = $this->command($client);
        $input = new ArrayInput(['--keep-webhook' => true], $command->getDefinition());
        $input->setInteractive(false);
        self::assertSame(1, (new \ReflectionMethod($command, 'ensureNoWebhook'))
            ->invoke($command, $input, new BufferedOutput()));
    }

    private function command(Client $client): StartCommand
    {
        $container = new Container();
        $container->singleton(Client::class, fn () => $client);
        return new StartCommand($this->createMock(App::class),
            $this->createMock(LoggerInterface::class), $container);
    }
}
