<?php
require_once __DIR__ . '/config.php';

use webrium\foxql\db;

$data = db::table('users')->take(2)->skip(2)->get();
echo json_encode($data);
