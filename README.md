# PHP Database Library
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
