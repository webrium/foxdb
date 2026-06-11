<?php

declare(strict_types=1);

namespace Foxdb\Schema\Grammars;

use Foxdb\Schema\Blueprint;
use Foxdb\Schema\ColumnDefinition;

/**
 * MySQL / MariaDB Schema Grammar.
 *
 * Differences from base:
 * - Backtick quoting
 * - MODIFY COLUMN for ALTER (not ALTER COLUMN)
 * - AUTO_INCREMENT keyword
 * - TINYINT(1) for boolean
 * - MEDIUMTEXT / LONGTEXT supported natively
 * - DROP INDEX uses table-scoped syntax
 * - RENAME TABLE uses RENAME TO syntax
 */
class MySqlSchemaGrammar extends SchemaGrammar
{
    protected string $quoteOpen  = '`';
    protected string $quoteClose = '`';

    // -----------------------------------------------------------------------
    // ALTER COLUMN (MODIFY COLUMN in MySQL)
    // -----------------------------------------------------------------------

    /**
     * {@inheritdoc}
     */
    public function compileChange(Blueprint $blueprint): array
    {
        $table = $this->wrapTable($blueprint->getTable());
        $sqls  = [];

        foreach ($blueprint->getChangedColumns() as $col) {
            $colSql = $this->compileColumnDefinition($col);
            $colSql .= $this->compileAfter($col);
            $sqls[] = "ALTER TABLE {$table} MODIFY COLUMN {$colSql}";
        }

        return $sqls;
    }

    // -----------------------------------------------------------------------
    // Indexes
    // -----------------------------------------------------------------------

    /**
     * {@inheritdoc}
     * MySQL: DROP INDEX index_name ON table_name
     */
    public function compileDropIndexes(Blueprint $blueprint): array
    {
        $table = $this->wrapTable($blueprint->getTable());
        $sqls  = [];

        foreach ($blueprint->getDroppedIndexes() as $name) {
            $sqls[] = "DROP INDEX {$this->wrap($name)} ON {$table}";
        }

        return $sqls;
    }

    // -----------------------------------------------------------------------
    // Foreign keys
    // -----------------------------------------------------------------------

    /**
     * {@inheritdoc}
     * MySQL: ALTER TABLE … DROP FOREIGN KEY constraint_name
     */
    public function compileDropForeignKeys(Blueprint $blueprint): array
    {
        $table = $this->wrapTable($blueprint->getTable());
        $sqls  = [];

        foreach ($blueprint->getDroppedForeignKeys() as $name) {
            $sqls[] = "ALTER TABLE {$table} DROP FOREIGN KEY {$this->wrap($name)}";
        }

        return $sqls;
    }

    // -----------------------------------------------------------------------
    // Rename table
    // -----------------------------------------------------------------------

    /**
     * {@inheritdoc}
     * MySQL: RENAME TABLE old TO new
     */
    public function compileRenameTable(string $from, string $to): string
    {
        return "RENAME TABLE {$this->wrapTable($from)} TO {$this->wrapTable($to)}";
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
            . "WHERE table_schema = '{$database}' AND table_name = '{$table}'";
    }

    /**
     * {@inheritdoc}
     */
    public function compileColumnListing(string $table, string $database): string
    {
        return "SELECT column_name as name, column_type as type, "
            . "is_nullable as nullable, column_default as `default`, "
            . "column_comment as comment "
            . "FROM information_schema.columns "
            . "WHERE table_schema = '{$database}' AND table_name = '{$table}' "
            . "ORDER BY ordinal_position";
    }

    // -----------------------------------------------------------------------
    // Type overrides
    // -----------------------------------------------------------------------

    /**
     * {@inheritdoc}
     * MySQL uses TINYINT(1) for boolean and AUTO_INCREMENT natively.
     */
    protected function compileType(ColumnDefinition $col): string
    {
        return match ($col->getType()) {
            'boolean'   => 'TINYINT(1)',
            'json'      => 'JSON',
            'uuid'      => 'CHAR(36)',
            'binary'    => 'BLOB',
            'mediumText'=> 'MEDIUMTEXT',
            'longText'  => 'LONGTEXT',
            default     => parent::compileType($col),
        };
    }
}
