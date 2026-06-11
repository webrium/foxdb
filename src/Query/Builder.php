<?php

declare(strict_types=1);

namespace Foxdb\Query;

use Foxdb\Contracts\ConnectionInterface;
use Foxdb\Exceptions\QueryException;
use Foxdb\Query\Grammars\Grammar;
use Foxdb\Support\Collection;
use InvalidArgumentException;

/**
 * Fluent Query Builder.
 *
 * Builds a SQL query state, compiles it via Grammar, and executes
 * it through a ConnectionInterface. Builder itself has no SQL or PDO logic.
 *
 * All chainable methods return `static` for full IDE autocompletion.
 *
 * Basic usage:
 *   $users = (new Builder($connection, $grammar))
 *       ->table('users')
 *       ->where('active', 1)
 *       ->orderBy('name')
 *       ->limit(10)
 *       ->get();
 */
class Builder
{
    // -----------------------------------------------------------------------
    // Query state
    // -----------------------------------------------------------------------

    /** @var string */
    protected string $table = '';

    /** @var array<int, string|RawExpression> */
    protected array $columns = [];

    /** @var bool */
    protected bool $distinct = false;

    /** @var array<int, array<string, mixed>> */
    protected array $wheres = [];

    /** @var array<int, JoinClause|array<string, mixed>> */
    protected array $joins = [];

    /** @var array<int, string> */
    protected array $groups = [];

    /** @var array<int, array<string, mixed>> */
    protected array $havings = [];

    /** @var array<int, array<string, mixed>> */
    protected array $orders = [];

    /** @var int|null */
    protected ?int $limitValue = null;

    /** @var int|null */
    protected ?int $offsetValue = null;

    /** @var array<string, mixed>|null */
    protected ?array $aggregateState = null;

    /** @var string */
    protected string $primaryKey = 'id';

    /**
     * Extra bindings from subqueries (whereIn, joinSub, fromSub).
     * These are prepended to WHERE bindings in the correct order.
     *
     * @var array<int, mixed>
     */
    protected array $subBindings = [];

    /**
     * Optional callback to hydrate each row into a model instance.
     * Set by Model::newQuery() — null means raw stdClass rows.
     *
     * @var callable(object): object|null
     */
    protected mixed $hydrator = null;

    // -----------------------------------------------------------------------
    // Dependencies
    // -----------------------------------------------------------------------

    /**
     * @param ConnectionInterface $connection
     * @param Grammar             $grammar
     */
    public function __construct(
        protected ConnectionInterface $connection,
        protected Grammar             $grammar,
    ) {}

    // -----------------------------------------------------------------------
    // Table / columns
    // -----------------------------------------------------------------------

    /**
     * Set the table to query.
     *
     * @param  string|RawExpression $table
     * @return static
     */
    public function table(string|RawExpression $table): static
    {
        $this->table = (string) $table;

        return $this;
    }

    /**
     * Set the columns to SELECT.
     *
     * @param  string|RawExpression ...$columns
     * @return static
     */
    public function select(string|RawExpression ...$columns): static
    {
        $this->columns = array_values($columns);

        return $this;
    }

    /**
     * Add one or more columns without replacing existing ones.
     *
     * @param  string|RawExpression ...$columns
     * @return static
     */
    public function addSelect(string|RawExpression ...$columns): static
    {
        array_push($this->columns, ...$columns);

        return $this;
    }

    /**
     * Add a raw SELECT expression.
     *
     * @param  string                   $expression
     * @param  array<int|string, mixed> $bindings
     * @return static
     */
    public function selectRaw(string $expression, array $bindings = []): static
    {
        $this->columns[] = new RawExpression($expression, $bindings);

        return $this;
    }

    /**
     * Force SELECT DISTINCT.
     *
     * @return static
     */
    public function distinct(): static
    {
        $this->distinct = true;

        return $this;
    }

    /**
     * Set the FROM table using a subquery Builder.
     *
     * @param  Builder $query
     * @param  string  $alias
     * @return static
     */
    public function fromSub(Builder $query, string $alias): static
    {
        $this->table = '(' . $query->toSql() . ') AS ' . $this->grammar->wrapTable($alias);
        $this->mergeBindingsFrom($query);

        return $this;
    }

    // -----------------------------------------------------------------------
    // WHERE
    // -----------------------------------------------------------------------

    /**
     * Add a basic WHERE condition.
     *
     * @param  string|RawExpression|callable $column
     * @param  mixed                         $operatorOrValue
     * @param  mixed                         $value
     * @param  string                        $boolean
     * @return static
     */
    public function where(
        string|RawExpression|callable $column,
        mixed $operatorOrValue = null,
        mixed $value = null,
        string $boolean = 'AND',
    ): static {
        // Nested group: ->where(function($q) { $q->where(...)->orWhere(...); })
        if (is_callable($column)) {
            return $this->whereNested($column, $boolean);
        }

        if ($column instanceof RawExpression) {
            $this->wheres[] = [
                'type'    => 'raw',
                'sql'     => (string) $column,
                'boolean' => $boolean,
            ];
            return $this;
        }

        [$operator, $val] = $this->parseOperatorAndValue($operatorOrValue, $value);

        $this->wheres[] = [
            'type'     => 'basic',
            'column'   => $column,
            'operator' => $this->grammar->validateOperator($operator),
            'value'    => $val,
            'boolean'  => $boolean,
        ];

        return $this;
    }

