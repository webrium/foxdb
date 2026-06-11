<?php

declare(strict_types=1);

namespace Foxdb\Query\Grammars;

/**
 * Abstract SQL Grammar.
 *
 * Responsible for compiling a Builder state snapshot into raw SQL strings.
 * Grammar is completely stateless — it only receives data and returns strings.
 * No connection, no execution, no side effects.
 *
 * Each driver subclass overrides only what differs from standard SQL.
 */
abstract class Grammar
{
    /**
     * The column/table quote character (open).
     * MySQL uses backtick, PostgreSQL uses double-quote.
     *
     * @var string
     */
    protected string $quoteOpen = '"';

    /**
     * The column/table quote character (close).
     *
     * @var string
     */
    protected string $quoteClose = '"';

    /**
     * Components compiled in order for a SELECT statement.
     *
     * @var array<int, string>
     */
    protected array $selectComponents = [
        'aggregate',
        'columns',
        'from',
        'joins',
        'wheres',
        'groups',
        'havings',
        'orders',
        'limit',
        'offset',
    ];

    // -----------------------------------------------------------------------
    // SELECT
    // -----------------------------------------------------------------------

    /**
     * Compile a full SELECT statement from a Builder state array.
     *
     * @param  array<string, mixed> $state
     * @return string
     */
    public function compileSelect(array $state): string
    {
        $parts = [];

        foreach ($this->selectComponents as $component) {
            $method = 'compile' . ucfirst($component);

            if (method_exists($this, $method)) {
                $sql = $this->$method($state);
                if ($sql !== '') {
                    $parts[] = $sql;
                }
            }
        }

        return implode(' ', $parts);
    }

    /**
     * Compile the aggregate portion (COUNT, SUM, etc.).
     *
     * @param  array<string, mixed> $state
     * @return string
     */
    protected function compileAggregate(array $state): string
    {
        if (empty($state['aggregate'])) {
            return '';
        }

        ['function' => $fn, 'column' => $col] = $state['aggregate'];

        $column = $col === '*' ? '*' : $this->wrapColumn($col);

        $distinct = ! empty($state['distinct']) ? 'DISTINCT ' : '';

        return "SELECT {$fn}({$distinct}{$column})";
    }

    /**
     * Compile the SELECT column list.
     *
     * @param  array<string, mixed> $state
     * @return string
     */
    protected function compileColumns(array $state): string
    {
        // Aggregate replaces column list.
        if (! empty($state['aggregate'])) {
            return '';
        }

        $distinct = ! empty($state['distinct']) ? 'DISTINCT ' : '';

        if (empty($state['columns'])) {
            return "SELECT {$distinct}*";
        }

        $cols = array_map(function (mixed $col): string {
            // RawExpression — embed as-is, no quoting.
            if ($col instanceof \Foxdb\Query\RawExpression) {
                return $col->value;
            }
            return $this->wrapColumn((string) $col);
        }, $state['columns']);

        return 'SELECT ' . $distinct . implode(', ', $cols);
    }

    /**
     * Compile the FROM clause.
     *
     * @param  array<string, mixed> $state
     * @return string
     */
    protected function compileFrom(array $state): string
    {
        if (empty($state['table'])) {
            return '';
        }

        return 'FROM ' . $this->wrapTable($state['table']);
    }

    // -----------------------------------------------------------------------
    // JOINs
    // -----------------------------------------------------------------------

