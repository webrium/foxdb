<?php

declare(strict_types=1);

namespace Foxdb\Query;

/**
 * Represents a single JOIN clause with its ON and WHERE conditions.
 *
 * JoinClause is a focused Builder used only inside join() callbacks.
 * It supports multiple ON conditions, OR ON, and WHERE filters
 * directly inside the join definition.
 *
 * Usage:
 *   ->join('orders', function(JoinClause $join) {
 *       $join->on('orders.user_id', '=', 'users.id')
 *            ->on('orders.active', '=', 'users.active')   // AND ON
 *            ->orOn('orders.type', '=', 'users.type')     // OR ON
 *            ->where('orders.status', 'paid')             // AND WHERE
 *            ->whereNull('orders.deleted_at');
 *   })
 */
class JoinClause
{
    /**
     * The type of join: INNER | LEFT | RIGHT | CROSS
     *
     * @var string
     */
    public readonly string $type;

    /**
     * The table being joined (may include alias: 'orders as o').
     *
     * @var string
     */
    public readonly string $table;

    /**
     * Collected ON/WHERE clauses for this join.
     *
     * Each entry:
     *   For ON  : ['type'=>'ON|AND|OR', 'first'=>'col', 'operator'=>'=', 'second'=>'col', 'raw'=>false]
     *   For RAW : ['type'=>'ON|AND|OR', 'first'=>'raw sql', 'raw'=>true]
     *   For WHERE: same structure as Builder wheres, with 'source'=>'where'
     *
     * @var array<int, array<string, mixed>>
     */
    public array $clauses = [];

    /**
     * @param string $type   JOIN type: INNER | LEFT | RIGHT | CROSS
     * @param string $table  The table name (with optional alias)
     */
    public function __construct(string $type, string $table)
    {
        $this->type  = strtoupper($type);
        $this->table = $table;
    }

    // -----------------------------------------------------------------------
    // ON conditions
    // -----------------------------------------------------------------------

    /**
     * Add an ON condition (AND ON for subsequent calls).
     *
     * @param  string $first     Left-side column (e.g. 'orders.user_id')
     * @param  string $operator  Comparison operator (=, <, >, !=, ...)
     * @param  string $second    Right-side column (e.g. 'users.id')
     * @return static
     */
    public function on(string $first, string $operator, string $second): static
    {
        $this->clauses[] = [
            'type'     => empty($this->clauses) ? 'ON' : 'AND',
            'first'    => $first,
            'operator' => $operator,
            'second'   => $second,
            'raw'      => false,
            'source'   => 'on',
        ];

        return $this;
    }

    /**
     * Add an OR ON condition.
     *
     * @param  string $first
     * @param  string $operator
     * @param  string $second
     * @return static
     */
    public function orOn(string $first, string $operator, string $second): static
    {
        $this->clauses[] = [
            'type'     => empty($this->clauses) ? 'ON' : 'OR',
            'first'    => $first,
            'operator' => $operator,
            'second'   => $second,
            'raw'      => false,
            'source'   => 'on',
        ];

        return $this;
    }

    /**
     * Add a raw ON expression.
     *
     * @param  string $expression  Raw SQL for the ON condition
     * @return static
     */
    public function onRaw(string $expression): static
    {
        $this->clauses[] = [
            'type'   => empty($this->clauses) ? 'ON' : 'AND',
            'first'  => $expression,
            'raw'    => true,
            'source' => 'on',
        ];

        return $this;
    }

    // -----------------------------------------------------------------------
    // WHERE conditions inside JOIN
    // -----------------------------------------------------------------------

    /**
     * Add a WHERE condition inside this join (AND WHERE).
     *
     * @param  string $column
     * @param  mixed  $operatorOrValue  Operator string, or value when $value is omitted
     * @param  mixed  $value
     * @return static
     */
    public function where(string $column, mixed $operatorOrValue, mixed $value = null): static
    {
        [$operator, $val] = $this->parseOperatorAndValue($operatorOrValue, $value);

        $this->clauses[] = [
            'type'     => 'AND',
            'first'    => $column,
            'operator' => $operator,
            'second'   => null,
            'value'    => $val,
            'raw'      => false,
            'source'   => 'where',
            'boolean'  => 'AND',
            'whereType'=> 'basic',
        ];

        return $this;
    }

