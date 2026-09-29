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
 *
 * This class wraps Illuminate's Capsule/Manager to provide a unified
 * interface for schema, query builder, and transaction management.
 */
class EloquentManager implements DatabaseManagerInterface
{
    private Capsule $capsule;
    private LoggerInterface $logger;

    /**
     * Constructor.
     *
     * @param Config          $config Application configuration.
     * @param LoggerInterface $logger Logger instance.
     */
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
        $this->logger->debug("Eloquent database manager initialized");
    }

    /**
     * Ensure the SQLite database file and its directory exist.
     *
     * @param array $config Database configuration array.
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
            $this->logger->debug("Database directory created: " . $databaseDir);
        }
        if (!file_exists($databasePath)) {
            touch($databasePath);
            chmod($databasePath, 0666);
            $this->logger->info("SQLite database file created: " . $databasePath);
        }
    }

    /**
     * Verify the database connection is usable.
     *
     * For non-SQLite drivers (MySQL, PostgreSQL) the SQLite file check
     * above is a no-op, so we perform an eager PDO handshake here to
     * fail fast with a clear error instead of failing on the first query.
     *
     * @param array $config Database configuration array.
     */
    private function verifyConnection(array $config): void
    {
        $driver = $config['driver'] ?? 'sqlite';
        if ($driver === 'sqlite') {
            return;
        }

        try {
            $this->capsule->connection()->getPdo();
            $this->logger->debug("Database connection verified", ['driver' => $driver]);
        } catch (\Throwable $e) {
            $this->logger->critical("Database connection failed", [
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

    /**
     * {@inheritdoc}
     */
    public function connection(): object
    {
        return $this->capsule->connection();
    }

    /**
     * {@inheritdoc}
     */
    public function schema(): SchemaBuilder
    {
        return $this->capsule->schema();
    }

    /**
     * {@inheritdoc}
     */
    public function table(string $table): QueryBuilder
    {
        return $this->capsule->table($table);
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction(): void
    {
        $this->capsule->connection()->beginTransaction();
    }

    /**
     * {@inheritdoc}
     */
    public function commit(): void
    {
        $this->capsule->connection()->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function rollBack(): void
    {
        $this->capsule->connection()->rollBack();
    }

    /**
     * {@inheritdoc}
     */
    public function transaction(callable $callback)
    {
        return $this->capsule->connection()->transaction($callback);
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig(): array
    {
        return $this->capsule->getConnection()->getConfig();
    }
}
