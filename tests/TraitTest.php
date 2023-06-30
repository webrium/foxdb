<?php
require_once __DIR__ . '/config.php';

use Foxdb\ModelTrait;
use PHPUnit\Framework\TestCase;

class TraitTest extends TestCase
{

   use ModelTrait;

   protected $table = 'users';



   public function testWhere()
   {
      $user = self::where('name', 'BEN')->first();
      $this->assertSame('BEN', $user->name);
   }

   public function testJoin()
   {
      $list = self::select(function ($query) {
         $query->all('users');
         $query->field('books.title')->as('book_title');
         $query->field('books.id')->as('book_id');
      })
         ->join('books.user_id', 'users.id')
         ->orderBy('id', 'DESC')
         ->get();

      $this->assertSame($list[0]->book_title, 'First title');
      $this->assertSame($list[1]->book_title, 'Second title');
      $this->assertSame(count($list), 3);
   }

   public function testIn()
   {

      $oldest_user = self::oldest()->first();

      $users_1 = self::in('id', [$oldest_user->id, $oldest_user->id + 1, $oldest_user->id + 2])->get();
      $this->assertSame(3, count($users_1));

      $users_2 = self::notIn('id', [$oldest_user->id, $oldest_user->id + 1, $oldest_user->id + 2])->get();
      $this->assertSame(4, count($users_2));

      foreach ($users_1 as $u_1) {
         foreach ($users_2 as $u_2) {
            $this->assertNotSame($u_1->id, $u_2->id);
         }
      }
   }


   public function testMin()
   {

      $this->table = 'books';

      $price = self::min('price');
      $this->assertSame($price, 55000);

      $price = self::max('price');
      $this->assertSame($price, 188000);

      $price = self::avg('price');
      $this->assertSame(intval($price), 104333);

      $price = self::sum('price');
      $this->assertSame(intval($price), 313000);
   }

   public function testLike()
   {
      $list = self::where('phone', 'like', '%003')->get();
      $this->assertSame(1, count($list));

      $list = self::like('phone', '%003')->get();
      $this->assertSame(1, count($list));


      $list = self::where('phone', 'like', '%003')->orWhere('phone', 'like', '%004')->get();
      $this->assertSame(2, count($list));

      $list = self::like('phone', '%003')->orLike('phone', '%004')->get();
      $this->assertSame(2, count($list));
   }

   public function testRand()
   {
      $one = self::inRandomOrder()->first();
      $two = self::inRandomOrder()->first();

      if ($one->id == $two->id) {
         $two = self::inRandomOrder()->first();
      }

      $this->assertNotSame($one->id, $two->id);
   }
}
