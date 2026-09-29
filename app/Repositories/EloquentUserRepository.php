<?php

namespace App\Repositories;

use App\Models\User;
use App\Contracts\Repository\UserRepositoryInterface;
use Reactor\Contracts\LanguageInterface;
use Reactor\Contracts\UserProviderInterface;

/**
 * Eloquent-based implementation of the user repository.
 *
 * Implements both the application-specific UserRepositoryInterface
 * and the core UserProviderInterface.
 *
 * The `temp` column is cast to array on the model level, so no manual
 * json_encode/json_decode is required here.
 */
class EloquentUserRepository implements UserRepositoryInterface, UserProviderInterface
{
    private LanguageInterface $language;

    public function __construct(LanguageInterface $language)
    {
        $this->language = $language;
    }

    public function syncUser(int $userId, ?string $username, ?string $firstName, ?string $lastName): void
    {
        User::updateOrCreate(
            ['user_id' => $userId],
            [
                'username'   => $username,
                'first_name' => $firstName,
                'last_name'  => $lastName,
            ]
        );
    }

    public function getLanguage(int $userId): string
    {
        $user = User::where('user_id', $userId)->first();
        return $user ? $user->language : $this->language->getDefaultLanguage();
    }

    public function getUser(int $userId): ?array
    {
        $user = User::where('user_id', $userId)->first();
        return $user ? $user->toArray() : null;
    }

    public function getStep(int $userId): ?string
    {
        $user = User::where('user_id', $userId)->first();
        return $user ? $user->step : null;
    }

    public function setStep(int $userId, ?string $step): void
    {
        User::updateOrCreate(
            ['user_id' => $userId],
            ['step' => $step]
        );
    }

    public function getTemp(int $userId): ?array
    {
        $user = User::where('user_id', $userId)->first();
        if (!$user) {
            return null;
        }
        // Eloquent casts 'temp' to array automatically.
        return $user->temp;
    }

    public function setTemp(int $userId, array $data): void
    {
        User::updateOrCreate(
            ['user_id' => $userId],
            ['temp' => $data]
        );
    }

    public function clearTemp(int $userId): void
    {
        User::updateOrCreate(
            ['user_id' => $userId],
            ['temp' => null]
        );
    }
}
