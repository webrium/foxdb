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

//$res = DB::select('select * from item1 where id in(:in)',['in'=>"1,2,3"]);
// $res = DB::table('')->where('id',5)->orWhere('id','!=','2')->where('status',1)->get();
// echo json_encode($res);


$res = DB::table('')
->whereNotNull('phone')
->get();
echo json_encode($res);
// DB::showConfigArray();
// echo json_encode($res->get_source_value());

echo "\n";