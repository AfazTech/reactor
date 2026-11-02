<?php

namespace Tests\Fixtures\FeatureHandlers;

use App\Handlers\BaseHandler;
use Reactor\Attributes\Callback;

#[Callback('order.view')]
class PayloadHandler extends BaseHandler
{
    protected function handle(array $params = []): void { $this->send('order:' . $this->callbackPayload()['id']); }
}
