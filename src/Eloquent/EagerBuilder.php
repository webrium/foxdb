<?php

declare(strict_types=1);

namespace Foxdb\Eloquent;

use Foxdb\Query\Builder;
use Foxdb\Support\Collection;

/**
 * EagerBuilder — a thin decorator around Builder that runs eager loading
 * after get() or first() completes.
 *
 * This keeps the Builder itself completely unaware of Models or Relations.
 * All chainable Builder methods are forwarded transparently.
 *
 * Usage (created by Model::with()):
 *   User::with('posts', 'profile')->where('active', 1)->orderBy('name')->get()
 */
class EagerBuilder
{
    /**
     * @param Builder                         $builder     The underlying query builder
     * @param class-string<Model>             $modelClass  The model class being queried
     * @param array<string, callable|null>    $withs       Relations to eager-load
     */
    public function __construct(
        protected Builder $builder,
        protected string  $modelClass,
        protected array   $withs,
    ) {}

    // -----------------------------------------------------------------------
    // Terminal methods — execute query then eager-load
    // -----------------------------------------------------------------------

    /**
     * Execute the query and eager-load all specified relations.
     *
     * @return Collection
     */
    public function get(): Collection
    {
        $results = $this->builder->get();

        if ($results->isEmpty()) {
            return $results;
        }

        return $this->modelClass::eagerLoad($results, $this->withs);
    }

    /**
     * Execute and return the first result with relations eager-loaded.
     * Returns null if no result found.
     *
     * @return Model|null
     */
    public function first(): ?Model
    {
        $row = $this->builder->first();

        if ($row === false || $row === null) {
            return null;
        }

        // Wrap in a Collection, eager-load, then return the single item.
        $collection = new Collection([$row]);
        $loaded     = $this->modelClass::eagerLoad($collection, $this->withs);

        return $loaded->first();
    }

    /**
     * Add more relations to eager-load.
     *
     * @param  string|array<string|int, string|callable> ...$relations
     * @return static
     */
    public function with(string|array ...$relations): static
    {
        foreach ($relations as $rel) {
            if (is_array($rel)) {
                foreach ($rel as $k => $v) {
                    if (is_int($k)) {
                        $this->withs[$v] = null;
                    } else {
                        $this->withs[$k] = $v;
                    }
                }
            } else {
                $this->withs[$rel] = null;
            }
        }

        return $this;
    }

    // -----------------------------------------------------------------------
    // Forward all other calls to the underlying Builder
    // -----------------------------------------------------------------------

    /**
     * Proxy all unknown method calls to the Builder.
     * Returns static when the Builder returns itself (chainable methods),
     * otherwise returns the raw Builder return value.
     *
     * @param  string  $method
     * @param  mixed[] $args
     * @return static|mixed
     */
    public function __call(string $method, array $args): mixed
    {
        $result = $this->builder->$method(...$args);

        // If Builder returned itself (chaining), wrap back in EagerBuilder.
        if ($result === $this->builder) {
            return $this;
        }

        return $result;
    }
}
