<?php
namespace webrium\foxql;

class DB extends query{

  private static $db_configs = [],$currentdb=false,$pdo=false;
  protected static $cache = [];


  public static function addConfig($name,$params)
  {
    // Set Config Params
    self::$db_configs[$name] = $params;

    // Init Main Config Name
    if (! self::$currentdb) {
      self::$currentdb = $name;
    }
  }

  /**
  * Get Current Pdo
  *
  * @return PDO
  */
  public static function pdo(){
    self::connect();
    return self::config()['pdo']??false;
  }

  /**
  * Get Current sqlsrv_confg
  *
  * @return array Config
  */
  public static function config()
  {
    return self::$db_configs[self::$currentdb]??false;
  }

  /**
  * connect to the DB
  *
  * @return array of current config
  */
  public static function connect()
  {

    // Get Current Config
    $config = self::config();

    // If Not Init
    if ($config && ! isset($config['pdo'])) {

      // generate host string
      $host = $config['driver'].":host=".$config['db_host'];
      if ( isset($config['db_host_port']) && $config['db_host_port'] !=false ) {
        $host .= ':'.$config['db_host_port'];
      }

      // Init Pdo Connection
      $config['pdo'] = new \PDO("$host;dbname=".$config['db_name'].";charset=".$config['charset'],$config['username'],$config['password']);
      $config['pdo']->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_WARNING);
      $config['fetch'] = $config['result_stdClass']?\PDO::FETCH_CLASS:\PDO::FETCH_ASSOC;

      // update and add pdo method
      self::$db_configs[self::$currentdb] = $config;
    }

    return $config;
  }

  /**
  * Set Table Name
  *
  * @return query class
  */
  public static function table($name){
    $query = new query();
    $query->setTable($name);
    return $query;
  }

  /**
  * Change Database Usage
  */
  public static function use($name){

    // Change Current Config
    self::$currentdb = $name;
  }

  public static function beginTransaction()
  {
    return self::pdo()->beginTransaction();
  }

  public static function rollBack()
  {
    return self::pdo()->rollBack();
  }

  public static function commit()
  {
    return self::pdo()->commit();
  }

}
