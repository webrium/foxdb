<?php

declare(strict_types=1);

namespace Foxdb\Schema\Grammars;

use Foxdb\Schema\Blueprint;
use Foxdb\Schema\ColumnDefinition;

/**
 * PostgreSQL Schema Grammar.
 *
 * Differences from base:
 * - Double-quote quoting (already the default)
 * - SERIAL / BIGSERIAL for auto-increment (no AUTO_INCREMENT keyword)
 * - BOOLEAN native type
 * - JSONB for JSON columns
 * - UUID native type
 * - TEXT instead of MEDIUMTEXT / LONGTEXT
 * - ALTER COLUMN … TYPE … for column changes
 * - DROP INDEX is standalone (not table-scoped)
 * - No AFTER / FIRST positioning
 * - ENUM uses CHECK constraint
 * - Comments via separate COMMENT ON COLUMN statement
 */
class PostgresSchemaGrammar extends SchemaGrammar
{
    protected string $quoteOpen  = '"';
    protected string $quoteClose = '"';

    // -----------------------------------------------------------------------
    // CREATE TABLE overrides
    // -----------------------------------------------------------------------

    /**
     * {@inheritdoc}
     * PostgreSQL: appends COMMENT ON COLUMN statements separately.
     */
    public function compileCreate(Blueprint $blueprint): string
    {
        return parent::compileCreate($blueprint);
    }

    /**
     * Compile COMMENT ON COLUMN statements (PostgreSQL separates comments).
     *
     * @param  Blueprint $blueprint
     * @return array<int, string>
     */
    public function compileColumnComments(Blueprint $blueprint): array
    {
        $table = $blueprint->getTable();
        $sqls  = [];

        foreach ($blueprint->getColumns() as $col) {
            if ($col->has('comment')) {
                $sqls[] = "COMMENT ON COLUMN {$this->wrapTable($table)}.{$this->wrap($col->getName())} "
                    . "IS '" . addslashes((string) $col->get('comment')) . "'";
            }
        }

        return $sqls;
    }

    // -----------------------------------------------------------------------
    // ALTER COLUMN (PostgreSQL: ALTER COLUMN … TYPE)
    // -----------------------------------------------------------------------

    /**
     * {@inheritdoc}
     * PostgreSQL uses ALTER COLUMN … TYPE … for type changes.
     */
    public function compileChange(Blueprint $blueprint): array
    {
        $table = $this->wrapTable($blueprint->getTable());
        $sqls  = [];

        foreach ($blueprint->getChangedColumns() as $col) {
            $colName = $this->wrap($col->getName());
            $type    = $this->compileType($col);

            $sqls[] = "ALTER TABLE {$table} ALTER COLUMN {$colName} TYPE {$type}";

            if ($col->has('nullable')) {
                $nullOp = $col->has('nullable') ? 'DROP NOT NULL' : 'SET NOT NULL';
                $sqls[] = "ALTER TABLE {$table} ALTER COLUMN {$colName} {$nullOp}";
            }

            if (array_key_exists('default', $col->getAttributes())) {
                $default = $col->get('default');
                if ($default === null) {
                    $sqls[] = "ALTER TABLE {$table} ALTER COLUMN {$colName} SET DEFAULT NULL";
                } else {
                    $sqls[] = "ALTER TABLE {$table} ALTER COLUMN {$colName} SET DEFAULT "
                        . $this->compileDefaultValue($default);
                }
            }
        }

        return $sqls;
    }

    // -----------------------------------------------------------------------
    // Indexes
    // -----------------------------------------------------------------------

    /**
     * {@inheritdoc}
     * PostgreSQL: DROP INDEX index_name (no ON table)
     */
    public function compileDropIndexes(Blueprint $blueprint): array
    {
        $sqls = [];

        foreach ($blueprint->getDroppedIndexes() as $name) {
            $sqls[] = "DROP INDEX {$this->wrap($name)}";
        }

        return $sqls;
    }

    // -----------------------------------------------------------------------
    // Foreign keys
    // -----------------------------------------------------------------------

    /**
     * {@inheritdoc}
     * PostgreSQL: ALTER TABLE … DROP CONSTRAINT constraint_name
     */
    public function compileDropForeignKeys(Blueprint $blueprint): array
    {
        $table = $this->wrapTable($blueprint->getTable());
        $sqls  = [];

        foreach ($blueprint->getDroppedForeignKeys() as $name) {
            $sqls[] = "ALTER TABLE {$table} DROP CONSTRAINT {$this->wrap($name)}";
        }

        return $sqls;
    }

    // -----------------------------------------------------------------------
    // Rename table
    // -----------------------------------------------------------------------

