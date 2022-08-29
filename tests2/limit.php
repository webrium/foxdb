<?php

$befor = memory_get_usage();

require_once __DIR__ . '/config.php';

use webrium\foxql\DB;

// $res = DB::table('users')->join('books.user_id','users.id')->get();
// $res = DB::table('users')->join('books.user_id','>','users.id')->get();
// $res = DB::table('users')
// ->select(function($query){
//     $query->raw('id * ? as id2',[2]);
// })
// ->whereRaw('id = ? or id = ?',[2,3])->get();
// DB::raw('id * ? as id3', [3])
$res =
DB::table('users')
->select(DB::raw('id * 5 as ttt'))
->get();
// $main = new Main;
// $res = $main->where('id',1)->get();
echo json_encode($res);

echo "\n";
echo "Using ", ((memory_get_usage()-$befor)/1e+6), " bytes of ram.";
echo "\n";
