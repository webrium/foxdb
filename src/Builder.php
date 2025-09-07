<?php
namespace Foxdb;

use PDO;
use stdClass;

class Builder
{

  use Process;

  protected $CONFIG;
  protected $TABLE;
  protected $PARAMS = [];
  protected $ACTION = 'select';
  protected $SOURCE_VALUE = [];

  protected $PRIMARY_KEY = 'id';
  protected $TIMESTAMPS = false;
  protected $CREATED_AT = 'created_at';
  protected $UPDATED_AT = 'updated_at';



  public function setTable($name)
  {
    $this->TABLE = $name;
  }

  public function setConfig($config)
  {
    $this->CONFIG = $config;
  }

  public function setAction($action)
  {
    $this->ACTION = $action;
    return $this;
  }

  public function getAction()
  {
    return $this->ACTION;
  }

  public function setPrimaryKey(string $value)
  {
    $this->PRIMARY_KEY = $value;
  }

  public function setTimestampsStatus(bool $value, $set_created_at_name = false, $set_updated_at_name = false)
  {
    $this->TIMESTAMPS = $value;

    if ($set_created_at_name) {
      $this->CREATED_AT = $set_created_at_name;
    }

    if ($set_updated_at_name) {
      $this->UPDATED_AT = $set_updated_at_name;
    }
  }


  /**
   * Executes a query with optional parameters and returns the result.
   *
   * @param string $query The SQL query to execute.
   * @param array|null $params The optional parameters to bind to the query.
   * @param bool $query_result Whether to fetch the query result or count the affected rows.
   *
   * @return mixed The query result or the number of affected rows, depending on the $query_result parameter.
   */
  protected function execute($query, $params = [], $query_result = false)
  {
    try {
      $this->CONFIG->connect();
      $this->PARAMS = $params;

      $stmt = $this->CONFIG->pdo()->prepare($query);
      
      if (!$stmt) {
        $errorInfo = $this->CONFIG->pdo()->errorInfo();
        $errorMessage = "Failed to prepare SQL statement: " . ($errorInfo[2] ?? 'Unknown error');
        
        if ($this->CONFIG->getThrowExceptions()) {
          throw new \Foxdb\Exceptions\QueryException(
            $errorMessage,
            (int)($errorInfo[1] ?? 0),
            null,
            $query,
            $params,
            $errorInfo[0] ?? null,
            $errorInfo
          );
        } else {
          error_log($errorMessage . " - SQL: " . $query . " - Params: " . json_encode($params));
          return false;
        }
      }

      $success = $stmt->execute($this->PARAMS);
      
      if (!$success) {
        $errorInfo = $stmt->errorInfo();
        $errorMessage = "Failed to execute SQL statement: " . ($errorInfo[2] ?? 'Unknown error');
        
        if ($this->CONFIG->getThrowExceptions()) {
          throw new \Foxdb\Exceptions\QueryException(
            $errorMessage,
            (int)($errorInfo[1] ?? 0),
            null,
            $query,
            $params,
            $errorInfo[0] ?? null,
            $errorInfo
          );
        } else {
          error_log($errorMessage . " - SQL: " . $query . " - Params: " . json_encode($params));
          return false;
        }
      }

      if ($query_result) {
        try {
          $result = $stmt->fetchAll($this->CONFIG->getFetch());
        } catch (\PDOException $fetchException) {
          $errorMessage = "Failed to fetch results: " . $fetchException->getMessage();
          
          if ($this->CONFIG->getThrowExceptions()) {
            throw new \Foxdb\Exceptions\QueryException(
              $errorMessage,
              $fetchException->getCode(),
              $fetchException,
              $query,
              $params,
              $fetchException->getCode(),
              method_exists($fetchException, 'errorInfo') ? $fetchException->errorInfo : null
            );
          } else {
            error_log($errorMessage . " - SQL: " . $query . " - Params: " . json_encode($params));
            return false;
          }
        }
      } else {
        $result = $stmt->rowCount();
      }

      return $result;
      
    } catch (\PDOException $e) {
      $errorMessage = "Database error: " . $e->getMessage();
      
      if ($this->CONFIG->getThrowExceptions()) {
        throw new \Foxdb\Exceptions\QueryException(
          $errorMessage,
          $e->getCode(),
          $e,
          $query,
          $params,
          $e->getCode(),
          method_exists($e, 'errorInfo') ? $e->errorInfo : null
        );
      } else {
        error_log($errorMessage . " - SQL: " . $query . " - Params: " . json_encode($params));
        return false;
      }
    }
  }


