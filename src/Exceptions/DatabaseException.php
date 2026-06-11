<?php

declare(strict_types=1);

namespace Foxdb\Exceptions;

use RuntimeException;
use Throwable;

class DatabaseException extends RuntimeException
{
    /**
     * @param string         $message
     * @param Throwable|null $previous
     */
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Create exception for failed connection attempts.
     *
     * @param  string         $connectionName
     * @param  Throwable|null $previous
     * @return static
     */
    public static function connectionFailed(string $connectionName, ?Throwable $previous = null): static
    {
        return new static(
            "Failed to connect to database using connection [{$connectionName}].",
            $previous,
        );
    }

    /**
     * Create exception for missing connection config.
     *
     * @param  string $connectionName
     * @return static
     */
    public static function connectionNotFound(string $connectionName): static
    {
        return new static(
            "Connection [{$connectionName}] is not defined. "
            . "Make sure to call DB::addConnection() before using it."
        );
    }

    /**
     * Create exception for transaction failures.
     *
     * @param  string         $operation  'begin' | 'commit' | 'rollback'
     * @param  Throwable|null $previous
     * @return static
     */
    public static function transactionFailed(string $operation, ?Throwable $previous = null): static
    {
        return new static(
            "Database transaction [{$operation}] failed.",
            $previous,
        );
    }
}
