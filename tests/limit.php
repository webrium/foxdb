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

$res = DB::select('select * from item1 where id=:id',[
    'id'=>2
]);

$res = DB::update('update item1 set status=:status where id=:id',[
    'status'=>0,
    'id'=>2
]);
// DB::showConfigArray();
echo json_encode($res);

echo "\n";