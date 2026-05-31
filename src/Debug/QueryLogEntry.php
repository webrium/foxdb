<?php

declare(strict_types=1);

namespace Foxdb\Debug;

use DateTimeImmutable;

/**
 * Immutable value object representing a single executed query.
 */
final class QueryLogEntry
{
    /**
     * Wall-clock time when this query was executed.
     *
     * @var DateTimeImmutable
     */
    public readonly DateTimeImmutable $executedAt;

    /**
     * @param string                   $sql       The SQL string (with ? placeholders)
     * @param array<int|string, mixed> $bindings  Bound parameter values
     * @param float                    $timeMs    Execution time in milliseconds
     */
    public function __construct(
        public readonly string $sql,
        public readonly array  $bindings,
        public readonly float  $timeMs,
    ) {
        $this->executedAt = new DateTimeImmutable();
    }

    /**
     * Build a human-readable representation of this entry.
     *
     * @return string
     */
    public function toString(): string
    {
        $params = json_encode($this->bindings, JSON_UNESCAPED_UNICODE);
        $time   = number_format($this->timeMs, 3);

        return sprintf(
            '[%s] (%s ms) %s | bindings: %s',
            $this->executedAt->format('Y-m-d H:i:s'),
            $time,
            $this->sql,
            $params,
        );
    }

    /**
     * Return the SQL with binding values interpolated (for display only — NOT for execution).
     *
     * @return string
     */
    public function toSqlWithBindings(): string
    {
        $sql      = $this->sql;
        $bindings = array_values($this->bindings);
        $index    = 0;

        return (string) preg_replace_callback('/\?/', function () use ($bindings, &$index): string {
            if (! isset($bindings[$index])) {
                return '?';
            }

            $value = $bindings[$index++];

            return match (true) {
                is_null($value)   => 'NULL',
                is_bool($value)   => $value ? 'TRUE' : 'FALSE',
                is_int($value),
                is_float($value)  => (string) $value,
                default           => "'" . addslashes((string) $value) . "'",
            };
        }, $sql);
    }
}
