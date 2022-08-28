<?php
namespace webrium\foxql;


trait Process{

  private $param_index = 0;

  protected function method_in_maker(array $list, $callback){
    foreach($list as $item){
      $param_name = $this->add_to_param_auto_name($item);
      $callback($param_name);
    }
  }

  protected function add_to_param($name, $value){
    $this->PARAMS[":$name"] = $value;
    return ":$name";
  }

  protected function add_to_param_auto_name($value){
    $name = $this->get_new_param_name();
    return $this->add_to_param($name, $value);
  }

  protected function get_new_param_name(){
    $this->param_index++;
    return 'p'.$this->param_index;
  }



  protected function fix_column_name($name){
    $array = explode('.', $name);
    $count = count($array);

    $table ='';
    $column = '';
    $type = '';

    if($count == 1){
      $table = "`$this->TABLE`";
      $column = "`$array[0]`";
      $type = 'column';
    }
    else if($count == 2){
      $table = "`$array[0]`";
      $column = "`$array[1]`";
      $type = 'table_and_column';
    }

    return ['name'=>"$table.$column", 'table'=>$table, 'column'=>$column, 'type'=>$type];
  }

  protected function fix_operator_and_value(&$operator, &$value){
    if($value == false || $value == null){
      $value = $operator;
      $operator = '=';
    }
  }



  public function get_params(){
    return $this->PARAMS;
  }

  public function get_source_value(){
    return $this->SOURCE_VALUE;
  }

  protected function sql_stractur($key=null)
  {
    $arr=[
      'SELECT'        =>1,
      'FIELDS'        =>2,
      'ALL'           =>3,
      'DISTINCT'      =>4,
      'DISTINCTROW'   =>5,
      'HIGH_PRIORITY' =>6,
      'STRAIGHT_JOIN' =>7,
      'FROM'          =>8,
      'JOIN'          =>9,
      'WHERE'         =>10,
      'GROUP_BY'      =>12,
      'HAVING'        =>13,
      'ORDER_BY'      =>14,
      'LIMIT'         =>15,
      'OFFSET'        =>16,
      'UNION'         =>17
    ];
    if ($key==null) {
      return $arr;
    }
    else {
      return $arr[$key];
    }
  }
}
