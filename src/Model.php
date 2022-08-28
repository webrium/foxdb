<?php
namespace webrium\foxql;


 class Model {


    public function __construct()
    {
        echo "__construct\n";
    }

    public function __call($name, $arguments)
    {
      echo "normal $name";
    }

    public static function __callStatic($name, $arguments)
    {
      echo "static $name".json_encode($arguments)."\n";
      $builder = new Builder;
      $builder->setTable(self::getTable());
        return $builder->{$name}($arguments);
    }


    public static function getTable(){
        return self::$table;
    }


   
}