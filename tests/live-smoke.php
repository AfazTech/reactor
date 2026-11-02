<?php

// Explicit opt-in script. It is deliberately outside the PHPUnit *Test.php suite.
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/Support/CurlProxyTransport.php';

set_exception_handler(function (Throwable $error): void {
    fwrite(STDERR, 'Live smoke failed: ' . get_class($error) . ". Credential-bearing error details are suppressed.\n");
    exit(1);
});

// Supply credentials as JSON on stdin or through existing secure environment bindings.
$input = getenv('REACTOR_LIVE_BOT_TOKEN') ? [] : json_decode(fgets(STDIN) ?: '', true, 512, JSON_THROW_ON_ERROR);
$token = getenv('REACTOR_LIVE_BOT_TOKEN') ?: ($input['token'] ?? '');
$chatId = (int) (getenv('REACTOR_LIVE_CHAT_ID') ?: ($input['chat_id'] ?? 0));
if (!is_string($token) || !preg_match('/^[0-9]+:[A-Za-z0-9_-]+$/D', $token) || $chatId <= 0) {
    throw new InvalidArgumentException('A valid test-bot token and your positive chat ID are required.');
}
$client = new Neili\Client((new Neili\Settings())->setAccessToken($token));
$client->setHttpClient(new Tests\Support\CurlProxyTransport());
$me = $client->getMe()->await();
if (!($me['ok'] ?? false) || !($me['result']['is_bot'] ?? false)) { throw new RuntimeException('getMe did not confirm a bot.'); }
echo "getMe: passed\n";
$message = $client->sendMessage($chatId, '[Reactor live smoke test] Checking Telegram delivery.')->await();
$messageId = $message['result']['message_id'] ?? null;
if (!is_int($messageId)) { throw new RuntimeException('sendMessage failed.'); }
echo "sendMessage: passed\n";
$edited = $client->editMessageText($chatId, $messageId, '[Reactor live smoke test] Delivery and editing passed.')->await();
if (!($edited['ok'] ?? false)) { throw new RuntimeException('editMessageText failed.'); }
echo "editMessageText: passed\n";
$deleted = $client->deleteMessage($chatId, $messageId)->await();
if (($deleted['result'] ?? null) !== true) { throw new RuntimeException('deleteMessage failed.'); }
$client->flush();
echo "deleteMessage: passed\nLive Telegram smoke test: passed\n";
