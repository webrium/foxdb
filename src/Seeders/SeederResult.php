<?php

declare(strict_types=1);

namespace Foxdb\Seeders;

/**
 * SeederResult — immutable record of a single seeder execution.
 */
final class SeederResult
{
    /**
     * @param string $name    Seeder class name (with or without namespace)
     * @param bool   $success Whether it completed without error
     * @param float  $timeMs  Execution time in milliseconds
     * @param string $error   Error message if $success = false
     */
    public function __construct(
        public readonly string $name,
        public readonly bool   $success,
        public readonly float  $timeMs,
        public readonly string $error = '',
    ) {}

    /**
     * Convenience constructor for a successful result.
     */
    public static function ok(string $name, float $timeMs): self
    {
        return new self($name, true, $timeMs);
    }

    /**
     * Convenience constructor for a failed result.
     */
    public static function fail(string $name, float $timeMs, string $error): self
    {
        return new self($name, false, $timeMs, $error);
    }

    /**
     * Human-readable one-line summary.
     *
     * @return string
     */
    public function toString(): string
    {
        $status = $this->success ? 'OK' : 'FAILED';
        $time   = number_format($this->timeMs, 2);
        $line   = "[{$status}] seed {$this->name} ({$time} ms)";

        if (! $this->success && $this->error !== '') {
            $line .= " — {$this->error}";
        }

        return $line;
    }
}
