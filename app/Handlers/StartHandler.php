<?php
namespace App\Handlers;

use Reactor\Attributes\Text;
use Reactor\Attributes\OnUpdate;
use Reactor\Attributes\Group;
use App\Keyboard;

/**
 * Handler for the /start and related commands.
 */
#[Group('private')]
#[Text(name: '/start', isCommand: true, priority: 100)]
#[Text(name: '/restart', isCommand: true, priority: 90)]
#[Text(name: 'back', priority: 10)]
#[Text(name: 'start', priority: 10)]
#[OnUpdate('message')]
class StartHandler extends BaseHandler
{
    protected function handle(array $params = []): void
    {
        $user = $this->extractUser($this->update);
        if (!$user) {
            return;
        }

        $userLang = $this->getUserLanguage();
        $keyboard = (new Keyboard($this->language))->mainMenu($userLang);
        $text = $this->language->get('start_message', $userLang);

        $this->reply($text, $keyboard);
    }
}
