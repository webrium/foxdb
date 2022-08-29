<?php

namespace webrium\foxql;


class Model
{

  /**
   * The table associated with the model.
   *
   * @var string
   */
  protected $table;


  /**
   * The primary key for the model.
   *
   * @var string
   */
  protected $primaryKey = 'id';

  /**
   * Indicates if the IDs are auto-incrementing.
   *
   * @var bool
   */
  public $incrementing = true;

  /**
   * The relations to eager load on every query.
   *
   * @var array
   */
  protected $with = [];


  /**
   * The name of the "created at" column.
   *
   * @var string
   */
  const CREATED_AT = 'created_at';

  /**
   * The name of the "updated at" column.
   *
   * @var string
   */
  const UPDATED_AT = 'updated_at';

  


  public function __call($name, $arguments)
  {
  }


  protected static function __callStatic($name, $arguments)
  {
    return (new static)->makeInstance($name, $arguments);
  }


  private function makeInstance($name, $arguments)
  {
    $builder = new Builder;
    $builder->setTable($this->table);
    return $builder->{$name}(...$arguments);
  }
}
