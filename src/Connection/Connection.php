<?php

declare(strict_types=1);

namespace Foxdb\Connection;

use Foxdb\Config;
use Foxdb\Contracts\ConnectionInterface;
use Foxdb\Debug\QueryEventHook;
use Foxdb\Debug\QueryLog;
use Foxdb\Debug\QueryLogEntry;
use Foxdb\Exceptions\DatabaseException;
use Foxdb\Exceptions\QueryException;
use PDO;
use PDOException;
use PDOStatement;
use Throwable;

class Connection implements ConnectionInterface
{
    /**
     * The active PDO connection.
     *
     * @var PDO
     */
    protected PDO $pdo;

    /**
     * The connection configuration array.
     *
     * @var array<string, mixed>
     */
    protected array $config;

    /**
     * The name of this connection (e.g. 'main', 'read').
     *
     * @var string
     */
    protected string $name;

    /**
     * Current transaction nesting depth.
     *
     * @var int
     */
    protected int $transactionDepth = 0;

    /**
     * Whether to throw exceptions on query errors.
     *
     * @var bool
     */
    protected bool $throwExceptions;

    /**
     * The fetch mode used for SELECT queries.
     *
     * @var int
     */
    protected int $fetchMode;

    /**
     * Query log — disabled by default, zero overhead when not in use.
     *
     * @var QueryLog
     */
    protected QueryLog $queryLog;

    /**
     * Before/after query hooks — no-op when no callbacks are registered.
     *
     * @var QueryEventHook
     */
    protected QueryEventHook $eventHook;

    /**
     * @param string               $name   Connection label (e.g. 'main')
     * @param array<string, mixed> $config Connection configuration
     *
     * @throws DatabaseException If the PDO connection cannot be established
     */
    public function __construct(string $name, array $config)
    {
        $this->name            = $name;
        $this->config          = $config;
        $this->throwExceptions = (bool) ($config['throw_exceptions'] ?? true);
        $this->fetchMode       = $config['fetch'] ?? Config::FETCH_OBJ;
        $this->queryLog        = new QueryLog();
        $this->eventHook       = new QueryEventHook();

        $this->pdo = $this->createPdo($config);
    }

    // -----------------------------------------------------------------------
    // ConnectionInterface — public API
    // -----------------------------------------------------------------------

    /**
     * {@inheritdoc}
     *
     * @return array<int, object>
     */
    public function select(string $sql, array $bindings = []): array
    {
        $stmt = $this->run($sql, $bindings);

        /** @var array<int, object> */
        return $stmt->fetchAll($this->fetchMode);
    }

    /**
     * {@inheritdoc}
     */
    public function selectOne(string $sql, array $bindings = []): object|false
    {
        $stmt = $this->run($sql, $bindings);

        $result = $stmt->fetch($this->fetchMode);

        return $result === false ? false : (object) $result;
    }

