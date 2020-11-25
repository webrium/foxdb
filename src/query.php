<?php
namespace webrium\foxql;

use webrium\foxql\biuld;

class query extends builder{

  private $config,$pdo,$connected=false;
  protected $table = false,$query_array;

  protected function setConfig($config,$table)
  {
    $this->config = $config;
    $this->table = "`$table`";
  }

  private function connect()
  {
    $this->pdo = new \PDO($this->config['driver'].":host=".$this->config['db_host'].':'.$this->config['db_host_port'].";dbname=".$this->config['db_name'].";charset=".$this->config['charset'],$this->config['username'],$this->config['password']);
    $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_WARNING);
    $this->connected = true;
    $this->setSelectResultType($this->config['result_stdClass']);
  }


  public function setSelectResultType($getArray)
  {
    if ($getArray) {
      $this->getType=\PDO::FETCH_ASSOC;
    }
    else {
      $this->getType=\PDO::FETCH_CLASS;
    }
  }

  public function select($args)
  {
    $select = new \webrium\foxql\selectFields();
    $select->table = $this->table;

    if (is_callable($args)) {
      $args($select);
    }
    elseif(is_array($args)){
      $select->autoSet($args);
    }

    $this->addToSelectFields($select->get());

    return $this;
  }


  public function makeWhere($op,$args)
  {

    $str= $this->makeValueString($args,$op);

    if ($str!=false) {
      $this->addToWhereQuery($op,$str);
    }

    return $this;
  }

  public function where(...$args)
  {
    $this->makeWhere('and',$args);
    return $this;
  }

  public function and(...$args)
  {
    $this->makeWhere('and',$args);
    return $this;
  }

  public function or(...$args)
  {
    $this->makeWhere('or',$args);
    return $this;
  }

  public function in($field,$array)
  {
    return $this->makeWhere('and',[$field,'in()',$array]);
  }

  public function orIn($field,$array)
  {
    return $this->makeWhere('or',[$field,'in()',$array]);
  }

  public function notIn($field,$array)
  {
    return $this->makeWhere('and',[$field,'not in()',$array]);
  }

  public function orNotIn($field,$array)
  {
    return $this->makeWhere('or',[$field,'not in()',$array]);
  }



  // public function between($field,$array)
  // {
  //   return $this->makeWhere('and',[$field,'not in()',$array]);
  // }
  //
  // public function orBetween($field,$array)
  // {
  //   return $this->makeWhere('or',[$field,'not in()',$array]);
  // }
  //
  // public function notBetween($field,$array)
  // {
  //   return $this->makeWhere('or',[$field,'not in()',$array]);
  // }
  //
  // public function orNotBetween($field,$array)
  // {
  //   return $this->makeWhere('or',[$field,'not in()',$array]);
  // }


  public function like($field,$array)
  {
    return $this->makeWhere('and',[$field,'like()',$array]);
  }

  public function orLike($field,$array)
  {
    return $this->makeWhere('or',[$field,'like()',$array]);
  }

  public function null($field)
  {
    return $this->makeWhere('and',[$field,'is','null']);
  }

  public function orNull($field)
  {
    return $this->makeWhere('or',[$field,'is','null']);
  }

  public function notNull($field)
  {
    return $this->makeWhere('and',[$field,'is not','null']);
  }

  public function orNotNull($field)
  {
    return $this->makeWhere('or',[$field,'is not','null']);
  }


  public function orderBy($field,$order)
  {
    $this->addToQuery('order by '.$this->getFieldStr($field)." $order",'ORDER_BY');
    return $this;
  }

  public function limit($number)
  {
    if (is_numeric($number)){
      $this->addToQuery("limit $number",'LIMIT');
    }

    return $this;
  }


  public function latest($field='id'){
    return $this->orderBy($field,'DESC');
  }

  public function oldest($field='id'){
    return $this->orderBy($field,'ASC');
  }

  public function groupBy($field)
  {
    $this->addToQuery('group by '.$this->getFieldStr($field),'GROUP_BY');
    return $this;
  }

  public function count($field='*',$as='count'){
    $res = $this->select(function ($query) use($field,$as)
    {
      $query->count($field,$as);
    })->first();

    // return $res->$as;
    return $this;
  }

  public function sum($field='*',$as='sum'){
    $res = $this->select(function ($query) use($field,$as)
    {
      $query->sum($field,$as);
    })->first();

    // return $res->$as;
    return $this;
  }

  public function avg($field='*',$as='avg'){
    $res = $this->select(function ($query) use($field,$as)
    {
      $query->avg($field,$as);
    })->first();

    // return $res->$as;
    return $this;
  }

  public function makeJoinQuery($type,$args)
  {

    if (count($args)==4) {

      $join_table_name                  = "`".$args[0]."`";
      $join_table_and_field_name        = $this->getFieldStr($arg[1]);

      $join_table_name       = $args[0];
      $join_table_field_name = $args[1];
      $join_op               = $args[2];
      $main_field_key        = $this->getFieldStr($args[3]);
    }
    elseif (count($args)==3) {
      $join_table_and_field_name_object = $this->explodeFieldName($args[0]);
      $join_table_name       = $join_table_and_field_name_object['table'];
      $join_table_and_field_name = $this->getFieldStr($join_table_and_field_name_object);

      $join_op               = $args[1];
      $main_field_key        = $this->getFieldStr($args[2]);
    }
    elseif (count($args)==2) {

      $join_table_and_field_name_object = $this->explodeFieldName($args[0]);
      $join_table_name       = $join_table_and_field_name_object['table'];
      $join_table_and_field_name = $this->getFieldStr($join_table_and_field_name_object);

      $join_op               = '=';
      $main_field_key        = $this->getFieldStr($args[1]);
    }
    $this->addToQuery("$type $join_table_name ON $main_field_key $join_op $join_table_and_field_name","JOIN");
  }

  public function join(...$args)
  {
    $this->makeJoinQuery(' INNER JOIN',$args);
    return $this;
  }

  public function leftJoin(...$args)
  {
    $this->makeJoinQuery(' LEFT JOIN',$args);
    return $this;
  }

  public function rightJoin(...$args)
  {
    $this->makeJoinQuery(' RIGHT JOIN',$args);
    return $this;
  }


  public function query()
  {
    return $this->query_array;
  }

  public function get()
  {
    return $this->execute($this->makeQueryStr(),true);
  }

  public function first()
  {
    $this->limit(1);
    return $this->execute($this->makeQueryStr(),true)[0]??false;
  }

  public function execute($query,$return=false){
    if (! $this->connected) {
      $this->connect();
    }

    if ($this->params==null) {
      $stmt = $this->pdo->query($query);
    }
    else {
      echo json_encode($this->params)."<br>$query<br>";
      $stmt=$this->pdo->prepare($query);
      $stmt->execute($this->params);
    }

    if($return){
      return $stmt->fetchAll($this->getType);
    }
  }


}
