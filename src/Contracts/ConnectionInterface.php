<?php

declare(strict_types=1);

namespace Foxdb\Contracts;

use PDO;
use PDOStatement;

interface ConnectionInterface
{
    /**
     * Run a select query and return all results.
     *
     * @param  string                $sql
     * @param  array<int|string, mixed> $bindings
     * @return array<int, object>
     */
    public function select(string $sql, array $bindings = []): array;

    /**
     * Run a select query and return a single row.
     *
     * @param  string                $sql
     * @param  array<int|string, mixed> $bindings
     * @return object|false
     */
    public function selectOne(string $sql, array $bindings = []): object|false;

    /**
     * Run an insert statement and return bool.
     *
     * @param  string                $sql
     * @param  array<int|string, mixed> $bindings
     * @return bool
     */
    public function insert(string $sql, array $bindings = []): bool;

    /**
     * Run an insert and return the last inserted ID.
     *
     * @param  string                $sql
     * @param  array<int|string, mixed> $bindings
     * @return int|string
     */
    public function insertGetId(string $sql, array $bindings = []): int|string;

    /**
     * Run an update statement and return affected rows count.
     *
     * @param  string                $sql
     * @param  array<int|string, mixed> $bindings
     * @return int
     */
    public function update(string $sql, array $bindings = []): int;

    /**
     * Run a delete statement and return affected rows count.
     *
     * @param  string                $sql
     * @param  array<int|string, mixed> $bindings
     * @return int
     */
    public function delete(string $sql, array $bindings = []): int;

    /**
     * Run a raw SQL statement (DDL, etc.) and return bool.
     *
     * @param  string $sql
     * @return bool
     */
    public function statement(string $sql): bool;

    /**
     * Run a raw SQL and return number of affected rows.
     *
     * @param  string                $sql
     * @param  array<int|string, mixed> $bindings
     * @return int
     */
    public function affectingStatement(string $sql, array $bindings = []): int;

    /**
     * Execute a closure within a database transaction.
     *
     * @param  callable $callback
     * @return mixed
     */
    public function transaction(callable $callback): mixed;

    /**
     * Begin a database transaction.
     *
     * @return void
     */
    public function beginTransaction(): void;

    /**
     * Commit the active database transaction.
     *
     * @return void
     */
    public function commit(): void;

    /**
     * Rollback the active database transaction.
     *
     * @return void
     */
    public function rollBack(): void;

    /**
     * Get the underlying PDO instance.
     *
     * @return PDO
     */
    public function getPdo(): PDO;

    /**
     * Get the name of the connected database.
     *
     * @return string
     */
    public function getDatabaseName(): string;

    /**
     * Get the driver name (mysql, pgsql, sqlite, etc.).
     *
     * @return string
     */
    public function getDriverName(): string;
}
