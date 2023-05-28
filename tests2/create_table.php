<?php
require_once __DIR__ . '/config.php';

use Foxdb\Schema;

// $res = books::latest()->first();
$table = new Schema('users2');
$table->id();
$table->string('name')->utf8mb4();
$table->string('username')->utf8();
$table->string('password');
$table->boolean('active')->default(true);
$table->integer('age')->nullable();
$table->timestamps();

$result = $table->create();

echo "\n $result \n";
