<?php

declare(strict_types=1);

namespace Foxdb\Eloquent\Concerns;

use Foxdb\Query\Builder;

/**
 * HasSoftDeletes — marks rows as deleted via a nullable `deleted_at` column
 * instead of physically removing them.
 *
 * When this trait is used:
 *   - delete() sets deleted_at to the current UTC timestamp
 *   - All default queries automatically add WHERE deleted_at IS NULL
 *   - restore() clears deleted_at, bringing the row back
 *   - withTrashed() removes the soft-delete scope for one query
 *   - onlyTrashed() returns only soft-deleted rows
 *
 * Requirements:
 *   - The table must have a nullable `deleted_at` DATETIME column.
 *
 * Usage:
 *   class Post extends Model {
 *       use HasSoftDeletes;
 *   }
 *
 *   Post::find(1)->delete();          // sets deleted_at
 *   Post::find(1);                    // null — excluded by default scope
 *   Post::withTrashed()->find(1);     // returns the row
 *   Post::withTrashed()->find(1)->restore(); // clears deleted_at
 */
trait HasSoftDeletes
{
    /**
     * The name of the soft-delete column.
     *
     * @var string
     */
    protected string $deletedAt = 'deleted_at';

    /**
     * Whether the next query should include soft-deleted rows.
     * Reset to false after each query execution.
     *
     * @var bool
     */
    protected static bool $withTrashed = false;

    /**
     * Whether the next query should return ONLY soft-deleted rows.
     *
     * @var bool
     */
    protected static bool $onlyTrashed = false;

    // -----------------------------------------------------------------------
    // Soft delete
    // -----------------------------------------------------------------------

    /**
     * Soft-delete the model by setting the deleted_at column.
     *
     * @return bool
     */
    public function softDelete(): bool
    {
        $this->attributes[$this->deletedAt] = gmdate('Y-m-d H:i:s');

        return $this->newModelQuery()
            ->where($this->primaryKey, $this->getKey())
            ->update([$this->deletedAt => $this->attributes[$this->deletedAt]]) >= 0;
    }

    /**
     * Restore a soft-deleted model by clearing deleted_at.
     *
     * @return bool
     */
    public function restore(): bool
    {
        $this->attributes[$this->deletedAt] = null;

        return $this->newModelQuery()
            ->where($this->primaryKey, $this->getKey())
            ->update([$this->deletedAt => null]) >= 0;
    }

    /**
     * Determine whether this model instance has been soft-deleted.
     *
     * @return bool
     */
    public function trashed(): bool
    {
        return isset($this->attributes[$this->deletedAt])
            && $this->attributes[$this->deletedAt] !== null;
    }

    // -----------------------------------------------------------------------
    // Query modifiers (static — used before query execution)
    // -----------------------------------------------------------------------

    /**
     * Include soft-deleted rows in the next query.
     *
     * @return static  A new model instance with the flag set
     */
    public static function withTrashed(): \Foxdb\Query\Builder
    {
        static::$withTrashed = true;
        static::$onlyTrashed = false;

        return (new static())->newQuery();
    }

    /**
     * Return only soft-deleted rows in the next query.
     *
     * @return \Foxdb\Query\Builder
     */
    public static function onlyTrashed(): \Foxdb\Query\Builder
    {
        static::$onlyTrashed = true;
        static::$withTrashed = false;

        return (new static())->newQuery();
    }

    // -----------------------------------------------------------------------
    // Apply scope to Builder (called by Model::newQuery())
    // -----------------------------------------------------------------------

    /**
     * Apply the soft-delete WHERE scope to a Builder.
     * Called automatically by newQuery() when the trait is in use.
     *
     * @param  Builder $query
     * @return Builder
     */
    public function applySoftDeleteScope(Builder $query): Builder
    {
        if (static::$onlyTrashed) {
            static::$onlyTrashed = false;
            return $query->whereNotNull($this->deletedAt);
        }

        if (static::$withTrashed) {
            static::$withTrashed = false;
            return $query;
        }

        return $query->whereNull($this->deletedAt);
    }

    /**
     * Get the name of the deleted_at column.
     *
     * @return string
     */
    public function getDeletedAtColumn(): string
    {
        return $this->deletedAt;
    }
}