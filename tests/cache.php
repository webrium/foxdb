<?php
require_once __DIR__ . '/config.php';

use webrium\foxql\DB;

$data = db::table('users')->cache('user',function ($db)
{
  return $db->latest()->first();
});
