<?php

require __DIR__ . '/../vendor/autoload.php';

use Reactor\Core\App;

$basePath = __DIR__ . '/..';

$appProviders = [
    \App\Providers\AppServiceProvider::class,
];

$app = new App($basePath, [], [], $appProviders);
$client = $app->getClient();
$logger = $app->getLogger();
$config = $app->getConfig();

$secret = $config->getWebhookSecret();

try {
    $update = $client->handleUpdate($secret);

    if (!empty($update)) {
        $logger->debug("Webhook received update", ['type' => array_keys($update)]);
        $app->processUpdate($update);
    } else {
        $logger->debug("Empty update received");
    }
} catch (Throwable $e) {
    $logger->error("Webhook error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
}
