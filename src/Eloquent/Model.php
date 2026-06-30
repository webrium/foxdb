<?php

declare(strict_types=1);

namespace Foxdb\Eloquent;

use Foxdb\DB;
use Foxdb\Eloquent\Concerns\HasCasts;
use Foxdb\Eloquent\Concerns\HasRelations;
use Foxdb\Eloquent\Concerns\HasSoftDeletes;
use Foxdb\Eloquent\Concerns\HasTimestamps;
use Foxdb\Exceptions\ModelNotFoundException;
use Foxdb\Query\Builder;
use Foxdb\Support\Collection;
use Foxdb\Eloquent\EagerBuilder;

/**
 * Base Model — Eloquent-style Active Record for FoxDB.
 *
 * Quick start:
 *
 *   class User extends Model
 *   {
 *       protected string $table      = 'users';
 *       protected array  $fillable   = ['name', 'email', 'age'];
 *       protected array  $hidden     = ['password'];
 *       protected array  $casts      = ['is_active' => 'bool', 'settings' => 'array'];
 *   }
 *
 *   // Queries
 *   $users  = User::where('active', 1)->orderBy('name')->get();
 *   $user   = User::find(1);
 *   $user   = User::findOrFail(1);
 *   $users  = User::all();
 *   $user   = User::create(['name' => 'Ali', 'email' => 'a@b.com']);
 *
 *   // Persistence
 *   $user->name = 'New Name';
 *   $user->save();
 *   $user->delete();
 *
 *   // Soft deletes (add `use HasSoftDeletes;`)
 *   $user->delete();                    // sets deleted_at
 *   User::withTrashed()->find(1);
 *   User::onlyTrashed()->get();
 *   $user->restore();
 *
 * ---------------------------------------------------------------------------
 * IDE method completion — forwarded via __callStatic to Builder
 * ---------------------------------------------------------------------------
 *
 * SELECT
 * @method static ModelBuilder select(string|RawExpression ...$columns)
 * @method static ModelBuilder addSelect(string|RawExpression ...$columns)
 * @method static ModelBuilder selectRaw(string $expression, array $bindings = [])
 * @method static ModelBuilder distinct()
 *
 * EAGER LOADING
 * @method static EagerBuilder with(string|array ...$relations)
 *
 * WHERE
 * @method static ModelBuilder orWhere(string|\Closure $column, mixed $operatorOrValue = null, mixed $value = null)
 * @method static ModelBuilder whereNot(string $column, mixed $operatorOrValue, mixed $value = null)
 * @method static ModelBuilder whereRaw(string $expression, array $bindings = [])
 * @method static ModelBuilder orWhereRaw(string $expression, array $bindings = [])
 * @method static ModelBuilder whereIn(string $column, array $values)
 * @method static ModelBuilder whereNotIn(string $column, array $values)
 * @method static ModelBuilder orWhereIn(string $column, array $values)
 * @method static ModelBuilder orWhereNotIn(string $column, array $values)
 * @method static ModelBuilder whereBetween(string $column, mixed $min, mixed $max)
 * @method static ModelBuilder whereNotBetween(string $column, mixed $min, mixed $max)
 * @method static ModelBuilder whereNull(string $column)
 * @method static ModelBuilder whereNotNull(string $column)
 * @method static ModelBuilder orWhereNull(string $column)
 * @method static ModelBuilder orWhereNotNull(string $column)
 * @method static ModelBuilder whereColumn(string $first, string $operator, string $second)
 * @method static ModelBuilder whereExists(callable|Builder $subquery)
 * @method static ModelBuilder whereNotExists(callable|Builder $subquery)
 * @method static ModelBuilder whereDate(string $column, string $operator, mixed $value)
 * @method static ModelBuilder whereMonth(string $column, string $operator, mixed $value)
 * @method static ModelBuilder whereDay(string $column, string $operator, mixed $value)
 * @method static ModelBuilder whereYear(string $column, string $operator, mixed $value)
 * @method static ModelBuilder whereTime(string $column, string $operator, mixed $value)
 *
 * WHERE shorthands (v1 compatibility)
 * @method static ModelBuilder is(string $column, mixed $value)
 * @method static ModelBuilder true(string $column)
 * @method static ModelBuilder false(string $column)
 * @method static ModelBuilder like(string $column, string $value)
 * @method static ModelBuilder orLike(string $column, string $value)
 * @method static ModelBuilder null(string $column)
 * @method static ModelBuilder notNull(string $column)
 * @method static ModelBuilder in(string $column, array $values)
 * @method static ModelBuilder notIn(string $column, array $values)
 *
 * JOIN
 * @method static ModelBuilder join(string $table, string $first, string $operator, string $second)
 * @method static ModelBuilder leftJoin(string $table, string $first, string $operator, string $second)
 * @method static ModelBuilder rightJoin(string $table, string $first, string $operator, string $second)
 * @method static ModelBuilder crossJoin(string $table)
 * @method static ModelBuilder joinSub(Builder $query, string $alias, string $first, string $operator, string $second)
 * @method static ModelBuilder joinRaw(string $expression)
 *
 * GROUP BY / HAVING
 * @method static ModelBuilder groupBy(string ...$columns)
 * @method static ModelBuilder having(string $column, string $operator, mixed $value)
 * @method static ModelBuilder orHaving(string $column, string $operator, mixed $value)
 * @method static ModelBuilder havingRaw(string $expression, array $bindings = [])
 *
 * ORDER / LIMIT / OFFSET
 * @method static ModelBuilder orderBy(string $column, string $direction = 'asc')
 * @method static ModelBuilder orderByDesc(string $column)
 * @method static ModelBuilder orderByRaw(string $expression)
 * @method static ModelBuilder latest(string $column = 'created_at')
 * @method static ModelBuilder oldest(string $column = 'created_at')
 * @method static ModelBuilder inRandomOrder()
 * @method static ModelBuilder reorder()
 * @method static ModelBuilder limit(int $value)
 * @method static ModelBuilder take(int $value)
 * @method static ModelBuilder offset(int $value)
 * @method static ModelBuilder skip(int $value)
 *
 * EXECUTE / FETCH
 * @method static Collection          get()
 * @method static static|null         first()
 * @method static mixed               value(string $column)
 * @method static array               pluck(string $column, string|null $keyColumn = null)
 * @method static void                chunk(int $size, callable $callback)
 * @method static void                each(callable $callback)
 * @method static object              paginate(int $perPage = 15, int $page = 1)
 *
 * AGGREGATES
 * @method static int                 count(string $column = '*')
 * @method static float|int           sum(string $column)
 * @method static float|int           avg(string $column)
 * @method static float|int           min(string $column)
 * @method static float|int           max(string $column)
 * @method static bool                doesntExist()
 *
 * WRITE
 * @method static bool                insert(array $values)
 * @method static int|string          insertGetId(array $values)
 * @method static bool                insertBatch(array $rows)
 * @method static int                 update(array $values)
 * @method static int                 delete()
 * @method static bool                truncate()
 * @method static int                 increment(string $column, int|float $amount = 1, array $extra = [])
 * @method static int                 decrement(string $column, int|float $amount = 1, array $extra = [])
 * @method static bool                updateOrInsert(array $conditions, array $values = [])
 *
 * DEBUG
 * @method static ModelBuilder        dump()
 * @method static never               dd()
 * @method static string              toSql()
 * @method static array               getBindings()
 */
