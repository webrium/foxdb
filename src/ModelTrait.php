<?php

namespace Foxdb;

use Foxdb\DB;

trait ModelTrait
{


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


  private function getTimestamps(){
    $timestamps = true;

    if(isset($this->timestamps)){
      $timestamps = $this->timestamps;
    }

    return $timestamps;
  }

  private function getPrimaryKey(){
    $primaryKey = 'id';

    if(isset($this->primaryKey)){
      $primaryKey = $this->primaryKey;
    }

    return $primaryKey;
  }

  private function getVisible(){
    $visible = [];

    if(isset($this->visible)){
      $visible = $this->visible;
    }

    return $visible;
  }


  protected function makeInstance($name, $arguments = [])
  {

    $db = DB::table($this->table);
    $db->setTimestampsStatus($this->getTimestamps(), self::CREATED_AT, self::UPDATED_AT);
    $db->setPrimaryKey($this->getPrimaryKey());

    if (count($this->getVisible()) && $db->getAction() == 'select' && count($db->getSourceValueItem('DISTINCT')) == 0) {
      $db->select($this->getVisible());
    }

    $db = $db->{$name}(...$arguments);

    return $db;
  }

  
  /**
   * @return \stdClass
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
    if (isset($this->dynamic_params[$this->getPrimaryKey()])) {
      return $this->setAction('update')->where($this->getPrimaryKey(), $this->dynamic_params[$this->getPrimaryKey()])->update($this->dynamic_params);
    } else {
      $this->id = $this->insertGetId($this->dynamic_params);
      return $this->id;
    }
  }


  public static function find($value)
  {
    $class = (new static);
    
    $find = self::where($class->getPrimaryKey(), $value)->first();

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
