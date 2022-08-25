<?php
namespace webrium\foxql;


class DB{


  private static $CONFIG;


  public static function addConnection($config_name,array $config_params){
    $config = self::$CONFIG[$config_name] = new Config($config_params);
    $config->connect();
  }

  public static function test(){
    self::$CONFIG['main']->pdo();
  }

  public static function showConfigArray(){
    foreach(self::$CONFIG as $config){
      echo json_encode($config->getAsArray())."\n";
    }
  }



}
