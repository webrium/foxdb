# MySQL PHP Library
![](https://repository-images.githubusercontent.com/305963460/de429f74-51c4-42ec-b0cf-581b59f2df7e)


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
<br>

## Basic Where Clauses


### Methods: oldest / latest


```PHP
// Oldest
$oldest_user = DB::table('users')->oldest()->first();

// Latest
$latest_user = DB::table('users')->latest()->first();
```
<br>

### Methods: where / orWhere
#### Similar: and / or
```PHP

$list = DB::table('orders')->where('price','>',50)->orWhere('vip',true)->get();
```
<br>

### Methods: whereNot / whereNull / orWhereNot / orWhereNull / orWhereNotNull
#### Similar: not / null / notNull / orNot / orNull / orNotNull

```PHP
$users = DB::table('users')->whereNull('name')->get();
```

```PHP
$users = DB::table('users')->whereNot('name', 'BEN')->orWhereNot('name', 'jac')->get();
// OR
$users = DB::table('users')->not('name', 'BEN')->orNot('name', 'jac')->get();
```



<br>

## Aggregates
### Methods: count / sum / avg

```PHP
$count_user = DB::table('users')->count();
// OR
$count_user = DB::table('users')->count('id'); // Default is 'id'
```

```PHP
// Sum of values
$sum_payment = DB::table('payments')->sum('cost');

// Average
$avg_paymrnt = DB::table('payments')->avg('cost');
```

<br>


### Methods: whereIn / whereNotIn / orWhereIn / orWhereNotIn
#### Similar: in / notIn / orIn / orNotIn

```PHP
$users = DB::table('users')->whereNotIn('id', [10,15,18])->get();
// OR
$users = DB::table('users')->notIn('id', [10,15,18])->get();
```



<br>

## DateTime

### Methods: whereDate / orWhereTime / orWhereDate / orWhereTime
#### Similar: date / time / orDate / orTime
```PHP
$order = DB::table('orders')
            ->date('created_at', '2022-02-01')
            ->orDate('created_at', '2022-01-01')
            ->time('created_at', '15:00:00')
            ->get();
```
<br>

### Methods: whereYear / whereMonth / whereDay  / orWhereYear / orWhereMonth / orWhereDay
#### Similar: year / month / day / orYear / orMonth/ orDay

```PHP
$orders = DB::table('orders')->year('created_at', '2015')->get();
```

<br>

### Methods: is / true / false

```PHP
// True

$active_list = DB::table('users')->is('active')->get();
//OR 
$active_list = DB::table('users')->true('active')->get();
//OR
$active_list = DB::table('users')->where('active',true)->get();
```

```PHP

// False

$inactive_list = DB::table('users')->is('active', false)->get();
//OR 
$inactive_list = DB::table('users')->false('active')->get();
//OR
$inactive_list = DB::table('users')->where('active',false)->get();

```