    /**
     * {@inheritdoc}
     */
    public function insert(string $sql, array $bindings = []): bool
    {
        $this->run($sql, $bindings);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function insertGetId(string $sql, array $bindings = []): int|string
    {
        $this->run($sql, $bindings);

        $id = $this->pdo->lastInsertId();

        return is_numeric($id) ? (int) $id : $id;
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $sql, array $bindings = []): int
    {
        $stmt = $this->run($sql, $bindings);

        return $stmt->rowCount();
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $sql, array $bindings = []): int
    {
        $stmt = $this->run($sql, $bindings);

        return $stmt->rowCount();
    }

    /**
     * {@inheritdoc}
     */
    public function statement(string $sql): bool
    {
        try {
            $start  = hrtime(true);
            $result = $this->pdo->exec($sql) !== false;
            $timeMs = $this->elapsedMs($start);

            $this->recordAndFire($sql, [], $timeMs);

            return $result;
        } catch (PDOException $e) {
            return $this->handleException($e, $sql, []);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function affectingStatement(string $sql, array $bindings = []): int
    {
        $stmt = $this->run($sql, $bindings);

        return $stmt->rowCount();
    }

    // -----------------------------------------------------------------------
    // Transactions
    // -----------------------------------------------------------------------

    /**
     * {@inheritdoc}
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();

            return $result;
        } catch (Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction(): void
    {
        if ($this->transactionDepth === 0) {
            try {
                $this->pdo->beginTransaction();
            } catch (PDOException $e) {
                throw DatabaseException::transactionFailed('begin', $e);
            }
        } else {
            $this->pdo->exec("SAVEPOINT trans{$this->transactionDepth}");
        }

        $this->transactionDepth++;
    }

    /**
     * {@inheritdoc}
     */
    public function commit(): void
    {
        $this->transactionDepth--;

        if ($this->transactionDepth === 0) {
            try {
                $this->pdo->commit();
            } catch (PDOException $e) {
                throw DatabaseException::transactionFailed('commit', $e);
            }
        } else {
            $this->pdo->exec("RELEASE SAVEPOINT trans{$this->transactionDepth}");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rollBack(): void
    {
        if ($this->transactionDepth === 1) {
            $this->transactionDepth = 0;
            try {
                $this->pdo->rollBack();
            } catch (PDOException $e) {
                throw DatabaseException::transactionFailed('rollback', $e);
            }
        } elseif ($this->transactionDepth > 1) {
            $this->transactionDepth--;
            $this->pdo->exec("ROLLBACK TO SAVEPOINT trans{$this->transactionDepth}");
        }
    }

    /**
     * Check whether a transaction is currently active.
     *
     * @return bool
     */
    public function inTransaction(): bool
    {
        return $this->transactionDepth > 0;
    }

    // -----------------------------------------------------------------------
    // Debug — Query Log (disabled by default)
    // -----------------------------------------------------------------------

    /**
     * Enable query logging.
     * Has a small per-query overhead; use only during development.
     *
     * @return static
     */
    public function enableQueryLog(): static
    {
        $this->queryLog->enable();

        return $this;
    }

    /**
     * Disable query logging.
     *
     * @return static
     */
    public function disableQueryLog(): static
    {
        $this->queryLog->disable();

        return $this;
    }

    /**
     * Get all recorded query log entries.
     *
     * @return array<int, QueryLogEntry>
     */
    public function getQueryLog(): array
    {
        return $this->queryLog->all();
    }

    /**
     * Get the last executed query entry, or null if the log is empty.
     *
     * @return QueryLogEntry|null
     */
    public function getLastQuery(): ?QueryLogEntry
    {
        return $this->queryLog->last();
    }

    /**
     * Get total number of queries executed (logged).
     *
     * @return int
     */
    public function getQueryCount(): int
    {
        return $this->queryLog->count();
    }

    /**
     * Get total execution time of all logged queries in milliseconds.
     *
     * @return float
     */
    public function getTotalQueryTime(): float
    {
        return $this->queryLog->totalTime();
    }

    /**
     * Get queries that took longer than the given threshold.
     *
     * @param  float $thresholdMs
     * @return array<int, QueryLogEntry>
     */
    public function getSlowQueries(float $thresholdMs): array
    {
        return $this->queryLog->slowQueries($thresholdMs);
    }

    /**
     * Clear all recorded log entries.
     *
     * @return static
     */
    public function flushQueryLog(): static
    {
        $this->queryLog->flush();

        return $this;
    }

    // -----------------------------------------------------------------------
    // Debug — Event Hooks (no-op when no callbacks registered)
    // -----------------------------------------------------------------------

    /**
     * Register a callback to fire before every query.
     *
     * Callback signature: function(string $sql, array $bindings): void
     *
     * @param  callable(string, array<int|string,mixed>): void $callback
     * @return static
     */
    public function beforeQuery(callable $callback): static
    {
        $this->eventHook->before($callback);

        return $this;
    }

    /**
     * Register a callback to fire after every query completes.
     *
     * Callback signature: function(QueryLogEntry $entry): void
     *
     * @param  callable(QueryLogEntry): void $callback
     * @return static
     */
    public function afterQuery(callable $callback): static
    {
        $this->eventHook->after($callback);

        return $this;
    }

    /**
     * Remove all registered before/after query callbacks.
     *
     * @return static
     */
    public function flushHooks(): static
    {
        $this->eventHook->flush();

        return $this;
    }

    // -----------------------------------------------------------------------
    // Accessors
    // -----------------------------------------------------------------------

    /**
     * {@inheritdoc}
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabaseName(): string
    {
        return (string) ($this->config['database'] ?? '');
    }

    /**
     * {@inheritdoc}
     */
    public function getDriverName(): string
    {
        return (string) ($this->config['driver'] ?? Config::MYSQL);
    }

    /**
     * Get the connection name label.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the connection configuration.
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Set the fetch mode for SELECT queries.
     *
     * @param  int $mode One of the PDO::FETCH_* constants
     * @return static
     */
    public function setFetchMode(int $mode): static
    {
        $this->fetchMode = $mode;

        return $this;
    }

    // -----------------------------------------------------------------------
    // Internal helpers
    // -----------------------------------------------------------------------

    /**
     * Prepare, time, and execute a PDO statement.
     *
     * @param  string                   $sql
     * @param  array<int|string, mixed> $bindings
     * @return PDOStatement
     *
     * @throws QueryException
     */
    protected function run(string $sql, array $bindings = []): PDOStatement
    {
        $this->eventHook->fireBefore($sql, $bindings);

        $start = hrtime(true);

        try {
            $stmt = $this->pdo->prepare($sql);
            $this->bindValues($stmt, $bindings);
            $stmt->execute();
        } catch (PDOException $e) {
            $this->handleException($e, $sql, $bindings);

            // Reached only when throw_exceptions = false.
            return $this->pdo->prepare('SELECT 1');
        }

        $timeMs = $this->elapsedMs($start);

        $this->recordAndFire($sql, $bindings, $timeMs);

        return $stmt;
    }

    /**
     * Record to query log and fire after-hooks in one call.
     * Both operations are no-ops when nothing is listening.
     *
     * @param  string                   $sql
     * @param  array<int|string, mixed> $bindings
     * @param  float                    $timeMs
     * @return void
     */
    private function recordAndFire(string $sql, array $bindings, float $timeMs): void
    {
        $needsLog   = $this->queryLog->isEnabled();
        $needsHooks = $this->eventHook->hasHooks();

        // Short-circuit: allocate nothing when both systems are silent.
        if (! $needsLog && ! $needsHooks) {
            return;
        }

        if ($needsLog) {
            $this->queryLog->record($sql, $bindings, $timeMs);
        }

        if ($needsHooks) {
            $entry = new QueryLogEntry($sql, $bindings, $timeMs);
            $this->eventHook->fireAfter($entry);
        }
    }

    /**
     * Bind values to a prepared statement with correct PDO types.
     *
     * @param  PDOStatement             $stmt
     * @param  array<int|string, mixed> $bindings
     * @return void
     */
    protected function bindValues(PDOStatement $stmt, array $bindings): void
    {
        foreach ($bindings as $key => $value) {
            $pdoType = match (true) {
                is_int($value)  => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                is_null($value) => PDO::PARAM_NULL,
                default         => PDO::PARAM_STR,
            };

            $param = is_int($key) ? $key + 1 : $key;

            $stmt->bindValue($param, $value, $pdoType);
        }
    }

    /**
     * Handle a PDOException — throw QueryException or return false silently.
     *
     * @param  PDOException             $e
     * @param  string                   $sql
     * @param  array<int|string, mixed> $bindings
     * @return false
     *
     * @throws QueryException
     */
    protected function handleException(PDOException $e, string $sql, array $bindings): false
    {
        if ($this->throwExceptions) {
            throw new QueryException(
                sql      : $sql,
                bindings : $bindings,
                message  : $e->getMessage(),
                errorCode: $e->getCode() !== 0 ? (string) $e->getCode() : null,
                previous : $e,
            );
        }

        return false;
    }

    /**
     * Calculate elapsed milliseconds from an hrtime(true) start value.
     *
     * @param  int $startNs  Value from hrtime(true) in nanoseconds
     * @return float         Elapsed time in milliseconds
     */
    private function elapsedMs(int $startNs): float
    {
        return (hrtime(true) - $startNs) / 1_000_000;
    }

    /**
     * Build the DSN and create a PDO instance.
     *
     * @param  array<string, mixed> $config
     * @return PDO
     *
     * @throws DatabaseException
     */
    protected function createPdo(array $config): PDO
    {
        $driver = $config['driver'] ?? Config::MYSQL;

        $dsn = match ($driver) {
            Config::MYSQL, 'mariadb' => $this->buildMysqlDsn($config),
            Config::PGSQL            => $this->buildPgsqlDsn($config),
            Config::SQLITE           => $this->buildSqliteDsn($config),
            default => throw new DatabaseException("Unsupported database driver [{$driver}]."),
        };

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => $this->fetchMode,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO(
                dsn      : $dsn,
                username : $config['username'] ?? null,
                password : $config['password'] ?? null,
                options  : $options,
            );
        } catch (PDOException $e) {
            throw DatabaseException::connectionFailed($this->name, $e);
        }

        if (in_array($driver, [Config::MYSQL, 'mariadb'], strict: true)) {
            $this->setMysqlCharset($pdo, $config);
        }

        return $pdo;
    }

    /**
     * @param  array<string, mixed> $config
     * @return string
     */
    protected function buildMysqlDsn(array $config): string
    {
        $host = $config['host']     ?? Config::DEFAULT_HOST;
        $port = $config['port']     ?? Config::DEFAULT_PORT_MYSQL;
        $db   = $config['database'] ?? '';

        return "mysql:host={$host};port={$port};dbname={$db}";
    }

    /**
     * @param  array<string, mixed> $config
     * @return string
     */
    protected function buildPgsqlDsn(array $config): string
    {
        $host = $config['host']     ?? Config::DEFAULT_HOST;
        $port = $config['port']     ?? Config::DEFAULT_PORT_PGSQL;
        $db   = $config['database'] ?? '';;

        return "pgsql:host={$host};port={$port};dbname={$db}";
    }

    /**
     * @param  array<string, mixed> $config
     * @return string
     */
    protected function buildSqliteDsn(array $config): string
    {
        $path = $config['database'] ?? ':memory:';

        return "sqlite:{$path}";
    }

    /**
     * Set charset and collation for MySQL / MariaDB.
     *
     * @param  PDO                  $pdo
     * @param  array<string, mixed> $config
     * @return void
     */
    protected function setMysqlCharset(PDO $pdo, array $config): void
    {
        $charset   = $config['charset']   ?? Config::UTF8MB4;
        $collation = $config['collation'] ?? Config::UTF8MB4_UNICODE_CI;

        $pdo->exec("SET NAMES '{$charset}' COLLATE '{$collation}'");
    }
}
