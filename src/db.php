<?php
namespace webrium\foxql;

class db extends query{

  private static $db_configs = [],$query=false;
  protected static $cache = [];


  public static function addConfig($name,$params)
  {
    self::$db_configs[$name] = $params;
  }

  private static function config($name)
  {
    return self::$db_configs[$name];
  }

  private static function firstConfig()
  {
    return reset(self::$db_configs);
  }


  public static function initConfig(){
    if (self::$query==false) {
      self::$query = new query();
      self::$query->setConfig(self::firstConfig());
    }
  }


  public static function table($name){
    self::initConfig();
    self::$query->setTable($name);
    return self::$query;
  }


  public static function beginTransaction()
  {
    self::initConfig();

    self::$query->connect();
    self::$query->getPdo()->beginTransaction();
  }

  public static function rollBack()
  {
    self::$query->getPdo()->rollBack();
  }

  public static function commit()
  {
    self::$query->getPdo()->commit();
  }

}
