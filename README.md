# A query builder similar to Laravel
![](https://repository-images.githubusercontent.com/305963460/de429f74-51c4-42ec-b0cf-581b59f2df7e)
<div align="center">
 
[![Latest Stable Version](http://poser.pugx.org/webrium/foxdb/v)](https://packagist.org/packages/webrium/foxdb) [![Total Downloads](http://poser.pugx.org/webrium/foxdb/downloads)](https://packagist.org/packages/webrium/foxdb) [![Latest Unstable Version](http://poser.pugx.org/webrium/foxdb/v/unstable)](https://packagist.org/packages/webrium/foxdb) [![License](http://poser.pugx.org/webrium/foxdb/license)](https://packagist.org/packages/webrium/foxdb) 
 
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
```

Of course, you may combine these methods with other clauses to fine-tune how your aggregate value is calculated:

```PHP
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

## Raw Methods

Instead of using the DB::raw method, you may also use the following methods to insert a raw expression into various parts of your query. Remember, Foxdb can not guarantee that any query using raw expressions is protected against SQL injection vulnerabilities.

### whereRaw / orWhereRaw

The whereRaw and orWhereRaw methods can be used to inject a raw "where" clause into your query. These methods accept an optional array of bindings as their second argument:

```PHP
$orders = DB::table('orders')
                ->whereRaw('price > IF(state = "TX", ?, 100)', [200])
                ->get();
```


### havingRaw / orHavingRaw

The havingRaw and orHavingRaw methods may be used to provide a raw string as the value of the "having" clause. These methods accept an optional array of bindings as their second argument:

```PHP
$orders = DB::table('orders')
                ->select('department', DB::raw('SUM(price) as total_sales'))
                ->groupBy('department')
                ->havingRaw('SUM(price) > ?', [2500])
                ->get();
```

<br>


## Inner Join Clause

The query builder may also be used to add join clauses to your queries. To perform a basic "inner join", you may use the join method on a query builder instance. The first argument passed to the join method is the name of the table you need to join to, while the remaining arguments specify the column constraints for the join. You may even join multiple tables in a single query:

```PHP
use Foxdb\DB;
 
$users = DB::table('users')
            ->join('contacts', 'users.id', '=', 'contacts.user_id')
            ->join('orders', 'users.id', '=', 'orders.user_id')
            ->select('users.*', 'contacts.phone', 'orders.price')
            ->get();
```

In Foxdb, you can do it more easily

```PHP
$users = DB::table('users')
        ->select('users.*', 'orders.price')
        ->join('orders.user_id', 'user.id')
        ->get();
```
In this structure, you enter the name of the table you want to join with its foreign key (`'orders.user_id'`) and then the primary key (`'user.id'`).

<br>

### Left Join / Right Join Clause

If you would like to perform a "left join" or "right join" instead of an "inner join", use the leftJoin or rightJoin methods. These methods have the same signature as the join method:

```PHP
$users = DB::table('users')
            ->leftJoin('posts', 'users.id', '=', 'posts.user_id')
            ->get();
 ```
 
 ```PHP
$users = DB::table('users')
            ->rightJoin('posts', 'users.id', '=', 'posts.user_id')
            ->get();
```

### Cross Join Clause

You may use the crossJoin method to perform a "cross join". Cross joins generate a cartesian product between the first table and the joined table:
```PHP
$sizes = DB::table('sizes')
            ->crossJoin('colors')
            ->get();
```

<br>


## Where Clauses

You may use the query builder's where method to add "where" clauses to the query. The most basic call to the where method requires three arguments. The first argument is the name of the column. The second argument is an operator, which can be any of the database's supported operators. The third argument is the value to compare against the column's value.

For example, the following query retrieves users where the value of the votes column is equal to 100 and the value of the age column is greater than 35:

```PHP
$users = DB::table('users')
                ->where('votes', '=', 100)
                ->where('age', '>', 35)
                ->get();
```

For convenience, if you want to verify that a column is = to a given value, you may pass the value as the second argument to the where method. Foxdb will assume you would like to use the = operator:

```PHP
$users = DB::table('users')->where('votes', 100)->get();
```

As previously mentioned, you may use any operator that is supported by your database system:

```PHP
$users = DB::table('users')
                ->where('votes', '>=', 100)
                ->get();
```

```PHP
$users = DB::table('users')
                ->where('votes', '<>', 100)
                ->get();
 ```
 
 ```PHP
$users = DB::table('users')
                ->where('name', 'like', 'T%')
                ->get();
```


<br>


### Or Where Clauses

When chaining together calls to the query builder's where method, the "where" clauses will be joined together using the and operator. However, you may use the orWhere method to join a clause to the query using the or operator. The orWhere method accepts the same arguments as the where method:

```PHP
$users = DB::table('users')
                    ->where('votes', '>', 100)
                    ->orWhere('name', 'John')
                    ->get();
```

If you need to group an "or" condition within parentheses, you may pass a closure as the first argument to the orWhere method:

```PHP
$users = DB::table('users')
            ->where('votes', '>', 100)
            ->orWhere(function($query) {
                $query->where('name', 'Abigail')
                      ->where('votes', '>', 50);
            })
            ->get();
```

The example above will produce the following SQL:

``
select * from users where votes > 100 or (name = 'Abigail' and votes > 50)
``

<br>

### Where Not Clauses

The `whereNot` and `orWhereNot` methods may be used to negate a given group of query constraints. For example, the following query excludes products that are on clearance or which have a price that is less than ten:

```PHP
$products = DB::table('products')
                ->whereNot(function ($query) {
                    $query->where('clearance', true)
                          ->orWhere('price', '<', 10);
                })
                ->get();
```

<br>




## Additional Where Clauses

### whereBetween / orWhereBetween

The `whereBetween` method verifies that a column's value is between two values:

```PHP
$users = DB::table('users')
           ->whereBetween('votes', [1, 100])
           ->get();
```

<br>

### whereNotBetween / orWhereNotBetween

The `whereNotBetween` method verifies that a column's value lies outside of two values:

```PHP
$users = DB::table('users')
                    ->whereNotBetween('votes', [1, 100])
                    ->get();
```

<br>


### whereIn / whereNotIn / orWhereIn / orWhereNotIn

The `whereIn` method verifies that a given column's value is contained within the given array:

```PHP
$users = DB::table('users')
                    ->whereIn('id', [1, 2, 3])
                    ->get();
```

The `whereNotIn` method verifies that the given column's value is not contained in the given array:

```PHP
$users = DB::table('users')
                    ->whereNotIn('id', [1, 2, 3])
                    ->get();
```










<br>

### whereNull / whereNotNull / orWhereNull / orWhereNotNull

The `whereNull` method verifies that the value of the given column is NULL:

```PHP
$users = DB::table('users')
                ->whereNull('updated_at')
                ->get();
```

The `whereNotNull` method verifies that the column's value is not NULL:

```PHP
$users = DB::table('users')
                ->whereNotNull('updated_at')
                ->get();
```

<br>

### whereDate / whereMonth / whereDay / whereYear / whereTime

The `whereDate` method may be used to compare a column's value against a date:

```PHP
$users = DB::table('users')
                ->whereDate('created_at', '2016-12-31')
                ->get();
```

The `whereMonth` method may be used to compare a column's value against a specific month:

```PHP
$users = DB::table('users')
                ->whereMonth('created_at', '12')
                ->get();
```

The `whereDay` method may be used to compare a column's value against a specific day of the month:

```PHP
$users = DB::table('users')
                ->whereDay('created_at', '31')
                ->get();
```

The `whereYear` method may be used to compare a column's value against a specific year:

```PHP
$users = DB::table('users')
                ->whereYear('created_at', '2016')
                ->get();
```

The `whereTime` method may be used to compare a column's value against a specific time:

```PHP
$users = DB::table('users')
                ->whereTime('created_at', '=', '11:20:45')
                ->get();
```

<br>

### whereColumn / orWhereColumn

The `whereColumn` method may be used to verify that two columns are equal:

```PHP
$users = DB::table('users')
                ->whereColumn('first_name', 'last_name')
                ->get();
```

You may also pass a comparison operator to the `whereColumn` method:

```PHP
$users = DB::table('users')
                ->whereColumn('updated_at', '>', 'created_at')
                ->get();
```







<br>

## Ordering, Grouping, Limit & Offset

### Ordering

#### The orderBy Method

The `orderBy` method allows you to sort the results of the query by a given column. The first argument accepted by the `orderBy` method should be the column you wish to sort by, while the second argument determines the direction of the sort and may be either asc or desc:

```PHP
$users = DB::table('users')
                ->orderBy('name', 'desc')
                ->get();
```

To sort by multiple columns, you may simply invoke orderBy as many times as necessary:

```PHP
$users = DB::table('users')
                ->orderBy('name', 'desc')
                ->orderBy('email', 'asc')
                ->get();
```

<br>

### The latest & oldest Methods

The `latest` and `oldest` methods allow you to easily order results by date. By default, the result will be ordered by the table's `created_at` column. Or, you may pass the column name that you wish to sort by:

```PHP
$user = DB::table('users')
                ->latest()
                ->first();

```







<br>

## Random Ordering

The inRandomOrder method may be used to sort the query results randomly. For example, you may use this method to fetch a random user:

```PHP
$randomUser = DB::table('users')
                ->inRandomOrder()
                ->first();
```


<br>



## Grouping

### The `groupBy` & `having` Methods

As you might expect, the `groupBy` and `having` methods may be used to group the query results. The `having` method's signature is similar to that of the where method:

```PHP
$users = DB::table('users')
                ->groupBy('account_id')
                ->having('account_id', '>', 100)
                ->get();
```



You may pass multiple arguments to the groupBy method to group by multiple columns:

```PHP
$users = DB::table('users')
                ->groupBy('first_name', 'status')
                ->having('account_id', '>', 100)
                ->get();
```

To build more advanced having statements, see the havingRaw method.

<br>

## Limit & Offset

### The skip & take Methods

You may use the `skip` and `take` methods to limit the number of results returned from the query or to skip a given number of results in the query:

```PHP
$users = DB::table('users')->skip(10)->take(5)->get();
```

Alternatively, you may use the `limit` and `offset` methods. These methods are functionally equivalent to the take and skip methods, respectively:

```PHP
$users = DB::table('users')
                ->offset(10)
                ->limit(5)
                ->get();
```


<br>

## Insert Statements

The query builder also provides an `insert` method that may be used to insert records into the database table. The insert method accepts an array of column names and values:

```PHP
DB::table('users')->insert([
    'email' => 'kayla@example.com',
    'votes' => 0
]);
```

### Auto-Incrementing IDs

If the table has an auto-incrementing id, use the `insertGetId` method to insert a record and then retrieve the ID:

```PHP
$id = DB::table('users')->insertGetId(
    ['email' => 'john@example.com', 'votes' => 0]
);
```
<br>

## Update Statements

In addition to inserting records into the database, the query builder can also update existing records using the update method. The update method, like the insert method, accepts an array of column and value pairs indicating the columns to be updated. The update method returns the number of affected rows. You may constrain the update query using where clauses:

```PHP
$affected = DB::table('users')
              ->where('id', 1)
              ->update(['votes' => 1]);
```

<br>

### Increment & Decrement

The query builder also provides convenient methods for incrementing or decrementing the value of a given column. Both of these methods accept at least one argument: the column to modify. A second argument may be provided to specify the amount by which the column should be incremented or decremented:

```PHP
DB::table('users')->increment('votes');
 
DB::table('users')->increment('votes', 5);
 
DB::table('users')->decrement('votes');
 
DB::table('users')->decrement('votes', 5);
```

<br>

## Special features:

You can use the more enjoyable Syntax, which in addition to shortening the code, also helps to make the code more readable

### Methods: is / true / false

To create queries based on boolean, you can use 'true' and 'false' or 'is' methods

```PHP

$active_list = DB::table('users')->is('active')->get();
// OR
$active_list = DB::table('users')->true('active')->get();
```

```PHP
$inactive_list = DB::table('users')->is('active', false)->get();
//OR 
$inactive_list = DB::table('users')->false('active')->get();
```

### Methods: and / or / in


You don't need to use the where method for your queries consecutively. You can use the and method or use or instead of orWhere.

Example:

```PHP
DB::table('users')
        ->is('active')
        ->and('credit', '>', 0)
        ->or('vip', true)
        
```


