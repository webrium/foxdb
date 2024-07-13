<?php
require_once __DIR__ . '/config.php';

use Foxdb\DB;
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

      $books = DB::table('books')->get();

      $_avg = 0;
      $_sum = 0;
      foreach ($books as $book) {
         $_avg += $book->price;
         $_sum += $book->price;
      }
      $_avg = $_avg / count($books);

      $price_avg = DB::table('books')->avg('price');
      $this->assertSame(intval($price_avg), $_avg);

      $price_sum = DB::table('books')->sum('price');
      $this->assertSame(intval($price_sum), $_sum);
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

      while($one->id == $two->id){
         $two = DB::table('users')->inRandomOrder()->first();
      }

      $this->assertNotSame($one->id, $two->id);
   }

   public function testCopyMethod(){
      $oldest_user = self::oldest()->first();
      $new_user = new self;

      $new_user->copy($oldest_user);
      $new_user->name = 'CLONE BEN';
      $new_user->phone = '0999000009';
      $new_user->save();

      $this->assertEquals($new_user->id, 8);
   }

   public function testFindMethod(){
      $user = self::where('name', 'CLONE BEN')->find();

      if($user){
         $user->name = 'ALI';
         $user->save();
   
         $this->assertEquals($user->id, 8);
         $this->assertEquals(self::where('name', 'ALI')->value('name'), 'ALI');
      }


      $user = self::where('name', 'CLONE BEN')->find();

      if($user == false){
         $user = new self;
         $user->name = 'BEN2';
         $user->phone = '0999000010';
         $user->email = 'test10@mail.com';
         $user->save();

         $this->assertEquals($user->id, 9);
      }

   }
}