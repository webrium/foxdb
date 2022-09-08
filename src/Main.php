<?php
namespace Foxql;

require_once __DIR__ . '/../vendor/autoload.php';

use Foxql\Model;

class Main extends Model{

    protected $table = 'users';
    // protected $visible = ['id','name','phone'];
}