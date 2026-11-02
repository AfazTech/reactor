<?php

namespace App\Repositories;

use App\Contracts\Repository\UserRepositoryInterface;
use Reactor\Contracts\AsyncDatabaseManagerInterface;
use Reactor\Contracts\UserProviderInterface;

/**
 * Non-blocking user persistence. Awaiting an Amp query suspends only the
 * current fiber, while preserving the existing repository API for handlers.
 */
class AsyncUserRepository implements UserRepositoryInterface, UserProviderInterface
{
    public function __construct(private AsyncDatabaseManagerInterface $db)
    {
    }

    public function syncUser(int $userId, ?string $username, ?string $firstName, ?string $lastName): bool
    {
        $values = ['username' => $username, 'first_name' => $firstName, 'last_name' => $lastName];
        $now = date('Y-m-d H:i:s');
        $params = ['user_id' => $userId] + $values + ['created_at' => $now, 'updated_at' => $now];
        $mysql = in_array($this->db->getDriver(), ['mysql', 'mariadb'], true);
        $sql = 'INSERT INTO users (user_id, username, first_name, last_name, created_at, updated_at)
                VALUES (:user_id, :username, :first_name, :last_name, :created_at, :updated_at)';
        if (!$mysql) {
            $sql .= ' ON CONFLICT (user_id) DO NOTHING';
        }
        try {
            $inserted = $this->db->execute($sql, $params)->await() === 1;
        } catch (\Amp\Sql\SqlQueryError $e) {
            // AMPHP MySQL exposes the server code in this message. Only a
            // duplicate-key failure is recoverable; never hide other errors.
            if (!$mysql || !str_starts_with($e->getMessage(), 'MySQL error (1062):')) {
                throw $e;
            }
            $inserted = false;
        }
        if (!$inserted) {
            $this->upsert($userId, $values);
        }
        return $inserted;
    }

    public function getUser(int $userId): ?array
    {
        $row = $this->db->fetchOne('SELECT * FROM users WHERE user_id = :user_id', ['user_id' => $userId])->await();
        if ($row === null) {
            return null;
        }
        $row['id'] = (int) $row['id'];
        $row['user_id'] = (int) $row['user_id'];
        $row['status'] = in_array($row['status'], [true, 1, '1', 't', 'true'], true);
        $row['temp'] = $row['temp'] === null ? null : json_decode($row['temp'], true, 512, JSON_THROW_ON_ERROR);
        return $row;
    }

    public function getLanguage(int $userId): string
    {
        $row = $this->db->fetchOne('SELECT language FROM users WHERE user_id = :user_id', ['user_id' => $userId])->await();
        return (string) ($row['language'] ?? '');
    }

    public function setLanguage(int $userId, string $language): void
    {
        $this->upsert($userId, ['language' => $language]);
    }

    public function getStep(int $userId): ?string
    {
        $row = $this->db->fetchOne('SELECT step FROM users WHERE user_id = :user_id', ['user_id' => $userId])->await();
        return $row['step'] ?? null;
    }

    public function setStep(int $userId, ?string $step): void
    {
        $this->upsert($userId, ['step' => $step]);
    }

    public function getTemp(int $userId): ?array
    {
        $row = $this->db->fetchOne('SELECT temp FROM users WHERE user_id = :user_id', ['user_id' => $userId])->await();
        return isset($row['temp']) ? json_decode($row['temp'], true, 512, JSON_THROW_ON_ERROR) : null;
    }

    public function setTemp(int $userId, array $data): void
    {
        $this->upsert($userId, ['temp' => json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
    }

    public function clearTemp(int $userId): void
    {
        $this->upsert($userId, ['temp' => null]);
    }

    public function setStatus(int $userId, int $status): void
    {
        $this->upsert($userId, ['status' => $status]);
    }

    public function setLastInteraction(int $userId): void
    {
        $this->upsert($userId, ['last_interaction_at' => date('Y-m-d H:i:s')]);
    }

    /** Atomically create or update by the unique Telegram user ID. */
    private function upsert(int $userId, array $values): void
    {
        $now = date('Y-m-d H:i:s');
        $values['updated_at'] = $now;
        $insert = ['user_id' => $userId] + $values + ['created_at' => $now];
        $mysql = in_array($this->db->getDriver(), ['mysql', 'mariadb'], true);
        $quote = static fn (string $name): string => $mysql ? '`' . $name . '`' : '"' . $name . '"';
        $columns = implode(', ', array_map($quote, array_keys($insert)));
        $parameters = implode(', ', array_map(static fn ($name) => ':' . $name, array_keys($insert)));
        $updates = [];
        foreach (array_keys($values) as $column) {
            $name = $quote($column);
            $updates[] = $name . ' = ' . ($mysql ? 'VALUES(' . $name . ')' : 'excluded.' . $name);
        }
        $conflict = $mysql ? ' ON DUPLICATE KEY UPDATE ' : ' ON CONFLICT (user_id) DO UPDATE SET ';
        $this->db->execute('INSERT INTO users (' . $columns . ') VALUES (' . $parameters . ')'
            . $conflict . implode(', ', $updates), $insert)->await();
    }
}
