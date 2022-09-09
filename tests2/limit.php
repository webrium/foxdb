<?php
//  declare(strict_types=1);
// $befor = memory_get_usage();

require_once __DIR__ . '/config.php';
// require_once __DIR__ . '/../vendor/autoload.php';

use Foxdb\DB;
use Foxdb\Main;



// $main = new Main;
// $res= $main->whereIn('id',[1.2])->count('id');

$res = Main::where(function($query){
    $query->where('id',1)->or('id',2);
})->get();
echo json_encode($res);
// echo "Using ", ((memory_get_usage()-$befor)/1e+6), " bytes of ram.";
// echo "\n";
