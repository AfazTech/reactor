<?php
namespace App;

use Neili\KeyboardBuilder;
use Reactor\Contracts\LanguageInterface;

/**
 * Telegram keyboard builder for the application.
 *
 * Provides pre‑defined keyboards such as main menu, back button,
 * and custom menus.
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
}
