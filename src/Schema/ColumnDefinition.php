<?php

declare(strict_types=1);

namespace Foxdb\Schema;

/**
 * ColumnDefinition — a fluent value object that accumulates column attributes.
 *
 * Created by Blueprint column methods (string, integer, etc.) and
 * modified via chained modifier calls.  SchemaGrammar reads the
 * collected attributes when compiling CREATE / ALTER statements.
 *
 * Usage:
 *   $table->string('email', 200)->nullable()->unique()->default('')
 */
class ColumnDefinition
{
    /**
     * All accumulated column attributes.
     *
     * @var array<string, mixed>
     */
    protected array $attributes = [];

    /**
     * @param array<string, mixed> $attributes  Initial attributes (type, name, …)
     */
    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    // -----------------------------------------------------------------------
    // Core modifiers
    // -----------------------------------------------------------------------

    /**
     * Allow NULL values for this column.
     *
     * @return static
     */
    public function nullable(bool $value = true): static
    {
        $this->attributes['nullable'] = $value;

        return $this;
    }

    /**
     * Set the column default value.
     *
     * @param  mixed $value
     * @return static
     */
    public function default(mixed $value): static
    {
        $this->attributes['default'] = $value;

        return $this;
    }

    /**
     * Mark the column as UNSIGNED (MySQL / MariaDB only).
     *
     * @return static
     */
    public function unsigned(): static
    {
        $this->attributes['unsigned'] = true;

        return $this;
    }

    /**
     * Add a UNIQUE index on this column.
     *
     * @return static
     */
    public function unique(): static
    {
        $this->attributes['unique'] = true;

        return $this;
    }

    /**
     * Add a plain index on this column.
     *
     * @return static
     */
    public function index(): static
    {
        $this->attributes['index'] = true;

        return $this;
    }

    /**
     * Add a PRIMARY KEY constraint on this column.
     *
     * @return static
     */
    public function primary(): static
    {
        $this->attributes['primary'] = true;

        return $this;
    }

    /**
     * Mark this column as AUTO_INCREMENT (MySQL) / SERIAL (PostgreSQL).
     * Usually set internally by id() / bigIncrements() etc.
     *
     * @return static
     */
    public function autoIncrement(): static
    {
        $this->attributes['autoIncrement'] = true;

        return $this;
    }

    /**
     * Place this column AFTER an existing column (MySQL / MariaDB only).
     *
     * @param  string $column
     * @return static
     */
    public function after(string $column): static
    {
        $this->attributes['after'] = $column;

        return $this;
    }

    /**
     * Place this column as the FIRST column in the table (MySQL / MariaDB only).
     *
     * @return static
     */
    public function first(): static
    {
        $this->attributes['first'] = true;

        return $this;
    }

    /**
     * Add a comment to the column (MySQL / MariaDB / PostgreSQL).
     *
     * @param  string $comment
     * @return static
     */
    public function comment(string $comment): static
    {
        $this->attributes['comment'] = $comment;

        return $this;
    }

    /**
     * Flag this column definition as a CHANGE (ALTER COLUMN) rather than ADD.
     * Used when modifying an existing column via Schema::table().
     *
     * @return static
     */
    public function change(): static
    {
        $this->attributes['change'] = true;

        return $this;
    }

    // -----------------------------------------------------------------------
    // Attribute access
    // -----------------------------------------------------------------------

    /**
     * Get a single attribute value, or the default when absent.
     *
     * @param  string $key
     * @param  mixed  $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Check whether an attribute is set (and truthy).
     *
     * @param  string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return ! empty($this->attributes[$key]);
    }

    /**
     * Get all column attributes.
     *
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get the column name.
     *
     * @return string
     */
    public function getName(): string
    {
        return (string) ($this->attributes['name'] ?? '');
    }

    /**
     * Get the column type identifier.
     *
     * @return string
     */
    public function getType(): string
    {
        return (string) ($this->attributes['type'] ?? '');
    }
}
