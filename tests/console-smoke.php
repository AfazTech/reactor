<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Providers\AppServiceProvider;
use Reactor\Console\Kernel;
use Reactor\Core\App;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

$app = new App(dirname(__DIR__), [], [], [AppServiceProvider::class]);
$console = new Application('Reactor console smoke test');
$console->setAutoExit(false);
(new Kernel($app->getContainer()))->registerCommands($console);
$output = new BufferedOutput();
$status = $console->run(new ArrayInput(['command' => 'list']), $output);
$text = $output->fetch();

if ($status !== 0) {
    throw new RuntimeException('Console list failed: ' . $text);
}
foreach (['start', 'restart', 'stop', 'migrate', 'queue:work'] as $command) {
    if (!$console->has($command) || !str_contains($text, $command)) {
        throw new RuntimeException('Missing command: ' . $command);
    }
}
echo "Console registration smoke test passed\n";
