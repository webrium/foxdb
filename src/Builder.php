<?php
namespace webrium\foxql;


class Builder extends Process {

  protected $CONFIG;
  protected $PARAMS = [];
  protected $ACTION = 'select';

  protected $SOURCE_VALUE = [];

  public function __construct(Config $config){
    $this->CONFIG = $config;
  }

  public function execute($query, $params=[], $return=false){
    $this->CONFIG->connect();
    $this->PARAMS = $params;

    if ($this->PARAMS==null) {
      $stmt = $this->CONFIG->pdo()->query($query);
    }
    else {
      $stmt= $this->CONFIG->pdo()->prepare($query);
      $stmt->execute($this->PARAMS);
    }

    if($return){
      return $stmt->fetchAll();
    }
    else {
      return $stmt->rowCount();
    }
  }

  public function addOperator($oprator){
    $array = $this->getSourceValueItem('WHERE');
    
    if(count($array)>0){

      $end = $array[count($array)-1];

      if(in_array($end, ['AND','OR'])==false){
        $this->addToSourceArray('WHERE', $oprator);
      }
    }
  }

  public function in($name, array $list){
    $query = $this->queryMakerIn($name, $list,'');
    $this->addOperator('AND');
    $this->addToSourceArray('WHERE', $query);
    return $this;
  }

  public function notIn($name, array $list){
    $query = $this->queryMakerIn($name, $list,'NOT');
    $this->addOperator('AND');
    $this->addToSourceArray('WHERE', $query);
    return $this;
  }

  public function orin($name, array $list){
    $query = $this->queryMakerIn($name, $list,'');
    $this->addOperator('OR');
    $this->addToSourceArray('WHERE', $query);
    return $this;
  }

  public function orNotIn($name, array $list){
    $query = $this->queryMakerIn($name, $list,'NOT');
    $this->addOperator('OR');
    $this->addToSourceArray('WHERE', $query);
    return $this;
  }



  public function queryMakerIn($name, array $list, $extra_opration = ''){

    $name = $this->fix_field_name($name)['name'];

    $values = [];

    $this->method_in_maker($list,function($get_param_name)use(&$values) {
      $values[] = $get_param_name;
    });

    $string_query_name = $name;

    if(!empty($extra_opration))
    {
      $string_query_name.= ' '.$extra_opration;
    }


    $string_query_value = 'IN('.implode(',',$values).')';

    $string_query = "$string_query_name $string_query_value";

    return $string_query;
  }

  public function makeSelectQueryString(){
    $array = ['SELECT * FROM item1 WHERE'];

    foreach($this->SOURCE_VALUE as $value){
      if(is_array($value)){
        $array []= implode(' ',$value);
      }
    }

    return implode(' ',$array);
  }

  public function get(){
    $query = $this->makeSelectQueryString();
    echo $query."\n\n";
    echo json_encode($this->PARAMS)."\n\n";
    // die;
    return $this->execute($query, $this->PARAMS, true);
  }


  public function getSourceValueItem($struct_name){
    $s_index = $this->sql_stractur($struct_name);
    return $this->SOURCE_VALUE[$s_index]??[];
  }

  public function addToSourceArray($struct_name, $value){
    $s_index = $this->sql_stractur($struct_name);
    $this->SOURCE_VALUE[$s_index][] = $value;
  }

}
