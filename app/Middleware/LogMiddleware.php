<?php
namespace App\Middleware;

use Reactor\Attributes\Middleware;
use Reactor\Attributes\OnUpdate;
use Reactor\Enums\MiddlewareMode;
use Reactor\Core\Config;
use Reactor\Contracts\LoggerInterface;
use Reactor\Contracts\MiddlewareInterface;

/**
 * Global middleware that logs incoming updates.
 *
 * In debug mode the full update payload is written at debug level.
 * In production only a compact summary (update type and, when
 * available, the sender's user ID) is logged to avoid leaking
 * sensitive data and inflating log volume.
 */
#[Middleware(priority: 10, mode: MiddlewareMode::GLOBAL)]
#[OnUpdate('any')]
class LogMiddleware implements MiddlewareInterface
{
    protected LoggerInterface $logger;
    protected Config $config;

    public function __construct(LoggerInterface $logger, Config $config)
    {
        $this->logger = $logger;
        $this->config = $config;
    }

    /**
     * Log the update and allow processing to continue.
     *
     * @param array $update Incoming Telegram update.
     * @return bool Always returns false to continue processing.
     */
    public function handle(array $update): bool
    {
        if ($this->config->isDebugMode()) {
            $this->logger->debug("Message received", $update);
        } else {
            $updateType = array_key_first($update) ?: 'unknown';
            $fromId = $update['message']['from']['id']
                ?? $update['callback_query']['from']['id']
                ?? null;

            $this->logger->info("Update received", [
                'type'    => $updateType,
                'user_id' => $fromId,
            ]);
        }

        return false;
    }
}
