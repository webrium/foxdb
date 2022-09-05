<?php
require_once __DIR__ . '/../vendor/autoload.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use webrium\foxql\DB;
use webrium\foxql\Config;

DB::addConnection('main', [
    'host'=>'localhost',
    'port'=>'3306',

    'database'=>'test',
    'username'=>'root',
    'password'=>'1234',

    'charset'=>Config::UTF8,
    'collation'=>Config::UTF8_GENERAL_CI,
    'fetch'=>Config::FETCH_CLASS
]);


DB::addConnection('second', [
    'host'=>'localhost',
    'port'=>'3306',

    'database'=>'test_2',
    'username'=>'root',
    'password'=>'1234',

    'charset'=>Config::UTF8,
    'collation'=>Config::UTF8_GENERAL_CI,
    'fetch'=>Config::FETCH_CLASS
]);

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

// db::addConfig('main',[
//   'driver'=>'mysql' ,
//   'db_host'=>'localhost' ,
//   'db_host_port'=>3306 ,
//   'db_name'=>'ce_panel' ,
//   'username'=>'root' ,
//   'password'=>'1234' ,
//   'charset'=>'utf8mb4' ,
//   'result_stdClass'=>true
// ]);

// db::addConfig('tv',[
//   'driver'=>'mysql' ,
//   'db_host'=>'localhost' ,
//   'db_host_port'=>3306 ,
//   'db_name'=>'mytvs.ir' ,
//   'username'=>'root' ,
//   'password'=>'1234' ,
//   'charset'=>'utf8mb4' ,
//   'result_stdClass'=>true
// ]);
