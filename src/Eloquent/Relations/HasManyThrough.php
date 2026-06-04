<?php

declare(strict_types=1);

namespace Foxdb\Eloquent\Relations;

use Foxdb\DB;
use Foxdb\Eloquent\Model;
use Foxdb\Query\Builder;
use Foxdb\Support\Collection;

/**
 * HasManyThrough — access a distant relation via an intermediate model.
 *
 * Example:
 *   Country → has many Users → has many Posts
 *   A Country has many Posts through Users.
 *
 *   countries.id  ←  users.country_id  ←  posts.user_id
 *
 *   class Country extends Model {
 *       public function posts(): HasManyThrough {
 *           return $this->hasManyThrough(
 *               Post::class,      // final related model
 *               User::class,      // intermediate model
 *               'country_id',     // FK on intermediate table (users)
 *               'user_id',        // FK on final table (posts)
 *               'id',             // local key on parent (countries)
 *               'id',             // local key on intermediate (users)
 *           );
 *       }
 *   }
 *
 *   $posts = $country->posts;      // Collection<Post>
 */
class HasManyThrough extends Relation
{
    /**
     * The intermediate model instance.
     *
     * @var Model
     */
    protected Model $through;

    /**
     * FK on the intermediate table pointing to the parent.
     *
     * @var string
     */
    protected string $firstKey;

    /**
     * FK on the related (final) table pointing to the intermediate.
     *
     * @var string
     */
    protected string $secondKey;

    /**
     * Local key on the parent model.
     *
     * @var string
     */
    protected string $localKey;

    /**
     * Local key on the intermediate model.
     *
     * @var string
     */
    protected string $secondLocalKey;

    /**
     * @param Builder               $query
     * @param Model                 $parent
     * @param class-string<Model>   $relatedClass     Final related model
     * @param class-string<Model>   $throughClass     Intermediate model
     * @param string                $firstKey         FK on intermediate → parent
     * @param string                $secondKey        FK on related → intermediate
     * @param string                $localKey         PK of parent
     * @param string                $secondLocalKey   PK of intermediate
     */
    public function __construct(
        Builder $query,
        Model   $parent,
        string  $relatedClass,
        string  $throughClass,
        string  $firstKey,
        string  $secondKey,
        string  $localKey,
        string  $secondLocalKey,
    ) {
        $this->through        = new $throughClass();
        $this->firstKey       = $firstKey;
        $this->secondKey      = $secondKey;
        $this->localKey       = $localKey;
        $this->secondLocalKey = $secondLocalKey;

        parent::__construct($query, $parent, $relatedClass);

        $this->addConstraints();
    }

    /**
     * {@inheritdoc}
     */
    protected function addConstraints(): void
    {
        $throughTable = $this->through->getTable();
        $relatedTable = $this->related->getTable();

        $this->query
            ->join(
                $throughTable,
                "{$throughTable}.{$this->secondLocalKey}",
                '=',
                "{$relatedTable}.{$this->secondKey}",
            )
            ->where(
                "{$throughTable}.{$this->firstKey}",
                $this->parent->getAttribute($this->localKey),
            )
            ->select("{$relatedTable}.*");
    }

    /**
     * {@inheritdoc}
     */
    public function addEagerConstraints(array $models): void
    {
        $throughTable = $this->through->getTable();
        $relatedTable = $this->related->getTable();
        $keys         = $this->collectKeys($models, $this->localKey);

        $this->query = $this->related->newQuery()
            ->join(
                $throughTable,
                "{$throughTable}.{$this->secondLocalKey}",
                '=',
                "{$relatedTable}.{$this->secondKey}",
            )
            ->whereIn("{$throughTable}.{$this->firstKey}", $keys)
            ->select(
                "{$relatedTable}.*",
                DB::raw("{$throughTable}.{$this->firstKey} as __through_fk"),
            );
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
        $dictionary = [];
        foreach ($results->all() as $result) {
            $key               = $result->getAttribute('__through_fk');
            $dictionary[$key][] = $result;
        }

        foreach ($models as $model) {
            $key     = $model->getAttribute($this->localKey);
            $related = isset($dictionary[$key])
                ? new Collection($dictionary[$key])
                : new Collection();

            $model->setRelation($relation, $related);
        }

        return $models;
    }
}
