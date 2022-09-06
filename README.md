# MySQL PHP Library
![](https://repository-images.githubusercontent.com/305963460/3f37a400-7c49-11eb-9bd8-2b04e15fdf19)

### Instal Foxql 
```
composer require webrium/foxql
```

### Add Connection Config
```PHP
use webrium\foxql\DB;

DB::addConnection('main', [
    'host'=>'localhost',
    'port'=>'3306',

    'database'=>'test',
    'username'=>'root',
    'password'=>'1234',

    'charset'=>Config::UTF8,
    'collation'=>Config::UTF8_GENERAL_CI,
    'fetch'=>Config::FETCH_CLASS
]);
```


## SELECT

```PHP
// Find
$user = DB::table('users')->find($user_id);
```

```PHP
// Oldest
$oldest_user = DB::table('users')->oldest()->first();

// Latest
$latest_user = DB::table('users')->latest()->first();
```

```PHP
// Get list
$order_list = DB::table('order')->where('user_id',56)->where('price','>',50)->get();
```

> But you can also use a simpler structure like the example below




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
#### ( is , true , false )
The "**is**" method is true by default ,You can set the second parameter to **false**
```PHP
$list = DB::table('users')->is('confirm')->get();       //.. `confirm` = true
$list = DB::table('users')->is('confirm',false)->get(); //.. `confirm` = false


// You can use true or false method

DB::table('users')->true('confirm')->get();
DB::table('users')->false('confirm')->get();
```
