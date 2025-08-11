<?php
require_once __DIR__ . '/config.php';

use Foxdb\DB;
use PHPUnit\Framework\TestCase;

class SelectTest extends TestCase
{

   public function testGetByFirst()
   {
      $user = DB::table('users')->where('name', 'BEN')->first();
      $this->assertSame('BEN', $user->name);
   }


   public function testGetList()
   {
      $users = DB::table('users')->get();
      $this->assertCount(7, $users);
   }

   public function testJoin()
   {
      $list = DB::table('users')->select(function ($query) {
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

   public function testWhereIn()
   {

      $oldest_user = DB::table('users')->oldest()->first();

      $users_1 = DB::table('users')->in('id', [$oldest_user->id, $oldest_user->id + 1, $oldest_user->id + 2])->get();
      $this->assertSame(3, count($users_1));

      $users_2 = DB::table('users')->notIn('id', [$oldest_user->id, $oldest_user->id + 1, $oldest_user->id + 2])->get();
      $this->assertSame(4, count($users_2));

      foreach ($users_1 as $u_1) {
         foreach ($users_2 as $u_2) {
            $this->assertNotSame($u_1->id, $u_2->id);
         }
      }
   }

   public function testWhereInWithEmptyArray()
   {
      // Test whereIn with empty array - should return all users (no WHERE constraint applied)
      $users = DB::table('users')->whereIn('id', [])->get();
      $this->assertSame(7, count($users)); // Should return all users since no WHERE clause added

      // Test whereNotIn with empty array - should return all users (no WHERE constraint applied)
      $users = DB::table('users')->whereNotIn('id', [])->get();
      $this->assertSame(7, count($users)); // Should return all users since no WHERE clause added

      // Test orWhereIn with empty array
      $users = DB::table('users')->where('id', '>', 0)->orWhereIn('id', [])->get();
      $this->assertSame(7, count($users)); // Should return all users since no WHERE clause added

      // Test orWhereNotIn with empty array
      $users = DB::table('users')->where('id', '>', 0)->orWhereNotIn('id', [])->get();
      $this->assertSame(7, count($users)); // Should return all users since no WHERE clause added
   }

   public function testWhereBetweenWithInsufficientArrays()
   {
      // Test whereBetween with empty array - should return all users (no WHERE constraint applied)
      $users = DB::table('users')->whereBetween('id', [])->get();
      $this->assertSame(7, count($users)); // Should return all users since no WHERE clause added

      // Test whereBetween with single element array - should return all users (no WHERE constraint applied)
      $users = DB::table('users')->whereBetween('id', [1])->get();
      $this->assertSame(7, count($users)); // Should return all users since no WHERE clause added

      // Test whereNotBetween with empty array - should return all users (no WHERE constraint applied)
      $users = DB::table('users')->whereNotBetween('id', [])->get();
      $this->assertSame(7, count($users)); // Should return all users since no WHERE clause added

      // Test orWhereBetween with insufficient array - should return all users (no WHERE constraint applied)
      $users = DB::table('users')->where('id', '>', 0)->orWhereBetween('id', [1])->get();
      $this->assertSame(7, count($users)); // Should return all users since no WHERE clause added
   }


   public function testMin()
   {

      $price = DB::table('books')->min('price');
      $this->assertSame($price, 55000);

      $price = DB::table('books')->max('price');
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

   public function testWehreLike()
   {
      $list = DB::table('users')->where('phone', 'like', '%003')->get();
      $this->assertSame(1, count($list));

      $list = DB::table('users')->like('phone', '%003')->get();
      $this->assertSame(1, count($list));


      $list = DB::table('users')->where('phone', 'like', '%003')->orWhere('phone', 'like', '%004')->get();
      $this->assertSame(2, count($list));

      $list = DB::table('users')->like('phone', '%003')->orLike('phone', '%004')->get();
      $this->assertSame(2, count($list));
   }

   public function testWhereDate()
   {
      $list = DB::table('users')->whereDate('date_of_birth', '2002-02-28')->get();
      $this->assertSame(1, count($list));

      $list = DB::table('users')->whereDate('date_of_birth', '2000-10-04')->get();
      $this->assertSame(1, count($list));
   }

   public function testWhereYear()
   {
      $list = DB::table('users')->whereYear('date_of_birth', '2002')->get();
      $this->assertSame(3, count($list));
   }

   public function testWhereMonth()
   {
      $list = DB::table('users')->whereMonth('date_of_birth', '01')->orMonth('date_of_birth', 9)->get();
      $this->assertSame(4, count($list));

      $list = DB::table('users')->month('date_of_birth','!=', '01')->orMonth('date_of_birth', 9)->get();
      $this->assertSame(3, count($list));

      $user = DB::table('users')->whereMonth('date_of_birth', '10')->first();
      $this->assertSame('Nic', $user->name);
   }

   public function testWhereDay()
   {
      $list = DB::table('users')->whereDay('date_of_birth', '01')->get();
      $this->assertSame(3, count($list));

      $user = DB::table('users')->whereDay('date_of_birth', 28)->first();
      $this->assertSame('Sofi', $user->name);
   }

   public function testInRandomOrder()
   {
      $one = DB::table('users')->inRandomOrder()->first();
      $two = DB::table('users')->inRandomOrder()->first();

      while($one->id == $two->id){
         $two = DB::table('users')->inRandomOrder()->first();
      }

      $this->assertNotSame($one->id, $two->id);
   }


   public function testOrderBy()
   {

      // Use a field for order
      $books = DB::table('books')->orderBy('amount', 'asc')->get();
      $this->assertLessThan($books[count($books)-1]->amount, $books[0]->amount);


      // Using an array of fields for order
      $books = DB::table('books')->orderBy(['amount', 'price'], 'asc')->get();
      $this->assertLessThan($books[1]->price, $books[0]->price);
   }

   public function testChunk(){
      $now = date('Y-m-d H:i:s');

      DB::table('categorys')->select('id')->chunk(2, function($res){
         $this->assertSame( 2 , count($res));
      });

      $index = 0;
      DB::table('categorys')->select('id')->chunk(3, function($res)use(&$index){
         $index++;
         if($index==3){
            $this->assertSame( 2 , count($res));
         }
         else{
            $this->assertSame( 3 , count($res));
         }
      });


   }


   public function testEach()
   {
      $now = date('Y-m-d H:i:s');
      
      $index = 0;
      DB::table('categorys')->each(function($category)use(&$index){
         $index++;
      });
      
   
      $this->assertSame( 6 , $index);
   }

   public function testSelectAmount(){
      $count = DB::table('books')->where('amount', '>', 0)->count();
      $this->assertEquals($count, 3);
   }
}