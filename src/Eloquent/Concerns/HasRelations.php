<?php

declare(strict_types=1);

namespace Foxdb\Eloquent\Concerns;

use Foxdb\Eloquent\Model;
use Foxdb\Eloquent\Relations\BelongsTo;
use Foxdb\Eloquent\Relations\BelongsToMany;
use Foxdb\Eloquent\Relations\HasMany;
use Foxdb\Eloquent\Relations\HasManyThrough;
use Foxdb\Eloquent\Relations\HasOne;
use Foxdb\Eloquent\Relations\Relation;
use Foxdb\Support\Collection;

/**
 * HasRelations — adds relation definition helpers and eager loading to Model.
 *
 * Lazy loading:
 *   Accessing $user->posts the first time calls posts() and caches the result.
 *   Subsequent accesses return the cached value.
 *
 * Eager loading:
 *   User::with('posts', 'profile')->get()
 *   User::with(['posts' => fn($q) => $q->where('published', 1)])->get()
 */
trait HasRelations
{
    /**
     * Cached relation results, keyed by relation name.
     *
     * @var array<string, Collection|Model|null>
     */
    protected array $relations = [];

    // -----------------------------------------------------------------------
    // Relation definition helpers (called inside model methods)
    // -----------------------------------------------------------------------

    /**
     * Define a HasOne relationship.
     *
     * @param  class-string<Model> $relatedClass
     * @param  string|null         $foreignKey   Default: snake_case(ParentClass)_id
     * @param  string|null         $localKey     Default: parent PK
     * @return HasOne
     */
    public function hasOne(
        string  $relatedClass,
        ?string $foreignKey = null,
        ?string $localKey   = null,
    ): HasOne {
        $related    = new $relatedClass();
        $foreignKey = $foreignKey ?? $this->guessFK();
        $localKey   = $localKey   ?? $this->primaryKey;

        return new HasOne(
            query        : $related->newQuery(),
            parent       : $this,
            relatedClass : $relatedClass,
            foreignKey   : $foreignKey,
            localKey     : $localKey,
        );
    }

    /**
     * Define a HasMany relationship.
     *
     * @param  class-string<Model> $relatedClass
     * @param  string|null         $foreignKey
     * @param  string|null         $localKey
     * @return HasMany
     */
    public function hasMany(
        string  $relatedClass,
        ?string $foreignKey = null,
        ?string $localKey   = null,
    ): HasMany {
        $related    = new $relatedClass();
        $foreignKey = $foreignKey ?? $this->guessFK();
        $localKey   = $localKey   ?? $this->primaryKey;

        return new HasMany(
            query        : $related->newQuery(),
            parent       : $this,
            relatedClass : $relatedClass,
            foreignKey   : $foreignKey,
            localKey     : $localKey,
        );
    }

    /**
     * Define a BelongsTo relationship.
     *
     * @param  class-string<Model> $relatedClass
     * @param  string|null         $foreignKey   Default: snake_case(RelatedClass)_id
     * @param  string|null         $ownerKey     Default: related model PK
     * @return BelongsTo
     */
    public function belongsTo(
        string  $relatedClass,
        ?string $foreignKey = null,
        ?string $ownerKey   = null,
    ): BelongsTo {
        $related    = new $relatedClass();
        $foreignKey = $foreignKey ?? $this->guessFKFor($relatedClass);
        $ownerKey   = $ownerKey   ?? $related->getPrimaryKey();

        return new BelongsTo(
            query        : $related->newQuery(),
            parent       : $this,
            relatedClass : $relatedClass,
            foreignKey   : $foreignKey,
            ownerKey     : $ownerKey,
        );
    }

    /**
     * Define a BelongsToMany relationship.
     *
     * @param  class-string<Model> $relatedClass
     * @param  string|null         $pivotTable       Default: sorted alphabetic snake_case pair
     * @param  string|null         $foreignPivotKey  FK for this model on pivot
     * @param  string|null         $relatedPivotKey  FK for related on pivot
     * @param  string|null         $parentKey        Default: parent PK
     * @param  string|null         $relatedKey       Default: related PK
     * @return BelongsToMany
     */
    public function belongsToMany(
        string  $relatedClass,
        ?string $pivotTable       = null,
        ?string $foreignPivotKey  = null,
        ?string $relatedPivotKey  = null,
        ?string $parentKey        = null,
        ?string $relatedKey       = null,
    ): BelongsToMany {
        $related         = new $relatedClass();
        $pivotTable      = $pivotTable      ?? $this->guessPivotTable($relatedClass);
        $foreignPivotKey = $foreignPivotKey ?? $this->guessFK();
        $relatedPivotKey = $relatedPivotKey ?? $this->guessFKFor($relatedClass);
        $parentKey       = $parentKey       ?? $this->primaryKey;
        $relatedKey      = $relatedKey      ?? $related->getPrimaryKey();

        return new BelongsToMany(
            query           : $related->newQuery(),
            parent          : $this,
            relatedClass    : $relatedClass,
            pivotTable      : $pivotTable,
            foreignPivotKey : $foreignPivotKey,
            relatedPivotKey : $relatedPivotKey,
            parentKey       : $parentKey,
            relatedKey      : $relatedKey,
        );
    }

