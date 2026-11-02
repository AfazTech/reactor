<?php

namespace App\Repositories;

use App\Contracts\Repository\UserRepositoryInterface;
use Reactor\Contracts\UserProviderInterface;
use Reactor\Core\UpdateScope;

/** Caches user reads only for the current update; raw repositories stay available. */
final class UpdateCachedUserRepository implements UserRepositoryInterface, UserProviderInterface
{
    public function __construct(private UserRepositoryInterface $inner, private UpdateScope $scope)
    {
    }

    public function getUser(int $userId): ?array
    {
        return $this->scope->remember($this->cacheKey($userId), fn () => $this->inner->getUser($userId));
    }

    private function cacheKey(int $userId): string
    {
        return self::class . ':' . spl_object_id($this) . ':' . $userId;
    }

    public function getLanguage(int $userId): string
    {
        return (string) ($this->getUser($userId)['language'] ?? '');
    }

    public function getStep(int $userId): ?string
    {
        return $this->getUser($userId)['step'] ?? null;
    }

    public function getTemp(int $userId): ?array
    {
        return $this->getUser($userId)['temp'] ?? null;
    }

    /** Call this after bypassing the decorator with direct ORM/SQL writes. */
    public function forgetUser(int $userId): void
    {
        $this->scope->forget($this->cacheKey($userId));
    }

    public function syncUser(int $userId, ?string $username, ?string $firstName, ?string $lastName): bool
    {
        $inserted = $this->inner->syncUser($userId, $username, $firstName, $lastName);
        $this->forgetUser($userId);
        return $inserted;
    }

    public function setLanguage(int $userId, string $language): void
    {
        $this->inner->setLanguage($userId, $language);
        $this->forgetUser($userId);
    }

    public function setStep(int $userId, ?string $step): void
    {
        $this->inner->setStep($userId, $step);
        $this->forgetUser($userId);
    }

    public function setTemp(int $userId, array $data): void
    {
        $this->inner->setTemp($userId, $data);
        $this->forgetUser($userId);
    }

    public function clearTemp(int $userId): void
    {
        $this->inner->clearTemp($userId);
        $this->forgetUser($userId);
    }

    public function setStatus(int $userId, int $status): void
    {
        $this->inner->setStatus($userId, $status);
        $this->forgetUser($userId);
    }

    public function setLastInteraction(int $userId): void
    {
        $this->inner->setLastInteraction($userId);
        $this->forgetUser($userId);
    }
}
