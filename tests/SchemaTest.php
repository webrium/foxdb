<?php
// require_once __DIR__ . '/config.php';

use Foxdb\DB;
use Foxdb\Schema;
use PHPUnit\Framework\TestCase;

class SchemaTest extends TestCase
{

  // public function testModify(){

  //   $table = new Schema('users');
  //   $table->addColumn()->integer('ttt')->change();
  //   $table->modifyColumn()->string('ttt')->change(true);

  //   // die;
  // }


    // public function testTest()
    // {
    //         $table = new Schema('users9');
    //         $table->id();
    //         $table->integer('age',3);
    //         $table->integer('amount');
    //         $table->bigInt('credit')->default(0)->nullable();
    //         $table->tinyInt('credit2')->nullable();
    //         $table->smallInt('credit3')->nullable();
    //         $table->mediumInt('credit4')->nullable();
    //         $table->text('note')->default('is empty');
    //         $table->tinyText('note3');
    //         $table->string('username');
    //         $table->string('password', 150);
    //         $table->timestamps();

    //         $table->create();
    // }

    public function testDropOldTestTables()
    {
        (new Schema('users'))->drop();
        (new Schema('books'))->drop();
        (new Schema('categorys'))->drop();
    
    
        $tables = DB::query('SHOW TABLES;', [], true);
        $this->assertCount(0, $tables);
    }
    
    public function testCreatedTables()
    {
    
        $table = new Schema('users');
        $table->id();
        $table->string('name')->utf8mb4();
        $table->string('phone');
        $table->string('fax');
        $table->boolean('status');
        $table->integer('age', 3)->nullable();
        $table->dateTime('register')->nullable();
        $table->timestamps();
        $table->create();
    
        $table = new Schema('books');
        $table->id();
        $table->string('code');
        $table->integer('user_id');
        $table->string('title')->utf8mb4();
        $table->text('text')->utf8mb4();
        $table->integer('amount');
        $table->integer('price');
        $table->timestamps();
        $table->create();
    
    
        $table = new Schema('categorys');
        $table->id();
        $table->string('name')->utf8mb4();
        $table->timestamps();
        $table->create();
    
        $tables = DB::query('SHOW TABLES;', [], true);
        $this->assertCount(3, $tables);
    
    
        $users_columns = DB::query('SHOW COLUMNS FROM users', [] , true);
        foreach($users_columns as $column){
            if($column->Field== 'name'){
                $this->assertEquals('NO', $column->Null, 'The name type must not be null');
            }
        }
    }
    
    
    public function testAddNewColumnToUsersTable(){
        $table = new Schema('users');
        $res = $table->addColumn()->boolean('active')->after('register')->default(1)->change();
    
        $columns = DB::query('SHOW COLUMNS FROM `users`', [], true);
    
        $status = false;
        $check_after = '';
        foreach($columns as $column){
            if($column->Field == 'active' && $check_after == 'register'){
                $status = true;
                break;
            }
            $check_after = $column->Field;
        }
    
        $this->assertTrue($status);
    }
    
    
    public function testRenameColumn(){
        $table = new Schema('users');
        $table->renameColumn('fax')->string('email', 150)->nullable()->change();
    
        $columns = DB::query('SHOW COLUMNS FROM `users`', [], true);
    
        $status = false;
    
        foreach($columns as $column){
            if($column->Field == 'email'){
                $status = true;
                break;
            }
        }
    
        $this->assertTrue($status);
    }
    
    
    public function testAddIndex(){
        $table = new Schema('users');
        $table->addIndex('phone',['phone'], Schema::INDEX_UNIQUE)->change();
        $table->addIndex('email',['email'])->change();
    
        $columns = DB::query('SHOW COLUMNS FROM `users`', [], true);
    
        $status = false;
    
        foreach($columns as $column){
            if($column->Field == 'phone' && $column->Key=='UNI'){
                $status = true;
                break;
            }
        }
    
        $this->assertTrue($status);
    }
    
    public function testDropIndex(){
        $table = new Schema('users');
    
        $columns = DB::query('SHOW COLUMNS FROM `users`', [], true);
    
        $status = false;
    
        foreach($columns as $column){
            if($column->Field == 'email' && $column->Key=='MUL'){
                $status = true;
                break;
            }
        }
    
        $this->assertTrue($status);
    
        $table->dropIndex('email')->change();
    
        $columns = DB::query('SHOW COLUMNS FROM `users`', [], true);
    
        $status = false;
    
        foreach($columns as $column){
            if($column->Field == 'email' && $column->Key=='MUL'){
                $status = true;
                break;
            }
        }
    
        $this->assertFalse($status);
    }
    
    public function testModifyColumn(){
        $table = new Schema('users');
        $table->addColumn()->integer('ttt')->change();
        $table->modifyColumn()->string('ttt')->change();


        $columns = DB::query('SHOW COLUMNS FROM `users`', [], true);
    
        $status = false;
    
        foreach($columns as $column){
            if($column->Field == 'ttt' && $column->Type=='varchar(255)'){
                $status = true;
                break;
            }
        }

        $this->assertTrue($status);

    }

    public function testDropColumn(){
      $table = new Schema('users');
      $table->dropColumn('ttt')->change();

      $columns = DB::query('SHOW COLUMNS FROM `users`', [], true);
    
        $status = false;
    
        foreach($columns as $column){
            if($column->Field == 'ttt'){
                $status = true;
                break;
            }
        }

        $this->assertFalse($status);
    }

}





