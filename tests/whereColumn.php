<?php
require_once __DIR__ . '/config.php';

use webrium\foxql\db;

$data = db::table('users')->column('dateTime','<','updated_at')->get();
echo json_encode($data);
echo "<br>==============<br>";

$data = db::table('users')->column('dateTime','updated_at')->get();
echo json_encode($data);
echo "<br>==============<br>";
