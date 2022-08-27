<?php
namespace webrium\foxql;


class Builder extends Process {

  protected $CONFIG;
  protected $TABLE;
  protected $PARAMS = [];
  protected $ACTION = 'select';

  protected $SOURCE_VALUE = [];

  public function __construct(Config $config){
    $this->CONFIG = $config;
  }

  public function setTable($name){
    $this->TABLE = $name;
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

      if(in_array($end, ['AND','OR','('])==false){
        $this->addToSourceArray('WHERE', $oprator);
      }
    }
    else{
      $this->addToSourceArray('WHERE', 'WHERE');
    }
  }

  public function addStartParentheses(){
    $this->addToSourceArray('WHERE', '(');
  }

  public function addEndParentheses(){
    $this->addToSourceArray('WHERE', ')');
  }




  public function whereIn($name, array $list){
    $query = $this->queryMakerIn($name, $list,'');
    $this->addOperator('AND');
    $this->addToSourceArray('WHERE', $query);
    return $this;
  }

  public function whereNotIn($name, array $list){
    $query = $this->queryMakerIn($name, $list,'NOT');
    $this->addOperator('AND');
    $this->addToSourceArray('WHERE', $query);
    return $this;
  }

  public function orWhereIn($name, array $list){
    $query = $this->queryMakerIn($name, $list,'');
    $this->addOperator('OR');
    $this->addToSourceArray('WHERE', $query);
    return $this;
  }

  public function orWhereNotIn($name, array $list){
    $query = $this->queryMakerIn($name, $list,'NOT');
    $this->addOperator('OR');
    $this->addToSourceArray('WHERE', $query);
    return $this;
  }





  private function queryMakerIn($name, array $list, $extra_opration = ''){

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




  public function where(...$args){
    $this->addOperator('AND');
    $this->queryMakerWhere($args);
    return $this;
  }

  public function orWhere(...$args){
    $this->addOperator('OR');
    $this->queryMakerWhere($args);
    return $this;
  }

  public function whereNot(...$args){
    $this->addOperator('AND');
    $this->queryMakerWhere($args,'NOT');
    return $this;
  }

  public function orWhereNot(...$args){
    $this->addOperator('OR');
    $this->queryMakerWhere($args,'NOT');
    return $this;
  }



  public function whereNull($name){
    $this->addOperator('AND');
    $this->queryMakerWhereStaticValue($name,'IS NULL');
    return $this;
  }

  public function orWhereNull($name){
    $this->addOperator('OR');
    $this->queryMakerWhereStaticValue($name,'IS NULL');
    return $this;
  }

  public function whereNotNull($name){
    $this->addOperator('AND');
    $this->queryMakerWhereStaticValue($name,'IS NOT NULL');
    return $this;
  }

  public function orWhereNotNull($name){
    $this->addOperator('OR');
    $this->queryMakerWhereStaticValue($name,'IS NOT NULL');
    return $this;
  }


  public function whereBetween($name, array $values){
    $this->addOperator('AND');
    $this->queryMakerWhereBetween($name, $values);
    return $this;
  }

  public function orWhereBetween($name, array $values){
    $this->addOperator('OR');
    $this->queryMakerWhereBetween($name, $values);
    return $this;
  }

  public function whereNotBetween($name, array $values){
    $this->addOperator('AND');
    $this->queryMakerWhereBetween($name, $values, 'NOT');
    return $this;
  }

  public function orWhereNotBetween($name, array $values){
    $this->addOperator('OR');
    $this->queryMakerWhereBetween($name, $values, 'NOT');
    return $this;
  }



  public function whereDate(...$args){
    $this->addOperator('AND');
    $this->queryMakerWhereLikeDate('DATE', $args);
    return $this;
  }

  public function orWhereDate(...$args){
    $this->addOperator('OR');
    $this->queryMakerWhereLikeDate('DATE', $args);
    return $this;
  }

  public function whereYear(...$args){
    $this->addOperator('AND');
    $this->queryMakerWhereLikeDate('YEAR', $args);
    return $this;
  }

  public function orWhereYear(...$args){
    $this->addOperator('OR');
    $this->queryMakerWhereLikeDate('YEAR', $args);
    return $this;
  }

  public function whereMonth(...$args){
    $this->addOperator('AND');
    $this->queryMakerWhereLikeDate('MONTH', $args);
    return $this;
  }

  public function orWhereMonth(...$args){
    $this->addOperator('OR');
    $this->queryMakerWhereLikeDate('MONTH', $args);
    return $this;
  }


  public function whereDay(...$args){
    $this->addOperator('AND');
    $this->queryMakerWhereLikeDate('DAY', $args);
    return $this;
  }

  public function orWhereDay(...$args){
    $this->addOperator('OR');
    $this->queryMakerWhereLikeDate('DAY', $args);
    return $this;
  }

  public function whereTime(...$args){
    $this->addOperator('AND');
    $this->queryMakerWhereLikeDate('TIME', $args);
    return $this;
  }

  public function orWhereTime(...$args){
    $this->addOperator('OR');
    $this->queryMakerWhereLikeDate('TIME', $args);
    return $this;
  }




  private function queryMakerWhereLikeDate($action,$args){

      $v1 = $args[0];
      $op = $args[1];
      $param = $args[2]??false;

      if($param==false){
        $param = $op;
        $op = '=';
      }

      $v1 = $this->fix_field_name($v1)['name'];

      $param_name = $this->add_to_param_auto_name($param);


      $query = "$action($v1) $op $param_name";


      /*
      | Add finally string to Source
      */
      $this->addToSourceArray('WHERE', $query);
  }



  private function queryMakerWhereStaticValue($name ,$value){
    $name = $this->fix_field_name($name)['name'];

    $query = "$name $value";

    /*
    | Add NOT to query
    */
    if(!empty($per_extra_opration)){
      $query = 'NOT '.$query ;
    }

    $this->addToSourceArray('WHERE', $query);
  }

  private function queryMakerWhereBetween($name, array $values, $per_extra_opration=''){
    $name = $this->fix_field_name($name)['name'];
    
    $v1 = $this->add_to_param_auto_name($values[0]);
    $v2 = $this->add_to_param_auto_name($values[1]);

    $query = "$name BETWEEN $v1 AND $v2";

    /*
    | Add NOT to query
    */
    if(!empty($per_extra_opration)){
      $query = 'NOT '.$query ;
    }

    $this->addToSourceArray('WHERE', $query);
  }

  private function queryMakerWhere($args,$per_extra_opration=''){

    if(is_string($args[0])){
      $v1 = $args[0];
      $op = $args[1];
      $param = $args[2]??false;

      if($param==false){
        $param = $op;
        $op = '=';
      }

      $v1 = $this->fix_field_name($v1)['name'];

      $param_name = $this->add_to_param_auto_name($param);


      $query = "$v1 $op $param_name";

      /*
      | Add NOT to query
      */
      if(!empty($per_extra_opration)){
        $query = 'NOT '.$query ;
      }

      /*
      | Add finally string to Source
      */
      $this->addToSourceArray('WHERE', $query);
    }
    else if(is_callable($args[0])){
      $this->addStartParentheses();
      $args[0]($this);
      $this->addEndParentheses();
    }

  }

  public function makeSelectQueryString(){
    $array = ["SELECT * FROM `$this->TABLE`"];

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