    /**
     * Add an OR WHERE condition.
     *
     * @param  string|RawExpression|callable $column
     * @param  mixed                         $operatorOrValue
     * @param  mixed                         $value
     * @return static
     */
    public function orWhere(
        string|RawExpression|callable $column,
        mixed $operatorOrValue = null,
        mixed $value = null,
    ): static {
        return $this->where($column, $operatorOrValue, $value, 'OR');
    }

    /**
     * Add a WHERE NOT condition.
     *
     * @param  string $column
     * @param  mixed  $operatorOrValue
     * @param  mixed  $value
     * @return static
     */
    public function whereNot(string $column, mixed $operatorOrValue, mixed $value = null): static
    {
        [$operator, $val] = $this->parseOperatorAndValue($operatorOrValue, $value);

        $negated = match ($operator) {
            '='  => '!=',
            '!=' => '=',
            '<>' => '=',
            '>'  => '<=',
            '>=' => '<',
            '<'  => '>=',
            '<=' => '>',
            default => $operator,
        };

        return $this->where($column, $negated, $val);
    }

    /**
     * Add a raw WHERE expression.
     *
     * @param  string                   $expression
     * @param  array<int|string, mixed> $bindings
     * @param  string                   $boolean
     * @return static
     */
    public function whereRaw(string $expression, array $bindings = [], string $boolean = 'AND'): static
    {
        $this->wheres[] = [
            'type'     => 'raw',
            'sql'      => $expression,
            'bindings' => $bindings,
            'boolean'  => $boolean,
        ];

        return $this;
    }

    /**
     * Add an OR raw WHERE expression.
     *
     * @param  string                   $expression
     * @param  array<int|string, mixed> $bindings
     * @return static
     */
    public function orWhereRaw(string $expression, array $bindings = []): static
    {
        return $this->whereRaw($expression, $bindings, 'OR');
    }