abstract class Model implements \JsonSerializable
{
    use HasTimestamps;
    use HasCasts;
    use HasRelations;

    // -----------------------------------------------------------------------
    // Model configuration (override in subclass)
    // -----------------------------------------------------------------------

    /**
     * The database table name.
     * Auto-derived from the class name (snake_case + plural) if not set.
     *
     * @var string
     */
    protected string $table = '';

    /**
     * The primary key column name.
     *
     * @var string
     */
    protected string $primaryKey = 'id';

    /**
     * Columns that are mass-assignable via fill() / create().
     * Empty means nothing is mass-assignable (use $guarded = [] to allow all).
     *
     * @var array<int, string>
     */
    protected array $fillable = [];

    /**
     * Columns that are NOT mass-assignable.
     * ['*'] means nothing is allowed (default).
     * [] means everything is allowed.
     *
     * @var array<int, string>
     */
    protected array $guarded = ['*'];

    /**
     * Columns to hide from toArray() / toJson() output (e.g. 'password').
     *
     * @var array<int, string>
     */
    protected array $hidden = [];

    /**
     * The named database connection to use. Null = default connection.
     *
     * @var string|null
     */
    protected ?string $connection = null;

    // -----------------------------------------------------------------------
    // Runtime state
    // -----------------------------------------------------------------------

