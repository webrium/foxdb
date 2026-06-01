<?php

declare(strict_types=1);

namespace Foxdb\Query\Grammars;

/**
 * PostgreSQL SQL Grammar.
 *
 * Differences from base Grammar / MySQL:
 * - Identifiers quoted with double-quotes (already the base default).
 * - LIMIT/ORDER BY not supported on UPDATE/DELETE without a subquery.
 * - Uses INSERT … ON CONFLICT DO UPDATE for upsert.
 * - Supports ILIKE, ~, ~*, !~, !~* operators.
 * - RETURNING clause on INSERT/UPDATE/DELETE.
 * - Uses OFFSET … ROWS / FETCH NEXT … ROWS ONLY (standard SQL) —
 *   but also supports the simpler LIMIT/OFFSET for compatibility.
 */
class PostgresGrammar extends Grammar
{
    /**
     * PostgreSQL uses double-quote (already the Grammar default).
     * Declared explicitly for clarity.
     *
     * @var string
     */
    protected string $quoteOpen  = '"';

    /**
     * @var string
     */
    protected string $quoteClose = '"';

    // -----------------------------------------------------------------------
    // PostgreSQL UPDATE — no ORDER BY / LIMIT support directly
    // -----------------------------------------------------------------------

    /**
     * {@inheritdoc}
     *
     * PostgreSQL does not support ORDER BY or LIMIT on UPDATE.
     * Only WHERE is appended.
     */
    public function compileUpdate(string $table, array $state, array $values): string
    {
        $table = $this->wrapTable($table);
        $set   = implode(', ', array_map(
            fn(string $col) => $this->wrapColumn($col) . ' = ?',
            array_keys($values),
        ));

        $sql = "UPDATE {$table} SET {$set}";

        $where = $this->compileWheres($state);
        if ($where !== '') {
            $sql .= ' ' . $where;
        }

        return $sql;
    }

    /**
     * {@inheritdoc}
     *
     * PostgreSQL does not support ORDER BY or LIMIT on DELETE directly.
     */
    public function compileDelete(string $table, array $state): string
    {
        $table = $this->wrapTable($table);
        $sql   = "DELETE FROM {$table}";

        $where = $this->compileWheres($state);
        if ($where !== '') {
            $sql .= ' ' . $where;
        }

        return $sql;
    }

    // -----------------------------------------------------------------------
    // PostgreSQL upsert: INSERT … ON CONFLICT DO UPDATE
    // -----------------------------------------------------------------------

    /**
     * Compile an INSERT … ON CONFLICT (column) DO UPDATE SET … statement.
     *
     * @param  string               $table
     * @param  array<string, mixed> $values         column => value (insert)
     * @param  array<string, mixed> $updateValues   column => value (update on conflict)
     * @param  string               $conflictColumn The unique/PK column to detect conflict on
     * @return string
     */
    public function compileUpsert(
        string $table,
        array  $values,
        array  $updateValues,
        string $conflictColumn = 'id',
    ): string {
        $insert  = $this->compileInsert($table, $values);
        $conflict = $this->wrapColumn($conflictColumn);

        $updates = implode(', ', array_map(
            fn(string $col) => $this->wrapColumn($col) . ' = EXCLUDED.' . $this->wrapColumn($col),
            array_keys($updateValues),
        ));

        return "{$insert} ON CONFLICT ({$conflict}) DO UPDATE SET {$updates}";
    }

    // -----------------------------------------------------------------------
    // RETURNING clause
    // -----------------------------------------------------------------------

    /**
     * Append a RETURNING clause to any DML statement.
     *
     * @param  string            $sql
     * @param  array<int, string>|string $columns  '*' or column list
     * @return string
     */
    public function withReturning(string $sql, array|string $columns = '*'): string
    {
        if ($columns === '*') {
            return $sql . ' RETURNING *';
        }

        $cols = implode(', ', array_map($this->wrapColumn(...), (array) $columns));

        return $sql . " RETURNING {$cols}";
    }

    // -----------------------------------------------------------------------
    // Operator validation — Postgres extras
    // -----------------------------------------------------------------------

    /**
     * {@inheritdoc}
     */
    public function validateOperator(string $operator): string
    {
        $extra = ['ILIKE', 'NOT ILIKE', '~', '~*', '!~', '!~*', '@>', '<@', '?', '?|', '?&', '||'];
        $upper = strtoupper(trim($operator));

        if (in_array($operator, $extra, strict: true)) {
            return $operator; // preserve case for symbol operators
        }

        if (in_array($upper, $extra, strict: true)) {
            return $upper;
        }

        return parent::validateOperator($operator);
    }

    // -----------------------------------------------------------------------
    // ILIKE where clause (Postgres case-insensitive LIKE)
    // -----------------------------------------------------------------------

    /**
     * Compile a WHERE ILIKE clause (PostgreSQL case-insensitive pattern match).
     *
     * @param  array<string, mixed> $where
     * @return string
     */
    protected function compileWhereIlike(array $where): string
    {
        $col = $this->wrapColumn($where['column']);

        return "{$col} ILIKE ?";
    }
}