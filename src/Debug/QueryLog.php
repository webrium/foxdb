<?php

declare(strict_types=1);

namespace Foxdb\Debug;

/**
 * Stores a log of executed SQL queries with their bindings and execution time.
 *
 * Disabled by default — zero overhead when not in use.
 * Enable only in development or when debugging is needed.
 *
 * Usage:
 *   $conn->enableQueryLog();
 *   // ... run queries ...
 *   $log = $conn->getQueryLog();
 */
class QueryLog
{
    /**
     * Whether query logging is active.
     *
     * @var bool
     */
    private bool $enabled = false;

    /**
     * The log entries collected so far.
     *
     * @var array<int, QueryLogEntry>
     */
    private array $entries = [];

    // -----------------------------------------------------------------------
    // Enable / disable
    // -----------------------------------------------------------------------

    /**
     * Enable query logging.
     *
     * @return void
     */
    public function enable(): void
    {
        $this->enabled = true;
    }

    /**
     * Disable query logging.
     * Existing entries are preserved until flush() is called.
     *
     * @return void
     */
    public function disable(): void
    {
        $this->enabled = false;
    }

    /**
     * Whether logging is currently active.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    // -----------------------------------------------------------------------
    // Recording
    // -----------------------------------------------------------------------

    /**
     * Record a query entry.
     * No-op when logging is disabled — no allocation, no overhead.
     *
     * @param  string                   $sql
     * @param  array<int|string, mixed> $bindings
     * @param  float                    $timeMs   Execution time in milliseconds
     * @return void
     */
    public function record(string $sql, array $bindings, float $timeMs): void
    {
        if (! $this->enabled) {
            return;
        }

        $this->entries[] = new QueryLogEntry($sql, $bindings, $timeMs);
    }

    // -----------------------------------------------------------------------
    // Reading
    // -----------------------------------------------------------------------

    /**
     * Get all recorded query entries.
     *
     * @return array<int, QueryLogEntry>
     */
    public function all(): array
    {
        return $this->entries;
    }

    /**
     * Get the last recorded entry, or null if the log is empty.
     *
     * @return QueryLogEntry|null
     */
    public function last(): ?QueryLogEntry
    {
        if (empty($this->entries)) {
            return null;
        }

        return end($this->entries);
    }

    /**
     * Get the total number of queries recorded.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->entries);
    }

    /**
     * Get the total execution time of all recorded queries in milliseconds.
     *
     * @return float
     */
    public function totalTime(): float
    {
        return array_sum(
            array_map(fn(QueryLogEntry $e) => $e->timeMs, $this->entries)
        );
    }

    /**
     * Get the slowest query entry, or null when the log is empty.
     *
     * @return QueryLogEntry|null
     */
    public function slowest(): ?QueryLogEntry
    {
        if (empty($this->entries)) {
            return null;
        }

        return array_reduce(
            $this->entries,
            fn(?QueryLogEntry $carry, QueryLogEntry $e) =>
                $carry === null || $e->timeMs > $carry->timeMs ? $e : $carry,
        );
    }

    /**
     * Get entries that took longer than the given threshold.
     *
     * @param  float $thresholdMs
     * @return array<int, QueryLogEntry>
     */
    public function slowQueries(float $thresholdMs): array
    {
        return array_values(
            array_filter(
                $this->entries,
                fn(QueryLogEntry $e) => $e->timeMs >= $thresholdMs,
            )
        );
    }

    // -----------------------------------------------------------------------
    // Maintenance
    // -----------------------------------------------------------------------

    /**
     * Clear all log entries (logging state is preserved).
     *
     * @return void
     */
    public function flush(): void
    {
        $this->entries = [];
    }
}
