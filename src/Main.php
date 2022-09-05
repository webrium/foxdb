<?php
namespace webrium\foxql;

require_once __DIR__ . '/../vendor/autoload.php';

use webrium\foxql\Model;

class Main extends Model{

    protected $table = 'users';
    // protected $visible = ['id','name','phone'];
}