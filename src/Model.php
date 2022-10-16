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
  const CREATED_AT = 'created_at';

  /**
   * The name of the "updated at" column.
   *
   * @var string
   */
  const UPDATED_AT = 'updated_at';



  private $dynamic_params = [];



  protected function makeInstance($name, $arguments = [])
  {
    $db = DB::table($this->table);
    $db->setTimestampsStatus($this->timestamps, self::CREATED_AT, self::UPDATED_AT);
    $db->setPrimaryKey($this->primaryKey);

    if (count($this->visible) && $db->getAction() == 'select' && count($db->getSourceValueItem('DISTINCT')) == 0) {
      $db->select($this->visible);
    }

    $db = $db->{$name}(...$arguments);

    return $db;
  }

  
  /**
   * @return stdClass
   */
  public function toObject(){
    return $this->dynamic_params;
  }


  public static function __callStatic($name, $arguments)
  {
    return (new static)->makeInstance($name, $arguments);
  }


  public function __call($name, $arguments)
  {
    return $this->makeInstance($name, $arguments);
  }


  /**
   * Dynamically retrieve attributes on the model.
   *
   * @param  string  $key
   * @return mixed
   */
  public function __get($key)
  {
    return $this->dynamic_params[$key]??null;
  }


  function __set($name, $value)
  {
    $this->dynamic_params[$name] = $value;
  }


  /**
   * Save the model to the database.
   *
   * @return int
   */
  public function save()
  {
    if (isset($this->dynamic_params[$this->primaryKey])) {
      return $this->setAction('update')->where($this->primaryKey, $this->dynamic_params[$this->primaryKey])->update($this->dynamic_params);
    } else {
      $this->id = $this->insertGetId($this->dynamic_params);
      return $this->id;
    }
  }


  public static function find($value)
  {
    $class = (new static);
    
    $find = self::where($class->primaryKey, $value)->first();

    if($find){
      foreach ($find as $key => $value) {
        $class->$key = $value;
      }
  
      return $class;
    }
    else{
      return false;
    }
  }

}
