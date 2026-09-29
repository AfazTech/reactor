<?php
namespace App\Handlers;

use Reactor\Attributes\Text;
use Reactor\Attributes\OnUpdate;
use Reactor\Attributes\Group;
use Neili\Client;
use Reactor\Core\Language;
use Reactor\Contracts\LoggerInterface;
use App\Contracts\Repository\UserRepositoryInterface;
use App\Keyboard;

/**
 * Handler for the "About" button or command.
 */
#[Group('private')]
#[Text(name: 'about', priority: 5)]
#[OnUpdate('message')]
class AboutHandler extends BaseHandler
{
    protected function handle(array $params = []): void
    {
        $user = $this->extractUser($this->update);
        if (!$user) {
            return;
        }

        $lang = $this->getUserLanguage();
        $text = $this->language->get('about_text', $lang);
        $keyboard = (new Keyboard($this->language))->mainMenu($lang);

        $this->reply($text, $keyboard);
    }
}
