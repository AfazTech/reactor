<?php
namespace App;

use Neili\KeyboardBuilder;
use Reactor\Contracts\LanguageInterface;

/**
 * Telegram keyboard builder for the application.
 *
 * Provides pre‑defined keyboards such as main menu, back button,
 * language selection, and custom menus.
 */
class Keyboard
{
    private LanguageInterface $language;

    /**
     * Constructor.
     *
     * @param LanguageInterface $language Translation manager.
     */
    public function __construct(LanguageInterface $language)
    {
        $this->language = $language;
    }

    /**
     * Build the main menu keyboard with "Start" and "About" buttons.
     *
     * @param string|null $lang Language code (uses default if null).
     * @return array Keyboard markup array.
     */
    public function mainMenu(?string $lang = null): array
    {
        $lang = $lang ?? $this->language->getDefaultLanguage();
        $kb = new KeyboardBuilder();
        $kb->row(
            $this->language->get('start', $lang),
            $this->language->get('about', $lang)
        );
        return $kb->resize(true)->oneTime(false)->build();
    }

    /**
     * Build a keyboard with a single "Back" button.
     *
     * @param string|null $lang Language code (uses default if null).
     * @return array Keyboard markup array.
     */
    public function backButton(?string $lang = null): array
    {
        $lang = $lang ?? $this->language->getDefaultLanguage();
        return (new KeyboardBuilder())
            ->row($this->language->get('back', $lang))
            ->resize(true)
            ->oneTime(true)
            ->build();
    }

    /**
     * Build a custom keyboard from an array of button labels.
     *
     * @param array       $buttons Array of strings or arrays with 'label' key.
     * @param string|null $lang    Language code (uses default if null).
     * @return array Keyboard markup array.
     */
    public function customMenu(array $buttons, ?string $lang = null): array
    {
        $lang = $lang ?? $this->language->getDefaultLanguage();
        $kb = new KeyboardBuilder();
        $row = [];
        foreach ($buttons as $button) {
            $label = is_array($button) ? $button['label'] : $button;
            $row[] = $this->language->get($label, $lang);
            if (count($row) === 2) {
                $kb->row(...$row);
                $row = [];
            }
        }
        if (!empty($row)) {
            $kb->row(...$row);
        }
        return $kb->resize(true)->oneTime(false)->build();
    }

    /**
     * Build an inline keyboard for language selection.
     *
     * Every language reported by the language manager becomes one
     * button whose callback data is "lang:<code>". The button label is
     * the human-readable language name. Languages are provided as a
     * flat row; callers can pass an explicit list to override the one
     * discovered from the lang/ directory.
     *
     * @param array<int, string>|null $languages Optional list of codes.
     * @return array Keyboard markup array.
     */
    public function languageSelection(?array $languages = null): array
    {
        $codes = $languages ?? $this->language->getAvailableLanguages();

        $buttons = [];
        foreach ($codes as $code) {
            $buttons[] = [
                'text'          => $this->language->getLanguageName($code),
                'callback_data' => 'lang:' . $code,
            ];
        }

        return (new KeyboardBuilder())
            ->inlineButtonRow($buttons)
            ->build();
    }
}
