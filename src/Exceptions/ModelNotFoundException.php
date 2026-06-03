<?php

declare(strict_types=1);

namespace Foxdb\Exceptions;

use RuntimeException;

/**
 * Thrown when a Model query expected exactly one result but found none.
 */
class ModelNotFoundException extends RuntimeException
{
    /**
     * The model class that was searched.
     *
     * @var string
     */
    protected string $model;

    /**
     * The primary key(s) that were searched.
     *
     * @var int|string|array<int|string>
     */
    protected int|string|array $ids;

    /**
     * @param string                       $model
     * @param int|string|array<int|string> $ids
     */
    public function __construct(string $model, int|string|array $ids = [])
    {
        $this->model = $model;
        $this->ids   = $ids;

        $idString = is_array($ids) ? implode(', ', $ids) : (string) $ids;
        $suffix   = $idString !== '' ? " [{$idString}]" : '';

        parent::__construct("No query results for model [{$model}]{$suffix}.");
    }

    /**
     * Get the class name of the model that was not found.
     *
     * @return string
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Get the primary key(s) that were searched.
     *
     * @return int|string|array<int|string>
     */
    public function getIds(): int|string|array
    {
        return $this->ids;
    }
}
