# A query builder similar to Laravel
![](https://repository-images.githubusercontent.com/305963460/de429f74-51c4-42ec-b0cf-581b59f2df7e)
<div align="center">
 
[![Latest Stable Version](http://poser.pugx.org/webrium/foxql/v)](https://packagist.org/packages/webrium/foxql) [![Total Downloads](http://poser.pugx.org/webrium/foxql/downloads)](https://packagist.org/packages/webrium/foxql) [![Latest Unstable Version](http://poser.pugx.org/webrium/foxql/v/unstable)](https://packagist.org/packages/webrium/foxql) [![License](http://poser.pugx.org/webrium/foxql/license)](https://packagist.org/packages/webrium/foxql) [![PHP Version Require](http://poser.pugx.org/webrium/foxql/require/php)](https://packagist.org/packages/webrium/foxql)
 
</div>


### Attributes:
 - ✔️ Low use of resources
 - ✔️ Lighter and faster
 - ✔️ Similar to Laravel syntax
 - ✔️ Easy to configure and use

> The Foxdb query builder uses PDO parameter binding to protect your application against SQL injection attacks. There is no need to clean or sanitize strings passed to the query builder as query bindings.

<br>

## Install by composer
```
composer require webrium\foxdb
```

<br>

### Add Connection Config
```PHP
use Foxdb\DB;
use Foxdb\Config;

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
 > The `'main'` statement is the default name of the connection config

<br>

### Retrieving All Rows From A Table
You may use the `table` method provided by the `DB` facade to begin a query. The `table` method returns a fluent query builder instance for the given table, allowing you to chain more constraints onto the query and then finally retrieve the results of the query using the get method:

```PHP
use Foxdb\DB;

$users = DB::table('users')->get();

foreach ($users as $user) {
    echo $user->name;
}
```

<br>

### Retrieving A Single Row / Column From A Table
If you just need to retrieve a single row from a database table, you may use the DB facade's `first` method. This method will return a single stdClass object:

```PHP
$user = DB::table('users')->where('name', 'Jack')->first();
 
return $user->email;
```
<br>


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

The whereDate method may be used to compare a column's value against a date:
```PHP
$order = DB::table('orders')
            ->whereDate('created_at', '2022-02-01')
            ->get();
```

The 'whereMonth' method may be used to compare a column's value against a specific month:
```PHP
$users = DB::table('users')
                ->whereMonth('created_at', '12')
                ->get();
```
The 'whereDay' method may be used to compare a column's value against a specific day of the month:
```PHP
$users = DB::table('users')
                ->whereDay('created_at', '31')
                ->get();
```

The 'whereYear' method may be used to compare a column's value against a specific year:
```PHP
$users = DB::table('users')
                ->whereYear('created_at', '2018')
                ->get();
```

<br>

### Methods: whereBetween / orWhereBetween

The `whereBetween` method verifies that a column's value is between two values:

```PHP
$users = DB::table('users')
           ->whereBetween('votes', [1, 100])
           ->get();
```

### Methods: whereNotBetween / orWhereNotBetween

The `whereNotBetween` method verifies that a column's value lies outside of two values:
```PHP
$users = DB::table('users')
                    ->whereNotBetween('votes', [1, 100])
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

$active_list = DB::table('users')->where('active',true)->get();
//OR 
$active_list = DB::table('users')->true('active')->get();
//OR
$active_list = DB::table('users')->is('active')->get();
```

```PHP

// False

$inactive_list = DB::table('users')->where('active',false)->get();
//OR
$inactive_list = DB::table('users')->is('active', false)->get();
//OR 
$inactive_list = DB::table('users')->false('active')->get();


```


