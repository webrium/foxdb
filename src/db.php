<?php
namespace webrium\foxql;

class DB extends query{

  private static $db_configs = [];

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


  public static function table($name){
    $query = new query();
    $query->setConfig(self::firstConfig(),$name);
    return $query;
  }


}
