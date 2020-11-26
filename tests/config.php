<?php
require_once __DIR__ . '/../vendor/autoload.php';

use webrium\foxql\db;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

db::addConfig('main',[
  'driver'=>'mysql' ,
  'db_host'=>'localhost' ,
  'db_host_port'=>3306 ,
  'db_name'=>'test' ,
  'username'=>'root' ,
  'password'=>'1234' ,
  'charset'=>'utf8mb4' ,
  'result_stdClass'=>true
]);

db::addConfig('two',[
  'driver'=>'mysql' ,
  'db_host'=>'localhost' ,
  'db_host_port'=>3306 ,
  'db_name'=>'two' ,
  'username'=>'root' ,
  'password'=>'1234' ,
  'charset'=>'utf8mb4' ,
  'result_stdClass'=>true
]);
