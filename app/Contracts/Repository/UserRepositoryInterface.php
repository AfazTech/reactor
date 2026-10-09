<?php

namespace App\Contracts\Repository;

/**
 * Interface for user repository operations.
 *
 * Defines the contract for managing user data, including retrieval,
 * synchronization, language preference, step management, activity
 * status, last interaction timestamp, and temporary data.
 */
interface UserRepositoryInterface
{
    /**
     * Create or update a user record.
     *
     * @param int         $userId    Telegram user ID.
     * @param string|null $username  Telegram username.
     * @param string|null $firstName User's first name.
     * @param string|null $lastName  User's last name.
     */
    public function syncUser(int $userId, ?string $username, ?string $firstName, ?string $lastName): void;

    /**
     * Get the user's preferred language code.
     *
     * Returns an empty string when the user has not chosen a language
     * yet, so callers can distinguish "not set" from a valid code.
     *
     * @param int $userId Telegram user ID.
     * @return string Language code (e.g., 'en', 'fa'), or '' if unset.
     */
    public function getLanguage(int $userId): string;

    /**
     * Set the user's preferred language code.
     *
     * @param int    $userId   Telegram user ID.
     * @param string $language Language code (e.g., 'en', 'fa').
     */
    public function setLanguage(int $userId, string $language): void;

    /**
     * Retrieve a user's data as an associative array.
     *
     * @param int $userId Telegram user ID.
     * @return array|null User data or null if not found.
     */
    public function getUser(int $userId): ?array;

    /**
     * Get the current step of the user.
     *
     * @param int $userId Telegram user ID.
     * @return string|null Step name or null if not set.
     */
    public function getStep(int $userId): ?string;

    /**
     * Set the step for the user.
     *
     * @param int         $userId Telegram user ID.
     * @param string|null $step   Step name to set, or null to clear.
     */
    public function setStep(int $userId, ?string $step): void;

    /**
     * Get temporary data stored for the user.
     *
     * @param int $userId Telegram user ID.
     * @return array|null Temporary data or null if not set.
     */
    public function getTemp(int $userId): ?array;

    /**
     * Set temporary data for the user.
     *
     * @param int   $userId Telegram user ID.
     * @param array $data   Temporary data to store.
     */
    public function setTemp(int $userId, array $data): void;

    /**
     * Clear temporary data for the user.
     *
     * @param int $userId Telegram user ID.
     */
    public function clearTemp(int $userId): void;

    /**
     * Update the user's activity status.
     *
     * @param int $userId Telegram user ID.
     * @param int $status 1 when the user is active, 0 when blocked or inactive.
     */
    public function setStatus(int $userId, int $status): void;

    /**
     * Record the current time as the user's last interaction moment.
     *
     * @param int $userId Telegram user ID.
     */
    public function setLastInteraction(int $userId): void;
}
