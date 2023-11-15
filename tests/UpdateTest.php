<?php
require_once __DIR__ . '/config.php';

use Foxdb\DB;
use PHPUnit\Framework\TestCase;

class UpdateTest extends TestCase
{

   public function testUpdate_a_User()
   {

      $user = DB::table('users')->latest()->first();

      DB::table('users')->where('id', $user->id)->update([
         'status' => false
      ]);

      $status = DB::table('users')->where('id', $user->id)->value('status');
      $this->assertSame(intval($status), 0);
   }

   public function testIncrementAmount()
   {
      DB::table('books')->where('id', 1)->increment('amount');
      $amount = DB::table('books')->where('id',1)->value('amount');
      $this->assertSame($amount, 6);
   }

   public function testDecrementAmount(){
      DB::table('books')->where('id', 2)->decrement('amount', 4);
      $amount = DB::table('books')->where('id',2)->value('amount');
      $this->assertSame($amount, 10);
   }
}
