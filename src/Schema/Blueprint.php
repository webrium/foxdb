<?php

declare(strict_types=1);

namespace Foxdb\Schema;

/**
 * Blueprint — accumulates column, index, and foreign key definitions
 * for a single table.  SchemaGrammar reads these lists and compiles
 * the final SQL statements.
 *
 * Usage (inside Schema::create / Schema::table callback):
 *   $table->id();
 *   $table->string('name', 150)->nullable();
 *   $table->timestamps();
 *   $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
 */
class Blueprint
{
    /**
     * The table this blueprint is for.
     *
     * @var string
     */
    protected string $table;

    /**
     * Column definitions added to this blueprint.
     *
     * @var array<int, ColumnDefinition>
     */
    protected array $columns = [];

    /**
     * Explicit index definitions (not inlined on a column).
     *
     * Each entry: ['type'=>'index|unique|primary', 'columns'=>[], 'name'=>'']
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $indexes = [];

    /**
     * Foreign key definitions.
     *
     * @var array<int, ForeignKeyDefinition>
     */
    protected array $foreignKeys = [];

    /**
     * Columns to drop (used in Schema::table context).
     *
     * @var array<int, string>
     */
    protected array $droppedColumns = [];

    /**
     * Column renames: ['from' => 'to'].
     *
     * @var array<string, string>
     */
    protected array $renamedColumns = [];

    /**
     * Indexes to drop by name.
     *
     * @var array<int, string>
     */
    protected array $droppedIndexes = [];

    /**
     * Foreign keys to drop by name.
     *
     * @var array<int, string>
     */
    protected array $droppedForeignKeys = [];

    /**
     * @param string $table
     */
    public function __construct(string $table)
    {
        $this->table = $table;
    }

    // -----------------------------------------------------------------------
    // Numeric column types
    // -----------------------------------------------------------------------

    /**
     * Auto-incrementing UNSIGNED BIGINT primary key named 'id'.
     *
     * @return ColumnDefinition
     */
    public function id(): ColumnDefinition
    {
        return $this->bigIncrements('id');
    }

    /**
     * Auto-incrementing UNSIGNED BIGINT primary key with a custom name.
     *
     * @param  string $column
     * @return ColumnDefinition
     */
    public function bigIncrements(string $column): ColumnDefinition
    {
        return $this->addColumn('bigInteger', $column, [
            'autoIncrement' => true,
            'unsigned'      => true,
            'primary'       => true,
        ]);
    }

    /**
     * Auto-incrementing UNSIGNED INT primary key.
     *
     * @param  string $column
     * @return ColumnDefinition
     */
    public function increments(string $column): ColumnDefinition
    {
        return $this->addColumn('integer', $column, [
            'autoIncrement' => true,
            'unsigned'      => true,
            'primary'       => true,
        ]);
    }

