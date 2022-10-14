<?php

namespace Foxdb;

use Foxdb\DB;

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
   * The type of primary key.
   *
   * @var string
   */
  protected $keyType = 'int';


  /**
   * The AUTO_INCREMENT tag for primary key.
   *
   * @var bool
   */
  protected $increment = true';


  /**
   * The attributes that should be visible in arrays.
   *
   * @var array
   */
  protected $visible = [];


  /**
   * Indicates if the model should be timestamped.
   *
   * @var bool
   */
  protected $timestamps = true;


  /**
   * The name of the "created at" column.
   *
   * @var string
   */
  protected $created_at = 'created_at';


  /**
   * The name of the "updated at" column.
   *
   * @var string
   */
  protected $updated_at = 'updated_at';



  protected function makeInstance($name, $arguments = [])
  {
    $db = DB::table($this->table);
    $db->setTimestampsStatus($this->timestamps, $this->created_at, $this->updated_at);
    $db->setPrimaryKey($this->primaryKey);

    if (count($this->visible) && $db->getAction() == 'select' && count($db->getSourceValueItem('DISTINCT')) == 0) {
      $db->select($this->visible);
    }

    $db = $db->{$name}(...$arguments);

    return $db;
  }


  public static function __callStatic($name, $arguments)
  {
    return (new static)->makeInstance($name, $arguments);
  }
}
