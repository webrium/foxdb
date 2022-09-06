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
$order_list = DB::table('orders')->where('user_id',56)->where('price','>',50)->get();
```

> But you can also use a modern structure like the example below

```PHP

// where() => and()
// whereNot() => not()
// orWhere() => or()
// orWhereNot => orNot()
// where('pain',true)

DB::table('orders')
->where('created_at','>','2022-1-1 00:00:00')
->and('price','>',50)
->or('vip',true)
->get();


// True or False 

// False Similar operations
// ..->where('paid', false)
// ..->is('paid', false)
// ..->false('pain')

// True Similar operations
// ..->where('paid', true)
// ..->is('paid')  Default is true
// ..->false('paid')

$active_list = DB::table('users')->is('active')->get();
```

### Methods: whereIn / whereNotIn / orWhereIn / orWhereNotIn
#### Similar: in / notIn / orIn / orNotIn
```PHP
$users = DB::table('users')->whereNotIn('id', [10,15,18])->get();
// OR
$users = DB::table('users')->notIn('id', [10,15,18])->get();
```
<br>

## Aggregates
### Methods: count / sum / avg
```PHP
$count_user = DB::table('users')->count();
// OR
$count_user = DB::table('users')->count('id'); // Default is 'id'

// Sum of values
$sum_payment = DB::table('payments')->sum('cost');

// Average
$avg_paymrnt = DB::table('payments')->avg('cost');
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