  protected function addOperator($oprator)
  {
    $array = $this->getSourceValueItem('WHERE');

    if (count($array) > 0) {

      $end = $array[count($array) - 1];

      if (in_array($end, ['AND', 'OR', '(']) == false) {
        $this->addToSourceArray('WHERE', $oprator);
      }
    } else {
      $this->addToSourceArray('WHERE', 'WHERE');
    }
  }

  protected function addOperatorHaving($oprator)
  {
    $array = $this->getSourceValueItem('HAVING');

    if (count($array) > 0) {

      $end = $array[count($array) - 1];

      if (in_array($end, ['AND', 'OR', '(']) == false) {
        $this->addToSourceArray('HAVING', $oprator);
      }
    }
  }

  protected function addStartParentheses()
  {
    $this->addToSourceArray('WHERE', '(');
  }

  protected function addEndParentheses()
  {
    $this->addToSourceArray('WHERE', ')');
  }




  public function select(...$args)
  {

    $this->clearSource('DISTINCT');

    if (count($args) == 1 && !is_string($args[0]) && !$args[0] instanceof Raw) {
      if (is_array($args[0])) {
        foreach ($args[0] as $key => $arg) {
          $args[$key] = $this->fix_column_name($arg)['name'];
        }

        $this->addToSourceArray('DISTINCT', implode(',', $args));
      } elseif (is_callable($args[0])) {
        $select = new Select($this);
        $select->setTable($this->TABLE);
        $args[0]($select);
        $this->addToSourceArray('DISTINCT', $select->getString());
      }
    } else {
      foreach ($args as $key => $arg) {
        if ($arg instanceof Raw) {
          $args[$key] = $this->raw_maker($arg->getRawQuery(), $arg->getRawValues());
        } else {
          $args[$key] = $this->fix_column_name($arg)['name'];
        }
      }

      $this->addToSourceArray('DISTINCT', implode(',', $args));
    }


    return $this;
  }

  public function selectRaw($query, $values = [])
  {
    $raw = new Raw;
    $raw->setRawData($query, $values);
    $this->select($raw);
    return $this;
  }


  public function whereIn($name, array $list)
  {
    $query = $this->queryMakerIn($name, $list, '');
    $this->addOperator('AND');
    $this->addToSourceArray('WHERE', $query);
    return $this;
  }

  public function whereNotIn($name, array $list)
  {
    $query = $this->queryMakerIn($name, $list, 'NOT');
    $this->addOperator('AND');
    $this->addToSourceArray('WHERE', $query);
    return $this;
  }

  public function orWhereIn($name, array $list)
  {
    $query = $this->queryMakerIn($name, $list, '');
    $this->addOperator('OR');
    $this->addToSourceArray('WHERE', $query);
    return $this;
  }

  public function orWhereNotIn($name, array $list)
  {
    $query = $this->queryMakerIn($name, $list, 'NOT');
    $this->addOperator('OR');
    $this->addToSourceArray('WHERE', $query);
    return $this;
  }


  public function whereColumn($first, $operator, $second = false)
  {

    $this->addOperator('AND');
    $this->fix_operator_and_value($operator, $second);
    $this->addToSourceArray('WHERE', "`$first` $operator `$second`");

    return $this;
  }





  private function queryMakerIn($name, array $list, $extra_opration = '')
  {
    if (count($list) == 0) {
      if( $extra_opration == 'NOT' ){
        return '1 = 1';
      }
      else{
        return '1 = 0';
      }
    }
    $name = $this->fix_column_name($name)['name'];
    $values = [];
    $this->method_in_maker($list, function ($get_param_name) use (&$values) {
      $values[] = $get_param_name;
    });
    $string_query_name = $name;
    if (!empty($extra_opration)) {
      $string_query_name .= ' ' . $extra_opration;
    }
    $string_query_value = 'IN(' . implode(',', $values) . ')';
    $string_query = "$string_query_name $string_query_value";
    return $string_query;
  }




  public function where(...$args)
  {
    $this->addOperator('AND');
    $this->queryMakerWhere($args);
    return $this;
  }

  public function orWhere(...$args)
  {
    $this->addOperator('OR');
    $this->queryMakerWhere($args);
    return $this;
  }

  public function whereNot(...$args)
  {
    $this->addOperator('AND');
    $this->queryMakerWhere($args, 'NOT');
    return $this;
  }

