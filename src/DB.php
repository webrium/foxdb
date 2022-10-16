<?php
namespace Foxdb;

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


  public static function raw($query, array $values = []){
    $raw = new Raw;
    $raw->setRawData($query, $values);
    return $raw;
  }

}
