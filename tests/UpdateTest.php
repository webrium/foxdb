<?php
require_once __DIR__ . '/config.php';

use Foxdb\DB;
use PHPUnit\Framework\TestCase;

class UpdateTest extends TestCase
{

   public function testUpdateUser()
   {

      $user = DB::table('users')->latest()->first();
      DB::table('users')->where('id', $user->id)->update([
         'status' => false
      ]);

      $status = DB::table('users')->where('id', $user->id)->value('status');
      $this->assertSame(intval($status), 0);
   }

   public function testIncrement()
   {
      DB::table('books')->where('id', 1)->increment('amount');
      DB::table('books')->where('id', 2)->decrement('amount', 4);
      DB::table('books')->where('id', 3)->increment('amount', 2);


      $amount = DB::table('books')->where('id',1)->value('amount');
      $this->assertSame($amount, 6);


      $amount = DB::table('books')->where('id',2)->value('amount');
      $this->assertSame($amount, 10);

      $amount = DB::table('books')->where('id',3)->value('amount');
      $this->assertSame($amount, 5);
   }
}