  public function orWhereNot(...$args)
  {
    $this->addOperator('OR');
    $this->queryMakerWhere($args, 'NOT');
    return $this;
  }




  public function whereNull($name)
  {
    $this->addOperator('AND');
    $this->queryMakerWhereStaticValue($name, 'IS NULL');
    return $this;
  }

  public function orWhereNull($name)
  {
    $this->addOperator('OR');
    $this->queryMakerWhereStaticValue($name, 'IS NULL');
    return $this;
  }

  public function whereNotNull($name)
  {
    $this->addOperator('AND');
    $this->queryMakerWhereStaticValue($name, 'IS NOT NULL');
    return $this;
  }

  public function orWhereNotNull($name)
  {
    $this->addOperator('OR');
    $this->queryMakerWhereStaticValue($name, 'IS NOT NULL');
    return $this;
  }


  public function whereBetween($name, array $values)
  {
    // Validate that the array contains exactly two elements
    if (count($values) !== 2) {
      throw new \Exception("Between values must contain exactly two elements.");
    }
    
    $this->addOperator('AND');
    $this->queryMakerWhereBetween($name, $values);
    return $this;
  }

  public function orWhereBetween($name, array $values)
  {
    // Validate that the array contains exactly two elements
    if (count($values) !== 2) {
      throw new \Exception("Between values must contain exactly two elements.");
    }
    
    $this->addOperator('OR');
    $this->queryMakerWhereBetween($name, $values);
    return $this;
  }

  public function whereNotBetween($name, array $values)
  {
    // Validate that the array contains exactly two elements
    if (count($values) !== 2) {
      throw new \Exception("Between values must contain exactly two elements.");
    }
    
    $this->addOperator('AND');
    $this->queryMakerWhereBetween($name, $values, 'NOT');
    return $this;
  }

  public function orWhereNotBetween($name, array $values)
  {
    // Validate that the array contains exactly two elements
    if (count($values) !== 2) {
      throw new \Exception("Between values must contain exactly two elements.");
    }
    
    $this->addOperator('OR');
    $this->queryMakerWhereBetween($name, $values, 'NOT');
    return $this;
  }


  public function whereRaw($query, array $values, $boolean = 'AND')
  {
    $this->addOperator($boolean);
    $this->addToSourceArray('WHERE', $this->raw_maker($query, $values));
    return $this;
  }

  public function orWhereRaw($query, array $values)
  {
    return $this->whereRaw($query, $values, 'OR');
  }





  public function whereDate(...$args)
  {
    $this->addOperator('AND');
    $this->queryMakerWhereLikeDate('DATE', $args);
    return $this;
  }

  public function orWhereDate(...$args)
  {
    $this->addOperator('OR');
    $this->queryMakerWhereLikeDate('DATE', $args);
    return $this;
  }

  public function whereYear(...$args)
  {
    $this->addOperator('AND');
    $this->queryMakerWhereLikeDate('YEAR', $args);
    return $this;
  }

  public function orWhereYear(...$args)
  {
    $this->addOperator('OR');
    $this->queryMakerWhereLikeDate('YEAR', $args);
    return $this;
  }

  public function whereMonth(...$args)
  {
    $this->addOperator('AND');
    $this->queryMakerWhereLikeDate('MONTH', $args);
    return $this;
  }

  public function orWhereMonth(...$args)
  {
    $this->addOperator('OR');
    $this->queryMakerWhereLikeDate('MONTH', $args);
    return $this;
  }


  public function whereDay(...$args)
  {
    $this->addOperator('AND');
    $this->queryMakerWhereLikeDate('DAY', $args);
    return $this;
  }

  public function orWhereDay(...$args)
  {
    $this->addOperator('OR');
    $this->queryMakerWhereLikeDate('DAY', $args);
    return $this;
  }

  public function whereTime(...$args)
  {
    $this->addOperator('AND');
    $this->queryMakerWhereLikeDate('TIME', $args);
    return $this;
  }

  public function orWhereTime(...$args)
  {
    $this->addOperator('OR');
    $this->queryMakerWhereLikeDate('TIME', $args);
    return $this;
  }


  public function and(...$args)
  {
    return $this->where(...$args);
  }

  public function or(...$args)
  {
    return $this->orWhere(...$args);
  }


  public function not(...$args)
  {
    return $this->whereNot(...$args);
  }

  public function orNot(...$args)
  {
    return $this->orWhereNot(...$args);
  }

  public function like($column, $value)
  {
    return $this->where($column, 'like', $value);
  }

