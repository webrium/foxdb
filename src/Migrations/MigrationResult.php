<?php

declare(strict_types=1);

namespace Foxdb\Migrations;

/**
 * MigrationResult — immutable record of a single migration execution.
 */
final class MigrationResult
{
    /**
     * @param string $name      Migration name (file without .php)
     * @param string $direction 'up' | 'down'
     * @param bool   $success   Whether it completed without error
     * @param float  $timeMs    Execution time in milliseconds
     * @param string $error     Error message if $success = false
     */
    public function __construct(
        public readonly string $name,
        public readonly string $direction,
        public readonly bool   $success,
        public readonly float  $timeMs,
        public readonly string $error = '',
    ) {}

    /**
     * Human-readable one-line summary.
     *
     * @return string
     */
    public function toString(): string
    {
        $status = $this->success ? 'OK' : 'FAILED';
        $time   = number_format($this->timeMs, 2);
        $line   = "[{$status}] {$this->direction} {$this->name} ({$time} ms)";

        if (! $this->success && $this->error !== '') {
            $line .= " — {$this->error}";
        }

        return $line;
    }
}
