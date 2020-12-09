<?php
require_once __DIR__ . '/config.php';

use webrium\foxql\db;


echo "date:<br>";

$res = DB::table('users')
                  ->date('dateTime','2020-10-09')
                  ->orDate('dateTime','2020-09-21')
                  ->get();
echo json_encode($res);

echo "<br>===========<br>month:<br>";

$res = DB::table('users')
                  ->month('dateTime','10')
                  ->orMonth('dateTime','11')
                  ->get();
echo json_encode($res);

echo "<br>===========<br>day:<br>";

$res = DB::table('users')
                  ->day('dateTime','21')
                  ->orDay('dateTime','9')
                  ->get();
echo json_encode($res);

echo "<br>===========<br>year:<br>";

$res = DB::table('users')
                  ->year('dateTime','2020')
                  ->get();
echo json_encode($res);

echo "<br>===========<br>time:<br>";

$res = DB::table('users')
                  ->time('dateTime','07:27:00')
                  ->orTime('dateTime','12:36:00')
                  ->get();
echo json_encode($res);