  public function orLike($column, $value)
  {
    return $this->orWhere($column, 'like', $value);
  }


  public function null($column)
  {
    return $this->whereNull($column);
  }

  public function orNull($column)
  {
    return $this->orWhereNull($column);
  }


  public function notNull($column)
  {
    return $this->whereNotNull($column);
  }

  public function orNotNull($column)
  {
    return $this->orWhereNotNull($column);
  }


  public function is($column, $boolean = true)
  {
    return $this->where($column, $boolean);
  }

  public function true($column)
  {
    return $this->is($column, false);
  }

  public function false($column)
  {
    return $this->is($column, false);
  }

  public function date(...$args)
  {
    return $this->whereDate(...$args);
  }

  public function orDate(...$args)
  {
    return $this->orWhereDate(...$args);
  }


  public function year(...$args)
  {
    return $this->whereYear(...$args);
  }

  public function orYear(...$args)
  {
    return $this->orWhereYear(...$args);
  }


  public function month(...$args)
  {
    return $this->whereMonth(...$args);
  }

  public function orMonth(...$args)
  {
    return $this->orWhereMonth(...$args);
  }


  public function day(...$args)
  {
    return $this->whereDay(...$args);
  }

  public function orDay(...$args)
  {
    return $this->orWhereDay(...$args);
  }


  public function time(...$args)
  {
    return $this->whereTime(...$args);
  }

  public function orTime(...$args)
  {
    return $this->orWhereTime(...$args);
  }


  public function in($name, array $list)
  {
    return $this->whereIn($name, $list);
  }

  public function notIn($name, array $list)
  {
    return $this->whereNotIn($name, $list);
  }

  public function orIn($name, array $list)
  {
    return $this->orWhereIn($name, $list);
  }

  public function orNotIn($name, array $list)
  {
    return $this->orWhereNotIn($name, $list);
  }


  public function join(...$args)
  {
    $query = $this->queryMakerJoin('INNER', $args);
    $this->addToSourceArray('JOIN', $query);
    return $this;
  }

  public function leftJoin(...$args)
  {
    $query = $this->queryMakerJoin('LEFT', $args);
    $this->addToSourceArray('JOIN', $query);
    return $this;
  }

  public function rightJoin(...$args)
  {
    $query = $this->queryMakerJoin('RIGHT', $args);
    $this->addToSourceArray('JOIN', $query);
    return $this;
  }

  public function fullJoin(...$args)
  {
    $query = $this->queryMakerJoin('FULL', $args);
    $this->addToSourceArray('JOIN', $query);
    return $this;
  }

  public function crossJoin($column)
  {
    $this->addToSourceArray('JOIN', "CROSS JOIN `$column`");
    return $this;
  }




  /**
   * Retrieve the "count" result of the query.
   *
   * @param  string  $columns
   * @return int
   */
  public function count($column = '*')
  {
    $this->select(function ($query) use ($column) {
      $query->count($column)->as('count');
    });

    return $this->get_value($this->first(), 'count');
  }

  /**
   * Retrieve the sum of the values of a given column.
   *
   * @param  string  $columns
   * @return int
   */
  public function sum($column = '*')
  {
    $this->select(function ($query) use ($column) {
      $query->sum($column)->as('sum');
    });

    return $this->get_value($this->first(), 'sum');
  }

  /**
   * Retrieve the average of the values of a given column.
   *
   * @param  string  $column
   * @return mixed
   */
  public function avg($column = '*')
  {
    $this->select(function ($query) use ($column) {
      $query->avg($column)->as('avg');
    });

    return $this->get_value($this->first(), 'avg');
  }

  /**
   * Retrieve the average of the values of a given column.
   *
   * @param  string  $column
   * @return mixed
   */
  public function min($column = '*')
  {
    $this->select(function ($query) use ($column) {
      $query->min($column)->as('min');
    });

    return $this->get_value($this->first(), 'min');
  }

  /**
   * Retrieve the average of the values of a given column.
   *
   * @param  string  $column
   * @return mixed
   */
  public function max($column = '*')
  {
    $this->select(function ($query) use ($column) {
      $query->max($column)->as('max');
    });

    return $this->get_value($this->first(), 'max');
  }

  /**
   * Get a single column's value from the first result of a query.
   *
   * @param  string  $column
   * @return mixed
   */
  public function value($column = '*')
  {
    return $this->get_value($this->first(), $column);
  }






