<?php

declare(strict_types=1);

namespace Foxdb;

use Foxdb\Connection\Connection;
use Foxdb\Connection\ConnectionManager;
use Foxdb\Debug\QueryLogEntry;
use Foxdb\Exceptions\DatabaseException;
use Foxdb\Query\Builder;
use Foxdb\Query\Grammars\Grammar;
use Foxdb\Query\Grammars\MySqlGrammar;
use Foxdb\Query\Grammars\PostgresGrammar;
use Foxdb\Query\RawExpression;

/**
 * DB — Static facade for the FoxDB query builder.
 *
 * Wraps a singleton ConnectionManager and exposes a clean static API
 * for registering connections and building queries.
 *
 * Quick start:
 *
 *   DB::addConnection([
 *       'driver'    => 'mysql',
 *       'host'      => '127.0.0.1',
 *       'database'  => 'mydb',
 *       'username'  => 'root',
 *       'password'  => '',
 *       'charset'   => 'utf8mb4',
 *   ]);
 *
 *   $users = DB::table('users')
 *               ->where('active', 1)
 *               ->orderBy('name')
 *               ->get();
 *
 *   DB::transaction(function () {
 *       DB::table('accounts')->where('id', 1)->decrement('balance', 100);
 *       DB::table('accounts')->where('id', 2)->increment('balance', 100);
 *   });
 */
final class DB
{
    /**
     * The shared ConnectionManager instance.
     *
     * @var ConnectionManager|null
     */
    private static ?ConnectionManager $manager = null;

    /**
     * Grammar instances keyed by driver name (cached).
     *
     * @var array<string, Grammar>
     */
    private static array $grammars = [];

    // -----------------------------------------------------------------------
    // Prevent instantiation — this is a static-only facade.
    // -----------------------------------------------------------------------
    private function __construct() {}

    // -----------------------------------------------------------------------
    // Bootstrap
    // -----------------------------------------------------------------------

    /**
     * Register a named connection.
     *
     * The 'name' key inside $config is optional; defaults to 'main'.
     * The first registered connection automatically becomes the default.
     *
     * Supported config keys:
     *   driver    : mysql | pgsql | sqlite  (required)
     *   host      : string                  (default: 127.0.0.1)
     *   port      : int|string              (default: 3306 / 5432)
     *   database  : string                  (required)
     *   username  : string
     *   password  : string
     *   charset   : string                  (default: utf8mb4)
     *   collation : string                  (default: utf8mb4_unicode_ci)
     *   fetch     : PDO::FETCH_*            (default: FETCH_OBJ)
     *   throw_exceptions : bool             (default: true)
     *
     * @param  array<string, mixed> $config
     * @param  string               $name    Connection label (default: 'main')
     * @return void
     */
    public static function addConnection(array $config, string $name = 'main'): void
    {
        static::manager()->addConnection($name, $config);
    }

    /**
     * Switch the default connection to an already-registered connection.
     *
     * @param  string $name
     * @return void
     *
     * @throws DatabaseException If the connection was never registered
     */
    public static function use(string $name): void
    {
        static::manager()->use($name);
    }

    /**
     * Resolve and return a raw Connection instance.
     *
     * @param  string|null $name  Connection name; null = current default
     * @return Connection
     */
    public static function connection(?string $name = null): Connection
    {
        return static::manager()->connection($name);
    }

    /**
     * Reset the facade (closes all connections, clears state).
     * Useful in tests or when re-bootstrapping the application.
     *
     * @return void
     */
    public static function reset(): void
    {
        static::$manager  = null;
        static::$grammars = [];
    }

    // -----------------------------------------------------------------------
    // Query Builder entry point
    // -----------------------------------------------------------------------

    /**
     * Begin a fluent query against a table on the default connection.
     *
     * @param  string $table
     * @param  string|null $connection  Named connection to use (optional)
     * @return Builder
     */
    public static function table(string $table, ?string $connection = null): Builder
    {
        $conn    = static::manager()->connection($connection);
        $grammar = static::grammarFor($conn);

        return (new Builder($conn, $grammar))->table($table);
    }

    /**
     * Create a wrapped raw SQL expression (safe passthrough to Grammar).
     *
     * Usage:
     *   DB::table('users')->select(DB::raw('COUNT(*) as total'))
     *
     * @param  string                   $expression
     * @param  array<int|string, mixed> $bindings
     * @return RawExpression
     */
    public static function raw(string $expression, array $bindings = []): RawExpression
    {
        return new RawExpression($expression, $bindings);
    }

    // -----------------------------------------------------------------------
    // Direct query execution (bypasses Builder)
    // -----------------------------------------------------------------------

