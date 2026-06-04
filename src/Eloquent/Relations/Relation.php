<?php

declare(strict_types=1);

namespace Foxdb\Eloquent\Relations;

use Foxdb\Eloquent\Model;
use Foxdb\Query\Builder;
use Foxdb\Support\Collection;

/**
 * Abstract base for all relation types.
 *
 * A Relation wraps a Builder that is pre-constrained to fetch
 * the related model(s) for a given parent model instance.
 *
 * Lazy loading:  $user->posts          → runs query on first access
 * Eager loading: User::with('posts')   → one extra query for all parents
 */
abstract class Relation
{
    /**
     * The pre-constrained Builder for this relation.
     *
     * @var Builder
     */
    protected Builder $query;

    /**
     * The parent model instance that owns this relation.
     *
     * @var Model
     */
    protected Model $parent;

    /**
     * The related model class name.
     *
     * @var class-string<Model>
     */
    protected string $relatedClass;

    /**
     * A cached instance of the related model (for table/key resolution).
     *
     * @var Model
     */
    protected Model $related;

    /**
     * @param Builder             $query        Pre-constrained relation query
     * @param Model               $parent       The owning model instance
     * @param class-string<Model> $relatedClass FQCN of the related model
     */
    public function __construct(Builder $query, Model $parent, string $relatedClass)
    {
        $this->query        = $query;
        $this->parent       = $parent;
        $this->relatedClass = $relatedClass;
        $this->related      = new $relatedClass();
    }

    // -----------------------------------------------------------------------
    // Core interface — implemented by each relation type
    // -----------------------------------------------------------------------

    /**
     * Add the constraints for a simple (lazy) load of this relation.
     * Called once when the relation object is first created.
     *
     * @return void
     */
    abstract protected function addConstraints(): void;

    /**
     * Add constraints for eager loading across multiple parent models.
     *
     * @param  array<int, Model> $models
     * @return void
     */
    abstract public function addEagerConstraints(array $models): void;

    /**
     * For eager loading: match loaded related models back to their parents
     * and set the relation result on each parent.
     *
     * @param  array<int, Model> $models   Parent models
     * @param  Collection        $results  All related models fetched
     * @param  string            $relation Relation name (for setRelation)
     * @return array<int, Model>
     */
    abstract public function match(array $models, Collection $results, string $relation): array;

    /**
     * Execute the relation query and return the result.
     * Returns a Collection for *-many relations, a Model|null for *-one.
     *
     * @return Collection|Model|null
     */
    abstract public function getResults(): Collection|Model|null;

    // -----------------------------------------------------------------------
    // Query forwarding — allow chaining on the relation
    // -----------------------------------------------------------------------

    /**
     * Forward any unknown method call to the underlying Builder.
     * Enables:  $user->posts()->where('published', 1)->get()
     *
     * @param  string  $method
     * @param  mixed[] $args
     * @return mixed
     */
    public function __call(string $method, array $args): mixed
    {
        return $this->query->$method(...$args);
    }

    /**
     * Get the underlying constrained Builder.
     *
     * @return Builder
     */
    public function getQuery(): Builder
    {
        return $this->query;
    }

    /**
     * Get the related model instance (for metadata access).
     *
     * @return Model
     */
    public function getRelated(): Model
    {
        return $this->related;
    }

    /**
     * Get the parent model instance.
     *
     * @return Model
     */
    public function getParent(): Model
    {
        return $this->parent;
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Collect the primary key values from an array of parent models.
     *
     * @param  array<int, Model> $models
     * @param  string            $key
     * @return array<int, mixed>
     */
    protected function collectKeys(array $models, string $key): array
    {
        return array_values(array_unique(
            array_filter(
                array_map(fn(Model $m) => $m->getAttribute($key), $models),
                fn(mixed $v) => $v !== null,
            )
        ));
    }
}