  /**
   * Add a "having" clause to the query.
   *
   * @param  string  $column
   * @param  string|null  $operator
   * @param  string|null  $value
   * @param  string  $boolean
   * @return $this
   */
  public function having($column, $operator, $value = null, $boolean = 'and', $fn = '')
  {
    $this->addOperatorHaving($boolean);
    $this->fix_operator_and_value($operator, $value);
    $column = $this->fix_column_name($column)['name'];

    $array = $this->getSourceValueItem('HAVING');
    $beginning = 'HAVING';

    if (count($array) > 0) {
      $beginning = '';
    }

    if (empty($fn)) {
      $this->addToSourceArray('HAVING', "$beginning $column $operator $value");
    } else {
      $this->addToSourceArray('HAVING', "$beginning $fn($column) $operator $value");
    }

    return $this;
  }

  /**
   * Add a "or having" clause to the query.
   *
   * @param  string  $column
   * @param  string|null  $operator
   * @param  string|null  $value
   * @return \Illuminate\Database\Query\Builder|static
   */
  public function orHaving($column, $operator, $value = null)
  {
    return $this->having($column, $operator, $value, 'OR');
  }

  /**
   * Add a "having count()" clause to the query.
   *
   * @param  string  $column
   * @param  string|null  $operator
   * @param  string|null  $value
   * @return $this
   */
  public function havingCount($column, $operator, $value = null)
  {
    return $this->having($column, $operator, $value, 'AND', 'COUNT');
  }

  /**
   * Add a "having sum()" clause to the query.
   *
   * @param  string  $column
   * @param  string|null  $operator
   * @param  string|null  $value
   * @return $this
   */
  public function havingSum($column, $operator, $value = null)
  {
    return $this->having($column, $operator, $value, 'AND', 'SUM');
  }

  /**
   * Add a "having avg()" clause to the query.
   *
   * @param  string  $column
   * @param  string|null  $operator
   * @param  string|null  $value
   * @return $this
   */
  public function havingAvg($column, $operator, $value = null)
  {
    return $this->having($column, $operator, $value, 'AND', 'AVG');
  }

  /**
   * Add a "or having count()" clause to the query.
   *
   * @param  string  $column
   * @param  string|null  $operator
   * @param  string|null  $value
   * @return $this
   */
  public function orHavingCount($column, $operator, $value = null)
  {
    return $this->having($column, $operator, $value, 'OR', 'COUNT');
  }

  /**
   * Add a "or having sum()" clause to the query.
   *
   * @param  string  $column
   * @param  string|null  $operator
   * @param  string|null  $value
   * @return $this
   */
  public function orHavingSum($column, $operator, $value = null)
  {
    return $this->having($column, $operator, $value, 'OR', 'SUM');
  }

  /**
   * Add a "or having avg()" clause to the query.
   *
   * @param  string  $column
   * @param  string|null  $operator
   * @param  string|null  $value
   * @return $this
   */
  public function orHavingAvg($column, $operator, $value = null)
  {
    return $this->having($column, $operator, $value, 'OR', 'AVG');
  }


  public function havingRaw($sql, array $bindings = [], $boolean = 'AND')
  {
    $this->addOperatorHaving($boolean);

    $array = $this->getSourceValueItem('HAVING');
    $beginning = 'HAVING';

    if (count($array) > 0) {
      $beginning = '';
    }
    $raw = DB::raw($sql, $bindings);
    $raw = $this->raw_maker($raw->getRawQuery(), $raw->getRawValues());
    $this->addToSourceArray('HAVING', "$beginning " . $raw);

    return $this;
  }

  public function orHavingRaw($sql, array $bindings = [])
  {
    return $this->havingRaw($sql, $bindings, 'OR');
  }







  /**
   * Add a "group by" clause to the query.
   *
   * @param  array  ...$groups
   * @return $this
   */
  public function groupBy(...$groups)
  {
    $arr = [];
    foreach ($groups as $group) {
      $arr[] = $this->fix_column_name($group)['name'];
    }
    $this->addToSourceArray('GROUP_BY', "GROUP BY " . implode(',', $arr));
    return $this;
  }


  /**
   * Add an "order by" clause to the query.
   *
   * @param  string  $column
   * @param  string  $direction
   * @return $this
   */
  public function orderBy($columns, $direction = 'asc')
  {

    $column_string = '';

    if (is_array($columns)) {
      $array_string = [];

      foreach ($columns as $column) {

        if (is_array($column) && count($column) == 2) {
          $array_string[] = $this->fix_column_name($column[0])['name'] . " " . $column[1];
        } else {
          $array_string[] = $this->fix_column_name($column)['name'] . " $direction";
        }
      }

      $column_string = implode(',', $array_string);
      $this->addToSourceArray('ORDER_BY', "ORDER BY $column_string");
    } else {
      $column_string = $this->fix_column_name($columns)['name'];
      $this->addToSourceArray('ORDER_BY', "ORDER BY $column_string $direction");
    }


    return $this;
  }


