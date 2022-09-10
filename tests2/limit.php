<?php
require_once __DIR__ . '/config.php';
require_once __DIR__.'/books.php';

use Foxdb\DB;

$res = books::latest()->first();
echo json_encode($res);
