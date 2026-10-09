<?php
namespace App\Handlers;

use Reactor\Attributes\Callback;
use Reactor\Attributes\OnUpdate;
use App\Keyboard;

/**
 * Handles the language selection callback from the first-time
 * language picker shown by StartHandler.
 *
 * Callback data has the form "lang:<code>". The captured code is
 * validated against the languages actually loaded from disk, then
 * persisted on the user record. Finally, the callback query is
 * answered and the welcome screen is sent in the chosen language.
 */
#[Callback(data: 'lang', pattern: '/^lang:([A-Za-z0-9_-]+)$/', priority: 100)]
#[OnUpdate('callback_query')]
class LanguageSelectionHandler extends BaseHandler
{
    protected function handle(array $params = []): void
    {
        $lang = $params[0] ?? null;
        $userId = $this->getUserId();

        if (!$userId || !is_string($lang) || $lang === '') {
            return;
        }

        // Only accept languages that actually exist on disk.
        if (!in_array($lang, $this->language->getAvailableLanguages(), true)) {
            $this->logger->warning('Rejected unknown language selection', [
                'user_id'  => $userId,
                'language' => $lang,
            ]);
            return;
        }

        $this->userRepository->setLanguage($userId, $lang);

        // Acknowledge the callback so the client spinner stops.
        $callbackId = $this->update['callback_query']['id'] ?? null;
        if (is_string($callbackId) && $callbackId !== '') {
            $this->client->answerCallbackQuery(
                $callbackId,
                $this->language->get('language_set', $lang)
            );
        }

        // Send the welcome screen in the newly selected language.
        $keyboard = (new Keyboard($this->language))->mainMenu($lang);
        $text = $this->language->get('start_message', $lang);
        $this->send($text, $keyboard);

        $this->logger->debug('User language set', [
            'user_id'  => $userId,
            'language' => $lang,
        ]);
    }
}
