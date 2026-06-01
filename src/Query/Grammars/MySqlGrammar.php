<?php

declare(strict_types=1);

namespace Foxdb\Query\Grammars;

/**
 * MySQL / MariaDB SQL Grammar.
 *
 * Differences from standard SQL:
 * - Identifiers quoted with backticks instead of double-quotes.
 * - LIMIT supported on UPDATE and DELETE.
 * - ORDER BY supported on UPDATE and DELETE.
 * - Uses IF() / VALUES() for upsert (INSERT … ON DUPLICATE KEY UPDATE).
 * - REGEXP operator supported.
 */
class MySqlGrammar extends Grammar
{
    /**
     * {@inheritdoc}
     */
    protected string $quoteOpen  = '`';

    /**
     * {@inheritdoc}
     */
    protected string $quoteClose = '`';

    /**
     * MySQL-specific operators added on top of the base set.
     *
     * @var array<int, string>
     */
    protected array $extraOperators = ['REGEXP', 'NOT REGEXP', 'SOUNDS LIKE'];

    // -----------------------------------------------------------------------
    // MySQL-specific: INSERT … ON DUPLICATE KEY UPDATE (upsert)
    // -----------------------------------------------------------------------

    /**
     * Compile an INSERT … ON DUPLICATE KEY UPDATE statement.
     *
     * @param  string               $table
     * @param  array<string, mixed> $values       column => value (insert set)
     * @param  array<string, mixed> $updateValues column => value (update set on conflict)
     * @return string
     */
    public function compileUpsert(string $table, array $values, array $updateValues): string
    {
        $insert = $this->compileInsert($table, $values);

        $updates = implode(', ', array_map(
            fn(string $col) => $this->wrapColumn($col) . ' = ?',
            array_keys($updateValues),
        ));

        return "{$insert} ON DUPLICATE KEY UPDATE {$updates}";
    }

    // -----------------------------------------------------------------------
    // MySQL-specific: REPLACE INTO
    // -----------------------------------------------------------------------

    /**
     * Compile a REPLACE INTO statement (MySQL extension).
     * Acts like INSERT but deletes + re-inserts on duplicate key.
     *
     * @param  string               $table
     * @param  array<string, mixed> $values  column => value pairs
     * @return string
     */
    public function compileReplace(string $table, array $values): string
    {
        $table        = $this->wrapTable($table);
        $columns      = implode(', ', array_map($this->wrapColumn(...), array_keys($values)));
        $placeholders = implode(', ', array_fill(0, count($values), '?'));

        return "REPLACE INTO {$table} ({$columns}) VALUES ({$placeholders})";
    }

    // -----------------------------------------------------------------------
    // MySQL-specific: LOCK clauses
    // -----------------------------------------------------------------------

    /**
     * Compile a SELECT … FOR UPDATE lock clause.
     *
     * @param  array<string, mixed> $state
     * @return string
     */
    public function compileLockForUpdate(array $state): string
    {
        return $this->compileSelect($state) . ' FOR UPDATE';
    }

    /**
     * Compile a SELECT … LOCK IN SHARE MODE clause.
     *
     * @param  array<string, mixed> $state
     * @return string
     */
    public function compileLockShared(array $state): string
    {
        return $this->compileSelect($state) . ' LOCK IN SHARE MODE';
    }

    // -----------------------------------------------------------------------
    // Operator validation — extend base set with MySQL extras
    // -----------------------------------------------------------------------

    /**
     * {@inheritdoc}
     */
    public function validateOperator(string $operator): string
    {
        $extra = ['REGEXP', 'NOT REGEXP', 'SOUNDS LIKE'];
        $upper = strtoupper(trim($operator));

        if (in_array($upper, $extra, strict: true)) {
            return $upper;
        }

        return parent::validateOperator($operator);
    }

    // -----------------------------------------------------------------------
    // MySQL uses backtick — quoteSingle already handled via $quoteOpen/Close.
    // The override below is here only to document the difference explicitly.
    // -----------------------------------------------------------------------

    /**
     * {@inheritdoc}
     *
     * MySQL uses backtick quoting: `column_name`
     */
    protected function quoteSingle(string $value): string
    {
        // Escape any embedded backtick by doubling it.
        return '`' . str_replace('`', '``', $value) . '`';
    }
}
