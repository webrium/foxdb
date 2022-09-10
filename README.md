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

If you don't need an entire row, you may extract a single value from a record using the `value` method. This method will return the value of the column directly:

```PHP
$email = DB::table('users')->where('name', 'John')->value('email');
```

To retrieve a single row by its `id` column value, use the `find` method:

```PHP
$user = DB::table('users')->find(3);
```

<br>

### Retrieving A List Of Column Values

you may use the `pluck` method. In this example, we'll retrieve a collection of user titles:

```PHP
use Foxdb\DB;
 
$titles = DB::table('users')->pluck('title');
 
foreach ($titles as $title) {
    echo $title;
}
```

You may specify the column that the resulting collection should use as its keys by providing a second argument to the pluck method:

```PHP
$titles = DB::table('users')->pluck('title', 'name');
 
foreach ($titles as $name => $title) {
    echo $title;
}
```

<br>

### Chunking Results

If you need to work with thousands of database records, consider using the `chunk` method provided by the DB facade. This method retrieves a small chunk of results at a time and feeds each chunk into a closure for processing. For example, let's retrieve the entire users table in chunks of 100 records at a time:

```PHP
use Foxdb\DB;
 
DB::table('users')->orderBy('id')->chunk(100, function ($users) {
    foreach ($users as $user) {
        //
    }
});
```

You may stop further chunks from being processed by returning false from the closure:

```PHP
DB::table('users')->orderBy('id')->chunk(100, function ($users) {
    // Process the records...
 
    return false;
});
```

you may use the `each` method.

```PHP
use Foxdb\DB;
 
DB::table('users')->orderBy('id')->each(function ($user) {
    //
});
```

<br>

### Aggregates

The query builder also provides a variety of methods for retrieving aggregate values like `count`, `max`, `min`, `avg`, and `sum`. You may call any of these methods after constructing your query:

```PHP
use Foxdb\DB;
 
$users = DB::table('users')->count();
 
$price = DB::table('orders')->max('price');

Of course, you may combine these methods with other clauses to fine-tune how your aggregate value is calculated:

$price = DB::table('orders')
                ->where('finalized', 1)
                ->avg('price');
```

#### Determining If Records Exist

Instead of using the count method to determine if any records exist that match your query's constraints, you may use the exists and doesntExist methods:

```PHP
if (DB::table('orders')->where('finalized', 1)->exists()) {
    // ...
}
 
if (DB::table('orders')->where('finalized', 1)->doesntExist()) {
    // ...
}
```

<br>


### Select Statements

Specifying A Select Clause

You may not always want to select all columns from a database table. Using the `select` method, you can specify a custom "select" clause for the query:

```PHP
use Foxdb\DB;
 
$users = DB::table('users')
            ->select('name', 'email as user_email')
            ->get();
            
 // Or you can send as an array
 $users = DB::table('users')
            ->select(['name', 'email as user_email'])
            ->get();
```

But there is a more modern way to do this. You can act like the example below

```PHP
$users = DB::table('users')
         ->select(function($query){
            $query->field('name');
            $query->field('email')->as('user_email');
         })
         ->get();
```

<br>

### Raw Expressions

Sometimes you may need to insert an arbitrary string into a query. To create a raw string expression, you may use the `raw` method provided by the `DB` facade:

```PHP
$users = DB::table('users')
             ->select(DB::raw('count(*) as user_count, status'))
             ->where('status', '<>', 1)
             ->groupBy('status')
             ->get();
```
To use the parameter in raw like the example below

``
DB::raw('count(?)',['id'])
``
> ⚠️ Raw statements will be injected into the query as strings, so you should be extremely careful to avoid creating SQL injection vulnerabilities.

#### Our suggestion

But for this purpose, it is better to use the following method to avoid `SQL injection` attack
```PHP
$users = DB::table('users')
         ->select(function($query){
            $query->count('*')->as('user_count')
            $query->field('status');
         })
         ->get();
```
In this structure, you have access to `field`, `count`, `sum`, `avg`, `min`, `max`, `all`, `as` methods.

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


