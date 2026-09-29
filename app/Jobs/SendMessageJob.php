<?php
namespace App\Jobs;

use Reactor\Queue\BaseJob;
use Reactor\Contracts\JobInterface;

/**
 * Example job that sends a delayed Telegram message.
 *
 * Expected payload keys:
 *  - chatId   (int)    Telegram chat ID.
 *  - text     (string) Message body.
 *  - keyboard (array)  Optional inline/reply keyboard markup.
 */
class SendMessageJob extends BaseJob implements JobInterface
{
    public function handle(array $data): void
    {
        $chatId   = $data['chatId']   ?? null;
        $text     = $data['text']     ?? '';
        $keyboard = $data['keyboard'] ?? null;

        if ($chatId === null) {
            $this->logger->warning('SendMessageJob skipped: missing chatId', ['data' => $data]);
            return;
        }

        $this->client->sendMessage($chatId, $text, $keyboard);
        $this->logger->info("Delayed message sent to $chatId");
    }
}
