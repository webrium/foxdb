<?php

declare(strict_types=1);

namespace Foxdb\Eloquent\Relations;

use Foxdb\DB;
use Foxdb\Eloquent\Model;
use Foxdb\Query\Builder;
use Foxdb\Support\Collection;

/**
 * BelongsToMany — many-to-many via an intermediate pivot table.
 *
 * Example:
 *   users ←→ role_user ←→ roles
 *
 *   class User extends Model {
 *       public function roles(): BelongsToMany {
 *           return $this->belongsToMany(
 *               Role::class,
 *               'role_user',     // pivot table
 *               'user_id',       // FK for this model on pivot
 *               'role_id',       // FK for related model on pivot
 *           );
 *       }
 *   }
 *
 *   $roles = $user->roles;                      // lazy → Collection<Role>
 *   $user->roles()->attach(1);                  // add row to pivot
 *   $user->roles()->detach(1);                  // remove from pivot
 *   $user->roles()->sync([1, 2, 3]);            // replace pivot rows
 *   $user->roles()->toggle([1, 2]);             // add if missing, remove if present
 *   User::with('roles')->get();                 // eager load
 *
 *   // Access pivot columns:
 *   foreach ($user->roles as $role) {
 *       echo $role->pivot->created_at;
 *   }
 */
class BelongsToMany extends Relation
{
    /**
     * Override get() calls forwarded via __call to apply pivot hydration.
     *
     * @return Collection
     */
    public function get(): Collection
    {
        return $this->getResults();
    }

    /**
     * The intermediate (pivot) table name.
     *
     * @var string
     */
    protected string $pivotTable;

    /**
     * FK column on the pivot table for the *parent* model.
     *
     * @var string
     */
    protected string $foreignPivotKey;

    /**
     * FK column on the pivot table for the *related* model.
     *
     * @var string
     */
    protected string $relatedPivotKey;

    /**
     * Local key on the parent model (usually its PK).
     *
     * @var string
     */
    protected string $parentKey;

    /**
     * Local key on the related model (usually its PK).
     *
     * @var string
     */
    protected string $relatedKey;

    /**
     * Extra pivot columns to select and expose on the pivot object.
     *
     * @var array<int, string>
     */
    protected array $pivotColumns = [];

    /**
     * @param Builder             $query
     * @param Model               $parent
     * @param class-string<Model> $relatedClass
     * @param string              $pivotTable
     * @param string              $foreignPivotKey  FK for parent on pivot
     * @param string              $relatedPivotKey  FK for related on pivot
     * @param string              $parentKey        PK of parent model
     * @param string              $relatedKey       PK of related model
     */
    public function __construct(
        Builder $query,
        Model   $parent,
        string  $relatedClass,
        string  $pivotTable,
        string  $foreignPivotKey,
        string  $relatedPivotKey,
        string  $parentKey,
        string  $relatedKey,
    ) {
        $this->pivotTable       = $pivotTable;
        $this->foreignPivotKey  = $foreignPivotKey;
        $this->relatedPivotKey  = $relatedPivotKey;
        $this->parentKey        = $parentKey;
        $this->relatedKey       = $relatedKey;

        parent::__construct($query, $parent, $relatedClass);

        $this->addConstraints();
    }

    /**
     * {@inheritdoc}
     */
    protected function addConstraints(): void
    {
        $relatedTable = $this->related->getTable();
        $parentId     = $this->parent->getAttribute($this->parentKey);

        $this->query
            ->join(
                $this->pivotTable,
                "{$this->pivotTable}.{$this->relatedPivotKey}",
                '=',
                "{$relatedTable}.{$this->relatedKey}",
            )
            ->where("{$this->pivotTable}.{$this->foreignPivotKey}", $parentId)
            ->select("{$relatedTable}.*", ...$this->buildPivotSelectsRaw());
    }

