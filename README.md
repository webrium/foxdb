# MySQL PHP Library
![](https://repository-images.githubusercontent.com/305963460/3f37a400-7c49-11eb-9bd8-2b04e15fdf19)

### Instal Foxql 
```
composer require webrium/foxql
```

### Add Connection Config
```PHP
use webrium\foxql\DB;

DB::addConfig('main',[
  'driver'=>'mysql' ,
  'db_host'=>'localhost' ,
  'db_host_port'=>3306 ,
  'db_name'=>'test' ,
  'username'=>'root' ,
  'password'=>'1234' ,
  'charset'=>'utf8mb4' ,
  'result_stdClass'=>true
]);
```

> Now it is ready to work :)

## SELECT

```PHP
$user = DB::table('users')->find($user_id);
```

|Method|Alias|
|--|--|
|where|and|
|orWhere|or|
|is||
|true||
|false||

### where

```PHP
..->where('name','jan')->..
```

#### ( where , and )
```PHP
DB::table('uses')
->where('age','>',18)
->and('score','>',200)
->get();
```

#### ( where , or )
```PHP
DB::table('uses')
->where('age','>',18)
->or('score','>',200)
->get();
```
### ( is , true , false )
The "**is**" method is true by default ,You can set the second parameter to **false**
```PHP
$list = DB::table('users')->is('confirm')->get();       //.. `confirm` = true
$list = DB::table('users')->is('confirm',false)->get(); //.. `confirm` = false


// You can use true or false method

DB::table('users')->true('confirm')->get();
DB::table('users')->false('confirm')->get();
```
