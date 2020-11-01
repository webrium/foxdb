<?php
namespace webrium\foxql;
// use webrium\foxql\query;

class builder {


  public $skipFirstOp=false,$index_var=1;


  public function makeValueString($args,$op)
  {
    if (count($args)==3) {
      $value_name = ":".$args[0]."_$this->index_var";

      if (strpos($args[1],'()')===false) {
        $str = "$this->table.`".$args[0]."` ".$args[1]." $value_name";
      }
      else {
        $args[1] = str_replace('()',"($value_name)",$args[1]);
        $str = "$this->table.`".$args[0]."` ".$args[1];
      }
    }
    elseif (count($args)==2) {
      $value_name = ":".$args[0]."_$this->index_var";
      $str = "$this->table.`".$args[0]."` = $value_name";
    }
    elseif (count($args)==1 && is_callable($args[0])) {
      $this->addToWhereQuery($op,'(');
      $this->skipFirstOp=true;
      $str = "( ".$args[0]($this)." )";
      $this->addToWhereQuery('',')');
      return false;
    }

    $this->index_var++;
    return $str;
  }

  public function addToQuery($str,$_index)
  {
    $index = $this->SqlStractur($_index);
    $this->query_array[$index] = " $str";
  }


  public function addToWhereQuery($op,$str)
  {
    $index = $this->SqlStractur("WHERE");

    if (! isset($this->query_array[$index])) {
      $this->query_array[$index]= 'where ';
    }
    else {
      if ($this->skipFirstOp) {
        $this->skipFirstOp = false;
      }
      else {
        $this->query_array[$index] .= " $op ";
      }
    }

    $this->query_array[$index] .= $str;
  }

  public function addToSelectFields($fields)
  {
    $index = $this->SqlStractur("FIELDS");

    if (! isset($this->query_array[$index])) {
      $this->query_array[$index]='';
    }

    $this->query_array[$index]=$fields;
  }

  public function makeQueryStr($type='select')
  {
    if ($type=='select') {
      return $this->getSelectQuery();
    }
  }

  public function getSelectQuery()
  {
    $fields = $this->query_array[$this->SqlStractur('FIELDS')]??false;

    if ($fields==false) {
      $fields = '*';
    }

    $str = "select $fields from $this->table ";

    foreach ($this->query_array??[] as $key => $value) {
      $str.=$value;
    }

    return $str;
  }

  public function explodeFieldName($name)
  {
    $table = $this->table;

    if (strpos($name,'.')>0) {
      $arr   = explode('.',$name);
      $table = "`".$arr[0]."`";
      $name  =  $arr[1];
    }

    return['table'=>$table,'field'=>$name];
  }

  public function getFieldStr($name)
  {

    if (is_string($name)) {
      if ( trim($name) =='*') {
        return $name;
      }
      else {
        $name = $this->explodeFieldName($name);
      }
    }
    
    return $name['table'].".`".$name['field']."`";
  }


  public function SqlStractur($key=null)
  {
    $arr=[
      'SELECT'        =>1,
      'FIELDS'        =>2,
      'ALL'           =>3,
      'DISTINCT '     =>4,
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
