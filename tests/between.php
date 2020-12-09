<?php
require_once __DIR__ . '/config.php';

use webrium\foxql\db;


echo "between:<br>";

$res = DB::table('users')->between('age',[16,20])->get();
echo json_encode($res);

echo "<br>===========<br>orBetween:<br>";

$res = DB::table('users')
->where('name','ben')
->orBetween('age',[18,20])->get();
echo json_encode($res);

echo "<br>===========<br>notBetween:<br>";

$res = DB::table('users')
->where('name','ben')
->notBetween('age',[18,20])->get();
echo json_encode($res);

echo "<br>===========<br>orNotBetween:<br>";

$res = DB::table('users')
->where('name','ben')
->orNotBetween('age',[18,20])->get();
echo json_encode($res);
