<?php

declare(strict_types=1);

namespace Foxdb\Schema\Grammars;

use Foxdb\Schema\Blueprint;
use Foxdb\Schema\ColumnDefinition;
use Foxdb\Schema\ForeignKeyDefinition;

/**
 * Abstract SchemaGrammar — compiles Blueprint definitions into DDL SQL.
 *
 * Each supported driver extends this class and overrides only the
 * methods where the SQL syntax differs.
 */
abstract class SchemaGrammar
{
    /**
     * The identifier quote character (open).
     *
     * @var string
     */
    protected string $quoteOpen  = '"';

    /**
     * The identifier quote character (close).
     *
     * @var string
     */
    protected string $quoteClose = '"';

    // -----------------------------------------------------------------------
    // CREATE TABLE
    // -----------------------------------------------------------------------

    /**
     * Compile a CREATE TABLE statement from a Blueprint.
     *
     * @param  Blueprint $blueprint
     * @return string
     */
    public function compileCreate(Blueprint $blueprint): string
    {
        $table   = $this->wrapTable($blueprint->getTable());
        $parts   = [];

        foreach ($blueprint->getColumns() as $col) {
            $parts[] = $this->compileColumnDefinition($col);
        }

        foreach ($blueprint->getIndexes() as $index) {
            $compiled = $this->compileInlineIndex($index, $blueprint);
            if ($compiled !== '') {
                $parts[] = $compiled;
            }
        }

        foreach ($blueprint->getForeignKeys() as $fk) {
            $parts[] = $this->compileForeignKeyConstraint($fk, $blueprint->getTable());
        }

        return "CREATE TABLE {$table} (\n  "
            . implode(",\n  ", $parts)
            . "\n)";
    }

    /**
     * Compile a single column definition line.
     *
     * @param  ColumnDefinition $col
     * @return string
     */
    public function compileColumnDefinition(ColumnDefinition $col): string
    {
        $sql = $this->wrap($col->getName()) . ' ' . $this->compileType($col);

        $sql .= $this->compileUnsigned($col);
        $sql .= $this->compileNullable($col);
        $sql .= $this->compileDefault($col);
        $sql .= $this->compileAutoIncrement($col);
        $sql .= $this->compilePrimary($col);
        $sql .= $this->compileUnique($col);
        $sql .= $this->compileComment($col);

        return $sql;
    }

    // -----------------------------------------------------------------------
    // ALTER TABLE — add columns
    // -----------------------------------------------------------------------

    /**
     * Compile ALTER TABLE … ADD COLUMN statements.
     * Returns one SQL string per column.
     *
     * @param  Blueprint $blueprint
     * @return array<int, string>
     */
    public function compileAdd(Blueprint $blueprint): array
    {
        $table = $this->wrapTable($blueprint->getTable());
        $sqls  = [];

        foreach ($blueprint->getAddedColumns() as $col) {
            $colSql = $this->compileColumnDefinition($col);
            $colSql .= $this->compileAfter($col);
            $sqls[] = "ALTER TABLE {$table} ADD COLUMN {$colSql}";
        }

        return $sqls;
    }

    // -----------------------------------------------------------------------
    // ALTER TABLE — change column
    // -----------------------------------------------------------------------

    /**
     * Compile ALTER TABLE … MODIFY/ALTER COLUMN for changed columns.
     * Returns one SQL string per column.
     *
     * @param  Blueprint $blueprint
     * @return array<int, string>
     */
    abstract public function compileChange(Blueprint $blueprint): array;

    // -----------------------------------------------------------------------
    // ALTER TABLE — drop columns
    // -----------------------------------------------------------------------

    /**
     * Compile ALTER TABLE … DROP COLUMN statements.
     *
     * @param  Blueprint $blueprint
     * @return array<int, string>
     */
    public function compileDrop(Blueprint $blueprint): array
    {
        $table = $this->wrapTable($blueprint->getTable());
        $sqls  = [];

        foreach ($blueprint->getDroppedColumns() as $col) {
            $sqls[] = "ALTER TABLE {$table} DROP COLUMN {$this->wrap($col)}";
        }

        return $sqls;
    }

    // -----------------------------------------------------------------------
    // ALTER TABLE — rename column
    // -----------------------------------------------------------------------

    /**
     * Compile ALTER TABLE … RENAME COLUMN statements.
     *
     * @param  Blueprint $blueprint
     * @return array<int, string>
     */
    public function compileRenameColumn(Blueprint $blueprint): array
    {
        $table = $this->wrapTable($blueprint->getTable());
        $sqls  = [];

        foreach ($blueprint->getRenamedColumns() as $from => $to) {
            $sqls[] = "ALTER TABLE {$table} RENAME COLUMN {$this->wrap($from)} TO {$this->wrap($to)}";
        }

        return $sqls;
    }

