<?php

namespace webrium\foxql;
use webrium\foxql\DB;

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


  protected $visible = [];


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

  

  public static function where(...$args){
    return (new static)->makeInstance('where', $args);
  }

  public static function find($id){
    $instance = (new static);
    return self::where($instance->primaryKey, $id)->first();
  }


  protected function makeInstance($name, $arguments)
  {
    $db = DB::table($this->table)->{$name}(...$arguments);

    if(count($this->visible)){
      $db->select($this->visible);
    }

    return $db;
  }


}
