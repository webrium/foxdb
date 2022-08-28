<?php
namespace webrium\foxql;


Class Select extends Builder{

  protected $builder;
  protected $stringArray=[];

  public function __construct(Builder $builder){
    $this->builder = $builder;
  }

  public function all($table){
    $this->stringArray[] = "`$table`.*";
    return $this;
  }

  public function field($name){
    $field_name = $this->builder->fix_column_name($name)['name'];
    $this->stringArray[] = $field_name;
    return new AsFleld($this);
  }

  public function getString(){
    return implode(',',$this->stringArray);
  }

}





Class AsFleld extends Select {

  protected $select;

  public function __construct(Select $select){
    $this->select = $select; 
  }

  public function as($value){
    $end_index = count($this->select->stringArray)-1;
    $this->select->stringArray[$end_index] =$this->select->stringArray[$end_index] ." as '$value'";
  }
}