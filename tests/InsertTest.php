<?php
require_once __DIR__ . '/config.php';

use Foxdb\DB;
use PHPUnit\Framework\TestCase;

class InsertTest extends TestCase{
    
   public function testInsertUser(){
    parent::setUp();
    $now = date('Y-m-d H:i:s');
    

    /*
    | Delete table
    */
    DB::table('users')->delete();
    $this->assertSame(0,  intval(DB::table('users')->count()));

    DB::table('users')->truncate();

    /*
    | Insert
    */
    $user_params = [
        ['name'=>'BEN','phone'=>'0999000001','fax'=>'','created_at'=>'2018-05-01 00:00:00'],
        ['name'=>'Jo','phone'=>'0999000002','fax'=>'','created_at'=>$now],
        ['name'=>'Sofi','phone'=>'0999000003','fax'=>'','created_at'=>$now],
        ['name'=>'Tom','phone'=>'0999000004','fax'=>'','created_at'=>$now],
        ['name'=>'Nic','phone'=>'0999000005','fax'=>'','created_at'=>$now],
        ['name'=>'Shakira','phone'=>'0999000006','fax'=>'','created_at'=>$now],
    ];

    foreach($user_params as $user){
        DB::table('users')->insert($user);
    }

    $count_result = intval(DB::table('users')->count());
    $this->assertSame(count($user_params),  $count_result);


    /*
    | Get last insert id
    */
    $user = DB::table('users')->latest()->first();    
    $last_id = DB::table('users')->insertGetId(['name'=>'Ebi','phone'=>'0999000007','fax'=>'','created_at'=>$now]);
    $this->assertSame(intval($user->id+1),  intval($last_id));


    $oldest_user = DB::table('users')->oldest()->first();
    $this->assertSame($oldest_user->id,'1');
   }

   public function testInsertBooks(){
    $now = date('Y-m-d H:i:s');
    

    DB::table('books')->truncate();



    $last_user = DB::table('users')->latest()->first();

    /*
    | Insert
    */
    $book_params = [
        ['user_id'=>$last_user->id, 'title'=>'First title', 'text'=>'test',   'amount'=>5, 'price'=>55000, 'created_at'=>$now, 'updated_at'=>$now],
        ['user_id'=>$last_user->id-1, 'title'=>'Second title', 'text'=>'test', 'amount'=>14, 'price'=>70000, 'created_at'=>$now, 'updated_at'=>$now],
        ['user_id'=>$last_user->id-2, 'title'=>'Third title', 'text'=>'test',  'amount'=>3, 'price'=>188000, 'created_at'=>$now, 'updated_at'=>$now],
    ];

    foreach($book_params as $user){
        DB::table('books')->insert($user);
    }

    $count_result = intval(DB::table('books')->count());
    $this->assertSame(count($book_params),  $count_result);

   }

}





