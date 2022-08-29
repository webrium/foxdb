<?php
namespace webrium\foxql;

// require_once __DIR__ . '/../vendor/autoload.php';

// use webrium\foxql\Model;

class Main extends Model{

    protected $table = 'users';


    public static function ttt(){
        return self::oldest()->first();
    }


}