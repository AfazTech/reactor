<?php

namespace Tests\Fixtures\FeatureHandlers;

use App\Handlers\BaseHandler;
use Reactor\Attributes\Text;
use Reactor\Attributes\Throttle;

#[Text(name: '/limited', isCommand: true)]
#[Throttle(limit: 2, seconds: 60)]
class LimitedHandler extends BaseHandler
{
    public static int $runs = 0;
    protected function handle(array $params = []): void { self::$runs++; $this->send('limited'); }
}
