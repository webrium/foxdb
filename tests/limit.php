<?php
require_once __DIR__ . '/config.php';

use webrium\foxql\DB;
use webrium\foxql\Config;

$db =new DB;





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
DB::test();
// DB::showConfigArray();

echo "\n";