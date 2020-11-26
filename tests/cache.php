<?php
require_once __DIR__ . '/config.php';

use webrium\foxql\db;


// using cache

$start_time = microtime(true);

foreach (range(0, 20) as $number) {

  $data = db::table('users')->cache('user',function ($db)
  {
    return $db->where("username",'ben')->first();
  });

}

$end_time = microtime(true);

echo "Time using cache : " . ($end_time - $start_time);
echo "<br><br> access to cache : ";

echo "db::table('users')->cache('user')" ;


// without using cache

$start_time = microtime(true);

foreach (range(0, 20) as $number) {
    $data = db::table('users')->where("username",'ben')->first();
}

$end_time = microtime(true);

echo "Time without using cache : " . ($end_time - $start_time);
echo "<br><br>";