    // -----------------------------------------------------------------------
    // ALTER TABLE — indexes
    // -----------------------------------------------------------------------

    /**
     * Compile CREATE INDEX statements for standalone index definitions.
     *
     * @param  Blueprint $blueprint
     * @return array<int, string>
     */
    public function compileIndexes(Blueprint $blueprint): array
    {
        $sqls = [];
        $tbl  = $blueprint->getTable();

        foreach ($blueprint->getIndexes() as $index) {
            $type    = strtoupper($index['type']);
            $name    = $this->wrap($index['name']);
            $table   = $this->wrapTable($tbl);
            $cols    = implode(', ', array_map($this->wrap(...), $index['columns']));

            if ($type === 'PRIMARY') {
                $sqls[] = "ALTER TABLE {$table} ADD PRIMARY KEY ({$cols})";
            } elseif ($type === 'UNIQUE') {
                $sqls[] = "CREATE UNIQUE INDEX {$name} ON {$table} ({$cols})";
            } else {
                $sqls[] = "CREATE INDEX {$name} ON {$table} ({$cols})";
            }
        }

        return $sqls;
    }

    /**
     * Compile DROP INDEX statements.
     *
     * @param  Blueprint $blueprint
     * @return array<int, string>
     */
    abstract public function compileDropIndexes(Blueprint $blueprint): array;

    // -----------------------------------------------------------------------
    // ALTER TABLE — foreign keys
    // -----------------------------------------------------------------------

    /**
     * Compile ADD CONSTRAINT … FOREIGN KEY statements.
     *
     * @param  Blueprint $blueprint
     * @return array<int, string>
     */
    public function compileForeignKeys(Blueprint $blueprint): array
    {
        $table = $this->wrapTable($blueprint->getTable());
        $sqls  = [];

        foreach ($blueprint->getForeignKeys() as $fk) {
            $constraint = $this->compileForeignKeyConstraint($fk, $blueprint->getTable());
            $sqls[]     = "ALTER TABLE {$table} ADD {$constraint}";
        }

        return $sqls;
    }

    /**
     * Compile DROP FOREIGN KEY statements.
     *
     * @param  Blueprint $blueprint
     * @return array<int, string>
     */
    abstract public function compileDropForeignKeys(Blueprint $blueprint): array;

    // -----------------------------------------------------------------------
    // DROP TABLE / RENAME TABLE
    // -----------------------------------------------------------------------

    /**
     * Compile DROP TABLE.
     *
     * @param  string $table
     * @return string
     */
    public function compileDropTable(string $table): string
    {
        return "DROP TABLE {$this->wrapTable($table)}";
    }

    /**
     * Compile DROP TABLE IF EXISTS.
     *
     * @param  string $table
     * @return string
     */
    public function compileDropTableIfExists(string $table): string
    {
        return "DROP TABLE IF EXISTS {$this->wrapTable($table)}";
    }

    /**
     * Compile RENAME TABLE.
     *
     * @param  string $from
     * @param  string $to
     * @return string
     */
    abstract public function compileRenameTable(string $from, string $to): string;

    // -----------------------------------------------------------------------
    // Introspection queries (driver-specific)
    // -----------------------------------------------------------------------

    /**
     * SQL to check whether a table exists.
     *
     * @param  string $table
     * @param  string $database
     * @return string
     */
    abstract public function compileTableExists(string $table, string $database): string;

    /**
     * SQL to list columns for a table.
     *
     * @param  string $table
     * @param  string $database
     * @return string
     */
    abstract public function compileColumnListing(string $table, string $database): string;

    // -----------------------------------------------------------------------
    // Column type compilation — overridden per driver
    // -----------------------------------------------------------------------

    /**
     * Compile the SQL type string for a column.
     *
     * @param  ColumnDefinition $col
     * @return string
     */
    protected function compileType(ColumnDefinition $col): string
    {
        return match ($col->getType()) {
            'tinyInteger'  => 'TINYINT',
            'smallInteger' => 'SMALLINT',
            'integer'      => 'INT',
            'bigInteger'   => 'BIGINT',
            'float'        => sprintf('FLOAT(%d,%d)', $col->get('precision', 8), $col->get('scale', 2)),
            'decimal'      => sprintf('DECIMAL(%d,%d)', $col->get('precision', 10), $col->get('scale', 2)),
            'boolean'      => 'TINYINT(1)',
            'string'       => 'VARCHAR(' . $col->get('length', 255) . ')',
            'char'         => 'CHAR(' . $col->get('length', 1) . ')',
            'text'         => 'TEXT',
            'mediumText'   => 'MEDIUMTEXT',
            'longText'     => 'LONGTEXT',
            'enum'         => $this->compileEnumType($col),
            'json'         => 'JSON',
            'uuid'         => 'CHAR(36)',
            'binary'       => 'BLOB',
            'date'         => 'DATE',
            'time'         => 'TIME',
            'dateTime'     => 'DATETIME',
            'timestamp'    => 'TIMESTAMP',
            default        => strtoupper($col->getType()),
        };
    }