    /**
     * {@inheritdoc}
     */
    public function addEagerConstraints(array $models): void
    {
        $relatedTable = $this->related->getTable();
        $keys         = $this->collectKeys($models, $this->parentKey);

        // Re-build without single-parent constraint — use whereIn instead.
        $this->query = $this->related->newQuery()
            ->join(
                $this->pivotTable,
                "{$this->pivotTable}.{$this->relatedPivotKey}",
                '=',
                "{$relatedTable}.{$this->relatedKey}",
            )
            ->whereIn("{$this->pivotTable}.{$this->foreignPivotKey}", $keys)
            ->select(
                "{$relatedTable}.*",
                DB::raw("{$this->pivotTable}.{$this->foreignPivotKey} as __pivot_fk"),
                ...$this->buildPivotSelectsRaw(),
            );
    }

    /**
     * {@inheritdoc}
     *
     * @return Collection
     */
    public function getResults(): Collection
    {
        return $this->hydratePivot($this->query->get());
    }

    /**
     * {@inheritdoc}
     */
    public function match(array $models, Collection $results, string $relation): array
    {
        // Group by the __pivot_fk we added during eager loading.
        $dictionary = [];
        foreach ($results->all() as $result) {
            $fk = $result->getAttribute('__pivot_fk');
            $dictionary[$fk][] = $result;
        }

        foreach ($models as $model) {
            $key = $model->getAttribute($this->parentKey);
            $related = isset($dictionary[$key])
                ? new Collection($dictionary[$key])
                : new Collection();

            $model->setRelation($relation, $related);
        }

        return $models;
    }

    // -----------------------------------------------------------------------
    // Pivot extra columns
    // -----------------------------------------------------------------------

    /**
     * Specify additional columns from the pivot table to include.
     *
     * @param  string ...$columns
     * @return static
     */
    public function withPivot(string ...$columns): static
    {
        $this->pivotColumns = array_merge($this->pivotColumns, $columns);

        // Re-add pivot selects to the existing query so they appear in results.
        foreach ($columns as $col) {
            $this->query->addSelect(DB::raw("{$this->pivotTable}.{$col} as __pivot_{$col}"));
        }

        return $this;
    }

    // -----------------------------------------------------------------------
    // Pivot mutation methods
    // -----------------------------------------------------------------------

    /**
     * Attach one or more related model IDs to the pivot table.
     *
     * @param  int|string|array<int, int|string> $ids
     * @param  array<string, mixed>              $pivotData  Extra pivot columns
     * @return void
     */
    public function attach(int|string|array $ids, array $pivotData = []): void
    {
        $ids = (array) $ids;

        foreach ($ids as $id) {
            $row = array_merge(
                [
                    $this->foreignPivotKey => $this->parent->getAttribute($this->parentKey),
                    $this->relatedPivotKey => $id,
                ],
                $pivotData,
            );

            DB::table($this->pivotTable)->insert($row);
        }
    }

    /**
     * Detach one or more related model IDs from the pivot table.
     * Passing nothing detaches all.
     *
     * @param  int|string|array<int, int|string>|null $ids
     * @return int  Number of rows removed
     */
    public function detach(int|string|array|null $ids = null): int
    {
        $query = DB::table($this->pivotTable)
            ->where($this->foreignPivotKey, $this->parent->getAttribute($this->parentKey));

        if ($ids !== null) {
            $query->whereIn($this->relatedPivotKey, (array) $ids);
        }

        return $query->delete();
    }

    /**
     * Sync the pivot table to exactly match the given ID list.
     * Adds missing rows, removes extra rows, leaves existing rows untouched.
     *
     * @param  array<int, int|string>       $ids
     * @param  array<string, mixed>         $pivotData  Applied to newly attached rows
     * @return array{attached: array, detached: array}
     */
    public function sync(array $ids, array $pivotData = []): array
    {
        $parentId = $this->parent->getAttribute($this->parentKey);

        // Current IDs in pivot for this parent.
        $current = DB::table($this->pivotTable)
            ->where($this->foreignPivotKey, $parentId)
            ->pluck($this->relatedPivotKey);

        $current = array_map('strval', $current);
        $ids     = array_map('strval', $ids);

        $toAttach = array_diff($ids, $current);
        $toDetach = array_diff($current, $ids);

        if (! empty($toDetach)) {
            $this->detach($toDetach);
        }

        if (! empty($toAttach)) {
            $this->attach($toAttach, $pivotData);
        }

        return [
            'attached' => array_values($toAttach),
            'detached' => array_values($toDetach),
        ];
    }