  /**
   * Add an "order by count(`column`)" clause to the query.
   *
   * @param  string  $column
   * @param  string  $direction
   * @return $this
   */
  public function orderByCount($column, $direction = 'asc')
  {
    $column = $this->fix_column_name($column)['name'];
    $this->addToSourceArray('ORDER_BY', "ORDER BY COUNT($column) $direction");
    return $this;
  }


  public function inRandomOrder()
  {
    $this->addToSourceArray('ORDER_BY', "ORDER BY RAND()");
    return $this;
  }


  public function latest($column = 'created_at')
  {
    $this->orderBy($column, 'DESC');
    return $this;
  }

  public function oldest($column = 'created_at')
  {
    $this->orderBy($column, 'ASC');
    return $this;
  }


  /**
   * Set the "limit" value of the query.
   *
   * @param  int  $value
   * @return $this
   */
  public function limit(int $value)
  {
    $this->addToSourceArray('LIMIT', "LIMIT $value");
    return $this;
  }

  /**
   * Alias to set the "limit" value of the query.
   *
   * @param  int  $value
   * @return $this
   */
  public function take(int $value)
  {
    return $this->limit($value);
  }


  /**
   * Set the "offset" value of the query.
   *
   * @param  int  $value
   * @return $this
   */
  public function offset(int $offset)
  {
    $this->addToSourceArray('OFFSET', "OFFSET $offset");
    return $this;
  }

  /**
   * Alias to set the "offset" value of the query.
   *
   * @param  int  $value
   * @return \Illuminate\Database\Query\Builder|static
   */
  public function skip(int $skip)
  {
    return $this->offset($skip);
  }



  public function page(int $page_number, int $take)
  {
    $offset = $page_number * $take;
    return $this->take($take)->offset($offset)->get();
  }


  public function paginate(int $take = 15, int $page_number = 1)
  {
    // Ensure the page number is at least 1
    if ($page_number <= 0) {
      $page_number = 1;
    }

    // Fetch the list of items for the current page
    $list = $this->page($page_number - 1, $take);
    // Get the total count of items
    $count = $this->clone()->count();

    // Calculate the total number of pages
    $params = new stdClass;
    $params->total = $count;
    $params->per_page = $take;
    $params->count = count($list);
    $params->current_page = $page_number;

    // Calculate last page
    $params->last_page = (int) ceil($count / $take); // Use ceil to ensure we round up

    // Determine next and previous page numbers
    $params->next_page = ($page_number < $params->last_page) ? ($page_number + 1) : false;
    $params->prev_page = ($page_number > 1) ? ($page_number - 1) : false;

    // Assign the data to the params
    $params->data = $list;

    return $params;
  }




  /**
   * Chunk the results of the query.
   *
   * @param  int  $count
   * @param  callable  $callback
   * @return bool
   */
  public function chunk($count, callable $callback)
  {
    $list = $this->get();

    while (count($list)) {
      $return = $callback(array_splice($list, 0, $count));
      if ($return === false) {
        break;
      }
    }
  }


  /**
   * Chunk the results of the query.
   *
   * @param  int  $count
   * @param  callable  $callback
   * @return bool|null
   */
  public function each(callable $callback)
  {
    $list = $this->get();

    foreach ($list as $key => $item) {
      $result = $callback($item);

      if ($result === false) {
        break;
      }
    }
  }


  /**
   * Determine if any rows exist for the current query.
   *
   * @return bool
   */
  public function exists()
  {
    $result = $this->first();
    return $result ? true : false;
  }

  /**
   * Determine if no rows exist for the current query.
   *
   * @return bool
   */
  public function doesntExist()
  {
    return !$this->exists();
  }


