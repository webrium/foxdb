<?php

declare(strict_types=1);

namespace Foxdb\Eloquent\Relations;

use Foxdb\Eloquent\Model;
use Foxdb\Query\Builder;
use Foxdb\Support\Collection;

/**
 * BelongsTo — inverse of HasOne/HasMany.
 * The FK lives on the *parent* (owning) model's table.
 *
 * Example:
 *   posts.user_id  →  users.id
 *
 *   class Post extends Model {
 *       public function user(): BelongsTo {
 *           return $this->belongsTo(User::class);
 *           // foreign key defaults to 'user_id'  (relation name + '_id')
 *           // owner key defaults to 'id'         (related model PK)
 *       }
 *   }
 *
 *   $author = $post->user;               // lazy → User|null
 *   Post::with('user')->get();            // eager load
 */
class BelongsTo extends Relation
{
    /**
     * The FK column on the *parent* model's table (e.g. posts.user_id).
     *
     * @var string
     */
    protected string $foreignKey;

    /**
     * The referenced key on the *related* (owner) table (e.g. users.id).
     *
     * @var string
     */
    protected string $ownerKey;

    /**
     * @param Builder             $query
     * @param Model               $parent       The model that has the FK column
     * @param class-string<Model> $relatedClass The "owner" model class
     * @param string              $foreignKey   FK column on the parent table
     * @param string              $ownerKey     Referenced key on the related table
     */
    public function __construct(
        Builder $query,
        Model   $parent,
        string  $relatedClass,
        string  $foreignKey,
        string  $ownerKey,
    ) {
        $this->foreignKey = $foreignKey;
        $this->ownerKey   = $ownerKey;

        parent::__construct($query, $parent, $relatedClass);

        $this->addConstraints();
    }

    /**
     * {@inheritdoc}
     */
    protected function addConstraints(): void
    {
        $fkValue = $this->parent->getAttribute($this->foreignKey);

        $this->query->where($this->ownerKey, $fkValue);
    }

    /**
     * {@inheritdoc}
     * For eager loading, collect all FK values from the parent models.
     */
    public function addEagerConstraints(array $models): void
    {
        $keys = $this->collectKeys($models, $this->foreignKey);

        $this->query = $this->related->newQuery()
            ->whereIn($this->ownerKey, $keys);
    }

    /**
     * {@inheritdoc}
     *
     * @return Model|null
     */
    public function getResults(): Model|null
    {
        $row = $this->query->first();

        return $row instanceof Model ? $row : null;
    }

    /**
     * {@inheritdoc}
     */
    public function match(array $models, Collection $results, string $relation): array
    {
        // Index related (owner) models by their ownerKey value.
        $dictionary = [];
        foreach ($results->all() as $result) {
            $ownerVal = $result->getAttribute($this->ownerKey);
            $dictionary[$ownerVal] = $result;
        }

        foreach ($models as $model) {
            $fkVal = $model->getAttribute($this->foreignKey);
            $model->setRelation($relation, $dictionary[$fkVal] ?? null);
        }

        return $models;
    }

    /**
     * Associate a related model with this parent (sets the FK value).
     *
     * @param  Model $model  The related (owner) model to associate
     * @return static
     */
    public function associate(Model $model): static
    {
        $this->parent->setAttribute(
            $this->foreignKey,
            $model->getAttribute($this->ownerKey),
        );

        return $this;
    }

    /**
     * Dissociate the related model (sets the FK to null).
     *
     * @return static
     */
    public function dissociate(): static
    {
        $this->parent->setAttribute($this->foreignKey, null);

        return $this;
    }

    /**
     * Get the FK column name (on the parent table).
     *
     * @return string
     */
    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    /**
     * Get the owner key column name (on the related table).
     *
     * @return string
     */
    public function getOwnerKey(): string
    {
        return $this->ownerKey;
    }
}
