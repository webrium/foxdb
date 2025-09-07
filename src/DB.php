<?php
namespace Foxdb;

class DB extends Builder
{


  protected static $CONFIG_LIST;
  protected static $USE_DATABASE = 'main';
  protected static $CHANGE_ONCE = false;


  public static function addConnection($config_name, array $config_params)
  {
    self::$CONFIG_LIST[$config_name] = new Config($config_params);
  }

  private static function getCurrentConfig()
  {
    return self::$CONFIG_LIST[self::$USE_DATABASE];
  }

  public static function table($name)
  {
    $builder = new Builder;
    $builder->setConfig(self::getCurrentConfig());
    $builder->setTable($name);
    return $builder;
  }

  public static function use(string $config_name)
  {
    self::$USE_DATABASE = $config_name;
    return new static;
  }

  public static function useOnce(string $config_name)
  {
    self::$USE_DATABASE = $config_name;
    self::$CHANGE_ONCE = true;
    return new static;
  }

  public static function beginTransaction()
  {
    try {
      DB::getCurrentConfig()->connect();
      DB::getCurrentConfig()->pdo()->beginTransaction();
    } catch (\PDOException $e) {
      throw new \Foxdb\Exceptions\DatabaseException(
        "Failed to begin transaction: " . $e->getMessage(),
        $e->getCode(),
        $e
      );
    }
  }

  public static function rollBack()
  {
    try {
      DB::getCurrentConfig()->pdo()->rollBack();
    } catch (\PDOException $e) {
      throw new \Foxdb\Exceptions\DatabaseException(
        "Failed to rollback transaction: " . $e->getMessage(),
        $e->getCode(),
        $e
      );
    }
  }

  public static function commit()
  {
    try {
      DB::getCurrentConfig()->pdo()->commit();
    } catch (\PDOException $e) {
      throw new \Foxdb\Exceptions\DatabaseException(
        "Failed to commit transaction: " . $e->getMessage(),
        $e->getCode(),
        $e
      );
    }
  }

  public static function setTimestamp()
  {
    $now = date('Y-m-d H:i:s');

    return [
      'created_at' => $now,
      'updated_at' => $now
    ];
  }


  public static function raw($query, array $values = [])
  {
    $raw = new Raw;
    $raw->setRawData($query, $values);
    return $raw;
  }


  /**
   * Executes an SQL query on the database using a Builder object.
   *
   * @param string $sql The SQL query to execute.
   * @param array $params Optional array of parameter values to use in the query.
   * @param bool $query_result Optional flag indicating whether to return the query result (true) or the number of affected rows (false). Defaults to false.
   * @return mixed The query result or number of affected rows, depending on the value of $query_result.
   */
  public static function query($sql, $params = [], $query_result = false)
  {
    $builder = new Builder;
    $builder->setConfig(self::getCurrentConfig());
    return $builder->execute($sql, $params, $query_result);
  }

}