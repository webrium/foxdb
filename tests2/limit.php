<?php
//  declare(strict_types=1);
// $befor = memory_get_usage();

require_once __DIR__ . '/config.php';
// require_once __DIR__ . '/../vendor/autoload.php';

use webrium\foxql\DB;
use webrium\foxql\Main;
// use webrium\foxql\Model;



// $main = new Main;
// $res= $main->whereIn('id',[1.2])->count('id');

$res = DB::table('users')->paginate(2);
header('Content-Type: application/json ; charset=utf-8 ');
echo json_encode($res);
echo "\n";


// echo "Using ", ((memory_get_usage()-$befor)/1e+6), " bytes of ram.";
// echo "\n";
