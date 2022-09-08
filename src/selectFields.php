<?php
namespace Foxql;

class selectFields extends builder{

  private $fields=false;
  public $table;

  public function addToFields($str)
  {
    if ($this->fields==false) {
      $this->fields.=$str;
    }
    else {
      $this->fields.=",$str";
    }
  }

  public function all($name)
  {
    $arr = $this->explodeFieldName($name);
    $this->addToFields($arr['table'].".*");
    return $this;
  }

  public function field($name,$as=false)
  {
    $this->addToFields($this->getFieldStr($name));

    if ($as) {
      $this->as($as);
    }

    return $this;
  }

  public function as($name)
  {
    $this->fields.=" as '$name'";
    return $this;
  }

  public function count($field,$as=false)
  {
    $this->addToFields("count(".$this->getFieldStr($field).")");
    
    if ($as) {
      $this->as($as);
    }

    return $this;
  }

  public function sum($field,$as=false)
  {
    $this->addToFields("sum(".$this->getFieldStr($field).")");

    if ($as) {
      $this->as($as);
    }

    return $this;
  }

  public function avg($field,$as=false)
  {
    $this->addToFields("avg(".$this->getFieldStr($field).")");

    if ($as) {
      $this->as($as);
    }

    return $this;
  }
  
  public function autoSet($array){
    foreach ($array as $key => $field) {
      $this->addToFields($field);
    }
  }

  public function get()
  {
    return $this->fields;
  }

}