    /**
     * Add an OR WHERE condition inside this join.
     *
     * @param  string $column
     * @param  mixed  $operatorOrValue
     * @param  mixed  $value
     * @return static
     */
    public function orWhere(string $column, mixed $operatorOrValue, mixed $value = null): static
    {
        [$operator, $val] = $this->parseOperatorAndValue($operatorOrValue, $value);

        $this->clauses[] = [
            'type'     => 'OR',
            'first'    => $column,
            'operator' => $operator,
            'second'   => null,
            'value'    => $val,
            'raw'      => false,
            'source'   => 'where',
            'boolean'  => 'OR',
            'whereType'=> 'basic',
        ];

        return $this;
    }

    /**
     * Add a WHERE column IS NULL condition inside this join.
     *
     * @param  string $column
     * @return static
     */
    public function whereNull(string $column): static
    {
        $this->clauses[] = [
            'type'     => 'AND',
            'first'    => $column,
            'raw'      => false,
            'source'   => 'where',
            'boolean'  => 'AND',
            'whereType'=> 'null',
        ];

        return $this;
    }

    /**
     * Add a WHERE column IS NOT NULL condition inside this join.
     *
     * @param  string $column
     * @return static
     */
    public function whereNotNull(string $column): static
    {
        $this->clauses[] = [
            'type'     => 'AND',
            'first'    => $column,
            'raw'      => false,
            'source'   => 'where',
            'boolean'  => 'AND',
            'whereType'=> 'notNull',
        ];

        return $this;
    }

    /**
     * Add a WHERE column IN (...) condition inside this join.
     *
     * @param  string            $column
     * @param  array<int, mixed> $values
     * @return static
     */
    public function whereIn(string $column, array $values): static
    {
        $this->clauses[] = [
            'type'     => 'AND',
            'first'    => $column,
            'values'   => $values,
            'raw'      => false,
            'source'   => 'where',
            'boolean'  => 'AND',
            'whereType'=> 'in',
        ];

        return $this;
    }

    /**
     * Add a WHERE column NOT IN (...) condition inside this join.
     *
     * @param  string            $column
     * @param  array<int, mixed> $values
     * @return static
     */
    public function whereNotIn(string $column, array $values): static
    {
        $this->clauses[] = [
            'type'     => 'AND',
            'first'    => $column,
            'values'   => $values,
            'raw'      => false,
            'source'   => 'where',
            'boolean'  => 'AND',
            'whereType'=> 'notIn',
        ];

        return $this;
    }

    /**
     * Add a raw WHERE expression inside this join.
     *
     * @param  string                   $expression
     * @param  array<int|string, mixed> $bindings
     * @return static
     */
    public function whereRaw(string $expression, array $bindings = []): static
    {
        $this->clauses[] = [
            'type'     => 'AND',
            'first'    => $expression,
            'bindings' => $bindings,
            'raw'      => true,
            'source'   => 'where',
            'boolean'  => 'AND',
            'whereType'=> 'raw',
            'sql'      => $expression,
        ];

        return $this;
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Get only the ON-type clauses (for Grammar::compileJoinClauses).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOnClauses(): array
    {
        return array_values(array_filter(
            $this->clauses,
            fn(array $c) => $c['source'] === 'on',
        ));
    }

    /**
     * Get only the WHERE-type clauses (for Grammar::compileWheres).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getWhereClauses(): array
    {
        return array_values(array_filter(
            $this->clauses,
            fn(array $c) => $c['source'] === 'where',
        ));
    }

    /**
     * Collect all binding values from WHERE clauses inside this join.
     *
     * @return array<int, mixed>
     */
    public function getBindings(): array
    {
        $bindings = [];

        foreach ($this->clauses as $clause) {
            if ($clause['source'] !== 'where') {
                continue;
            }

            match ($clause['whereType']) {
                'basic'  => $bindings[] = $clause['value'],
                'in',
                'notIn'  => array_push($bindings, ...$clause['values']),
                'raw'    => array_push($bindings, ...($clause['bindings'] ?? [])),
                default  => null, // null, notNull — no bindings
            };
        }

        return $bindings;
    }

    /**
     * Parse the ($operatorOrValue, $value) shorthand into [$operator, $value].
     * When called as ->where('col', 'val'), defaults operator to '='.
     *
     * @param  mixed $operatorOrValue
     * @param  mixed $value
     * @return array{0: string, 1: mixed}
     */
    protected function parseOperatorAndValue(mixed $operatorOrValue, mixed $value): array
    {
        if ($value === null) {
            return ['=', $operatorOrValue];
        }

        return [(string) $operatorOrValue, $value];
    }
}
