<?php

namespace Tests\Fixtures\FeatureHandlers;

use App\Handlers\BaseHandler;
use Reactor\Attributes\Text;

#[Text(name: '/fail', isCommand: true)]
class FailingHandler extends BaseHandler
{
    protected function handle(array $params = []): void { throw new \RuntimeException('Expected handler failure'); }
}