    /**
     * Add a WHERE IN condition.
     *
     * @param  string                     $column
     * @param  array<int, mixed>|Builder  $values
     * @param  string                     $boolean
     * @param  bool                       $not
     * @return static
     */
    public function whereIn(
        string $column,
        array|Builder $values,
        string $boolean = 'AND',
        bool $not = false,
    ): static {
        if ($values instanceof Builder) {
            $this->wheres[] = [
                'type'    => $not ? 'notInSub' : 'inSub',
                'column'  => $column,
                'sql'     => $values->toSql(),
                'builder' => $values,
                'boolean' => $boolean,
            ];
            $this->mergeBindingsFrom($values);
            return $this;
        }

        $this->wheres[] = [
            'type'    => $not ? 'notIn' : 'in',
            'column'  => $column,
            'values'  => $values,
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * Add a WHERE NOT IN condition.
     *
     * @param  string                    $column
     * @param  array<int, mixed>|Builder $values
     * @return static
     */
    public function whereNotIn(string $column, array|Builder $values): static
    {
        return $this->whereIn($column, $values, 'AND', true);
    }

    /**
     * Add an OR WHERE IN condition.
     *
     * @param  string            $column
     * @param  array<int, mixed> $values
     * @return static
     */
    public function orWhereIn(string $column, array $values): static
    {
        return $this->whereIn($column, $values, 'OR');
    }

    /**
     * Add an OR WHERE NOT IN condition.
     *
     * @param  string            $column
     * @param  array<int, mixed> $values
     * @return static
     */
    public function orWhereNotIn(string $column, array $values): static
    {
        return $this->whereIn($column, $values, 'OR', true);
    }

    /**
     * Add a WHERE BETWEEN condition.
     *
     * @param  string  $column
     * @param  mixed   $min
     * @param  mixed   $max
     * @param  string  $boolean
     * @param  bool    $not
     * @return static
     */
    public function whereBetween(
        string $column,
        mixed $min,
        mixed $max,
        string $boolean = 'AND',
        bool $not = false,
    ): static {
        $this->wheres[] = [
            'type'    => $not ? 'notBetween' : 'between',
            'column'  => $column,
            'min'     => $min,
            'max'     => $max,
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * Add a WHERE NOT BETWEEN condition.
     *
     * @param  string $column
     * @param  mixed  $min
     * @param  mixed  $max
     * @return static
     */
    public function whereNotBetween(string $column, mixed $min, mixed $max): static
    {
        return $this->whereBetween($column, $min, $max, 'AND', true);
    }

    /**
     * Add a WHERE IS NULL condition.
     *
     * @param  string $column
     * @param  string $boolean
     * @param  bool   $not
     * @return static
     */
    public function whereNull(string $column, string $boolean = 'AND', bool $not = false): static
    {
        $this->wheres[] = [
            'type'    => $not ? 'notNull' : 'null',
            'column'  => $column,
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * Add a WHERE IS NOT NULL condition.
     *
     * @param  string $column
     * @return static
     */
    public function whereNotNull(string $column): static
    {
        return $this->whereNull($column, 'AND', true);
    }

    /**
     * @param  string $column
     * @return static
     */
    public function orWhereNull(string $column): static
    {
        return $this->whereNull($column, 'OR');
    }

    /**
     * @param  string $column
     * @return static
     */
    public function orWhereNotNull(string $column): static
    {
        return $this->whereNull($column, 'OR', true);
    }

    /**
     * Add a WHERE column = column condition.
     *
     * @param  string $first
     * @param  string $operator
     * @param  string $second
     * @param  string $boolean
     * @return static
     */
    public function whereColumn(
        string $first,
        string $operator,
        string $second,
        string $boolean = 'AND',
    ): static {
        $this->wheres[] = [
            'type'     => 'column',
            'first'    => $first,
            'operator' => $this->grammar->validateOperator($operator),
            'second'   => $second,
            'boolean'  => $boolean,
        ];

        return $this;
    }

    /**
     * @param  string $first
     * @param  string $operator
     * @param  string $second
     * @return static
     */
    public function orWhereColumn(string $first, string $operator, string $second): static
    {
        return $this->whereColumn($first, $operator, $second, 'OR');
    }

    /**
     * Add a nested WHERE group: WHERE ( ... AND/OR ... ).
     *
     * @param  callable $callback  Receives a Builder instance
     * @param  string   $boolean
     * @return static
     */
    public function whereNested(callable $callback, string $boolean = 'AND'): static
    {
        $nested = $this->newQuery();
        $callback($nested);

        if (! empty($nested->wheres)) {
            $this->wheres[] = [
                'type'    => 'nested',
                'wheres'  => $nested->wheres,
                'boolean' => $boolean,
            ];
            // Do NOT call mergeBindingsFrom here — extractWhereBindings()
            // recursively walks nested wheres and collects their bindings.
        }

        return $this;
    }

    /**
     * Add a WHERE EXISTS (subquery) condition.
     *
     * @param  callable|Builder $subquery
     * @param  string           $boolean
     * @param  bool             $not
     * @return static
     */
    public function whereExists(callable|Builder $subquery, string $boolean = 'AND', bool $not = false): static
    {
        if (is_callable($subquery)) {
            $sub = $this->newQuery();
            $subquery($sub);
        } else {
            $sub = $subquery;
        }

        $this->wheres[] = [
            'type'    => $not ? 'notExists' : 'exists',
            'sql'     => $sub->toSql(),
            'boolean' => $boolean,
        ];

        $this->mergeBindingsFrom($sub);

        return $this;
    }

    /**
     * Add a WHERE NOT EXISTS condition.
     *
     * @param  callable|Builder $subquery
     * @return static
     */
    public function whereNotExists(callable|Builder $subquery): static
    {
        return $this->whereExists($subquery, 'AND', true);
    }

    // WHERE date helpers -------------------------------------------------------

    /**
     * @param  string $column
     * @param  string $operator
     * @param  mixed  $value
     * @param  string $boolean
     * @return static
     */
    public function whereDate(string $column, string $operator, mixed $value, string $boolean = 'AND'): static
    {
        return $this->addDateWhere('date', $column, $operator, $value, $boolean);
    }

    /**
     * @param  string $column
     * @param  string $operator
     * @param  mixed  $value
     * @return static
     */
    public function whereMonth(string $column, string $operator, mixed $value): static
    {
        return $this->addDateWhere('month', $column, $operator, $value);
    }

    /**
     * @param  string $column
     * @param  string $operator
     * @param  mixed  $value
     * @return static
     */
    public function whereDay(string $column, string $operator, mixed $value): static
    {
        return $this->addDateWhere('day', $column, $operator, $value);
    }

    /**
     * @param  string $column
     * @param  string $operator
     * @param  mixed  $value
     * @return static
     */
    public function whereYear(string $column, string $operator, mixed $value): static
    {
        return $this->addDateWhere('year', $column, $operator, $value);
    }

    /**
     * @param  string $column
     * @param  string $operator
     * @param  mixed  $value
     * @return static
     */
    public function whereTime(string $column, string $operator, mixed $value): static
    {
        return $this->addDateWhere('time', $column, $operator, $value);
    }

    /**
     * @param  string $type
     * @param  string $column
     * @param  string $operator
     * @param  mixed  $value
     * @param  string $boolean
     * @return static
     */
    protected function addDateWhere(
        string $type,
        string $column,
        string $operator,
        mixed $value,
        string $boolean = 'AND',
    ): static {
        $this->wheres[] = [
            'type'     => $type,
            'column'   => $column,
            'operator' => $this->grammar->validateOperator($operator),
            'value'    => $value,
            'boolean'  => $boolean,
        ];

        return $this;
    }

    // FoxDB v1 shorthands (kept for compatibility) ----------------------------

    /**
     * Shorthand: ->is('column', 'value')  ≡  ->where('column', '=', 'value')
     *
     * @param  string $column
     * @param  mixed  $value
     * @return static
     */
    public function is(string $column, mixed $value): static
    {
        return $this->where($column, '=', $value);
    }

    /**
     * Shorthand: ->true('column')  ≡  ->where('column', '=', 1)
     *
     * @param  string $column
     * @return static
     */
    public function true(string $column): static
    {
        return $this->where($column, '=', 1);
    }

    /**
     * Shorthand: ->false('column')  ≡  ->where('column', '=', 0)
     *
     * @param  string $column
     * @return static
     */
    public function false(string $column): static
    {
        return $this->where($column, '=', 0);
    }

    /**
     * Shorthand: ->and('column', 'value')  ≡  ->where('column', 'value')
     *
     * @param  string $column
     * @param  mixed  $operatorOrValue
     * @param  mixed  $value
     * @return static
     */
    public function and(string $column, mixed $operatorOrValue, mixed $value = null): static
    {
        return $this->where($column, $operatorOrValue, $value);
    }

    /**
     * Shorthand: ->or('column', 'value')  ≡  ->orWhere('column', 'value')
     *
     * @param  string $column
     * @param  mixed  $operatorOrValue
     * @param  mixed  $value
     * @return static
     */
    public function or(string $column, mixed $operatorOrValue, mixed $value = null): static
    {
        return $this->orWhere($column, $operatorOrValue, $value);
    }

    /**
     * Shorthand: ->in('column', [1, 2, 3])  ≡  ->whereIn('column', [1,2,3])
     *
     * @param  string            $column
     * @param  array<int, mixed> $values
     * @return static
     */
    public function in(string $column, array $values): static
    {
        return $this->whereIn($column, $values);
    }

    /**
     * Shorthand: ->notIn('column', [1, 2, 3])
     *
     * @param  string            $column
     * @param  array<int, mixed> $values
     * @return static
     */
    public function notIn(string $column, array $values): static
    {
        return $this->whereNotIn($column, $values);
    }

    /**
     * Shorthand: ->like('column', '%value%')
     *
     * @param  string $column
     * @param  string $value
     * @param  string $boolean
     * @return static
     */
    public function like(string $column, string $value, string $boolean = 'AND'): static
    {
        return $this->where($column, 'LIKE', $value, $boolean);
    }

    /**
     * Shorthand: ->orLike('column', '%value%')
     *
     * @param  string $column
     * @param  string $value
     * @return static
     */
    public function orLike(string $column, string $value): static
    {
        return $this->like($column, $value, 'OR');
    }

    /**
     * Shorthand: ->null('column')  ≡  ->whereNull('column')
     *
     * @param  string $column
     * @return static
     */
    public function null(string $column): static
    {
        return $this->whereNull($column);
    }

    /**
     * Shorthand: ->notNull('column')  ≡  ->whereNotNull('column')
     *
     * @param  string $column
     * @return static
     */
    public function notNull(string $column): static
    {
        return $this->whereNotNull($column);
    }

    // -----------------------------------------------------------------------
    // JOIN
    // -----------------------------------------------------------------------

    /**
     * Add an INNER JOIN.
     *
     * Simple form:
     *   ->join('orders', 'orders.user_id', '=', 'users.id')
     *
     * Advanced form with callback:
     *   ->join('orders', function(JoinClause $join) {
     *       $join->on('orders.user_id', '=', 'users.id')
     *            ->where('orders.active', 1);
     *   })
     *
     * @param  string                   $table
     * @param  string|callable          $firstOrCallback
     * @param  string|null              $operator
     * @param  string|null              $second
     * @return static
     */
    public function join(
        string $table,
        string|callable $firstOrCallback,
        ?string $operator = null,
        ?string $second = null,
    ): static {
        return $this->addJoin('INNER', $table, $firstOrCallback, $operator, $second);
    }

    /**
     * Add a LEFT JOIN.
     *
     * @param  string          $table
     * @param  string|callable $firstOrCallback
     * @param  string|null     $operator
     * @param  string|null     $second
     * @return static
     */
    public function leftJoin(
        string $table,
        string|callable $firstOrCallback,
        ?string $operator = null,
        ?string $second = null,
    ): static {
        return $this->addJoin('LEFT', $table, $firstOrCallback, $operator, $second);
    }

    /**
     * Add a RIGHT JOIN.
     *
     * @param  string          $table
     * @param  string|callable $firstOrCallback
     * @param  string|null     $operator
     * @param  string|null     $second
     * @return static
     */
    public function rightJoin(
        string $table,
        string|callable $firstOrCallback,
        ?string $operator = null,
        ?string $second = null,
    ): static {
        return $this->addJoin('RIGHT', $table, $firstOrCallback, $operator, $second);
    }

    /**
     * Add a CROSS JOIN (no ON condition needed).
     *
     * @param  string $table
     * @return static
     */
    public function crossJoin(string $table): static
    {
        $join = new JoinClause('CROSS', $table);
        $this->joins[] = $join;

        return $this;
    }

    /**
     * Add a join using a subquery Builder.
     *
     * @param  Builder         $query
     * @param  string          $alias
     * @param  string|callable $firstOrCallback
     * @param  string|null     $operator
     * @param  string|null     $second
     * @param  string          $type
     * @return static
     */
    public function joinSub(
        Builder $query,
        string $alias,
        string|callable $firstOrCallback,
        ?string $operator = null,
        ?string $second = null,
        string $type = 'INNER',
    ): static {
        $table = '(' . $query->toSql() . ') AS ' . $this->grammar->wrapTable($alias);
        $this->mergeBindingsFrom($query);

        return $this->addJoin($type, $table, $firstOrCallback, $operator, $second);
    }

    /**
     * Add a LEFT join using a subquery Builder.
     *
     * @param  Builder         $query
     * @param  string          $alias
     * @param  string|callable $firstOrCallback
     * @param  string|null     $operator
     * @param  string|null     $second
     * @return static
     */
    public function leftJoinSub(
        Builder $query,
        string $alias,
        string|callable $firstOrCallback,
        ?string $operator = null,
        ?string $second = null,
    ): static {
        return $this->joinSub($query, $alias, $firstOrCallback, $operator, $second, 'LEFT');
    }

    /**
     * Add a raw JOIN expression.
     *
     * @param  string $expression  Full JOIN SQL (e.g. 'INNER JOIN orders ON ...')
     * @return static
     */
    public function joinRaw(string $expression): static
    {
        $this->joins[] = ['raw' => $expression];

        return $this;
    }

    /**
     * Internal: build and store a JoinClause.
     *
     * @param  string          $type
     * @param  string          $table
     * @param  string|callable $firstOrCallback
     * @param  string|null     $operator
     * @param  string|null     $second
     * @return static
     */
    protected function addJoin(
        string $type,
        string $table,
        string|callable $firstOrCallback,
        ?string $operator,
        ?string $second,
    ): static {
        $join = new JoinClause($type, $table);

        if (is_callable($firstOrCallback)) {
            $firstOrCallback($join);
        } else {
            $join->on($firstOrCallback, $operator ?? '=', $second ?? '');
        }

        $this->joins[] = $join;

        return $this;
    }

    // -----------------------------------------------------------------------
    // ORDER BY
    // -----------------------------------------------------------------------

    /**
     * Add an ORDER BY clause.
     *
     * @param  string $column
     * @param  string $direction  'asc' | 'desc'
     * @return static
     */
    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $this->orders[] = [
            'column'    => $column,
            'direction' => strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC',
        ];

        return $this;
    }

    /**
     * Order by column descending.
     *
     * @param  string $column
     * @return static
     */
    public function orderByDesc(string $column): static
    {
        return $this->orderBy($column, 'desc');
    }

    /**
     * Order by column descending (newest first).
     *
     * @param  string $column
     * @return static
     */
    public function latest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'desc');
    }

    /**
     * Order by column ascending (oldest first).
     *
     * @param  string $column
     * @return static
     */
    public function oldest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'asc');
    }

    /**
     * Order results randomly.
     *
     * @return static
     */
    public function inRandomOrder(): static
    {
        $this->orders[] = ['raw' => 'RAND()'];

        return $this;
    }

    /**
     * Add a raw ORDER BY expression.
     *
     * @param  string $expression
     * @return static
     */
    public function orderByRaw(string $expression): static
    {
        $this->orders[] = ['raw' => $expression];

        return $this;
    }

    /**
     * Remove all ORDER BY clauses.
     *
     * @return static
     */
    public function reorder(): static
    {
        $this->orders = [];

        return $this;
    }

    // -----------------------------------------------------------------------
    // GROUP BY / HAVING
    // -----------------------------------------------------------------------

    /**
     * Add GROUP BY columns.
     *
     * @param  string ...$columns
     * @return static
     */
    public function groupBy(string ...$columns): static
    {
        array_push($this->groups, ...$columns);

        return $this;
    }

    /**
     * Add a HAVING condition.
     *
     * @param  string $column
     * @param  string $operator
     * @param  mixed  $value
     * @param  string $boolean
     * @return static
     */
    public function having(string $column, string $operator, mixed $value, string $boolean = 'AND'): static
    {
        $this->havings[] = [
            'type'     => 'basic',
            'column'   => $column,
            'operator' => $this->grammar->validateOperator($operator),
            'value'    => $value,
            'boolean'  => $boolean,
        ];

        return $this;
    }

    /**
     * Add an OR HAVING condition.
     *
     * @param  string $column
     * @param  string $operator
     * @param  mixed  $value
     * @return static
     */
    public function orHaving(string $column, string $operator, mixed $value): static
    {
        return $this->having($column, $operator, $value, 'OR');
    }

    /**
     * Add a raw HAVING expression.
     *
     * @param  string                   $expression
     * @param  array<int|string, mixed> $bindings
     * @param  string                   $boolean
     * @return static
     */
    public function havingRaw(string $expression, array $bindings = [], string $boolean = 'AND'): static
    {
        $this->havings[] = [
            'type'     => 'raw',
            'sql'      => $expression,
            'bindings' => $bindings,
            'boolean'  => $boolean,
        ];

        return $this;
    }

    /**
     * Add an OR raw HAVING expression.
     *
     * @param  string                   $expression
     * @param  array<int|string, mixed> $bindings
     * @return static
     */
    public function orHavingRaw(string $expression, array $bindings = []): static
    {
        return $this->havingRaw($expression, $bindings, 'OR');
    }

    // -----------------------------------------------------------------------
    // LIMIT / OFFSET
    // -----------------------------------------------------------------------

    /**
     * Set the LIMIT value.
     *
     * @param  int $value
     * @return static
     */
    public function limit(int $value): static
    {
        $this->limitValue = max(0, $value);

        return $this;
    }

    /** Alias for limit(). @param int $value @return static */
    public function take(int $value): static
    {
        return $this->limit($value);
    }

    /**
     * Set the OFFSET value.
     *
     * @param  int $value
     * @return static
     */
    public function offset(int $value): static
    {
        $this->offsetValue = max(0, $value);

        return $this;
    }

    /** Alias for offset(). @param int $value @return static */
    public function skip(int $value): static
    {
        return $this->offset($value);
    }

    // -----------------------------------------------------------------------
    // Terminal — READ
    // -----------------------------------------------------------------------

    /**
     * Execute the query and return all matching rows as a Collection.
     * When a hydrator is set (Model queries), each row is transformed
     * into a model instance.
     *
     * @return Collection
     */
    public function get(): Collection
    {
        $rows = $this->connection->select($this->toSql(), $this->getBindings());

        if ($this->hydrator !== null) {
            $rows = array_map($this->hydrator, $rows);
        }

        return new Collection($rows);
    }

    /**
     * Execute the query and return the first row, or false if none.
     * When a hydrator is set, the row is transformed into a model instance.
     *
     * @return object|false
     */
    public function first(): object|false
    {
        $row = $this->limit(1)->connection->selectOne($this->toSql(), $this->getBindings());

        if ($row === false) {
            return false;
        }

        if ($this->hydrator !== null) {
            return ($this->hydrator)($row);
        }

        return $row;
    }

    /**
     * Find a row by primary key, or return false.
     *
     * @param  int|string $id
     * @return object|false
     */
    public function find(int|string $id): object|false
    {
        return $this->where($this->primaryKey, '=', $id)->first();
    }

    /**
     * Return the value of a single column from the first matching row.
     *
     * @param  string $column
     * @return mixed
     */
    public function value(string $column): mixed
    {
        $row = $this->select($column)->first();

        return $row ? ($row->{$column} ?? null) : null;
    }

    /**
     * Return an array of values for a single column.
     * Optionally key the array by a second column.
     *
     * @param  string      $column
     * @param  string|null $keyColumn
     * @return array<int|string, mixed>
     */
    public function pluck(string $column, ?string $keyColumn = null): array
    {
        $cols = $keyColumn ? [$column, $keyColumn] : [$column];
        $rows = $this->select(...$cols)->get();

        return $rows->pluck($column, $keyColumn);
    }

    /**
     * Process query results in batches of $size rows at a time.
     *
     * @param  int      $size
     * @param  callable(Collection): bool|void $callback  Receives a Collection per batch; return false to stop.
     * @return void
     */
    public function chunk(int $size, callable $callback): void
    {
        $page = 0;

        do {
            $results = (clone $this)->limit($size)->offset($page * $size)->get();

            if ($results->isEmpty()) {
                break;
            }

            if ($callback($results) === false) {
                break;
            }

            $page++;
        } while ($results->count() === $size);
    }

    /**
     * Iterate over every row one at a time, calling $callback for each.
     *
     * @param  callable $callback  Receives object; return false to stop.
     * @return void
     */
    public function each(callable $callback): void
    {
        $this->chunk(100, function (Collection $rows) use ($callback): bool {
            foreach ($rows as $row) {
                if ($callback($row) === false) {
                    return false;
                }
            }
            return true;
        });
    }

    /**
     * Paginate the results.
     *
     * @param  int $perPage   Rows per page
     * @param  int $page      Current page number (1-based)
     * @return object{total: int, per_page: int, current_page: int, last_page: int, from: int, to: int, data: array<int, object>}
     */
    public function paginate(int $perPage = 15, int $page = 1): object
    {
        $total   = (clone $this)->count();
        $results = (clone $this)->limit($perPage)->offset(($page - 1) * $perPage)->get();

        return (object) [
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => max(1, (int) ceil($total / $perPage)),
            'from'         => $total > 0 ? ($page - 1) * $perPage + 1 : 0,
            'to'           => min($page * $perPage, $total),
            'data'         => $results,   // Collection
        ];
    }

    // -----------------------------------------------------------------------
    // Terminal — AGGREGATES
    // -----------------------------------------------------------------------

    /**
     * @param  string $column
     * @return int
     */
    public function count(string $column = '*'): int
    {
        return (int) $this->aggregate('COUNT', $column);
    }

    /**
     * @param  string $column
     * @return float|int
     */
    public function min(string $column): float|int
    {
        return $this->aggregate('MIN', $column);
    }

    /**
     * @param  string $column
     * @return float|int
     */
    public function max(string $column): float|int
    {
        return $this->aggregate('MAX', $column);
    }

    /**
     * @param  string $column
     * @return float|int
     */
    public function avg(string $column): float|int
    {
        return $this->aggregate('AVG', $column);
    }

    /**
     * @param  string $column
     * @return float|int
     */
    public function sum(string $column): float|int
    {
        return $this->aggregate('SUM', $column);
    }

    /**
     * @return bool
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * @return bool
     */
    public function doesntExist(): bool
    {
        return ! $this->exists();
    }

    /**
     * Run a raw aggregate function and return the scalar result.
     *
     * @param  string $function
     * @param  string $column
     * @return float|int
     */
    protected function aggregate(string $function, string $column): float|int
    {
        $sql  = $this->grammar->compileAggregateQuery($function, $column, $this->buildState());
        $row  = $this->connection->selectOne($sql, $this->getBindings());

        if ($row === false) {
            return 0;
        }

        $val = current((array) $row);

        return is_numeric($val) ? $val + 0 : 0;
    }

    // -----------------------------------------------------------------------
    // Terminal — WRITE
    // -----------------------------------------------------------------------

    /**
     * Insert a single row.
     *
     * @param  array<string, mixed> $values
     * @return bool
     */
    public function insert(array $values): bool
    {
        $sql      = $this->grammar->compileInsert($this->table, $values);
        $bindings = array_values($values);

        return $this->connection->insert($sql, $bindings);
    }

    /**
     * Insert a row and return the last inserted ID.
     *
     * @param  array<string, mixed> $values
     * @return int|string
     */
    public function insertGetId(array $values): int|string
    {
        $sql      = $this->grammar->compileInsert($this->table, $values);
        $bindings = array_values($values);

        return $this->connection->insertGetId($sql, $bindings);
    }

    /**
     * Insert multiple rows in a single query.
     *
     * @param  array<int, array<string, mixed>> $rows
     * @return bool
     */
    public function insertBatch(array $rows): bool
    {
        if (empty($rows)) {
            return true;
        }

        $sql      = $this->grammar->compileInsertBatch($this->table, $rows);
        $bindings = array_merge(...array_map('array_values', $rows));

        return $this->connection->insert($sql, $bindings);
    }

    /**
     * Update rows matching the current WHERE conditions.
     *
     * @param  array<string, mixed> $values
     * @return int  Number of affected rows
     */
    public function update(array $values): int
    {
        $sql = $this->grammar->compileUpdate($this->table, $this->buildState(), $values);

        // Filter out RawExpression values — they are embedded directly in SQL, not bound.
        $setBindings = array_values(array_filter(
            $values,
            fn(mixed $v) => ! ($v instanceof RawExpression),
        ));

        $bindings = array_merge($setBindings, $this->getWhereBindings());

        return $this->connection->update($sql, $bindings);
    }

    /**
     * Delete rows matching the current WHERE conditions.
     *
     * @return int  Number of affected rows
     */
    public function delete(): int
    {
        $sql = $this->grammar->compileDelete($this->table, $this->buildState());

        return $this->connection->delete($sql, $this->getBindings());
    }

    /**
     * Truncate the table (remove all rows, reset auto-increment).
     *
     * @return bool
     */
    public function truncate(): bool
    {
        return $this->connection->statement("TRUNCATE TABLE {$this->grammar->wrapTable($this->table)}");
    }

    /**
     * Increment a column value by $amount.
     *
     * @param  string               $column
     * @param  int|float            $amount
     * @param  array<string, mixed> $extra   Additional columns to update
     * @return int
     */
    public function increment(string $column, int|float $amount = 1, array $extra = []): int
    {
        $wrapped = $this->grammar->wrapColumn($column);
        $values  = array_merge([$column => new RawExpression("{$wrapped} + {$amount}")], $extra);

        return $this->update($values);
    }

    /**
     * Decrement a column value by $amount.
     *
     * @param  string               $column
     * @param  int|float            $amount
     * @param  array<string, mixed> $extra
     * @return int
     */
    public function decrement(string $column, int|float $amount = 1, array $extra = []): int
    {
        $wrapped = $this->grammar->wrapColumn($column);
        $values  = array_merge([$column => new RawExpression("{$wrapped} - {$amount}")], $extra);

        return $this->update($values);
    }

    /**
     * Update or insert a row.
     * If a row matching $conditions exists, update it with $values;
     * otherwise insert $conditions + $values.
     *
     * @param  array<string, mixed> $conditions  Lookup criteria
     * @param  array<string, mixed> $values      Values to set on update or merge into insert
     * @return bool
     */
    public function updateOrInsert(array $conditions, array $values = []): bool
    {
        $query = clone $this;
        foreach ($conditions as $col => $val) {
            $query->where($col, $val);
        }

        if ($query->exists()) {
            $updateQuery = clone $this;
            foreach ($conditions as $col => $val) {
                $updateQuery->where($col, $val);
            }
            return $updateQuery->update($values) >= 0;
        }

        return $this->insert(array_merge($conditions, $values));
    }

    // -----------------------------------------------------------------------
    // Debug helpers
    // -----------------------------------------------------------------------

    /**
     * Compile and return the SQL string without executing.
     *
     * @return string
     */
    public function toSql(): string
    {
        return $this->grammar->compileSelect($this->buildState());
    }

    /**
     * Get all bindings in the correct order for the compiled SQL.
     *
     * @return array<int, mixed>
     */
    public function getBindings(): array
    {
        $bindings = [];

        // SELECT raw expression bindings
        foreach ($this->columns as $col) {
            if ($col instanceof RawExpression) {
                array_push($bindings, ...$col->bindings);
            }
        }

        // JOIN WHERE bindings
        foreach ($this->joins as $join) {
            if ($join instanceof JoinClause) {
                array_push($bindings, ...$join->getBindings());
            }
        }

        // Subquery bindings (from whereIn(Builder), joinSub, fromSub) — injected before WHERE
        array_push($bindings, ...$this->subBindings);

        // WHERE bindings
        array_push($bindings, ...$this->getWhereBindings());

        // HAVING bindings
        foreach ($this->havings as $having) {
            if ($having['type'] === 'basic') {
                $bindings[] = $having['value'];
            } elseif ($having['type'] === 'raw' && ! empty($having['bindings'])) {
                array_push($bindings, ...$having['bindings']);
            }
        }

        return $bindings;
    }

    /**
     * Dump the SQL + bindings and continue execution.
     *
     * @return static
     */
    public function dump(): static
    {
        var_dump(['sql' => $this->toSql(), 'bindings' => $this->getBindings()]);

        return $this;
    }

    /**
     * Dump the SQL + bindings and stop execution.
     *
     * @return never
     */
    public function dd(): never
    {
        $this->dump();
        exit(1);
    }

    /**
     * Set the primary key used by find().
     *
     * @param  string $key
     * @return static
     */
    public function setPrimaryKey(string $key): static
    {
        $this->primaryKey = $key;

        return $this;
    }

    /**
     * Set a hydration callback that transforms each raw stdClass row
     * into a model instance. Used by Model::newQuery().
     *
     * @param  callable(object): object $hydrator
     * @return static
     */
    public function setHydrator(callable $hydrator): static
    {
        $this->hydrator = $hydrator;

        return $this;
    }

    // -----------------------------------------------------------------------
    // Internal helpers
    // -----------------------------------------------------------------------

    /**
     * Merge another Builder's extra bindings (subquery, raw) into this one.
     * Used for subquery WHERE/FROM to propagate their bindings up.
     *
     * @param  Builder $query
     * @return static
     */
    public function mergeBindingsFrom(Builder $query): static
    {
        // Collect all bindings from the subquery and store them separately.
        // They are injected into getBindings() at the right position.
        array_push($this->subBindings, ...$query->getBindings());

        return $this;
    }

    /**
     * Collect all binding values from the WHERE clause array.
     *
     * @return array<int, mixed>
     */
    protected function getWhereBindings(): array
    {
        return $this->extractWhereBindings($this->wheres);
    }

    /**
     * Recursively extract bindings from a where clause list.
     *
     * @param  array<int, array<string, mixed>> $wheres
     * @return array<int, mixed>
     */
    protected function extractWhereBindings(array $wheres): array
    {
        $bindings = [];

        foreach ($wheres as $where) {
            $type = $where['type'] ?? 'basic';

            match ($type) {
                'basic'        => $bindings[] = $where['value'],
                'in', 'notIn'  => array_push($bindings, ...$where['values']),
                'between',
                'notBetween'   => array_push($bindings, $where['min'], $where['max']),
                'raw'          => array_push($bindings, ...($where['bindings'] ?? [])),
                'nested'       => array_push($bindings, ...$this->extractWhereBindings($where['wheres'])),
                'date', 'time',
                'day', 'month',
                'year'         => $bindings[] = $where['value'],
                default        => null, // null, notNull, column, exists, notExists — no bindings
            };
        }

        return $bindings;
    }

    /**
     * Build the state snapshot array consumed by Grammar.
     *
     * @return array<string, mixed>
     */
    protected function buildState(): array
    {
        // Normalise columns: RawExpression → its string value for Grammar.
        // Keep RawExpression objects intact — Grammar identifies and skips quoting them.
        $columns = $this->columns;

        // Normalise joins: raw-join arrays pass their raw key through.
        $joins = array_map(function (mixed $join) {
            if ($join instanceof JoinClause) {
                return $join; // Grammar handles JoinClause objects directly
            }
            return $join; // plain array (from raw joins)
        }, $this->joins);

        return [
            'table'     => $this->table,
            'columns'   => $columns,
            'distinct'  => $this->distinct,
            'aggregate' => $this->aggregateState,
            'joins'     => $joins,
            'wheres'    => $this->wheres,
            'groups'    => $this->groups,
            'havings'   => $this->havings,
            'orders'    => $this->orders,
            'limit'     => $this->limitValue,
            'offset'    => $this->offsetValue,
        ];
    }

    /**
     * Create a fresh Builder sharing the same connection and grammar.
     *
     * @return static
     */
    public function newQuery(): static
    {
        return new static($this->connection, $this->grammar);
    }

    /**
     * Parse ($operatorOrValue, $value) shorthand into [$operator, $value].
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