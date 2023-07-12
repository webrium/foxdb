<?php
require_once __DIR__ . '/config.php';

use Foxdb\DB;
use PHPUnit\Framework\TestCase;
use Foxdb\Schema;


class EachTest extends TestCase
{
   public function testCheckEmptyWhere()
   {

      (new Schema('categorys'))->drop();

      $table = new Schema('categorys');
      $table->id();
      $table->string('name')->utf8mb4();
      $table->timestamps();
      $table->create();

      
      $list = DB::table('categorys')->get();
      $this->assertSame( 0 , count($list));

      $index = 0;
      DB::table('categorys')->each(function($category)use(&$index){
         $index++;
      });

      DB::table('categorys')->chunk(2, function($res)use(&$index){
         $index++;
      });

      $this->assertSame( 0 , $index);

      $now = date('Y-m-d H:i:s');
      DB::table('categorys')->insert(['name'=>'test', 'created_at'=>$now, 'updated_at'=>$now]);

      
      $index = 0;
      DB::table('categorys')->each(function($category)use(&$index){
         $index++;
      });
      
   
      $this->assertSame( 1 , $index);



      DB::table('categorys')->insert(['name'=>'test2', 'created_at'=>$now, 'updated_at'=>$now]);
      DB::table('categorys')->insert(['name'=>'test3', 'created_at'=>$now, 'updated_at'=>$now]);
      
      $index = 0;
      DB::table('categorys')->each(function($category)use(&$index){
         $index++;
      });
      
   
      $this->assertSame( 3 , $index);

   }

   public function testChunk(){
      $now = date('Y-m-d H:i:s');
      DB::table('categorys')->insert(['name'=>'test4', 'created_at'=>$now, 'updated_at'=>$now]);
      DB::table('categorys')->insert(['name'=>'test5', 'created_at'=>$now, 'updated_at'=>$now]);
      DB::table('categorys')->insert(['name'=>'test6', 'created_at'=>$now, 'updated_at'=>$now]);
      DB::table('categorys')->insert(['name'=>'test7', 'created_at'=>$now, 'updated_at'=>$now]);
      DB::table('categorys')->insert(['name'=>'test8', 'created_at'=>$now, 'updated_at'=>$now]);

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
   
}
