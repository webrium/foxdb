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

  

  protected function makeInstance($name, $arguments = [])
  {
    $db = DB::table($this->table);

    
    if(count($this->visible) && $db->getAction()=='select' && count($db->getSourceValueItem('DISTINCT'))==0){
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
