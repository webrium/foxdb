<?php

$befor = memory_get_usage();

require_once __DIR__ . '/config.php';

use webrium\foxql\DB;

// $res = DB::table('users')->join('books.user_id','users.id')->get();
// $res = DB::table('users')->join('books.user_id','>','users.id')->get();
$res = DB::table('users')->oldest()->first();
// $main = new Main;
// $res = $main->where('id',1)->get();
echo json_encode($res);

echo "\n";
echo "Using ", ((memory_get_usage()-$befor)/1e+6), " bytes of ram.";
echo "\n";
