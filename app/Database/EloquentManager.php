<?php

namespace App\Database;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Reactor\Contracts\DatabaseManagerInterface;
use Reactor\Core\Config;
use Reactor\Contracts\LoggerInterface;

/**
 * Eloquent-based implementation of the database manager.
 */
class EloquentManager implements DatabaseManagerInterface
{
    private Capsule $capsule;
    private LoggerInterface $logger;

    public function __construct(Config $config, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->capsule = new Capsule();
        $configArray = $config->getDatabaseConfig();
        $this->ensureDatabaseFile($configArray);
        $this->capsule->addConnection($configArray);
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();
        $this->verifyConnection($configArray);
        $this->logger->debug('Eloquent database manager initialized');
    }

    /**
     * Ensure the SQLite database file and its directory exist.
     *
     * The file is created with mode 0640 (owner read/write, group read)
     * instead of 0666 so that other local users cannot read or modify
     * the database.
     */
    private function ensureDatabaseFile(array $config): void
    {
        if (($config['driver'] ?? null) !== 'sqlite') {
            return;
        }

        $databasePath = $config['database'];
        $databaseDir = dirname($databasePath);

        if (!is_dir($databaseDir)) {
            mkdir($databaseDir, 0755, true);
            $this->logger->debug('Database directory created: ' . $databaseDir);
        }

        if (!file_exists($databasePath)) {
            touch($databasePath);
            chmod($databasePath, 0640);
            $this->logger->info('SQLite database file created: ' . $databasePath);
        }
    }

    /**
     * Verify the database connection is usable.
     */
    private function verifyConnection(array $config): void
    {
        $driver = $config['driver'] ?? 'sqlite';
        if ($driver === 'sqlite') {
            return;
        }

        try {
            $this->capsule->connection()->getPdo();
            $this->logger->debug('Database connection verified', ['driver' => $driver]);
        } catch (\Throwable $e) {
            $this->logger->critical('Database connection failed', [
                'driver'  => $driver,
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException(
                "Failed to connect to {$driver} database: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    public function connection(): object
    {
        return $this->capsule->connection();
    }

    public function schema(): SchemaBuilder
    {
        return $this->capsule->schema();
    }

    public function table(string $table): QueryBuilder
    {
        return $this->capsule->table($table);
    }

    public function beginTransaction(): void
    {
        $this->capsule->connection()->beginTransaction();
    }

    public function commit(): void
    {
        $this->capsule->connection()->commit();
    }

    public function rollBack(): void
    {
        $this->capsule->connection()->rollBack();
    }

    public function transaction(callable $callback)
    {
        return $this->capsule->connection()->transaction($callback);
    }

    public function getConfig(): array
    {
        return $this->capsule->getConnection()->getConfig();
    }
}
