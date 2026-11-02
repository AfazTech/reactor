<?php

namespace App\Middleware;

use Reactor\Attributes\Middleware;
use Reactor\Attributes\OnUpdate;
use Reactor\Enums\MiddlewareMode;

#[Middleware(priority: 40, mode: MiddlewareMode::GLOBAL)]
#[OnUpdate('message')]
class ConversationMiddleware extends \Reactor\Conversation\ConversationMiddleware
{
}
