<?php

namespace App\Contracts\Repository;

/**
 * Interface for user repository operations.
 *
 * Defines the contract for managing user data, including retrieval,
 * synchronization, language preference, step management, and temporary data.
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
     * @param int $userId Telegram user ID.
     * @return string Language code (e.g., 'en', 'fa').
     */
    public function getLanguage(int $userId): string;

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
}