    /**
     * Raw column values from the database (or set via fill/constructor).
     *
     * @var array<string, mixed>
     */
    protected array $attributes = [];

    /**
     * Attribute values at the time the model was last fetched/saved.
     * Used to detect "dirty" (changed) attributes.
     *
     * @var array<string, mixed>
     */
    protected array $original = [];

    /**
     * Whether this model instance has been persisted (exists in the DB).
     *
     * @var bool
     */
    protected bool $exists = false;

    // -----------------------------------------------------------------------
    // Constructor
    // -----------------------------------------------------------------------

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    // -----------------------------------------------------------------------
    // Table name resolution
    // -----------------------------------------------------------------------

    /**
     * Get the table name for this model.
     * If $table is not set, auto-derive from the short class name:
     *   UserProfile → user_profiles
     *
     * @return string
     */
    public function getTable(): string
    {
        if ($this->table !== '') {
            return $this->table;
        }

        // Convert CamelCase class name to snake_case plural.
        $class = (new \ReflectionClass($this))->getShortName();
        $snake = (string) preg_replace('/(?<!^)[A-Z]/', '_$0', $class);

        return strtolower($snake) . 's';
    }

    /**
     * Get the primary key column name.
     *
     * @return string
     */
    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    /**
     * Get the primary key value of this instance.
     *
     * @return mixed
     */
    public function getKey(): mixed
    {
        return $this->attributes[$this->primaryKey] ?? null;
    }

    /**
     * Determine whether this model instance exists in the database.
     * Use this instead of directly accessing $exists.
     *
     * @return bool
     */
    public function isExists(): bool
    {
        return $this->exists;
    }

    /**
     * Mark the model as existing (persisted in DB).
     * Called internally after successful INSERT.
     * Not intended for external use.
     *
     * @internal
     * @return void
     */
    protected function markAsExisting(): void
    {
        $this->exists = true;
    }

    // -----------------------------------------------------------------------
    // Attribute access
    // -----------------------------------------------------------------------

    /**
     * Get an attribute value, applying any declared cast.
     *
     * @param  string $key
     * @return mixed
     */
    public function getAttribute(string $key): mixed
    {
        $value = $this->attributes[$key] ?? null;

        return $this->castValue($key, $value);
    }

