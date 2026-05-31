<?php

declare(strict_types=1);

namespace Foxdb\Exceptions;

use RuntimeException;
use Throwable;

class QueryException extends RuntimeException
{
    /**
     * The SQL query that caused the exception.
     *
     * @var string
     */
    protected string $sql;

    /**
     * The bindings that were used in the query.
     *
     * @var array<int|string, mixed>
     */
    protected array $bindings;

    /**
     * The PDO error code.
     *
     * @var string|null
     */
    protected ?string $errorCode;

    /**
     * @param string                   $sql
     * @param array<int|string, mixed> $bindings
     * @param string                   $message
     * @param string|null              $errorCode
     * @param Throwable|null           $previous
     */
    public function __construct(
        string    $sql,
        array     $bindings,
        string    $message,
        ?string   $errorCode = null,
        ?Throwable $previous  = null,
    ) {
        $this->sql       = $sql;
        $this->bindings  = $bindings;
        $this->errorCode = $errorCode;

        parent::__construct($message, 0, $previous);
    }

    /**
     * Get the SQL query that caused this exception.
     *
     * @return string
     */
    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * Get the bindings used in the failed query.
     *
     * @return array<int|string, mixed>
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Get the database-level error code.
     *
     * @return string|null
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * Get a formatted message including SQL and bindings.
     *
     * @return string
     */
    public function getFormattedMessage(): string
    {
        $params = json_encode($this->bindings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return sprintf(
            "[QueryException]\nMessage  : %s\nSQL      : %s\nBindings : %s\nErrCode  : %s",
            $this->getMessage(),
            $this->sql,
            $params,
            $this->errorCode ?? 'N/A',
        );
    }
}
