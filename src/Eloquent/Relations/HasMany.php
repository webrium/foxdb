<?php

declare(strict_types=1);

namespace Foxdb\Eloquent\Relations;

use Foxdb\Eloquent\Model;
use Foxdb\Query\Builder;
use Foxdb\Support\Collection;

/**
 * HasMany — one-to-many relationship where the FK lives on the related table.
 *
 * Example:
 *   users.id  ←  posts.user_id
 *
 *   class User extends Model {
 *       public function posts(): HasMany {
 *           return $this->hasMany(Post::class);
 *           // foreign key defaults to 'user_id'
 *           // local key defaults to 'id' (parent PK)
 *       }
 *   }
 *
 *   $posts = $user->posts;               // lazy → Collection<Post>
 *   $posts = $user->posts()->where('published', 1)->get();  // constrained
 *   User::with('posts')->get();           // eager load
 */
class HasMany extends Relation
{
    /**
     * The foreign key column on the related table.
     *
     * @var string
     */
    protected string $foreignKey;

    /**
     * The local key column on the parent table (usually the PK).
     *
     * @var string
     */
    protected string $localKey;

    /**
     * @param Builder             $query
     * @param Model               $parent
     * @param class-string<Model> $relatedClass
     * @param string              $foreignKey
     * @param string              $localKey
     */
    public function __construct(
        Builder $query,
        Model   $parent,
        string  $relatedClass,
        string  $foreignKey,
        string  $localKey,
    ) {
        $this->foreignKey = $foreignKey;
        $this->localKey   = $localKey;

        parent::__construct($query, $parent, $relatedClass);

        $this->addConstraints();
    }

    /**
     * {@inheritdoc}
     */
    protected function addConstraints(): void
    {
        $this->query->where($this->foreignKey, $this->parent->getAttribute($this->localKey));
    }

    /**
     * {@inheritdoc}
     */
    public function addEagerConstraints(array $models): void
    {
        $keys = $this->collectKeys($models, $this->localKey);

        // Replace the query with a fresh one (no single-parent constraint)
        // so we can load for multiple parents at once.
        $this->query = $this->related->newQuery()
            ->whereIn($this->foreignKey, $keys);
    }

    /**
     * {@inheritdoc}
     *
     * @return Collection
     */
    public function getResults(): Collection
    {
        return $this->query->get();
    }

    /**
     * {@inheritdoc}
     */
    public function match(array $models, Collection $results, string $relation): array
    {
        // Group related models by foreign key value.
        $dictionary = [];
        foreach ($results->all() as $result) {
            $fkVal                  = $result->getAttribute($this->foreignKey);
            $dictionary[$fkVal][]   = $result;
        }

        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);
            $related = isset($dictionary[$key])
                ? new Collection($dictionary[$key])
                : new Collection();

            $model->setRelation($relation, $related);
        }

        return $models;
    }

    /**
     * Get the foreign key column name.
     *
     * @return string
     */
    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    /**
     * Get the local key column name.
     *
     * @return string
     */
    public function getLocalKey(): string
    {
        return $this->localKey;
    }
}