    /**
     * Set a raw attribute value (no cast applied on write — cast happens on read).
     *
     * @param  string $key
     * @param  mixed  $value
     * @return void
     */
    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Get all attributes, applying casts.
     *
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->castAttributes($this->attributes);
    }

    /**
     * Determine whether an attribute has changed since last sync.
     *
     * @param  string|null $key  Null = check any attribute
     * @return bool
     */
    public function isDirty(?string $key = null): bool
    {
        if ($key !== null) {
            return ($this->attributes[$key] ?? null) !== ($this->original[$key] ?? null);
        }

        return $this->attributes !== $this->original;
    }

    /**
     * Get only the attributes that have changed since last sync.
     *
     * @return array<string, mixed>
     */
    public function getDirty(): array
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if ($value !== ($this->original[$key] ?? null)) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    /**
     * Sync the original attributes to the current state.
     *
     * @return void
     */
    public function syncOriginal(): void
    {
        $this->original = $this->attributes;
    }

    // -----------------------------------------------------------------------
    // Mass assignment
    // -----------------------------------------------------------------------

    /**
     * Fill the model with an array of attributes.
     * Respects $fillable / $guarded guards.
     *
     * @param  array<string, mixed> $attributes
     * @return static
     */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->attributes[$key] = $value;
            }
        }

        return $this;
    }

    /**
     * Force-fill attributes, bypassing $fillable / $guarded guards.
     *
     * @param  array<string, mixed> $attributes
     * @return static
     */
    public function forceFill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
        }

        return $this;
    }

    /**
     * Determine whether a given attribute key is mass-assignable.
     *
     * @param  string $key
     * @return bool
     */
    public function isFillable(string $key): bool
    {
        // If $guarded = [] everything is allowed.
        if (empty($this->guarded)) {
            return true;
        }

        // If $guarded = ['*'] nothing is allowed unless explicitly in $fillable.
        if (in_array('*', $this->guarded, strict: true)) {
            return in_array($key, $this->fillable, strict: true);
        }

        // Key is explicitly guarded.
        if (in_array($key, $this->guarded, strict: true)) {
            return false;
        }

        // $fillable non-empty — key must be listed.
        if (! empty($this->fillable)) {
            return in_array($key, $this->fillable, strict: true);
        }

        return true;
    }

    // -----------------------------------------------------------------------
    // Persistence
    // -----------------------------------------------------------------------

    /**
     * Save the model to the database.
     * Performs INSERT when $exists is false, UPDATE otherwise.
     * Only dirty attributes are included in UPDATE.
     *
     * @return bool
     */
    public function save(): bool
    {
        if ($this->exists) {
            return $this->performUpdate();
        }

        return $this->performInsert();
    }

    /**
     * Perform an INSERT and mark the model as existing.
     *
     * @return bool
     */
    protected function performInsert(): bool
    {
        $attributes = $this->addTimestampsForInsert($this->attributes);
        $attributes = $this->castAttributesForStorage($attributes);

        $id = $this->newModelQuery()->insertGetId($attributes);

        if ($id) {
            $this->attributes[$this->primaryKey] = $id;
            $this->attributes = array_merge(
                $this->attributes,
                $this->addTimestampsForInsert([]),
            );
            $this->exists = true;
            $this->syncOriginal();

            return true;
        }

        return false;
    }

    /**
     * Perform an UPDATE for dirty attributes only.
     * Returns true immediately if nothing has changed.
     *
     * @return bool
     */
    protected function performUpdate(): bool
    {
        $dirty = $this->getDirty();

        if (empty($dirty)) {
            return true;
        }

        $dirty = $this->addTimestampsForUpdate($dirty);
        $dirty = $this->castAttributesForStorage($dirty);

        $affected = $this->newModelQuery()
            ->where($this->primaryKey, $this->getKey())
            ->update($dirty);

        if ($affected >= 0) {
            // Sync timestamps back into attributes.
            foreach ($dirty as $k => $v) {
                $this->attributes[$k] = $v;
            }
            $this->syncOriginal();

            return true;
        }

        return false;
    }

    /**
     * Delete the model from the database.
     * If HasSoftDeletes is in use, sets deleted_at instead of removing the row.
     *
     * @return bool
     */
    public function delete(): bool
    {
        // Delegate to soft-delete if the trait is loaded.
        if ($this->usesSoftDeletes()) {
            /** @var HasSoftDeletes $this */
            return $this->softDelete();
        }

        $affected = $this->newModelQuery()
            ->where($this->primaryKey, $this->getKey())
            ->delete();

        if ($affected > 0) {
            $this->exists = false;

            return true;
        }

        return false;
    }

    /**
     * Re-fetch the model from the database and return a fresh instance.
     *
     * @return static|null
     */
    public function fresh(): ?static
    {
        if (! $this->exists) {
            return null;
        }

        return static::find($this->getKey());
    }

    /**
     * Re-fetch and update the current model instance in place.
     *
     * @return static
     */
    public function refresh(): static
    {
        $fresh = $this->fresh();

        if ($fresh !== null) {
            $this->attributes = $fresh->attributes;
            $this->syncOriginal();
        }

        return $this;
    }

    // -----------------------------------------------------------------------
    // Static query entry points
    // -----------------------------------------------------------------------

    /**
     * Get a new ModelBuilder for this model's table, with the soft-delete scope applied.
     * ModelBuilder wraps the raw Builder and exposes correct return types for IDE support.
     *
     * @return ModelBuilder<static>
     */
    public static function query(): ModelBuilder
    {
        return new ModelBuilder((new static())->newQuery(), static::class);
    }

    /**
     * Eager-load the given relations on a query.
     *
     * Usage:
     *   User::with('posts', 'profile')->get()
     *   User::with(['posts' => fn($q) => $q->where('published', 1)])->get()
     *
     * @param  string|array<string|int, string|callable> ...$relations
     * @return EagerBuilder
     */
    public static function with(string|array ...$relations): EagerBuilder
    {
        // Normalise: accept both with('a','b') and with(['a','b']) and with(['a'=>fn])
        $withs = [];
        foreach ($relations as $rel) {
            if (is_array($rel)) {
                foreach ($rel as $k => $v) {
                    if (is_int($k)) {
                        $withs[$v] = null;
                    } else {
                        $withs[$k] = $v;
                    }
                }
            } else {
                $withs[$rel] = null;
            }
        }

        return new EagerBuilder((new static())->newQuery(), static::class, $withs);
    }

    /**
     * Return all rows as a Collection of model instances.
     *
     * @return Collection<int, static>
     */
    public static function all(): Collection
    {
        return static::query()->get();
    }

    /**
     * Find a model by its primary key. Returns null if not found.
     *
     * @param  int|string $id
     * @return static|null
     */
    public static function find(int|string $id): ?static
    {
        $instance = new static();

        $row = $instance->newQuery()
            ->where($instance->primaryKey, $id)
            ->first();

        return $row instanceof static ? $row : null;
    }

    /**
     * Find a model by its primary key, or throw ModelNotFoundException.
     *
     * @param  int|string $id
     * @return static
     *
     * @throws ModelNotFoundException
     */
    public static function findOrFail(int|string $id): static
    {
        $model = static::find($id);

        if ($model === null) {
            throw new ModelNotFoundException(static::class, $id);
        }

        return $model;
    }

    /**
     * Create and persist a new model instance.
     *
     * @param  array<string, mixed> $attributes
     * @return static
     */
    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save();

        return $model;
    }

    /**
     * Begin a fluent WHERE query on the model's table.
     *
     * @param  string|\Closure $column
     * @param  mixed           $operatorOrValue
     * @param  mixed           $value
     * @return ModelBuilder<static>
     */
    public static function where(
        string|\Closure $column,
        mixed $operatorOrValue = null,
        mixed $value = null,
    ): ModelBuilder {
        return static::query()->where($column, $operatorOrValue, $value);
    }

    /**
     * Get the first model matching a column condition, or null.
     *
     * @param  string $column
     * @param  mixed  $operatorOrValue
     * @param  mixed  $value
     * @return static|null
     */
    public static function firstWhere(
        string $column,
        mixed $operatorOrValue,
        mixed $value = null,
    ): ?static {
        $result = static::where($column, $operatorOrValue, $value)->first();

        return $result instanceof static ? $result : null;
    }

    /**
     * Determine whether any row matches the given conditions.
     *
     * @param  array<string, mixed> $conditions
     * @return bool
     */
    public static function exists(array $conditions = []): bool
    {
        $query = static::query();

        foreach ($conditions as $col => $val) {
            $query->where($col, $val);
        }

        return $query->exists();
    }

    // -----------------------------------------------------------------------
    // Builder factory
    // -----------------------------------------------------------------------

    /**
     * Get a new Builder scoped to this model's table and connection.
     * Applies the soft-delete scope when HasSoftDeletes is in use.
     *
     * @return Builder
     */
    public function newQuery(): Builder
    {
        $builder = DB::table($this->getTable(), $this->connection)
            ->setPrimaryKey($this->primaryKey)
            ->setHydrator(fn(object $row) => static::fromRow($row));

        // Apply soft-delete scope when HasSoftDeletes trait is in use.
        // applySoftDeleteScope() is public on the trait so IDE and PHP
        // can both resolve it without ambiguity.
        if ($this->usesSoftDeletes()) {
            $softDelete = $this; // $this implements HasSoftDeletes when trait is used
            assert(method_exists($softDelete, 'applySoftDeleteScope'));
            $builder = $softDelete->applySoftDeleteScope($builder);
        }

        return $builder;
    }

    /**
     * Get a plain Builder without the soft-delete scope (for internal use).
     *
     * @return Builder
     */
    protected function newModelQuery(): Builder
    {
        return DB::table($this->getTable(), $this->connection)
            ->setPrimaryKey($this->primaryKey);
    }

    // -----------------------------------------------------------------------
    // Hydration — build a Model instance from a plain stdClass row
    // -----------------------------------------------------------------------

    /**
     * Hydrate a model instance from a raw database row object.
     *
     * @param  object $row
     * @return static
     */
    public static function fromRow(object $row): static
    {
        $model = new static();
        $model->attributes = (array) $row;
        $model->syncOriginal();
        $model->exists = true;

        return $model;
    }

    /**
     * Hydrate a Collection of model instances from raw rows.
     *
     * @param  Collection $rows
     * @return Collection<int, static>
     */
    public static function hydrate(Collection $rows): Collection
    {
        return new Collection(
            array_map(
                fn(object $row) => static::fromRow($row),
                $rows->all(),
            )
        );
    }

    // -----------------------------------------------------------------------
    // Local Scopes
    // -----------------------------------------------------------------------

    /**
     * Apply a named local scope to a Builder.
     * Scopes are defined as scopeXxx(Builder $query): Builder on the model.
     *
     * Usage:
     *   class User extends Model {
     *       public function scopeActive(Builder $q): Builder {
     *           return $q->where('active', 1);
     *       }
     *   }
     *   User::active()->get()
     *
     * @param  string  $name       Scope name (without 'scope' prefix)
     * @param  mixed[] $parameters Extra arguments passed to the scope method
     * @return Builder
     */
    public static function __callStatic(string $name, array $parameters): mixed
    {
        $instance = new static();
        $scope    = 'scope' . ucfirst($name);

        if (method_exists($instance, $scope)) {
            $query = $instance->newQuery();
            return $instance->$scope($query, ...$parameters);
        }

        // Forward to ModelBuilder (select, where, orderBy, limit, etc.) so
        // every static entry point — not just the hand-written where() —
        // returns a model-aware builder. Without this, calls like
        // Model::select(...) leaked the raw Query\Builder, which knows
        // nothing about relations and breaks with() being chained after it.
        return static::query()->$name(...$parameters);
    }

    // -----------------------------------------------------------------------
    // Conversion
    // -----------------------------------------------------------------------

    /**
     * Convert the model to an associative array.
     *
     * - Casts are applied to all attribute values.
     * - Columns listed in $hidden are removed.
     * - Loaded relations are included recursively:
     *     Collection → array of toArray() results
     *     Model      → toArray() result
     *     null       → null
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $attrs = $this->getAttributes();

        // Remove hidden columns.
        foreach ($this->hidden as $key) {
            unset($attrs[$key]);
        }

        // Append any loaded (eager or lazy cached) relations.
        foreach ($this->relations as $name => $value) {
            if ($value instanceof Collection) {
                $attrs[$name] = array_map(
                    fn(object $item) => $item instanceof self ? $item->toArray() : (array) $item,
                    $value->all(),
                );
            } elseif ($value instanceof self) {
                $attrs[$name] = $value->toArray();
            } else {
                $attrs[$name] = $value; // null or primitive
            }
        }

        return $attrs;
    }

    /**
     * Serialize the model to a JSON string.
     *
     * @param  int $flags  json_encode flags
     * @return string
     */
    public function toJson(int $flags = JSON_UNESCAPED_UNICODE): string
    {
        return (string) json_encode($this->toArray(), $flags);
    }

    /**
     * Implement JsonSerializable so json_encode($model) works correctly.
     *
     * Without this, json_encode() on a Model produces {} because PHP
     * serializes only public properties — and Model has none.
     * With this, json_encode($model) is identical to $model->toJson().
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    // -----------------------------------------------------------------------
    // Magic property access
    // -----------------------------------------------------------------------

    /**
     * @param  string $key
     * @return mixed
     */
    public function __get(string $key): mixed
    {
        // Check raw attributes first.
        if (array_key_exists($key, $this->attributes)) {
            return $this->getAttribute($key);
        }

        // Check loaded relation cache.
        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        // Lazy-load relation if a method exists.
        if (method_exists($this, $key)) {
            $relation = $this->$key();
            if ($relation instanceof \Foxdb\Eloquent\Relations\Relation) {
                $result = $relation->getResults();
                $this->setRelation($key, $result);
                return $result;
            }
        }

        return $this->getAttribute($key);
    }

    /**
     * @param  string $key
     * @param  mixed  $value
     * @return void
     */
    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    /**
     * @param  string $key
     * @return bool
     */
    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]) || array_key_exists($key, $this->relations);
    }

    /**
     * @param  string $key
     * @return void
     */
    public function __unset(string $key): void
    {
        unset($this->attributes[$key]);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->toJson();
    }

    // -----------------------------------------------------------------------
    // Internal helpers
    // -----------------------------------------------------------------------

    /**
     * Per-class cache for usesSoftDeletes() result.
     * Keyed by class name — computed once per class, not per instance.
     *
     * @var array<class-string, bool>
     */
    private static array $softDeletesCache = [];

    /**
     * Determine whether HasSoftDeletes is active on this model.
     *
     * Walks the full class hierarchy (class + all parents) and collects
     * every trait used at any level, so inherited soft-delete works:
     *
     *   class BaseModel extends Model { use HasSoftDeletes; }
     *   class Post extends BaseModel {}   // <- Post correctly detected
     *
     * Result is cached per class to avoid repeated Reflection calls.
     *
     * @return bool
     */
    protected function usesSoftDeletes(): bool
    {
        $class = static::class;

        if (! isset(self::$softDeletesCache[$class])) {
            self::$softDeletesCache[$class] = in_array(
                HasSoftDeletes::class,
                self::classUsesRecursive($class),
                strict: true,
            );
        }

        return self::$softDeletesCache[$class];
    }

    /**
     * Collect all traits used by a class and its entire parent chain.
     *
     * Unlike ReflectionClass::getTraits(), which only returns traits declared
     * directly on the given class, this method walks every ancestor so that a
     * trait applied on a parent (or grandparent) is always visible.
     *
     * @param  class-string $class
     * @return array<int, class-string>
     */
    private static function classUsesRecursive(string $class): array
    {
        $traits = [];

        foreach (class_parents($class) + [$class => $class] as $c) {
            foreach (array_keys((new \ReflectionClass($c))->getTraits()) as $trait) {
                $traits[] = $trait;
            }
        }

        return array_values(array_unique($traits));
    }
}