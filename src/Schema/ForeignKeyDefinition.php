<?php

declare(strict_types=1);

namespace Foxdb\Schema;

/**
 * ForeignKeyDefinition — fluent builder for FOREIGN KEY constraints.
 *
 * Usage:
 *   $table->foreign('user_id')
 *         ->references('id')
 *         ->on('users')
 *         ->onDelete('cascade')
 *         ->onUpdate('restrict');
 */
class ForeignKeyDefinition
{
    /**
     * The column on THIS table that holds the FK value.
     *
     * @var string
     */
    protected string $column;

    /**
     * The referenced column on the foreign table.
     *
     * @var string
     */
    protected string $references = 'id';

    /**
     * The foreign table name.
     *
     * @var string
     */
    protected string $on = '';

    /**
     * The ON DELETE action.
     *
     * @var string
     */
    protected string $onDelete = 'RESTRICT';

    /**
     * The ON UPDATE action.
     *
     * @var string
     */
    protected string $onUpdate = 'RESTRICT';

    /**
     * Optional explicit constraint name.
     *
     * @var string|null
     */
    protected ?string $constraintName = null;

    /**
     * @param string $column  The local column holding the FK
     */
    public function __construct(string $column)
    {
        $this->column = $column;
    }

    /**
     * Set the referenced column on the foreign table.
     *
     * @param  string $column
     * @return static
     */
    public function references(string $column): static
    {
        $this->references = $column;

        return $this;
    }

    /**
     * Set the foreign (referenced) table.
     *
     * @param  string $table
     * @return static
     */
    public function on(string $table): static
    {
        $this->on = $table;

        return $this;
    }

    /**
     * Set the ON DELETE action.
     *
     * @param  string $action  cascade | restrict | set null | no action
     * @return static
     */
    public function onDelete(string $action): static
    {
        $this->onDelete = strtoupper($action);

        return $this;
    }

    /**
     * Set the ON UPDATE action.
     *
     * @param  string $action  cascade | restrict | set null | no action
     * @return static
     */
    public function onUpdate(string $action): static
    {
        $this->onUpdate = strtoupper($action);

        return $this;
    }

    /**
     * Cascade deletes — shorthand for onDelete('cascade').
     *
     * @return static
     */
    public function cascadeOnDelete(): static
    {
        return $this->onDelete('CASCADE');
    }

    /**
     * Null out the column on delete — shorthand for onDelete('set null').
     *
     * @return static
     */
    public function nullOnDelete(): static
    {
        return $this->onDelete('SET NULL');
    }

    /**
     * Set a custom constraint name (auto-generated from columns if not set).
     *
     * @param  string $name
     * @return static
     */
    public function name(string $name): static
    {
        $this->constraintName = $name;

        return $this;
    }

    // -----------------------------------------------------------------------
    // Accessors (used by SchemaGrammar)
    // -----------------------------------------------------------------------

    /** @return string */
    public function getColumn(): string { return $this->column; }

    /** @return string */
    public function getReferences(): string { return $this->references; }

    /** @return string */
    public function getOn(): string { return $this->on; }

    /** @return string */
    public function getOnDelete(): string { return $this->onDelete; }

    /** @return string */
    public function getOnUpdate(): string { return $this->onUpdate; }

    /**
     * Get the constraint name, generating one if not explicitly set.
     *
     * @param  string $table  The table that owns this FK (for auto-naming)
     * @return string
     */
    public function getConstraintName(string $table): string
    {
        return $this->constraintName
            ?? "{$table}_{$this->column}_foreign";
    }
}
