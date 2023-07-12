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
      echo "\nCount (0: true) :".count($list)."\n";
      $this->assertSame( 0 , count($list));

      $index = 0;
      DB::table('categorys')->each(function($category)use(&$index){
         $index++;
      });

      $this->assertSame( 0 , $index);

      $now = date('Y-m-d H:i:s');
      DB::table('categorys')->insert(['name'=>'test', 'created_at'=>$now, 'updated_at'=>$now]);

      
      $index = 0;
      DB::table('categorys')->each(function($category)use(&$index){
         $index++;
         echo "category :".json_encode($category)."\n";
      });
      
      echo "Index Each (1: true) :".$index."\n";
   
      $this->assertSame( 1 , $index);



      DB::table('categorys')->insert(['name'=>'test', 'created_at'=>$now, 'updated_at'=>$now]);
      DB::table('categorys')->insert(['name'=>'test', 'created_at'=>$now, 'updated_at'=>$now]);
      
      $index = 0;
      DB::table('categorys')->each(function($category)use(&$index){
         $index++;
      });
      
      echo "Index Each (3: true) :".$index."\n";
   
      $this->assertSame( 3 , $index);

   }
   
}
