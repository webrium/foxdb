<?php

declare(strict_types=1);

namespace Foxdb\Eloquent\Concerns;

/**
 * HasTimestamps — automatically manages created_at and updated_at columns.
 *
 * Used internally by Model::save().
 * Set $timestamps = false on the model to disable.
 */
trait HasTimestamps
{
    /**
     * Whether the model uses timestamp columns.
     *
     * @var bool
     */
    protected bool $timestamps = true;

    /**
     * The name of the "created at" column.
     *
     * @var string
     */
    protected string $createdAt = 'created_at';

    /**
     * The name of the "updated at" column.
     *
     * @var string
     */
    protected string $updatedAt = 'updated_at';

    /**
     * Set the created_at and updated_at timestamps on the attribute array.
     *
     * @param  array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    protected function addTimestampsForInsert(array $attributes): array
    {
        if (! $this->timestamps) {
            return $attributes;
        }

        $now = $this->freshTimestamp();

        if (! isset($attributes[$this->createdAt])) {
            $attributes[$this->createdAt] = $now;
        }

        if (! isset($attributes[$this->updatedAt])) {
            $attributes[$this->updatedAt] = $now;
        }

        return $attributes;
    }

    /**
     * Set only the updated_at timestamp on the attribute array.
     *
     * @param  array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    protected function addTimestampsForUpdate(array $attributes): array
    {
        if (! $this->timestamps) {
            return $attributes;
        }

        $attributes[$this->updatedAt] = $this->freshTimestamp();

        return $attributes;
    }

    /**
     * Get a fresh UTC timestamp string in 'Y-m-d H:i:s' format.
     *
     * @return string
     */
    protected function freshTimestamp(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    /**
     * Get the name of the created_at column.
     *
     * @return string
     */
    public function getCreatedAtColumn(): string
    {
        return $this->createdAt;
    }

    /**
     * Get the name of the updated_at column.
     *
     * @return string
     */
    public function getUpdatedAtColumn(): string
    {
        return $this->updatedAt;
    }

    /**
     * Determine whether timestamps are used on this model.
     *
     * @return bool
     */
    public function usesTimestamps(): bool
    {
        return $this->timestamps;
    }
}
