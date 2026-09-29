<?php
namespace App\Handlers;

use Reactor\Attributes\Fallback;
use App\Contracts\Repository\UserRepositoryInterface;

/**
 * Application-level fallback handler.
 *
 * Invoked when no other handler matches an update. Registering this
 * class replaces the framework's built-in UnknownCommandHandler for
 * this application, because Router::findHandler() returns this handler
 * as a normal HandlerExecution before UpdateProcessor would fall back
 * to the framework default.
 *
 * If you don't need a custom response, simply delete this file: the
 * framework will then use UnknownCommandHandler and reply with the
 * translated "unknown_command" string.
 */
#[Fallback(priority: 0)]
class FallbackHandler extends BaseHandler
{
    protected function handle(array $params = []): void
    {
        $userId = $this->getUserId();
        if (!$userId) {
            $this->logger->debug("Fallback: no user ID found");
            return;
        }

        $lang = $this->getUserLanguage();
        $message = $this->language->get('unknown_command', $lang);

        $this->reply($message);
    }
}