    /**
     * Run a raw SELECT and return all rows.
     *
     * @param  string                   $sql
     * @param  array<int|string, mixed> $bindings
     * @param  string|null              $connection
     * @return array<int, object>
     */
    public static function select(string $sql, array $bindings = [], ?string $connection = null): array
    {
        return static::manager()->connection($connection)->select($sql, $bindings);
    }

    /**
     * Run a raw SELECT and return a single row.
     *
     * @param  string                   $sql
     * @param  array<int|string, mixed> $bindings
     * @param  string|null              $connection
     * @return object|false
     */
    public static function selectOne(string $sql, array $bindings = [], ?string $connection = null): object|false
    {
        return static::manager()->connection($connection)->selectOne($sql, $bindings);
    }

    /**
     * Run a raw INSERT statement.
     *
     * @param  string                   $sql
     * @param  array<int|string, mixed> $bindings
     * @param  string|null              $connection
     * @return bool
     */
    public static function insert(string $sql, array $bindings = [], ?string $connection = null): bool
    {
        return static::manager()->connection($connection)->insert($sql, $bindings);
    }

    /**
     * Run a raw INSERT and return the last inserted ID.
     *
     * @param  string                   $sql
     * @param  array<int|string, mixed> $bindings
     * @param  string|null              $connection
     * @return int|string
     */
    public static function insertGetId(string $sql, array $bindings = [], ?string $connection = null): int|string
    {
        return static::manager()->connection($connection)->insertGetId($sql, $bindings);
    }

    /**
     * Run a raw UPDATE and return affected row count.
     *
     * @param  string                   $sql
     * @param  array<int|string, mixed> $bindings
     * @param  string|null              $connection
     * @return int
     */
    public static function update(string $sql, array $bindings = [], ?string $connection = null): int
    {
        return static::manager()->connection($connection)->update($sql, $bindings);
    }

    /**
     * Run a raw DELETE and return affected row count.
     *
     * @param  string                   $sql
     * @param  array<int|string, mixed> $bindings
     * @param  string|null              $connection
     * @return int
     */
    public static function delete(string $sql, array $bindings = [], ?string $connection = null): int
    {
        return static::manager()->connection($connection)->delete($sql, $bindings);
    }

    /**
     * Run a raw DDL statement (CREATE TABLE, ALTER TABLE, etc.).
     *
     * @param  string      $sql
     * @param  string|null $connection
     * @return bool
     */
    public static function statement(string $sql, ?string $connection = null): bool
    {
        return static::manager()->connection($connection)->statement($sql);
    }

    /**
     * Run a raw statement and return the number of affected rows.
     *
     * @param  string                   $sql
     * @param  array<int|string, mixed> $bindings
     * @param  string|null              $connection
     * @return int
     */
    public static function affectingStatement(string $sql, array $bindings = [], ?string $connection = null): int
    {
        return static::manager()->connection($connection)->affectingStatement($sql, $bindings);
    }

    // -----------------------------------------------------------------------
    // Transactions
    // -----------------------------------------------------------------------

    /**
     * Execute a callback inside a database transaction.
     * Automatically commits on success, rolls back on any exception.
     *
     * @param  callable    $callback  Receives the Connection as its first argument
     * @param  string|null $connection
     * @return mixed       Return value of the callback
     *
     * @throws \Throwable  Re-throws any exception thrown inside the callback
     */
    public static function transaction(callable $callback, ?string $connection = null): mixed
    {
        return static::manager()->connection($connection)->transaction($callback);
    }

    /**
     * Begin a database transaction manually.
     *
     * @param  string|null $connection
     * @return void
     */
    public static function beginTransaction(?string $connection = null): void
    {
        static::manager()->connection($connection)->beginTransaction();
    }

    /**
     * Commit the active transaction.
     *
     * @param  string|null $connection
     * @return void
     */
    public static function commit(?string $connection = null): void
    {
        static::manager()->connection($connection)->commit();
    }

    /**
     * Roll back the active transaction.
     *
     * @param  string|null $connection
     * @return void
     */
    public static function rollBack(?string $connection = null): void
    {
        static::manager()->connection($connection)->rollBack();
    }

    /**
     * Check whether a transaction is currently active.
     *
     * @param  string|null $connection
     * @return bool
     */
    public static function inTransaction(?string $connection = null): bool
    {
        return static::manager()->connection($connection)->inTransaction();
    }

    // -----------------------------------------------------------------------
    // Debug — Query Log
    // -----------------------------------------------------------------------

    /**
     * Enable query logging on the given (or default) connection.
     *
     * @param  string|null $connection
     * @return void
     */
    public static function enableQueryLog(?string $connection = null): void
    {
        static::manager()->connection($connection)->enableQueryLog();
    }