  private function queryMakerJoin($type, $args)
  {
    $join_table = $args[0];
    $join_table_column = $args[1];
    $operator = $args[2] ?? false;
    $main_column = $args[3] ?? false;

    if (!$operator && !$main_column) {
      $table_second = $this->fix_column_name($join_table);
      $table_main = $this->fix_column_name($join_table_column);

      $join_table = $table_second['table'];

      $join_table_column = $table_second['name'];

      $operator = '=';

      $main_column = $table_main['name'];
    } else if ($operator && !$main_column) {
      $table_second = $this->fix_column_name($join_table);
      $table_main = $this->fix_column_name($operator);

      $operator = $join_table_column;

      $join_table = $table_second['table'];
      $join_table_column = $table_second['name'];

      $main_column = $table_main['name'];
    } else if ($main_column) {
      $join_table = "`$join_table`";

      $join_table_column = $this->fix_column_name($join_table_column)['name'];
      $main_column = $this->fix_column_name($main_column)['name'];
    }

    return "$type JOIN $join_table ON $join_table_column $operator $main_column";
  }



  private function queryMakerWhereLikeDate($action, $args)
  {

    $column = $args[0];
    $operator = $args[1];
    $value = $args[2] ?? false;

    $this->fix_operator_and_value($operator, $value);

    $column = $this->fix_column_name($column)['name'];

    $value_name = $this->add_to_param_auto_name($value);


    $query = "$action($column) $operator $value_name";


    /*
      | Add finally string to Source
      */
    $this->addToSourceArray('WHERE', $query);
  }



  private function queryMakerWhereStaticValue($name, $value)
  {
    $name = $this->fix_column_name($name)['name'];

    $query = "$name $value";

    /*
    | Add NOT to query
    */
    if (!empty($extra_operation)) {
      $query = 'NOT ' . $query;
    }

    $this->addToSourceArray('WHERE', $query);
  }

  private function queryMakerWhereBetween($name, array $values, $extra_operation = '')
  {
    $name = $this->fix_column_name($name)['name'];

    $v1 = $this->add_to_param_auto_name($values[0]);
    $v2 = $this->add_to_param_auto_name($values[1]);

    $query = "$name BETWEEN $v1 AND $v2";

    /*
    | Add NOT to query
    */
    if (!empty($extra_operation)) {
      $query = 'NOT ' . $query;
    }

    $this->addToSourceArray('WHERE', $query);
  }

  private function queryMakerWhere($args, $extra_operation = '')
  {

    if (is_string($args[0])) {

      $column = $args[0];
      $operator = $args[1];
      $value = $args[2] ?? false;


      $this->fix_operator_and_value($operator, $value);

      $column = $this->fix_column_name($column)['name'];

      $value_name = $this->add_to_param_auto_name($value);


      $query = "$column $operator $value_name";

      /*
      | Add NOT to query
      */
      if (!empty($extra_operation)) {
        $query = 'NOT ' . $query;
      }

      /*
      | Add finally string to Source
      */
      $this->addToSourceArray('WHERE', $query);
    } else if (is_callable($args[0])) {

      $this->addStartParentheses();
      $args[0]($this);
      $this->addEndParentheses();
    }
  }

  protected function makeSelectQueryString()
  {

    $this->addToSourceArray('SELECT', "SELECT");
    $this->addToSourceArray('FROM', "FROM `$this->TABLE`");

    if (count($this->getSourceValueItem('DISTINCT')) == 0) {
      $this->select('*');
    }


    return $this->makeSourceValueStrign();
  }


  public function setTimestamps(array &$values, $just_update = false)
  {
    if ($this->TIMESTAMPS) {
      $now = date('Y-m-d H:i:s');
      if (!$just_update) {
        $values[$this->CREATED_AT] = $now;
      }
      $values[$this->UPDATED_AT] = $now;
    }
  }


  protected function makeInsertQueryString(array $values)
  {
    $param_name = [];
    $param_value_name_list = [];

    $this->setTimestamps($values);

    foreach ($values as $name => $value) {
      $param_name[] = $this->fix_column_name($name)['name'];
      $param_value_name_list[] = $this->add_to_param_auto_name($value);
    }

    return "INSERT INTO `$this->TABLE` (" . implode(',', $param_name) . ") VALUES (" . implode(',', $param_value_name_list) . ")";
  }

  protected function makeUpdateQueryString(array $values)
  {
    $this->setTimestamps($values, true);

    $params = [];
    foreach ($values as $name => $value) {
      $params[] = $this->fix_column_name($name)['name'] . ' = ' . $this->add_to_param_auto_name($value);
    }

    $extra = $this->makeSourceValueStrign();

    return "UPDATE `$this->TABLE` SET " . implode(',', $params) . " $extra";
  }

