<?php

declare(strict_types=1);

namespace Foxdb\Schema\Grammars;

use Foxdb\Schema\Blueprint;
use Foxdb\Schema\ColumnDefinition;

/**
 * SQLite Schema Grammar.
 *
 * SQLite has significant limitations compared to MySQL/PostgreSQL:
 * - No MODIFY / ALTER COLUMN (work-around: recreate table)
 * - No DROP COLUMN before SQLite 3.35.0
 * - No RENAME COLUMN before SQLite 3.25.0
 * - No UNSIGNED modifier
 * - No AFTER / FIRST positioning
 * - No FOREIGN KEY drop (must recreate table)
 * - INTEGER PRIMARY KEY is auto-increment by definition
 *
 * For test/development purposes this grammar is functional.
 * Production use on SQLite should be carefully evaluated.
 */
class SqliteSchemaGrammar extends SchemaGrammar
{
    protected string $quoteOpen  = '"';
    protected string $quoteClose = '"';

    // -----------------------------------------------------------------------
    // ALTER COLUMN — not supported natively; emit a warning comment
    // -----------------------------------------------------------------------

    /**
     * {@inheritdoc}
     * SQLite does not support modifying column types.
     * Returns an empty array (no-op with a comment for debugging).
     */
    public function compileChange(Blueprint $blueprint): array
    {
        return []; // SQLite < 3.35 cannot alter column types
    }

    // -----------------------------------------------------------------------
    // Indexes
    // -----------------------------------------------------------------------

    /**
     * {@inheritdoc}
     * SQLite: DROP INDEX index_name (no ON table)
     */
    public function compileDropIndexes(Blueprint $blueprint): array
    {
        $sqls = [];

        foreach ($blueprint->getDroppedIndexes() as $name) {
            $sqls[] = "DROP INDEX IF EXISTS {$this->wrap($name)}";
        }

        return $sqls;
    }

    // -----------------------------------------------------------------------
    // Foreign keys — SQLite ignores FK constraints unless enabled
    // -----------------------------------------------------------------------

    /**
     * {@inheritdoc}
     * SQLite: DROP FOREIGN KEY is not supported.
     */
    public function compileDropForeignKeys(Blueprint $blueprint): array
    {
        return []; // not supported in SQLite
    }

    // -----------------------------------------------------------------------
    // Rename table
    // -----------------------------------------------------------------------

    /**
     * {@inheritdoc}
     * SQLite: ALTER TABLE old RENAME TO new
     */
    public function compileRenameTable(string $from, string $to): string
    {
        return "ALTER TABLE {$this->wrapTable($from)} RENAME TO {$this->wrapTable($to)}";
    }

    // -----------------------------------------------------------------------
    // Introspection
    // -----------------------------------------------------------------------

    /**
     * {@inheritdoc}
     * SQLite: uses sqlite_master
     */
    public function compileTableExists(string $table, string $database): string
    {
        return "SELECT COUNT(*) as count FROM sqlite_master "
            . "WHERE type = 'table' AND name = '{$table}'";
    }

    /**
     * {@inheritdoc}
     * SQLite: PRAGMA table_info
     */
    public function compileColumnListing(string $table, string $database): string
    {
        return "PRAGMA table_info({$this->wrap($table)})";
    }

    // -----------------------------------------------------------------------
    // Type overrides
    // -----------------------------------------------------------------------

    /**
     * {@inheritdoc}
     * SQLite uses type affinity — most types map to TEXT/INTEGER/REAL/BLOB.
     */
    protected function compileType(ColumnDefinition $col): string
    {
        return match ($col->getType()) {
            'tinyInteger',
            'smallInteger',
            'integer',
            'bigInteger'   => 'INTEGER',
            'float',
            'decimal'      => 'REAL',
            'boolean'      => 'INTEGER',
            'string',
            'char',
            'text',
            'mediumText',
            'longText',
            'enum',
            'uuid',
            'json',
            'date',
            'time',
            'dateTime',
            'timestamp'    => 'TEXT',
            'binary'       => 'BLOB',
            default        => 'TEXT',
        };
    }

    // -----------------------------------------------------------------------
    // Modifier overrides
    // -----------------------------------------------------------------------

    /**
     * {@inheritdoc}
     * SQLite: no UNSIGNED modifier.
     */
    protected function compileUnsigned(ColumnDefinition $col): string
    {
        return '';
    }

    /**
     * {@inheritdoc}
     * SQLite: no AFTER / FIRST modifier.
     */
    protected function compileAfter(ColumnDefinition $col): string
    {
        return '';
    }

    /**
     * {@inheritdoc}
     * SQLite: no COMMENT modifier.
     */
    protected function compileComment(ColumnDefinition $col): string
    {
        return '';
    }

    /**
     * {@inheritdoc}
     * SQLite: no AUTO_INCREMENT keyword — INTEGER PRIMARY KEY is auto by definition.
     */
    protected function compileAutoIncrement(ColumnDefinition $col): string
    {
        return '';
    }
}