    /**
     * Compile the ENUM type with its allowed values.
     *
     * @param  ColumnDefinition $col
     * @return string
     */
    protected function compileEnumType(ColumnDefinition $col): string
    {
        $allowed = array_map(
            fn(string $v) => "'" . addslashes($v) . "'",
            (array) $col->get('allowed', []),
        );

        return 'ENUM(' . implode(', ', $allowed) . ')';
    }

    // -----------------------------------------------------------------------
    // Column modifier compilation
    // -----------------------------------------------------------------------

    /**
     * @param  ColumnDefinition $col
     * @return string
     */
    protected function compileUnsigned(ColumnDefinition $col): string
    {
        return $col->has('unsigned') ? ' UNSIGNED' : '';
    }

    /**
     * @param  ColumnDefinition $col
     * @return string
     */
    protected function compileNullable(ColumnDefinition $col): string
    {
        return $col->has('nullable') ? ' NULL' : ' NOT NULL';
    }

    /**
     * @param  ColumnDefinition $col
     * @return string
     */
    protected function compileDefault(ColumnDefinition $col): string
    {
        if (! array_key_exists('default', $col->getAttributes())) {
            return '';
        }

        $value = $col->get('default');

        if ($value === null) {
            return ' DEFAULT NULL';
        }

        if (is_bool($value)) {
            return ' DEFAULT ' . ($value ? '1' : '0');
        }

        if (is_numeric($value)) {
            return ' DEFAULT ' . $value;
        }

        return " DEFAULT '" . addslashes((string) $value) . "'";
    }

    /**
     * @param  ColumnDefinition $col
     * @return string
     */
    protected function compileAutoIncrement(ColumnDefinition $col): string
    {
        return $col->has('autoIncrement') ? ' AUTO_INCREMENT' : '';
    }

    /**
     * @param  ColumnDefinition $col
     * @return string
     */
    protected function compilePrimary(ColumnDefinition $col): string
    {
        return $col->has('primary') ? ' PRIMARY KEY' : '';
    }

    /**
     * @param  ColumnDefinition $col
     * @return string
     */
    protected function compileUnique(ColumnDefinition $col): string
    {
        return $col->has('unique') ? ' UNIQUE' : '';
    }

    /**
     * @param  ColumnDefinition $col
     * @return string
     */
    protected function compileComment(ColumnDefinition $col): string
    {
        return $col->has('comment')
            ? " COMMENT '" . addslashes((string) $col->get('comment')) . "'"
            : '';
    }

    /**
     * @param  ColumnDefinition $col
     * @return string
     */
    protected function compileAfter(ColumnDefinition $col): string
    {
        return $col->has('after')
            ? ' AFTER ' . $this->wrap((string) $col->get('after'))
            : '';
    }

    // -----------------------------------------------------------------------
    // Foreign key constraint helper
    // -----------------------------------------------------------------------

    /**
     * Compile a FOREIGN KEY inline constraint (for CREATE TABLE).
     *
     * @param  ForeignKeyDefinition $fk
     * @param  string               $table
     * @return string
     */
    protected function compileForeignKeyConstraint(ForeignKeyDefinition $fk, string $table): string
    {
        $constraintName = $this->wrap($fk->getConstraintName($table));
        $column         = $this->wrap($fk->getColumn());
        $refTable       = $this->wrapTable($fk->getOn());
        $refCol         = $this->wrap($fk->getReferences());

        return "CONSTRAINT {$constraintName} FOREIGN KEY ({$column}) "
            . "REFERENCES {$refTable} ({$refCol}) "
            . "ON DELETE {$fk->getOnDelete()} "
            . "ON UPDATE {$fk->getOnUpdate()}";
    }

    /**
     * Compile an inline index for CREATE TABLE (PRIMARY KEY / UNIQUE).
     *
     * @param  array<string, mixed> $index
     * @param  Blueprint            $blueprint
     * @return string
     */
    protected function compileInlineIndex(array $index, Blueprint $blueprint): string
    {
        return ''; // CREATE TABLE uses column-level constraints by default
    }

    // -----------------------------------------------------------------------
    // Identifier quoting
    // -----------------------------------------------------------------------

    /**
     * Wrap a table name in the driver's quote characters.
     *
     * @param  string $table
     * @return string
     */
    public function wrapTable(string $table): string
    {
        return $this->wrap($table);
    }

    /**
     * Wrap a single identifier.
     *
     * @param  string $value
     * @return string
     */
    public function wrap(string $value): string
    {
        return $this->quoteOpen
            . str_replace($this->quoteClose, $this->quoteClose . $this->quoteClose, $value)
            . $this->quoteClose;
    }
}