    /**
     * Define a HasManyThrough relationship.
     *
     * @param  class-string<Model> $relatedClass
     * @param  class-string<Model> $throughClass
     * @param  string|null         $firstKey       FK on intermediate → parent
     * @param  string|null         $secondKey      FK on related → intermediate
     * @param  string|null         $localKey       PK of parent
     * @param  string|null         $secondLocalKey PK of intermediate
     * @return HasManyThrough
     */
    public function hasManyThrough(
        string  $relatedClass,
        string  $throughClass,
        ?string $firstKey       = null,
        ?string $secondKey      = null,
        ?string $localKey       = null,
        ?string $secondLocalKey = null,
    ): HasManyThrough {
        $through        = new $throughClass();
        $related        = new $relatedClass();
        $firstKey       = $firstKey       ?? $this->guessFK();
        $secondKey      = $secondKey      ?? $this->guessFKFor($throughClass);
        $localKey       = $localKey       ?? $this->primaryKey;
        $secondLocalKey = $secondLocalKey ?? $through->getPrimaryKey();

        return new HasManyThrough(
            query          : $related->newQuery(),
            parent         : $this,
            relatedClass   : $relatedClass,
            throughClass   : $throughClass,
            firstKey       : $firstKey,
            secondKey      : $secondKey,
            localKey       : $localKey,
            secondLocalKey : $secondLocalKey,
        );
    }

    // -----------------------------------------------------------------------
    // Eager loading
    // -----------------------------------------------------------------------

    /**
     * Eager-load the given relations on a Collection of models.
     * Called by the static with() method after get().
     *
     * @param  Collection                                     $models
     * @param  array<string, callable|null>                   $withs  relation => optional constraint
     * @return Collection
     */
    public static function eagerLoad(Collection $models, array $withs): Collection
    {
        if ($models->isEmpty()) {
            return $models;
        }

        $modelArray = $models->all();

        foreach ($withs as $name => $constraint) {
            $prototype = $modelArray[0];

            if (! method_exists($prototype, $name)) {
                continue;
            }

            /** @var Relation $relation */
            $relation = $prototype->$name();

            // First: replace query with fresh one scoped to all parent IDs.
            $relation->addEagerConstraints($modelArray);

            // Then: apply optional caller constraint AFTER the fresh query is set.
            if ($constraint !== null) {
                $constraint($relation->getQuery());
            }

            // Execute the eager query — always fetch all rows (getResults returns
            // Model|null for *-one relations, which is only for lazy; for eager
            // we always need all rows so we call get() on the query directly).
            $results = $relation->getQuery()->get();

            // Match results back to each parent.
            $modelArray = $relation->match($modelArray, $results, $name);
        }

        return new Collection($modelArray);
    }

    // -----------------------------------------------------------------------
    // Relation result cache
    // -----------------------------------------------------------------------

    /**
     * Get a loaded relation result by name, or null if not loaded.
     *
     * @param  string $relation
     * @return Collection|Model|null
     */
    public function getRelation(string $relation): Collection|Model|null
    {
        return $this->relations[$relation] ?? null;
    }

    /**
     * Set a relation result on this model instance.
     *
     * @param  string                  $relation
     * @param  Collection|Model|null   $value
     * @return void
     */
    public function setRelation(string $relation, Collection|Model|null $value): void
    {
        $this->relations[$relation] = $value;
    }

    /**
     * Determine whether a relation has been loaded (cached).
     *
     * @param  string $relation
     * @return bool
     */
    public function relationLoaded(string $relation): bool
    {
        return array_key_exists($relation, $this->relations);
    }

    /**
     * Unset a cached relation result, forcing the next access to re-query.
     *
     * @param  string $relation
     * @return void
     */
    public function unsetRelation(string $relation): void
    {
        unset($this->relations[$relation]);
    }

    // -----------------------------------------------------------------------
    // FK / pivot table name guessing
    // -----------------------------------------------------------------------

    /**
     * Guess the FK name for this model on a related table.
     * e.g. User → 'user_id'
     *
     * @return string
     */
    protected function guessFK(): string
    {
        $class = (new \ReflectionClass($this))->getShortName();
        $snake = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $class));

        return $snake . '_' . $this->primaryKey;
    }

    /**
     * Guess the FK name for a *related* class on some other table.
     * e.g. User::class → 'user_id'
     *
     * @param  class-string $class
     * @return string
     */
    protected function guessFKFor(string $class): string
    {
        $short = (new \ReflectionClass($class))->getShortName();
        $snake = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $short));
        $pk    = (new $class())->getPrimaryKey();

        return $snake . '_' . $pk;
    }

    /**
     * Guess the pivot table name for a BelongsToMany.
     * Uses alphabetical snake_case ordering: user + role → role_user
     *
     * @param  class-string $relatedClass
     * @return string
     */
    protected function guessPivotTable(string $relatedClass): string
    {
        $a = strtolower((string) preg_replace(
            '/(?<!^)[A-Z]/', '_$0',
            (new \ReflectionClass($this))->getShortName()
        ));

        $b = strtolower((string) preg_replace(
            '/(?<!^)[A-Z]/', '_$0',
            (new \ReflectionClass($relatedClass))->getShortName()
        ));

        $tables = [$a, $b];
        sort($tables);

        return implode('_', $tables);
    }
}
