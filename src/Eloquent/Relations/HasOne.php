<?php

declare(strict_types=1);

namespace Foxdb\Eloquent\Relations;

use Foxdb\Eloquent\Model;
use Foxdb\Query\Builder;
use Foxdb\Support\Collection;

/**
 * HasOne — one-to-one relationship where the FK lives on the related table.
 *
 * Example:
 *   users.id  ←  profiles.user_id
 *
 *   class User extends Model {
 *       public function profile(): HasOne {
 *           return $this->hasOne(Profile::class);
 *           // foreign key defaults to 'user_id'
 *           // local key defaults to 'id' (parent PK)
 *       }
 *   }
 *
 *   $profile = $user->profile;          // lazy load
 *   User::with('profile')->get();        // eager load
 */
class HasOne extends Relation
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
     * @param string              $foreignKey  Column on related table
     * @param string              $localKey    Column on parent table
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

        $this->query = $this->related->newQuery()
            ->whereIn($this->foreignKey, $keys);
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
        // Index related models by their foreign key value.
        $dictionary = [];
        foreach ($results->all() as $result) {
            $fkVal = $result->getAttribute($this->foreignKey);
            $dictionary[$fkVal] = $result;
        }

        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);
            $model->setRelation($relation, $dictionary[$key] ?? null);
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
