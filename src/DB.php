<?php
namespace webrium\foxql;


class DB{


  protected static $CONFIG_LIST;
  protected static $USE_DATABASE = 'main';


  public static function addConnection($config_name,array $config_params){
    self::$CONFIG_LIST[$config_name] = new Config($config_params);
  }

  public static function table($name){
    $config = self::getConfig();
    $builder = new Builder($config);
    $builder->setTable($name);
    return $builder;
  }

  // public static function select(string $query, array $params=[]){
  //   $config = self::getConfig();
  //   $builder = new Builder($config);
  //   return $builder->execute($query, $params, true);
  // }

  
  public static function update(string $query, array $params){
    $config = self::getConfig();
    $builder = new Builder($config);
    return $builder->execute($query, $params, false);
  }


  public static function insert(string $query, array $params, $getLastID=false){
    $config = self::getConfig();
    $builder = new Builder($config);
    $res = $builder->execute($query, $params, false);

    if($getLastID){
      $res = $config->pdo()->lastInsertId();
    }

    return $res;
  }

  public static function insertGetId(string $query, array $params){
    return self::insert($query, $params, true);
  }

  private static function getConfigByName($config_name){
    if (! isset(self::$CONFIG_LIST[$config_name])){
      throw new \Exception("'$config_name' config not found");
    }

    return self::$CONFIG_LIST[$config_name];
  }

  private static function getConfig(){
    return self::getConfigByName(self::$USE_DATABASE);
  }

  

  public static function test(){
    self::$CONFIG_LIST['main']->pdo();
  }

  public static function showConfigArray(){
    foreach(self::$CONFIG_LIST as $config){
      echo json_encode($config->getAsArray())."\n";
    }
  }



}
