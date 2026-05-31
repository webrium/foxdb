<?php

declare(strict_types=1);

namespace Foxdb\Debug;

/**
 * Lightweight hook system for query lifecycle events.
 *
 * Disabled by default — no callbacks are registered and no closures
 * are called unless the developer explicitly adds hooks.
 *
 * Usage:
 *   $conn->beforeQuery(function(string $sql, array $bindings): void {
 *       // e.g. log to external system
 *   });
 *
 *   $conn->afterQuery(function(QueryLogEntry $entry): void {
 *       if ($entry->timeMs > 100) {
 *           // alert on slow query
 *       }
 *   });
 */
class QueryEventHook
{
    /**
     * Callbacks fired before each query execution.
     *
     * @var array<int, callable(string, array<int|string,mixed>): void>
     */
    private array $beforeCallbacks = [];

    /**
     * Callbacks fired after each query execution.
     *
     * @var array<int, callable(QueryLogEntry): void>
     */
    private array $afterCallbacks = [];

    // -----------------------------------------------------------------------
    // Registration
    // -----------------------------------------------------------------------

    /**
     * Register a callback to run before every query.
     *
     * The callback receives (string $sql, array $bindings).
     *
     * @param  callable(string, array<int|string,mixed>): void $callback
     * @return void
     */
    public function before(callable $callback): void
    {
        $this->beforeCallbacks[] = $callback;
    }

    /**
     * Register a callback to run after every query completes.
     *
     * The callback receives a QueryLogEntry (sql, bindings, timeMs).
     *
     * @param  callable(QueryLogEntry): void $callback
     * @return void
     */
    public function after(callable $callback): void
    {
        $this->afterCallbacks[] = $callback;
    }

    // -----------------------------------------------------------------------
    // Firing — called internally by Connection
    // -----------------------------------------------------------------------

    /**
     * Fire all "before" callbacks.
     * No-op when no callbacks are registered — zero overhead.
     *
     * @param  string                   $sql
     * @param  array<int|string, mixed> $bindings
     * @return void
     */
    public function fireBefore(string $sql, array $bindings): void
    {
        if (empty($this->beforeCallbacks)) {
            return;
        }

        foreach ($this->beforeCallbacks as $cb) {
            $cb($sql, $bindings);
        }
    }

    /**
     * Fire all "after" callbacks.
     * No-op when no callbacks are registered — zero overhead.
     *
     * @param  QueryLogEntry $entry
     * @return void
     */
    public function fireAfter(QueryLogEntry $entry): void
    {
        if (empty($this->afterCallbacks)) {
            return;
        }

        foreach ($this->afterCallbacks as $cb) {
            $cb($entry);
        }
    }

    // -----------------------------------------------------------------------
    // State
    // -----------------------------------------------------------------------

    /**
     * Whether any hooks are currently registered.
     *
     * @return bool
     */
    public function hasHooks(): bool
    {
        return ! empty($this->beforeCallbacks) || ! empty($this->afterCallbacks);
    }

    /**
     * Remove all registered callbacks.
     *
     * @return void
     */
    public function flush(): void
    {
        $this->beforeCallbacks = [];
        $this->afterCallbacks  = [];
    }
}
