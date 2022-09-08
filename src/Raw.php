<?php
namespace Foxql;

class Raw
{

  protected $PARAMS = [];
  protected $QUERY;

  public function setRawData($query, array $values){
    $this->QUERY = $query;
    $this->PARAMS = $values;
  }

  public function getRawQuery(){
    return $this->QUERY;
  }

  public function getRawValues(){
    return $this->PARAMS;
  }
}
