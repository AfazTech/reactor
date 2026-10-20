#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

use Reactor\Console\Kernel;
use Reactor\Core\App;
use Dotenv\Dotenv;
use Symfony\Component\Console\Application;

if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

$basePath = __DIR__;
$appProviders = [
    \App\Providers\AppServiceProvider::class,
];
$app = new App($basePath, [], [], $appProviders);
$container = $app->getContainer();

$consoleApp = new Application('Bot Migration Console', '1.0');
$kernel = new Kernel($container);
$kernel->registerCommands($consoleApp);
$consoleApp->run();
