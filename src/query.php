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
    $this->$connected = true;
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
    $str= $this->makeValueString($args);
    $this->addToWhereQuery($op,$str);
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

  public function query()
  {
    return $this->query_array;
  }

  public function get()
  {
    return json_encode($this->makeQueryStr());
  }

  public function first()
  {
    return $this->table;
  }


}