    /**
     * Disable query logging.
     *
     * @param  string|null $connection
     * @return void
     */
    public static function disableQueryLog(?string $connection = null): void
    {
        static::manager()->connection($connection)->disableQueryLog();
    }

    /**
     * Get all logged query entries.
     *
     * @param  string|null $connection
     * @return array<int, QueryLogEntry>
     */
    public static function getQueryLog(?string $connection = null): array
    {
        return static::manager()->connection($connection)->getQueryLog();
    }

    /**
     * Get the last executed query entry.
     *
     * @param  string|null $connection
     * @return QueryLogEntry|null
     */
    public static function getLastQuery(?string $connection = null): ?QueryLogEntry
    {
        return static::manager()->connection($connection)->getLastQuery();
    }

    /**
     * Get the total number of logged queries.
     *
     * @param  string|null $connection
     * @return int
     */
    public static function getQueryCount(?string $connection = null): int
    {
        return static::manager()->connection($connection)->getQueryCount();
    }

    /**
     * Get the total execution time of all logged queries in milliseconds.
     *
     * @param  string|null $connection
     * @return float
     */
    public static function getTotalQueryTime(?string $connection = null): float
    {
        return static::manager()->connection($connection)->getTotalQueryTime();
    }

    /**
     * Get logged queries that exceeded the given threshold.
     *
     * @param  float       $thresholdMs
     * @param  string|null $connection
     * @return array<int, QueryLogEntry>
     */
    public static function getSlowQueries(float $thresholdMs, ?string $connection = null): array
    {
        return static::manager()->connection($connection)->getSlowQueries($thresholdMs);
    }

    /**
     * Clear all logged query entries.
     *
     * @param  string|null $connection
     * @return void
     */
    public static function flushQueryLog(?string $connection = null): void
    {
        static::manager()->connection($connection)->flushQueryLog();
    }

    // -----------------------------------------------------------------------
    // Debug — Event Hooks
    // -----------------------------------------------------------------------

    /**
     * Register a callback to fire before every query.
     *
     * Signature: function(string $sql, array $bindings): void
     *
     * @param  callable(string, array<int|string,mixed>): void $callback
     * @param  string|null                                     $connection
     * @return void
     */
    public static function beforeQuery(callable $callback, ?string $connection = null): void
    {
        static::manager()->connection($connection)->beforeQuery($callback);
    }

    /**
     * Register a callback to fire after every query.
     *
     * Signature: function(QueryLogEntry $entry): void
     *
     * @param  callable(QueryLogEntry): void $callback
     * @param  string|null                  $connection
     * @return void
     */
    public static function afterQuery(callable $callback, ?string $connection = null): void
    {
        static::manager()->connection($connection)->afterQuery($callback);
    }

    // -----------------------------------------------------------------------
    // Connection management helpers
    // -----------------------------------------------------------------------

    /**
     * Check whether a connection name has been registered.
     *
     * @param  string $name
     * @return bool
     */
    public static function hasConnection(string $name): bool
    {
        return static::manager()->hasConnection($name);
    }

    /**
     * Get the name of the current default connection.
     *
     * @return string
     */
    public static function getDefaultConnection(): string
    {
        return static::manager()->getDefaultName();
    }

    /**
     * Disconnect (close) a resolved connection.
     * The config is preserved; re-connecting is transparent on next use.
     *
     * @param  string $name
     * @return void
     */
    public static function disconnect(string $name): void
    {
        static::manager()->disconnect($name);
    }

    /**
     * Disconnect all resolved connections.
     *
     * @return void
     */
    public static function disconnectAll(): void
    {
        static::manager()->disconnectAll();
    }

    // -----------------------------------------------------------------------
    // Internal
    // -----------------------------------------------------------------------

    /**
     * Get or create the singleton ConnectionManager.
     *
     * @return ConnectionManager
     */
    private static function manager(): ConnectionManager
    {
        if (static::$manager === null) {
            static::$manager = new ConnectionManager();
        }

        return static::$manager;
    }

    /**
     * Resolve (or create) a Grammar instance appropriate for the connection's driver.
     *
     * Grammar instances are cached per driver name — they are stateless
     * so sharing them across Builder instances is safe.
     *
     * @param  Connection $connection
     * @return Grammar
     */
    private static function grammarFor(Connection $connection): Grammar
    {
        $driver = $connection->getDriverName();

        if (! isset(static::$grammars[$driver])) {
            static::$grammars[$driver] = ($driver === Config::PGSQL)
                ? new PostgresGrammar()
                : new MySqlGrammar();
        }

        return static::$grammars[$driver];
    }
}
