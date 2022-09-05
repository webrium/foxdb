<?php
require_once __DIR__ . '/config.php';

use webrium\foxql\DB;
use PHPUnit\Framework\TestCase;

class InsertTest extends TestCase{
    
   public function testInsertUser(){
    parent::setUp();
    
    DB::table('users')->delete();
    $now = date('Y-m-d H:i:s');

    $params = [
        ['name'=>'BEN','phone'=>'0999000001','fax'=>'','created_at'=>$now],
        ['name'=>'Jo','phone'=>'0999000002','fax'=>'','created_at'=>$now],
        ['name'=>'Sofi','phone'=>'0999000003','fax'=>'','created_at'=>$now],
        ['name'=>'Tom','phone'=>'0999000004','fax'=>'','created_at'=>$now],
        ['name'=>'Nic','phone'=>'0999000005','fax'=>'','created_at'=>$now],
        ['name'=>'Shakira','phone'=>'0999000006','fax'=>'','created_at'=>$now],
        ['name'=>'Ebi','phone'=>'0999000007','fax'=>'','created_at'=>$now],
    ];

    foreach($params as $user){
        DB::table('users')->insert($user);
    }

    $count_result = intval(DB::table('users')->count());
    $this->assertSame(count($params),  $count_result);
   }
}





