<?php

use Reactor\Core\App;
use Dotenv\Dotenv;

require 'vendor/autoload.php';

$basePath = __DIR__;

if (file_exists($basePath . '/.env')) {
    $dotenv = Dotenv::createImmutable($basePath);
    $dotenv->load();
}

echo "========================================\n";
echo "⚡ REACTOR BOT\n";
echo "========================================\n";

$appProviders = [
    \App\Providers\AppServiceProvider::class,
];

try {
    $app = new App($basePath, [], [], $appProviders);
    echo "✓ Bot initialized successfully\n";

    $mode = $app->getConfig()->getBotMode();
    echo "✓ Mode: " . strtoupper($mode) . "\n";

    if ($mode === 'webhook') {
        echo "ℹ️ Webhook mode is active. Polling not started.\n";
        echo "ℹ️ Ensure your webhook is set via /setwebhook endpoint.\n";
        echo "========================================\n";
        echo "✅ Bot is running in webhook mode.\n";
        echo "========================================\n\n";
    } else {
        echo "✓ Starting long polling...\n";
        echo "========================================\n";
        echo "🚀 Reactor is running and listening for messages\n";
        echo "📝 Press Ctrl+C to stop\n";
        echo "========================================\n\n";

        $app->start();
    }
} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";

    $logFile = $basePath . '/storage/logs/app.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] CRITICAL: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n", FILE_APPEND);

    if (isset($app) && $app->getLogger()) {
        $app->getLogger()->critical("Fatal error: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
    }
}