    /**
     * TINYINT column.
     *
     * @param  string $column
     * @return ColumnDefinition
     */
    public function tinyInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('tinyInteger', $column);
    }

    /**
     * SMALLINT column.
     *
     * @param  string $column
     * @return ColumnDefinition
     */
    public function smallInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('smallInteger', $column);
    }

    /**
     * INT column.
     *
     * @param  string $column
     * @return ColumnDefinition
     */
    public function integer(string $column): ColumnDefinition
    {
        return $this->addColumn('integer', $column);
    }

    /**
     * BIGINT column.
     *
     * @param  string $column
     * @return ColumnDefinition
     */
    public function bigInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('bigInteger', $column);
    }

    /**
     * FLOAT column with optional precision and scale.
     *
     * @param  string $column
     * @param  int    $precision  Total digits
     * @param  int    $scale      Digits after decimal point
     * @return ColumnDefinition
     */
    public function float(string $column, int $precision = 8, int $scale = 2): ColumnDefinition
    {
        return $this->addColumn('float', $column, [
            'precision' => $precision,
            'scale'     => $scale,
        ]);
    }

    /**
     * DECIMAL column with precision and scale.
     *
     * @param  string $column
     * @param  int    $precision
     * @param  int    $scale
     * @return ColumnDefinition
     */
    public function decimal(string $column, int $precision = 10, int $scale = 2): ColumnDefinition
    {
        return $this->addColumn('decimal', $column, [
            'precision' => $precision,
            'scale'     => $scale,
        ]);
    }

    /**
     * BOOLEAN column (TINYINT(1) on MySQL, BOOLEAN on PostgreSQL).
     *
     * @param  string $column
     * @return ColumnDefinition
     */
    public function boolean(string $column): ColumnDefinition
    {
        return $this->addColumn('boolean', $column);
    }

    // -----------------------------------------------------------------------
    // String column types
    // -----------------------------------------------------------------------

    /**
     * VARCHAR column.
     *
     * @param  string $column
     * @param  int    $length
     * @return ColumnDefinition
     */
    public function string(string $column, int $length = 255): ColumnDefinition
    {
        return $this->addColumn('string', $column, ['length' => $length]);
    }

    /**
     * CHAR column with a fixed length.
     *
     * @param  string $column
     * @param  int    $length
     * @return ColumnDefinition
     */
    public function char(string $column, int $length = 1): ColumnDefinition
    {
        return $this->addColumn('char', $column, ['length' => $length]);
    }

    /**
     * TEXT column.
     *
     * @param  string $column
     * @return ColumnDefinition
     */
    public function text(string $column): ColumnDefinition
    {
        return $this->addColumn('text', $column);
    }

    /**
     * MEDIUMTEXT column (MySQL) — falls back to TEXT on PostgreSQL.
     *
     * @param  string $column
     * @return ColumnDefinition
     */
    public function mediumText(string $column): ColumnDefinition
    {
        return $this->addColumn('mediumText', $column);
    }

    /**
     * LONGTEXT column (MySQL) — falls back to TEXT on PostgreSQL.
     *
     * @param  string $column
     * @return ColumnDefinition
     */
    public function longText(string $column): ColumnDefinition
    {
        return $this->addColumn('longText', $column);
    }

    /**
     * ENUM column (MySQL). On PostgreSQL a CHECK constraint is emitted instead.
     *
     * @param  string            $column
     * @param  array<int, string> $allowed
     * @return ColumnDefinition
     */
    public function enum(string $column, array $allowed): ColumnDefinition
    {
        return $this->addColumn('enum', $column, ['allowed' => $allowed]);
    }

    /**
     * JSON column (JSON on MySQL, JSONB on PostgreSQL).
     *
     * @param  string $column
     * @return ColumnDefinition
     */
    public function json(string $column): ColumnDefinition
    {
        return $this->addColumn('json', $column);
    }

    /**
     * UUID / CHAR(36) column.
     *
     * @param  string $column
     * @return ColumnDefinition
     */
    public function uuid(string $column): ColumnDefinition
    {
        return $this->addColumn('uuid', $column);
    }

    /**
     * BINARY / BLOB column.
     *
     * @param  string $column
     * @return ColumnDefinition
     */
    public function binary(string $column): ColumnDefinition
    {
        return $this->addColumn('binary', $column);
    }

    // -----------------------------------------------------------------------
    // Date / time column types
    // -----------------------------------------------------------------------

    /**
     * DATE column.
     *
     * @param  string $column
     * @return ColumnDefinition
     */
    public function date(string $column): ColumnDefinition
    {
        return $this->addColumn('date', $column);
    }

    /**
     * TIME column.
     *
     * @param  string $column
     * @return ColumnDefinition
     */
    public function time(string $column): ColumnDefinition
    {
        return $this->addColumn('time', $column);
    }

    /**
     * DATETIME column.
     *
     * @param  string $column
     * @return ColumnDefinition
     */
    public function dateTime(string $column): ColumnDefinition
    {
        return $this->addColumn('dateTime', $column);
    }

    /**
     * TIMESTAMP column.
     *
     * @param  string $column
     * @return ColumnDefinition
     */
    public function timestamp(string $column): ColumnDefinition
    {
        return $this->addColumn('timestamp', $column);
    }

    // -----------------------------------------------------------------------
    // Convenience shorthands
    // -----------------------------------------------------------------------

    /**
     * Add nullable `created_at` and `updated_at` TIMESTAMP columns.
     *
     * @return void
     */
    public function timestamps(): void
    {
        $this->timestamp('created_at')->nullable();
        $this->timestamp('updated_at')->nullable();
    }

    /**
     * Add a nullable `deleted_at` TIMESTAMP column for soft deletes.
     *
     * @param  string $column
     * @return ColumnDefinition
     */
    public function softDeletes(string $column = 'deleted_at'): ColumnDefinition
    {
        return $this->timestamp($column)->nullable();
    }

    /**
     * Add an UNSIGNED BIGINT `{relation}_id` column for a foreign key.
     *
     * @param  string $column
     * @return ColumnDefinition
     */
    public function foreignId(string $column): ColumnDefinition
    {
        return $this->addColumn('bigInteger', $column, ['unsigned' => true]);
    }

    /**
     * Shorthand: foreignId + constrained FK to the conventional table.
     *
     * ->foreignId('user_id')->constrained()
     * → BIGINT UNSIGNED + FK to users.id
     *
     * @param  string      $column
     * @param  string|null $table       Defaults to plural of the column root
     * @param  string      $reference   Referenced column (default 'id')
     * @return ForeignKeyDefinition
     */
    public function foreignIdFor(
        string $column,
        ?string $table = null,
        string $reference = 'id',
    ): ForeignKeyDefinition {
        $this->foreignId($column);

        $table ??= rtrim(str_replace('_id', '', $column), '_') . 's';

        return $this->foreign($column)->references($reference)->on($table);
    }

    // -----------------------------------------------------------------------
    // Indexes
    // -----------------------------------------------------------------------

    /**
     * Add a plain index on one or more columns.
     *
     * @param  string|array<int, string> $columns
     * @param  string|null               $name    Auto-generated if null
     * @return void
     */
    public function index(string|array $columns, ?string $name = null): void
    {
        $cols = (array) $columns;
        $this->indexes[] = [
            'type'    => 'index',
            'columns' => $cols,
            'name'    => $name ?? $this->generateIndexName('index', $cols),
        ];
    }

    /**
     * Add a UNIQUE index on one or more columns.
     *
     * @param  string|array<int, string> $columns
     * @param  string|null               $name
     * @return void
     */
    public function unique(string|array $columns, ?string $name = null): void
    {
        $cols = (array) $columns;
        $this->indexes[] = [
            'type'    => 'unique',
            'columns' => $cols,
            'name'    => $name ?? $this->generateIndexName('unique', $cols),
        ];
    }

    /**
     * Add a PRIMARY KEY constraint on one or more columns.
     *
     * @param  string|array<int, string> $columns
     * @param  string|null               $name
     * @return void
     */
    public function primary(string|array $columns, ?string $name = null): void
    {
        $cols = (array) $columns;
        $this->indexes[] = [
            'type'    => 'primary',
            'columns' => $cols,
            'name'    => $name ?? $this->generateIndexName('primary', $cols),
        ];
    }

    /**
     * Drop an index by name.
     *
     * @param  string $name
     * @return void
     */
    public function dropIndex(string $name): void
    {
        $this->droppedIndexes[] = $name;
    }

    /**
     * Drop a unique index by name.
     *
     * @param  string $name
     * @return void
     */
    public function dropUnique(string $name): void
    {
        $this->droppedIndexes[] = $name;
    }

    // -----------------------------------------------------------------------
    // Foreign keys
    // -----------------------------------------------------------------------

    /**
     * Define a foreign key constraint on a column.
     *
     * @param  string $column
     * @return ForeignKeyDefinition
     */
    public function foreign(string $column): ForeignKeyDefinition
    {
        $fk = new ForeignKeyDefinition($column);
        $this->foreignKeys[] = $fk;

        return $fk;
    }

    /**
     * Drop a foreign key constraint by name.
     *
     * @param  string $name
     * @return void
     */
    public function dropForeign(string $name): void
    {
        $this->droppedForeignKeys[] = $name;
    }

    // -----------------------------------------------------------------------
    // Column mutation (Schema::table context)
    // -----------------------------------------------------------------------

    /**
     * Mark a column for removal.
     *
     * @param  string|array<int, string> $columns
     * @return void
     */
    public function dropColumn(string|array $columns): void
    {
        foreach ((array) $columns as $col) {
            $this->droppedColumns[] = $col;
        }
    }

    /**
     * Rename a column.
     *
     * @param  string $from
     * @param  string $to
     * @return void
     */
    public function renameColumn(string $from, string $to): void
    {
        $this->renamedColumns[$from] = $to;
    }

    // -----------------------------------------------------------------------
    // Accessors (used by SchemaGrammar)
    // -----------------------------------------------------------------------

    /** @return string */
    public function getTable(): string
    {
        return $this->table;
    }

    /** @return array<int, ColumnDefinition> */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /** @return array<int, array<string, mixed>> */
    public function getIndexes(): array
    {
        return $this->indexes;
    }

    /** @return array<int, ForeignKeyDefinition> */
    public function getForeignKeys(): array
    {
        return $this->foreignKeys;
    }

    /** @return array<int, string> */
    public function getDroppedColumns(): array
    {
        return $this->droppedColumns;
    }

    /** @return array<string, string> */
    public function getRenamedColumns(): array
    {
        return $this->renamedColumns;
    }

    /** @return array<int, string> */
    public function getDroppedIndexes(): array
    {
        return $this->droppedIndexes;
    }

    /** @return array<int, string> */
    public function getDroppedForeignKeys(): array
    {
        return $this->droppedForeignKeys;
    }

    /**
     * Get only columns that have the 'change' flag set (for ALTER COLUMN).
     *
     * @return array<int, ColumnDefinition>
     */
    public function getChangedColumns(): array
    {
        return array_values(array_filter(
            $this->columns,
            fn(ColumnDefinition $col) => $col->has('change'),
        ));
    }

    /**
     * Get only columns that do NOT have the 'change' flag (for ADD COLUMN).
     *
     * @return array<int, ColumnDefinition>
     */
    public function getAddedColumns(): array
    {
        return array_values(array_filter(
            $this->columns,
            fn(ColumnDefinition $col) => ! $col->has('change'),
        ));
    }

    // -----------------------------------------------------------------------
    // Internal helpers
    // -----------------------------------------------------------------------

    /**
     * Add a column definition to the blueprint and return it for chaining.
     *
     * @param  string               $type
     * @param  string               $name
     * @param  array<string, mixed> $extra
     * @return ColumnDefinition
     */
    protected function addColumn(string $type, string $name, array $extra = []): ColumnDefinition
    {
        $col = new ColumnDefinition(array_merge(['type' => $type, 'name' => $name], $extra));
        $this->columns[] = $col;

        return $col;
    }

    /**
     * Generate a conventional index name from table + columns.
     *
     * @param  string            $type
     * @param  array<int, string> $columns
     * @return string
     */
    protected function generateIndexName(string $type, array $columns): string
    {
        return $this->table . '_' . implode('_', $columns) . '_' . $type;
    }
}
