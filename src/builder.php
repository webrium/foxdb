<?php
namespace webrium\foxql;
// use webrium\foxql\query;

class builder {

  public function makeValueString($args,$table=false)
  {

    if (count($args)==3) {
      if (strpos($args[1],'()')===false) {
        $str = "$this->table.`".$args[0]."` ".$args[1]." :".$args[0];
      }
      else {
        $args[1] = str_replace('()',"(:".$args[0].")",$args[1]);
        $str = "$this->table.`".$args[0]."` ".$args[1];
      }
    }
    elseif (count($args)==2) {
      $str = "$this->table.`".$args[0]."` = :".$args[0];
    }
    return $str;
  }

  public function addToWhereQuery($op,$str)
  {
    $index = $this->SqlStractur("WHERE");

    if (! isset($this->query_array[$index])) {
      $this->query_array[$index]=[];
      $this->query_array[$index][] = 'where';
    }
    else {
      $this->query_array[$index][] = $op;
    }

    $this->query_array[$index][] = $str;
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