    /**
     * Toggle IDs in the pivot table — attach if missing, detach if present.
     *
     * @param  array<int, int|string> $ids
     * @return array{attached: array, detached: array}
     */
    public function toggle(array $ids): array
    {
        $parentId = $this->parent->getAttribute($this->parentKey);

        $current = DB::table($this->pivotTable)
            ->where($this->foreignPivotKey, $parentId)
            ->pluck($this->relatedPivotKey);

        $current = array_map('strval', $current);
        $ids     = array_map('strval', $ids);

        $toAttach = array_diff($ids, $current);
        $toDetach = array_intersect($ids, $current);

        if (! empty($toDetach)) {
            $this->detach($toDetach);
        }

        if (! empty($toAttach)) {
            $this->attach($toAttach);
        }

        return [
            'attached' => array_values($toAttach),
            'detached' => array_values($toDetach),
        ];
    }

    /**
     * Determine whether a specific ID is currently attached.
     *
     * @param  int|string $id
     * @return bool
     */
    public function isAttached(int|string $id): bool
    {
        return DB::table($this->pivotTable)
            ->where($this->foreignPivotKey, $this->parent->getAttribute($this->parentKey))
            ->where($this->relatedPivotKey, $id)
            ->exists();
    }

    /**
     * Update pivot columns for an already-attached related ID.
     *
     * @param  int|string           $id
     * @param  array<string, mixed> $attributes
     * @return int
     */
    public function updateExistingPivot(int|string $id, array $attributes): int
    {
        return DB::table($this->pivotTable)
            ->where($this->foreignPivotKey, $this->parent->getAttribute($this->parentKey))
            ->where($this->relatedPivotKey, $id)
            ->update($attributes);
    }

    // -----------------------------------------------------------------------
    // Internal helpers
    // -----------------------------------------------------------------------

    /**
     * Build the SELECT expressions for pivot columns.
     *
     * @return array<int, string>
     */
    protected function buildPivotSelects(): array
    {
        $selects = [];

        foreach ($this->pivotColumns as $col) {
            $selects[] = "{$this->pivotTable}.{$col} as __pivot_{$col}";
        }

        return $selects;
    }

    /**
     * Build RawExpression SELECT items for pivot columns (avoids dot-quoting issues).
     *
     * @return array<int, \Foxdb\Query\RawExpression>
     */
    protected function buildPivotSelectsRaw(): array
    {
        $selects = [];

        foreach ($this->pivotColumns as $col) {
            $selects[] = DB::raw("{$this->pivotTable}.{$col} as __pivot_{$col}");
        }

        return $selects;
    }

    /**
     * Extract pivot columns from each result and attach as a 'pivot' object.
     *
     * @param  Collection $results
     * @return Collection
     */
    protected function hydratePivot(Collection $results): Collection
    {
        if (empty($this->pivotColumns)) {
            return $results;
        }

        return new Collection(array_map(function (object $item) {
            $pivot = [];

            foreach ($this->pivotColumns as $col) {
                $pivotKey = "__pivot_{$col}";

                // Support both stdClass and Model instances.
                if ($item instanceof \Foxdb\Eloquent\Model) {
                    $val = $item->getAttribute($pivotKey);
                    if ($val !== null) {
                        $pivot[$col] = $val;
                        unset($item->attributes[$pivotKey]);
                    }
                } elseif (property_exists($item, $pivotKey)) {
                    $pivot[$col] = $item->$pivotKey;
                    unset($item->$pivotKey);
                }
            }

            // Set pivot on Model via forceFill or as a plain property.
            if ($item instanceof \Foxdb\Eloquent\Model) {
                $item->forceFill(['pivot' => (object) $pivot]);
            } else {
                $item->pivot = (object) $pivot;
            }

            return $item;
        }, $results->all()));
    }

    /**
     * Get the pivot table name.
     *
     * @return string
     */
    public function getPivotTable(): string
    {
        return $this->pivotTable;
    }
}
