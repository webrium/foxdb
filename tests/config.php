<?php declare(strict_types=1);

use Foxdb\DB;
use Foxdb\Config;

DB::addConnection('main', [
    'host'=>'db',
    'port'=>'3306',

    'database'=>'test',
    'username'=>'root',
    'password'=>'123456',

    'charset'=>Config::UTF8,
    'collation'=>Config::UTF8_GENERAL_CI,
    'fetch'=>Config::FETCH_CLASS
]);
