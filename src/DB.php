<?php
namespace webrium\foxql;


class DB extends Builder{


  protected static $CONFIG_LIST;
  protected static $USE_DATABASE = 'main';
  protected static $CHANGE_ONCE = false;


  public static function addConnection($config_name, array $config_params){
    self::$CONFIG_LIST[$config_name] = new Config($config_params);
  }

  private static function getCurrentConfig(){
    return self::$CONFIG_LIST[self::$USE_DATABASE];
  }

  public static function table($name){
    $config = self::getCurrentConfig();
    $builder = new Builder($config);
    $builder->setConfig(self::getCurrentConfig());
    $builder->setTable($name);
    return $builder;
  }

  public static function use(string $config_name){
    self::$USE_DATABASE = $config_name;
    return new static;
  }

  public static function useOnce(string $config_name){
    self::$USE_DATABASE = $config_name;
    self::$CHANGE_ONCE = true;
    return new static;
  }

  public static function beginTransaction(){
    DB::getCurrentConfig()->connect();
    DB::getCurrentConfig()->pdo()->beginTransaction();
  }

  public static function rollBack()
  {
    DB::getCurrentConfig()->pdo()->rollBack();
  }

  public static function commit()
  {
    DB::getCurrentConfig()->pdo()->commit();
  }

  public static function setTimestamp(){
    $now = date('Y-m-d H:i:s');

    return [
      'created_at' => $now,
      'updated_at' => $now
    ];
  }

  // public static function select(string $query, array $params=[]){
  //   $config = self::getConfig();
  //   $builder = new Builder($config);
  //   return $builder->execute($query, $params, true);
  // }

  
  // public static function update(string $query, array $params){
  //   $config = self::getConfig();
  //   $builder = new Builder($config);
  //   return $builder->execute($query, $params, false);
  // }


  // public static function insert(string $query, array $params, $getLastID=false){
  //   $config = self::getCurrentConfig();
  //   $builder = new Builder($config);
  //   $res = $builder->execute($query, $params, false);

  //   if($getLastID){
  //     $res = $config->pdo()->lastInsertId();
  //   }

  //   return $res;
  // }

  // public static function insertGetId(string $query, array $params){
  //   return self::insert($query, $params, true);
  // }

  // private static function getConfigByName($config_name){
  //   if (! isset(self::$CONFIG_LIST[$config_name])){
  //     throw new \Exception("'$config_name' config not found");
  //   }

  //   return self::$CONFIG_LIST[$config_name];
  // }

  // private static function getConfig(){
  //   return self::getConfigByName(self::$USE_DATABASE);
  // }

  

  // public static function test(){
  //   self::$CONFIG_LIST['main']->pdo();
  // }

  // public static function showConfigArray(){
  //   foreach(self::$CONFIG_LIST as $config){
  //     echo json_encode($config->getAsArray())."\n";
  //   }
  // }


  public static function raw($query, array $values = []){
    $raw = new Raw;
    $raw->setRawData($query, $values);
    return $raw;
  }

}
