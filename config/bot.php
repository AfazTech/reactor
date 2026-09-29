<?php

return [
    'token' => env('TOKEN', ''),
    'api_url' => env('API_URL', 'https://api.telegram.org/bot'),
    'mode' => env('BOT_MODE', 'polling'),
    'multi_process' => env('MULTI_PROCESS', false),
    'webhook_secret' => env('WEBHOOK_SECRET', ''),
    'php_binary' => env('PHP_BINARY', '/usr/bin/php'),

    /*
     * Verify TLS certificates when talking to the Telegram API.
     *
     * MUST remain true in production. Disable only for local development
     * against a self-signed proxy or an interception proxy. When false,
     * the application is vulnerable to man-in-the-middle attacks.
     */
    'verify_ssl' => env('TELEGRAM_VERIFY_SSL', true),
];
