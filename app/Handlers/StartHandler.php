<?php
namespace App\Handlers;

use Reactor\Attributes\Text;
use Reactor\Attributes\OnUpdate;
use Reactor\Attributes\Group;
use App\Keyboard;

/**
 * Handler for the /start and related commands.
 *
 * On the user's very first interaction (before they have chosen a
 * language) this handler shows the language selection screen instead
 * of the welcome message. Once a language is stored, the normal
 * welcome flow is used.
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
        $userId = $this->getUserId();
        if (!$userId) {
            return;
        }

        // First-time users have no language yet: ask them to choose.
        if ($this->userRepository->getLanguage($userId) === '') {
            $keyboard = (new Keyboard($this->language))->languageSelection();
            $prompt = $this->language->get(
                'choose_language',
                $this->language->getDefaultLanguage()
            );
            $this->reply($prompt, $keyboard);
            return;
        }

        $userLang = $this->getUserLanguage();
        $keyboard = (new Keyboard($this->language))->mainMenu($userLang);
        $text = $this->language->get('start_message', $userLang);

        $this->reply($text, $keyboard);
    }
}
