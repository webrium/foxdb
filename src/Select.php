<?php

namespace webrium\foxql;


class Select extends Builder
{

  protected $builder;
  protected $stringArray = [];

  public function __construct(Builder $builder)
  {
    $this->builder = $builder;
  }

  public function all($table)
  {
    $this->stringArray[] = "`$table`.*";
    return $this;
  }

  public function field($column)
  {
    $column = $this->builder->fix_column_name($column)['name'];
    $this->stringArray[] = $column;
    return new AsFleld($this);
  }


  /**
   * Retrieve the "count" result of the query.
   *
   * @param  string  $columns
   * @return int
   */
  public function count($column = '*')
  {
    $this->fn("COUNT", $column);
    return new AsFleld($this);
  }

  /**
   * Retrieve the sum of the values of a given column.
   *
   * @param  string  $column
   * @return mixed
   */
  public function sum($column = '*')
  {
    $this->fn("SUM", $column);
    return new AsFleld($this);
  }

  /**
   * Retrieve the average of the values of a given column.
   *
   * @param  string  $column
   * @return mixed
   */
  public function avg($column = '*')
  {
    $this->fn("AVG", $column);
    return new AsFleld($this);
  }


  /**
   * Retrieve the maximum value of a given column.
   *
   * @param  string  $column
   * @return mixed
   */
  public function max($column)
  {
    $this->fn("MAX", $column);
    return new AsFleld($this);
  }

  /**
   * Retrieve the minimum value of a given column.
   *
   * @param  string  $column
   * @return mixed
   */
  public function min($column)
  {
    $this->fn("MIN", $column);
    return new AsFleld($this);
  }


  public function raw($column, $operator, $value = null){
    $this->builder->fix_operator_and_value($operator, $value);
    $this->builder->fix_column_name($column);
    $this->stringArray[] = "$column $operator $value";
    return new AsFleld($this);
  }


  
  public function fn($type, $column)
  {
    if ($column != '*') {
      $column = $this->builder->fix_column_name($column)['name'];
    }

    $this->stringArray[] = "$type($column)";
  }

  public function getString()
  {
    return implode(',', $this->stringArray);
  }
}





class AsFleld extends Select
{

  protected $select;

  public function __construct(Select $select)
  {
    $this->select = $select;
  }

  public function as($value)
  {
    $end_index = count($this->select->stringArray) - 1;
    $this->select->stringArray[$end_index] = $this->select->stringArray[$end_index] . " as '$value'";
  }
}