  protected function makeUpdateQueryIncrement(string $column, $value = 1, $action = '+')
  {

    $values = [];
    $this->setTimestamps($values, true);

    $column = $this->fix_column_name($column)['name'];

    $params = [];
    $params[] = "$column = $column $action $value";

    foreach ($values as $name => $value) {
      $params[] = $this->fix_column_name($name)['name'] . ' = ' . $this->add_to_param_auto_name($value);
    }

    $extra = $this->makeSourceValueStrign();

    return "UPDATE `$this->TABLE` SET " . implode(',', $params) . " $extra";
  }


  protected function makeSourceValueStrign()
  {
    ksort($this->SOURCE_VALUE);

    $array = [];
    foreach ($this->SOURCE_VALUE as $value) {
      if (is_array($value)) {
        $array[] = implode(' ', $value);
      }
    }

    return implode(' ', $array);
  }


  protected function makeDeleteQueryString()
  {
    $extra = $this->makeSourceValueStrign();
    return "DELETE FROM `$this->TABLE`  $extra";
  }


  public function insert(array $values, $get_last_insert_id = false)
  {
    $this->setAction('insert');
    $query = $this->makeInsertQueryString($values);
    $result = $this->execute($query, $this->PARAMS);

    if (!$get_last_insert_id) {
      return $result;
    } else {
      return $this->CONFIG->pdo()->lastInsertId();
    }
  }

  public function insertGetId(array $values)
  {
    return $this->insert($values, true);
  }


  public function increment(string $column, int $value = 1)
  {
    $query = $this->makeUpdateQueryIncrement($column, $value);
    return $this->execute($query, $this->PARAMS);
  }


  public function decrement(string $column, int $value = 1)
  {
    $query = $this->makeUpdateQueryIncrement($column, $value, '-');
    return $this->execute($query, $this->PARAMS);

  }


  public function update(array $values)
  {
    $this->setAction('update');
    $this->clearSource('DISTINCT');
    $query = $this->makeUpdateQueryString($values);
    return $this->execute($query, $this->PARAMS);
  }


  public function delete()
  {
    $this->setAction('delete');
    $query = $this->makeDeleteQueryString();
    return $this->execute($query, $this->PARAMS);
  }

  /**
   * This method truncates all rows from the table named `$this->TABLE`.
   *
   * @return \PDOStatement
   */
  public function truncate()
  {
    return $this->execute("TRUNCATE `$this->TABLE`");
  }



  public function get()
  {
    $query = $this->makeSelectQueryString();
    return $this->execute($query, $this->PARAMS, true);
  }


  public function pluck($column, $key = null)
  {
    $list = $this->get();
    $result = [];
    foreach ($list as $item) {

      if ($key == null) {
        $result[] = $this->get_value($item, $column);
      } else {
        $result[$this->get_value($item, $key)] = $this->get_value($item, $column);
      }
    }

    return $result;
  }


  public function first($columns = [])
  {
    $db = $this->limit(1);

    if (count($columns)) {
      $db->select($columns);
    }

    $array = $db->get();

    if (count($array) == 1) {
      return $array[0];
    }

    return false;
  }



  /**
   * Execute a query for a single record by ID.
   *
   * @param  int    $id
   * @param  array  $columns
   * @return Model|bool
   */
  public function find($id = null, $columns = [])
  {

    if ($id !== null) {
      $this->where($this->PRIMARY_KEY, $id);
    }

    $first = $this->first($columns);

    $model = false;

    if ($first) {
      $model = new Model;
      $model->table = $this->TABLE;

      foreach ($first as $key => $value) {
        $model->{$key} = $value;
      }
    }

    return $model;
  }


  public function getSourceValueItem($struct_name)
  {
    $s_index = $this->sql_stractur($struct_name);
    return $this->SOURCE_VALUE[$s_index] ?? [];
  }

  protected function addToSourceArray($struct_name, $value)
  {
    $s_index = $this->sql_stractur($struct_name);
    $this->SOURCE_VALUE[$s_index][] = $value;
  }

  protected function clearSource($struct_name)
  {
    $s_index = $this->sql_stractur($struct_name);
    $this->SOURCE_VALUE[$s_index] = [];
  }

  private function clone()
  {
    $db = DB::table($this->TABLE);
    $db->PARAMS = $this->PARAMS;
    $db->SOURCE_VALUE = $this->SOURCE_VALUE;
    $db->clearSource('SELECT');
    $db->clearSource('LIMIT');
    $db->clearSource('OFFSET');
    $db->clearSource('FROM');
    return $db;
  }
}
