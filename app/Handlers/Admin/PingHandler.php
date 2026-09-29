<?php
namespace App\Handlers\Admin;

use Reactor\Attributes\Text;
use Reactor\Attributes\OnUpdate;
use App\Handlers\BaseHandler;

#[Text(name: '/ping_admin', isCommand: true, priority: 50)]
#[OnUpdate('message')]
class PingHandler extends BaseHandler
{
    protected function handle(array $params = []): void
    {
        $this->reply('pong from admin subfolder');
    }
}
