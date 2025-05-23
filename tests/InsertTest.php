<?php
// require_once __DIR__ . '/config.php';

use Foxdb\DB;
use Foxdb\Schema;
use PHPUnit\Framework\TestCase;

class InsertTest extends TestCase
{

    public function testInsertDataToUsersTable()
    {

        $now = date('Y-m-d H:i:s');
        DB::table('users')->truncate();

        /*
        | Insert
        */
        $user_params = [
            ['name' => 'BEN',   'phone' => '0999000001', 'date_of_birth'=>'2002-01-01 00:00:00',  'email' => 'test1@mail.com', 'created_at' => '2018-05-01 00:00:00'],
            ['name' => 'Jo',    'phone' => '0999000002', 'date_of_birth'=>'2002-01-18 00:00:00', 'email' => 'test2@mail.com', 'created_at' => $now],
            ['name' => 'Sofi',  'phone' => '0999000003', 'date_of_birth'=>'2002-02-28 00:00:00', 'email' => 'test3@mail.com', 'created_at' => $now],
            ['name' => 'Tom',   'phone' => '0999000004', 'date_of_birth'=>'2001-01-01 00:00:00', 'email' => 'test4@mail.com', 'created_at' => $now],
            ['name' => 'Nic',   'phone' => '0999000005', 'date_of_birth'=>'2000-10-04 00:00:00', 'email' => 'test5@mail.com', 'created_at' => $now],
            ['name' => 'Shakira','phone' => '0999000006', 'date_of_birth'=>'2000-09-01 00:00:00', 'email' => 'test6@mail.com', 'created_at' => $now],
        ];

        foreach ($user_params as $user) {
            DB::table('users')->insert($user);
        }

        $count_result = intval(DB::table('users')->count());
        $this->assertSame(count($user_params), $count_result);

    }

    public function testInsertDataToBooksTable()
    {
        $now = date('Y-m-d H:i:s');
        DB::table('books')->truncate();

        $last_user = DB::table('users')->latest('id')->first();

        $book_params = [
            ['user_id' => $last_user->id,     'code'=>'AS1', 'title' => 'First title', 'text' => 'test', 'amount' => 5, 'price' => 55000, 'created_at' => $now, 'updated_at' => $now],
            ['user_id' => $last_user->id - 1, 'code'=>'AS2', 'title' => 'Second title', 'text' => 'test', 'amount' => 14, 'price' => 70000, 'created_at' => $now, 'updated_at' => $now],
            ['user_id' => $last_user->id - 2, 'code'=>'AS3', 'title' => 'Third title', 'text' => 'test', 'amount' => 3, 'price' => 188000, 'created_at' => $now, 'updated_at' => $now],
            ['user_id' => 0, 'code'=>'AS4', 'title' => 'test 1', 'text' => 'test', 'amount' => 0, 'price' => 178000, 'created_at' => $now, 'updated_at' => $now],
            ['user_id' => 0,'code'=>'AS5', 'title' => 'test 2', 'text' => 'test', 'amount' => 0, 'price' => 168000, 'created_at' => $now, 'updated_at' => $now],
        ];

        foreach ($book_params as $user) {
            DB::table('books')->insert($user);
        }

        $count_result = intval(DB::table('books')->count());
        $this->assertSame(count($book_params), $count_result);
    }


    public function testInsertDataToCategorysTable(){
        $now = date('Y-m-d H:i:s');

        DB::table('categorys')->insert(['name'=>'test4', 'created_at'=>$now, 'updated_at'=>$now]);
        DB::table('categorys')->insert(['name'=>'test5', 'created_at'=>$now, 'updated_at'=>$now]);
        DB::table('categorys')->insert(['name'=>'test6', 'created_at'=>$now, 'updated_at'=>$now]);
        DB::table('categorys')->insert(['name'=>'test7', 'created_at'=>$now, 'updated_at'=>$now]);
        DB::table('categorys')->insert(['name'=>'test8', 'created_at'=>$now, 'updated_at'=>$now]);
        DB::table('categorys')->insert(['name'=>'test9', 'created_at'=>$now, 'updated_at'=>$now]);

        $count_result = intval(DB::table('categorys')->count());
        $this->assertEquals(6, $count_result);
    }

    public function testInsertGetId()
    {
        $now = date('Y-m-d H:i:s');

        $user = DB::table('users')->latest('id')->first();
        $last_id = DB::table('users')->insertGetId(['name' => 'Ebi', 'phone' => '0999000007', 'email' => '', 'created_at' => $now]);

        $this->assertSame(intval($user->id + 1), intval($last_id));

        $oldest_user = DB::table('users')->oldest()->first();
        $this->assertSame($oldest_user->id, 1);
    }

}





