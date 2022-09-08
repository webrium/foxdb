<?php
namespace Foxdb;

require_once __DIR__ . '/../vendor/autoload.php';

use Foxdb\Model;

class Main extends Model{

    protected $table = 'users';
    // protected $visible = ['id','name','phone'];
}