<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Foxdb\Model;

class books extends Model{

    protected $table = 'books';
    protected $timestamps = true;
}