    /**
     * Compile JOIN clauses.
     *
     * Each join entry:
     *   ['type'=>'INNER|LEFT|RIGHT|CROSS', 'table'=>'...', 'clauses'=>[...]]
     *
     * Each clause:
     *   ['type'=>'ON|AND|OR', 'first'=>'col', 'operator'=>'=', 'second'=>'col', 'raw'=>false]
     *
     * @param  array<string, mixed> $state
     * @return string
     */
    protected function compileJoins(array $state): string
    {
        if (empty($state['joins'])) {
            return '';
        }

        $parts = [];

        foreach ($state['joins'] as $join) {
            // Accepts both plain arrays (from tests) and JoinClause objects.
            if ($join instanceof \Foxdb\Query\JoinClause) {
                $table    = $this->wrapTable($join->table);
                $type     = $join->type;
                $onPart   = $this->compileJoinClauses($join->getOnClauses());
                $wherePart = $this->compileJoinWhereClauses($join->getWhereClauses());
                $parts[]  = "{$type} JOIN {$table} {$onPart}{$wherePart}";
            } else {
                $table   = $this->wrapTable($join['table']);
                $type    = strtoupper($join['type']);
                $on      = $this->compileJoinClauses($join['clauses']);
                $parts[] = "{$type} JOIN {$table} {$on}";
            }
        }

        return implode(' ', $parts);
    }

    /**
     * Compile the ON/AND/OR clauses for a single join.
     *
     * @param  array<int, array<string, mixed>> $clauses
     * @return string
     */
    protected function compileJoinClauses(array $clauses): string
    {
        $parts = [];

        foreach ($clauses as $i => $clause) {
            $prefix = $i === 0 ? 'ON' : strtoupper($clause['type']);

            if (! empty($clause['raw'])) {
                $parts[] = "{$prefix} {$clause['first']}";
                continue;
            }

            $first    = $this->wrapColumn($clause['first']);
            $operator = $clause['operator'];
            $second   = $this->wrapColumn($clause['second']);

            $parts[] = "{$prefix} {$first} {$operator} {$second}";
        }

        return implode(' ', $parts);
    }

    /**
     * Compile WHERE conditions that appear inside a JoinClause.
     * These are appended after the ON conditions as AND/OR WHERE fragments.
     *
     * @param  array<int, array<string, mixed>> $clauses
     * @return string
     */
    protected function compileJoinWhereClauses(array $clauses): string
    {
        if (empty($clauses)) {
            return '';
        }

        $parts = [];

        foreach ($clauses as $clause) {
            $boolean = strtoupper($clause['boolean'] ?? 'AND');
            $type    = $clause['whereType'] ?? 'basic';

            $sql = match ($type) {
                'basic'   => $this->wrapColumn($clause['first']) . ' ' . $clause['operator'] . ' ?',
                'null'    => $this->wrapColumn($clause['first']) . ' IS NULL',
                'notNull' => $this->wrapColumn($clause['first']) . ' IS NOT NULL',
                'in'      => $this->wrapColumn($clause['first']) . ' IN (' . $this->parameters(count($clause['values'])) . ')',
                'notIn'   => $this->wrapColumn($clause['first']) . ' NOT IN (' . $this->parameters(count($clause['values'])) . ')',
                'raw'     => $clause['sql'],
                default   => '',
            };

            if ($sql !== '') {
                $parts[] = "{$boolean} {$sql}";
            }
        }

        // Keep boolean prefix intact — these clauses follow ON conditions.
        return ' ' . implode(' ', $parts);
    }

    // -----------------------------------------------------------------------
    // WHERE
    // -----------------------------------------------------------------------

    /**
     * Compile all WHERE clauses.
     *
     * Each where entry has a 'type' key that maps to a compile method:
     *   basic, raw, in, notIn, null, notNull, between, notBetween,
     *   column, date, time, day, month, year, nested, exists, notExists
     *
     * @param  array<string, mixed> $state
     * @return string
     */
    public function compileWheres(array $state): string
    {
        if (empty($state['wheres'])) {
            return '';
        }

        $parts = $this->compileWhereClauses($state['wheres']);

        return 'WHERE ' . $this->removeLeadingBoolean(implode(' ', $parts));
    }

    /**
     * Compile an array of where clause arrays into SQL fragments.
     *
     * @param  array<int, array<string, mixed>> $wheres
     * @return array<int, string>
     */
    protected function compileWhereClauses(array $wheres): array
    {
        $parts = [];

        foreach ($wheres as $where) {
            $method  = 'compileWhere' . ucfirst($where['type']);
            $boolean = strtoupper($where['boolean'] ?? 'AND');
            $sql     = method_exists($this, $method)
                ? $this->$method($where)
                : '';

            if ($sql !== '') {
                $parts[] = "{$boolean} {$sql}";
            }
        }

        return $parts;
    }