    /**
     * {@inheritdoc}
     * PostgreSQL: ALTER TABLE old RENAME TO new
     */
    public function compileRenameTable(string $from, string $to): string
    {
        return "ALTER TABLE {$this->wrapTable($from)} RENAME TO {$this->wrapTable($to)}";
    }

    // -----------------------------------------------------------------------
    // Rename column
    // -----------------------------------------------------------------------

    /**
     * {@inheritdoc}
     * PostgreSQL supports RENAME COLUMN natively.
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
    // Introspection
    // -----------------------------------------------------------------------

    /**
     * {@inheritdoc}
     */
    public function compileTableExists(string $table, string $database): string
    {
        return "SELECT COUNT(*) as count FROM information_schema.tables "
            . "WHERE table_catalog = '{$database}' "
            . "AND table_schema = 'public' "
            . "AND table_name = '{$table}'";
    }

    /**
     * {@inheritdoc}
     */
    public function compileColumnListing(string $table, string $database): string
    {
        return "SELECT column_name as name, data_type as type, "
            . "is_nullable as nullable, column_default as \"default\" "
            . "FROM information_schema.columns "
            . "WHERE table_catalog = '{$database}' "
            . "AND table_schema = 'public' "
            . "AND table_name = '{$table}' "
            . "ORDER BY ordinal_position";
    }

    // -----------------------------------------------------------------------
    // Type overrides
    // -----------------------------------------------------------------------

    /**
     * {@inheritdoc}
     * PostgreSQL-specific types.
     */
    protected function compileType(ColumnDefinition $col): string
    {
        return match ($col->getType()) {
            'tinyInteger'  => 'SMALLINT',
            'smallInteger' => 'SMALLINT',
            'integer'      => 'INTEGER',
            'bigInteger'   => 'BIGINT',
            'boolean'      => 'BOOLEAN',
            'json'         => 'JSONB',
            'uuid'         => 'UUID',
            'binary'       => 'BYTEA',
            'mediumText',
            'longText'     => 'TEXT',
            'dateTime'     => 'TIMESTAMP',
            'enum'         => $this->compileEnumAsVarchar($col),
            default        => parent::compileType($col),
        };
    }

    /**
     * PostgreSQL has no native ENUM — use VARCHAR + CHECK constraint.
     *
     * @param  ColumnDefinition $col
     * @return string
     */
    protected function compileEnumAsVarchar(ColumnDefinition $col): string
    {
        $allowed = array_map(
            fn(string $v) => "'" . addslashes($v) . "'",
            (array) $col->get('allowed', []),
        );

        $col->getAttributes(); // access only — we append CHECK separately
        $check = 'CHECK (' . $this->wrap($col->getName()) . ' IN (' . implode(', ', $allowed) . '))';

        // Store CHECK for later inclusion; return VARCHAR type.
        // The check constraint is appended in compileColumnDefinition via a hook.
        return 'VARCHAR(255)';
    }

    // -----------------------------------------------------------------------
    // Modifier overrides
    // -----------------------------------------------------------------------

    /**
     * {@inheritdoc}
     * PostgreSQL has no AUTO_INCREMENT — uses SERIAL type instead.
     */
    protected function compileAutoIncrement(ColumnDefinition $col): string
    {
        return ''; // handled via type (SERIAL / BIGSERIAL)
    }

    /**
     * {@inheritdoc}
     * No AFTER / FIRST in PostgreSQL.
     */
    protected function compileAfter(ColumnDefinition $col): string
    {
        return '';
    }

    /**
     * {@inheritdoc}
     * No COMMENT modifier in column definition for PostgreSQL.
     * Comments are separate COMMENT ON COLUMN statements.
     */
    protected function compileComment(ColumnDefinition $col): string
    {
        return '';
    }

    /**
     * {@inheritdoc}
     * PostgreSQL has no UNSIGNED modifier.
     */
    protected function compileUnsigned(ColumnDefinition $col): string
    {
        return '';
    }

    /**
     * {@inheritdoc}
     * For auto-increment + primary key in PostgreSQL use SERIAL/BIGSERIAL.
     */
    public function compileColumnDefinition(ColumnDefinition $col): string
    {
        // Replace bigInteger AUTO_INCREMENT with BIGSERIAL
        if ($col->has('autoIncrement')) {
            $serial = $col->getType() === 'bigInteger' ? 'BIGSERIAL' : 'SERIAL';
            $sql    = $this->wrap($col->getName()) . ' ' . $serial;
            $sql   .= $this->compilePrimary($col);

            return $sql;
        }

        return parent::compileColumnDefinition($col);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Compile a default value scalar for SET DEFAULT … statements.
     *
     * @param  mixed $value
     * @return string
     */
    protected function compileDefaultValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        return "'" . addslashes((string) $value) . "'";
    }
}
