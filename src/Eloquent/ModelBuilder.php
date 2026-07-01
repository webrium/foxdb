<?php

declare(strict_types=1);

namespace Foxdb\Eloquent;

use Foxdb\Query\Builder;
use Foxdb\Support\Collection;

/**
 * ModelBuilder — a thin decorator around Builder that carries the model
 * class name so that terminal methods (first, find, get) can declare
 * correct `static`-aware return types for IDE autocompletion.
 *
 * Without this wrapper, User::where(...)->first() returns `object|false`
 * (the Builder return type) and the IDE has no way to know the result
 * is a User instance.
 *
 * All Builder methods are forwarded transparently via __call so that
 * every chainable method (where, orderBy, limit, etc.) still works and
 * continues to return $this (a ModelBuilder) rather than the raw Builder.
 *
 * @template TModel of Model
 */
class ModelBuilder
{
    /**
     * @param Builder             $builder    The underlying query builder
     * @param class-string<TModel> $modelClass The model class being queried
     */
    public function __construct(
        protected Builder $builder,
        protected string  $modelClass,
    ) {}

    // -----------------------------------------------------------------------
    // Terminal methods — declared explicitly so IDEs see the right types
    // -----------------------------------------------------------------------

    /**
     * Execute the query and return all matching model instances.
     *
     * @return Collection<int, TModel>
     */
    public function get(): Collection
    {
        return $this->builder->get();
    }

    /**
     * Execute the query and return the first matching model instance,
     * or null if no row matches.
     *
     * @return TModel|null
     */
    public function first(): ?Model
    {
        $result = $this->builder->first();
        return $result instanceof Model ? $result : null;
    }

    /**
     * Find a model by its primary key, or return null.
     *
     * @param  int|string $id
     * @return TModel|null
     */
    public function find(int|string $id): ?Model
    {
        $result = $this->builder->find($id);
        return $result instanceof Model ? $result : null;
    }

    /**
     * Paginate the results.
     *
     * @param  int $perPage
     * @param  int $page
     * @return object{total:int,per_page:int,current_page:int,last_page:int,from:int,to:int,data:Collection}
     */
    public function paginate(int $perPage = 15, int $page = 1): object
    {
        return $this->builder->paginate($perPage, $page);
    }

    /**
     * Return the value of a single column from the first matching row.
     *
     * @param  string $column
     * @return mixed
     */
    public function value(string $column): mixed
    {
        return $this->builder->value($column);
    }

    /**
     * Return a flat array of values for a single column.
     *
     * @param  string      $column
     * @param  string|null $keyColumn
     * @return array<mixed>
     */
    public function pluck(string $column, ?string $keyColumn = null): array
    {
        return $this->builder->pluck($column, $keyColumn);
    }

    /**
     * Return the count of matching rows.
     *
     * @param  string $column
     * @return int
     */
    public function count(string $column = '*'): int
    {
        return $this->builder->count($column);
    }

    /**
     * Return the sum of a column.
     */
    public function sum(string $column): float|int
    {
        return $this->builder->sum($column);
    }

    /**
     * Return the average of a column.
     */
    public function avg(string $column): float|int
    {
        return $this->builder->avg($column);
    }

    /**
     * Return the minimum value of a column.
     */
    public function min(string $column): float|int|null
    {
        return $this->builder->min($column);
    }

    /**
     * Return the maximum value of a column.
     */
    public function max(string $column): float|int|null
    {
        return $this->builder->max($column);
    }

    /**
     * Check whether any matching row exists.
     */
    public function exists(): bool
    {
        return $this->builder->exists();
    }

    /**
     * Update matching rows.
     *
     * @param  array<string, mixed> $values
     * @return int  Number of affected rows
     */
    public function update(array $values): int
    {
        return $this->builder->update($values);
    }

    /**
     * Delete matching rows.
     *
     * @return int  Number of affected rows
     */
    public function delete(): int
    {
        return $this->builder->delete();
    }

    /**
     * Return the compiled SQL string.
     */
    public function toSql(): string
    {
        return $this->builder->toSql();
    }

    /**
     * Return the current bindings array.
     *
     * @return array<mixed>
     */
    public function getBindings(): array
    {
        return $this->builder->getBindings();
    }

    /**
     * Eager-load the given relations on this query.
     *
     * Unlike Model::with(), which must be the first call in a chain,
     * this allows with() to be added at any point after select(),
     * where(), or any other Builder method — matching the behaviour
     * Eloquent users expect from frameworks like Laravel, where with()
     * is order-independent:
     *
     *   Page::select('id', 'slug')->with('translations')->where(...)->first();
     *   Page::where(...)->with('translations')->select('id', 'slug')->first();
     *
     * Returns an EagerBuilder seeded with the current underlying Builder,
     * so all constraints already applied (select, where, etc.) are kept.
     *
     * @param  string|array<string|int, string|callable> ...$relations
     * @return EagerBuilder
     */
    public function with(string|array ...$relations): EagerBuilder
    {
        return (new EagerBuilder($this->builder, $this->modelClass, []))->with(...$relations);
    }

    // -----------------------------------------------------------------------
    // Forward all other Builder methods transparently
    // -----------------------------------------------------------------------

    /**
     * Forward any Builder method not declared above (where, orderBy, limit,
     * join, groupBy, etc.). If the Builder returns itself, we return $this
     * (the ModelBuilder) so the chain stays intact and the IDE continues
     * to see the correct type.
     *
     * Local scopes (scopeXxx on the model) are resolved here too, so a
     * scope stays chainable after another scope — e.g.
     * Model::active()->adult()->get() — and not just after plain Builder
     * methods. Without this, $name is forwarded straight to the raw
     * Builder, which knows nothing about scopes and throws "Call to
     * undefined method".
     *
     * @param  string       $name
     * @param  array<mixed> $arguments
     * @return static|mixed
     */
    public function __call(string $name, array $arguments): mixed
    {
        $modelInstance = new $this->modelClass();
        $scope         = 'scope' . ucfirst($name);

        if (method_exists($modelInstance, $scope)) {
            $result = $modelInstance->$scope($this->builder, ...$arguments);

            return $result === $this->builder ? $this : $result;
        }

        $result = $this->builder->$name(...$arguments);

        // If Builder returned itself, keep the chain on ModelBuilder
        if ($result === $this->builder) {
            return $this;
        }

        return $result;
    }
}