    /**
     * @param  array<string, mixed> $where
     * @return string
     */
    protected function compileWhereBasic(array $where): string
    {
        $col      = $this->wrapColumn($where['column']);
        $operator = $where['operator'];

        return "{$col} {$operator} ?";
    }

    /**
     * @param  array<string, mixed> $where
     * @return string
     */
    protected function compileWhereRaw(array $where): string
    {
        return (string) $where['sql'];
    }

    /**
     * @param  array<string, mixed> $where
     * @return string
     */
    protected function compileWhereIn(array $where): string
    {
        // An empty IN list is invalid SQL in MySQL/PostgreSQL (`IN ()`).
        // Semantically `x IN (nothing)` is always false, so emit `1 = 0`.
        if (count($where['values']) === 0) {
            return '1 = 0';
        }

        $col          = $this->wrapColumn($where['column']);
        $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));

        return "{$col} IN ({$placeholders})";
    }

    /**
     * @param  array<string, mixed> $where
     * @return string
     */
    protected function compileWhereNotIn(array $where): string
    {
        // An empty NOT IN list means "exclude nothing" → always true → `1 = 1`.
        if (count($where['values']) === 0) {
            return '1 = 1';
        }

        $col          = $this->wrapColumn($where['column']);
        $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));

        return "{$col} NOT IN ({$placeholders})";
    }

    /**
     * WHERE column IN (subquery)
     *
     * @param  array<string, mixed> $where
     * @return string
     */
    protected function compileWhereInSub(array $where): string
    {
        $col = $this->wrapColumn($where['column']);

        return "{$col} IN ({$where['sql']})";
    }

    /**
     * WHERE column NOT IN (subquery)
     *
     * @param  array<string, mixed> $where
     * @return string
     */
    protected function compileWhereNotInSub(array $where): string
    {
        $col = $this->wrapColumn($where['column']);

        return "{$col} NOT IN ({$where['sql']})";
    }

    /**
     * @param  array<string, mixed> $where
     * @return string
     */
    protected function compileWhereNull(array $where): string
    {
        return $this->wrapColumn($where['column']) . ' IS NULL';
    }

    /**
     * @param  array<string, mixed> $where
     * @return string
     */
    protected function compileWhereNotNull(array $where): string
    {
        return $this->wrapColumn($where['column']) . ' IS NOT NULL';
    }

    /**
     * @param  array<string, mixed> $where
     * @return string
     */
    protected function compileWhereBetween(array $where): string
    {
        $col = $this->wrapColumn($where['column']);

        return "{$col} BETWEEN ? AND ?";
    }

    /**
     * @param  array<string, mixed> $where
     * @return string
     */
    protected function compileWhereNotBetween(array $where): string
    {
        $col = $this->wrapColumn($where['column']);

        return "{$col} NOT BETWEEN ? AND ?";
    }

    /**
     * Column-to-column comparison (no binding — both sides are column refs).
     *
     * @param  array<string, mixed> $where
     * @return string
     */
    protected function compileWhereColumn(array $where): string
    {
        $first    = $this->wrapColumn($where['first']);
        $operator = $where['operator'];
        $second   = $this->wrapColumn($where['second']);

        return "{$first} {$operator} {$second}";
    }

    /**
     * @param  array<string, mixed> $where
     * @return string
     */
    protected function compileWhereDate(array $where): string
    {
        return 'DATE(' . $this->wrapColumn($where['column']) . ') ' . $where['operator'] . ' ?';
    }

    /**
     * @param  array<string, mixed> $where
     * @return string
     */
    protected function compileWhereTime(array $where): string
    {
        return 'TIME(' . $this->wrapColumn($where['column']) . ') ' . $where['operator'] . ' ?';
    }

    /**
     * @param  array<string, mixed> $where
     * @return string
     */
    protected function compileWhereDay(array $where): string
    {
        return 'DAY(' . $this->wrapColumn($where['column']) . ') ' . $where['operator'] . ' ?';
    }

    /**
     * @param  array<string, mixed> $where
     * @return string
     */
    protected function compileWhereMonth(array $where): string
    {
        return 'MONTH(' . $this->wrapColumn($where['column']) . ') ' . $where['operator'] . ' ?';
    }

    /**
     * @param  array<string, mixed> $where
     * @return string
     */
    protected function compileWhereYear(array $where): string
    {
        return 'YEAR(' . $this->wrapColumn($where['column']) . ') ' . $where['operator'] . ' ?';
    }

    /**
     * Nested WHERE group: ( ... AND/OR ... )
     *
     * @param  array<string, mixed> $where
     * @return string
     */
    protected function compileWhereNested(array $where): string
    {
        $inner = $this->compileWhereClauses($where['wheres']);
        $sql   = $this->removeLeadingBoolean(implode(' ', $inner));

        return "({$sql})";
    }

    /**
     * EXISTS subquery.
     *
     * @param  array<string, mixed> $where
     * @return string
     */
    protected function compileWhereExists(array $where): string
    {
        return 'EXISTS (' . $where['sql'] . ')';
    }

    /**
     * NOT EXISTS subquery.
     *
     * @param  array<string, mixed> $where
     * @return string
     */
    protected function compileWhereNotExists(array $where): string
    {
        return 'NOT EXISTS (' . $where['sql'] . ')';
    }

    // -----------------------------------------------------------------------
    // GROUP BY / HAVING
    // -----------------------------------------------------------------------

    /**
     * Compile the GROUP BY clause.
     *
     * @param  array<string, mixed> $state
     * @return string
     */
    protected function compileGroups(array $state): string
    {
        if (empty($state['groups'])) {
            return '';
        }

        $cols = array_map(
            fn(string $col) => $this->wrapColumn($col),
            $state['groups'],
        );

        return 'GROUP BY ' . implode(', ', $cols);
    }

    /**
     * Compile the HAVING clause.
     *
     * Each having: ['type'=>'basic|raw', 'column', 'operator', 'boolean']
     *
     * @param  array<string, mixed> $state
     * @return string
     */
    protected function compileHavings(array $state): string
    {
        if (empty($state['havings'])) {
            return '';
        }

        $parts = [];

        foreach ($state['havings'] as $i => $having) {
            $boolean = strtoupper($having['boolean'] ?? 'AND');

            $sql = match ($having['type']) {
                'raw'   => $having['sql'],
                default => $this->wrapColumn($having['column'])
                           . ' ' . $having['operator'] . ' ?',
            };

            $parts[] = $i === 0 ? $sql : "{$boolean} {$sql}";
        }

        return 'HAVING ' . implode(' ', $parts);
    }

    // -----------------------------------------------------------------------
    // ORDER BY
    // -----------------------------------------------------------------------

    /**
     * Compile the ORDER BY clause.
     *
     * Each order: ['column'=>'...', 'direction'=>'ASC|DESC'] | ['raw'=>'...']
     *
     * @param  array<string, mixed> $state
     * @return string
     */
    protected function compileOrders(array $state): string
    {
        if (empty($state['orders'])) {
            return '';
        }

        $parts = array_map(function (array $order): string {
            if (! empty($order['raw'])) {
                return $order['raw'];
            }

            $col = $this->wrapColumn($order['column']);
            $dir = strtoupper($order['direction'] ?? 'ASC');

            return "{$col} {$dir}";
        }, $state['orders']);

        return 'ORDER BY ' . implode(', ', $parts);
    }

    // -----------------------------------------------------------------------
    // LIMIT / OFFSET
    // -----------------------------------------------------------------------

    /**
     * Compile the LIMIT clause.
     *
     * @param  array<string, mixed> $state
     * @return string
     */
    protected function compileLimit(array $state): string
    {
        if (! isset($state['limit'])) {
            return '';
        }

        return 'LIMIT ' . (int) $state['limit'];
    }

    /**
     * Compile the OFFSET clause.
     *
     * @param  array<string, mixed> $state
     * @return string
     */
    protected function compileOffset(array $state): string
    {
        if (! isset($state['offset'])) {
            return '';
        }

        return 'OFFSET ' . (int) $state['offset'];
    }

    // -----------------------------------------------------------------------
    // INSERT / UPDATE / DELETE
    // -----------------------------------------------------------------------

    /**
     * Compile an INSERT statement.
     *
     * @param  string               $table
     * @param  array<string, mixed> $values  column => value pairs
     * @return string
     */
    public function compileInsert(string $table, array $values): string
    {
        $table        = $this->wrapTable($table);
        $columns      = implode(', ', array_map($this->wrapColumn(...), array_keys($values)));
        $placeholders = implode(', ', array_fill(0, count($values), '?'));

        return "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
    }

    /**
     * Compile a batch INSERT statement (multiple rows).
     *
     * @param  string                        $table
     * @param  array<int, array<string, mixed>> $rows  array of column => value maps
     * @return string
     */
    public function compileInsertBatch(string $table, array $rows): string
    {
        $table   = $this->wrapTable($table);
        $columns = implode(', ', array_map($this->wrapColumn(...), array_keys($rows[0])));

        $valueSets = array_map(
            fn(array $row) => '(' . implode(', ', array_fill(0, count($row), '?')) . ')',
            $rows,
        );

        return "INSERT INTO {$table} ({$columns}) VALUES " . implode(', ', $valueSets);
    }

    /**
     * Compile an UPDATE statement.
     *
     * @param  string               $table
     * @param  array<string, mixed> $state   Builder state (for WHERE + LIMIT + ORDER)
     * @param  array<string, mixed> $values  column => value pairs to set
     * @return string
     */
    public function compileUpdate(string $table, array $state, array $values): string
    {
        $table    = $this->wrapTable($table);
        $setParts = [];

        foreach ($values as $col => $val) {
            $wrapped = $this->wrapColumn((string) $col);
            // RawExpression (e.g. increment/decrement) — embed directly, no placeholder.
            if ($val instanceof \Foxdb\Query\RawExpression) {
                $setParts[] = "{$wrapped} = {$val->value}";
            } else {
                $setParts[] = "{$wrapped} = ?";
            }
        }

        $set = implode(', ', $setParts);

        $sql = "UPDATE {$table} SET {$set}";

        $where = $this->compileWheres($state);
        if ($where !== '') {
            $sql .= ' ' . $where;
        }

        $order = $this->compileOrders($state);
        if ($order !== '') {
            $sql .= ' ' . $order;
        }

        $limit = $this->compileLimit($state);
        if ($limit !== '') {
            $sql .= ' ' . $limit;
        }

        return $sql;
    }

    /**
     * Compile a DELETE statement.
     *
     * @param  string               $table
     * @param  array<string, mixed> $state  Builder state (for WHERE + LIMIT + ORDER)
     * @return string
     */
    public function compileDelete(string $table, array $state): string
    {
        $table = $this->wrapTable($table);
        $sql   = "DELETE FROM {$table}";

        $where = $this->compileWheres($state);
        if ($where !== '') {
            $sql .= ' ' . $where;
        }

        $order = $this->compileOrders($state);
        if ($order !== '') {
            $sql .= ' ' . $order;
        }

        $limit = $this->compileLimit($state);
        if ($limit !== '') {
            $sql .= ' ' . $limit;
        }

        return $sql;
    }

    // -----------------------------------------------------------------------
    // Aggregates
    // -----------------------------------------------------------------------

    /**
     * Compile an aggregate SELECT (COUNT, SUM, AVG, MIN, MAX).
     *
     * @param  string               $function  e.g. 'COUNT'
     * @param  string               $column    e.g. '*' or 'price'
     * @param  array<string, mixed> $state     full Builder state (for WHERE etc.)
     * @return string
     */
    public function compileAggregateQuery(string $function, string $column, array $state): string
    {
        $state['aggregate'] = ['function' => strtoupper($function), 'column' => $column];
        $state['columns']   = [];
        $state['orders']    = [];

        return $this->compileSelect($state);
    }

    // -----------------------------------------------------------------------
    // Identifier quoting
    // -----------------------------------------------------------------------

    /**
     * Wrap a column name in driver-appropriate quote characters.
     * Supports dot-notation: 'table.column' → `table`.`column`
     * Supports aliases: 'column as alias' → `column` AS `alias`
     * Raw expressions (containing spaces and operators) are returned as-is.
     *
     * @param  string $column
     * @return string
     */
    public function wrapColumn(string $column): string
    {
        // Raw expression — do not quote.
        if (str_contains($column, '(')) {
            return $column;
        }

        // Alias: "column as alias"
        if (stripos($column, ' as ') !== false) {
            [$col, $alias] = preg_split('/\s+as\s+/i', $column, 2);

            return $this->quoteSingle(trim($col)) . ' AS ' . $this->quoteSingle(trim($alias));
        }

        // Dot-notation: "table.column"
        if (str_contains($column, '.')) {
            return implode(
                '.',
                array_map(
                    fn(string $part) => $part === '*' ? '*' : $this->quoteSingle($part),
                    explode('.', $column),
                ),
            );
        }

        return $column === '*' ? '*' : $this->quoteSingle($column);
    }

    /**
     * Wrap a table name, supporting aliases: 'orders as o'.
     *
     * @param  string $table
     * @return string
     */
    public function wrapTable(string $table): string
    {
        // Subquery: starts with ( — already fully formed SQL.
        // Match alias only after the closing ) to avoid matching AS inside the subquery.
        if (str_starts_with(ltrim($table), '(')) {
            // Find the last ) and check for AS after it
            $lastParen = strrpos($table, ')');
            if ($lastParen !== false) {
                $afterParen = substr($table, $lastParen + 1);
                if (preg_match('/^\s+AS\s+(\S+)/i', $afterParen, $m)) {
                    $sub   = substr($table, 0, $lastParen + 1);
                    $alias = trim($m[1], '`"');
                    return $sub . ' AS ' . $this->quoteSingle($alias);
                }
            }
            return $table;
        }

        if (stripos($table, ' as ') !== false) {
            [$tbl, $alias] = preg_split('/\s+as\s+/i', $table, 2);
            return $this->quoteSingle(trim($tbl)) . ' AS ' . $this->quoteSingle(trim($alias));
        }

        return $this->quoteSingle($table);
    }

    /**
     * Quote a single identifier (no dots, no aliases).
     *
     * @param  string $value
     * @return string
     */
    protected function quoteSingle(string $value): string
    {
        return $this->quoteOpen . str_replace($this->quoteClose, $this->quoteClose . $this->quoteClose, $value) . $this->quoteClose;
    }

    /**
     * Return a single ? placeholder (used by Builder for raw binding slots).
     *
     * @return string
     */
    public function parameter(): string
    {
        return '?';
    }

    /**
     * Return n comma-separated ? placeholders.
     *
     * @param  int $count
     * @return string
     */
    public function parameters(int $count): string
    {
        return implode(', ', array_fill(0, $count, '?'));
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Remove the leading AND/OR boolean from a compiled WHERE string.
     *
     * @param  string $sql
     * @return string
     */
    protected function removeLeadingBoolean(string $sql): string
    {
        return (string) preg_replace('/^(AND|OR)\s+/i', '', $sql);
    }

    /**
     * Validate and normalise a comparison operator.
     * Throws on unknown operators to prevent SQL injection via operator param.
     *
     * @param  string $operator
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public function validateOperator(string $operator): string
    {
        $allowed = ['=', '<', '>', '<=', '>=', '<>', '!=', 'LIKE', 'NOT LIKE', 'ILIKE', 'RLIKE', '&', '|', '^', '<<', '>>', '~', '~*', '!~', '!~*'];

        $upper = strtoupper(trim($operator));

        if (! in_array($upper, $allowed, strict: true)) {
            throw new \InvalidArgumentException("Invalid operator [{$operator}].");
        }

        return $upper;
    }
}