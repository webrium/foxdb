# FoxDB v4

![Webrium FoxDB Cover](https://repository-images.githubusercontent.com/305963460/5261e7d1-7f5a-449a-bad7-8d95fbba1b19)

<div align="center">

[![Latest Stable Version](http://poser.pugx.org/webrium/foxdb/v)](https://packagist.org/packages/webrium/foxdb)
[![Total Downloads](http://poser.pugx.org/webrium/foxdb/downloads)](https://packagist.org/packages/webrium/foxdb)
[![License](http://poser.pugx.org/webrium/foxdb/license)](https://packagist.org/packages/webrium/foxdb)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://www.php.net)
[![Tests](https://github.com/webrium/foxdb/actions/workflows/tests.yml/badge.svg)](https://github.com/webrium/foxdb/actions/workflows/tests.yml)

**A standalone PHP database library — Query Builder, Eloquent ORM, Schema Builder, and Migrations.**

</div>

---

FoxDB is the database layer of the [Webrium](https://github.com/webrium) framework, available as a standalone package. It gives you a fluent query builder for writing SQL without strings, an Eloquent-style ORM for working with your data as objects, a schema builder for managing your database structure in PHP, and a migration system for versioning those changes. All of this runs on top of PDO with no external dependencies beyond the driver itself.

---

## What's new in v4

Version 4 is a complete rewrite that adds a full ORM layer and brings FoxDB from a query builder into a proper database toolkit.

- **Eloquent ORM** — define your tables as PHP classes. Get mass assignment protection, automatic timestamps, attribute casting, dirty tracking (only changed columns are saved), and full JSON serialization out of the box.
- **Relations** — `hasOne`, `hasMany`, `belongsTo`, `belongsToMany`, `hasManyThrough`. Both lazy and eager loading are supported.
- **Soft Deletes** — mark rows as deleted without actually removing them, and restore them later. The scope is applied automatically, including when the trait is inherited from a parent model.
- **Schema Builder** — create and modify tables using a fluent Blueprint API instead of writing DDL by hand.
- **Migrations** — version your schema changes with `up()` / `down()` methods and roll them back whenever you need.
- **Collection** — a rich wrapper around query results with map, filter, sort, chunk, paginate, and full JSON serialization. Calling `toArray()` on a collection of models correctly uses each model's own serialization, so hidden fields, casts, and loaded relations all behave as expected.
- **Multi-driver** — MySQL, PostgreSQL, and SQLite all work correctly, with per-driver SQL generation for things like identifier quoting, upserts, and UPDATE syntax.
- **Query Log & Hooks** — log every query that runs, measure execution time, detect slow queries, and attach `beforeQuery` / `afterQuery` callbacks.
- **PHPUnit test suite** — unit tests for SQL generation and model logic, plus integration tests that run against all three drivers in CI.

---

## Requirements

- PHP **8.1** or higher
- PDO extension for your chosen database: `pdo_mysql`, `pdo_pgsql`, or `pdo_sqlite`

---

## Installation

```bash
composer require webrium/foxdb
```

---

## Table of Contents

- [Connection Setup](#connection-setup)
- [Query Builder](#query-builder)
  - [Fetching rows](#fetching-rows)
  - [WHERE conditions](#where-conditions)
  - [JOIN](#join)
  - [ORDER, GROUP BY, HAVING, LIMIT](#order-group-by-having-limit)
  - [Aggregates](#aggregates)
  - [INSERT](#insert)
  - [UPDATE](#update)
  - [DELETE](#delete)
  - [Pagination](#pagination)
  - [Raw SQL](#raw-sql)
  - [Transactions](#transactions)
  - [Debug helpers](#debug-helpers)
- [Eloquent ORM](#eloquent-orm)
  - [Defining a Model](#defining-a-model)
  - [Mass assignment](#mass-assignment)
  - [CRUD operations](#crud-operations)
  - [Dirty tracking](#dirty-tracking)
  - [Casts](#casts)
  - [Soft Deletes](#soft-deletes)
  - [Relations](#relations)
  - [Eager Loading](#eager-loading)
  - [Local Scopes](#local-scopes)
  - [Serialization](#serialization)
- [Collection](#collection)
- [Schema Builder](#schema-builder)
- [Migrations](#migrations)
- [Query Log](#query-log)
- [Error Handling](#error-handling)
- [Running Tests](#running-tests)

---

## Connection Setup

Before you can do anything, tell FoxDB how to connect to your database. You do this once — typically in your application bootstrap file — by calling `DB::addConnection()` with a configuration array.

```php
use Foxdb\DB;

DB::addConnection([
    'driver'           => 'mysql',       // 'mysql' | 'pgsql' | 'sqlite'
    'host'             => '127.0.0.1',
    'port'             => '3306',
    'database'         => 'my_db',
    'username'         => 'root',
    'password'         => 'secret',
    'charset'          => 'utf8mb4',
    'throw_exceptions' => true,          // default: true
]);
```

For SQLite, you only need the path to the database file:

```php
DB::addConnection([
    'driver'   => 'sqlite',
    'database' => '/var/data/my_app.sqlite',
]);
```

### Multiple connections

If your application talks to more than one database, register each connection under a name and switch between them as needed:

```php
DB::addConnection([...], 'main');
DB::addConnection([...], 'analytics');

DB::use('analytics');               // subsequent calls use this connection
DB::use('main');                    // switch back

DB::connection('analytics');        // get the raw Connection instance
```

You can also assign a specific connection to a Model by setting `$connection` on the model class (see [Defining a Model](#defining-a-model)).

---

## Query Builder

The query builder lets you construct and execute SQL queries using a fluent PHP API. Every query starts with `DB::table('table_name')`, which returns a `Builder` instance. You chain methods to build the query, and finish with a terminal method (`get`, `first`, `insert`, `update`, etc.) to execute it.

All user-supplied values are passed as PDO bindings — FoxDB never interpolates values directly into SQL, so you are protected from SQL injection without any extra sanitization on your part.

### Fetching rows

The most common operation is fetching rows from a table:

```php
// Get all rows — returns a Collection of stdClass objects
$users = DB::table('users')->get();

foreach ($users as $user) {
    echo $user->name;
}

// Get the first matching row — returns stdClass or false if nothing matches
$user = DB::table('users')->where('email', 'ali@example.com')->first();

if ($user) {
    echo $user->name;
}

// Get a single column value from the first matching row
$email = DB::table('users')->where('id', 5)->value('email');

// Get a flat array of values from a single column
$names = DB::table('users')->pluck('name');
// → ['Alice', 'Bob', 'Carol']

// Get an array keyed by another column — useful for dropdowns
$nameById = DB::table('users')->pluck('name', 'id');
// → [1 => 'Alice', 2 => 'Bob']

// Find a row by its primary key
$user = DB::table('users')->find(5);

// Select only certain columns
$users = DB::table('users')->select('id', 'name', 'email')->get();

// Select a raw expression
$stats = DB::table('orders')
    ->selectRaw('COUNT(*) as total, SUM(amount) as revenue')
    ->where('status', 'paid')
    ->first();

// Remove duplicate rows
$countries = DB::table('users')->distinct()->pluck('country');
```

When you need to process a very large number of rows without loading them all into memory at once, use `chunk` or `each`:

```php
// Process 200 rows at a time
DB::table('users')->orderBy('id')->chunk(200, function ($users) {
    foreach ($users as $user) {
        // process each user
    }

    // Return false from the callback to stop chunking early
});

// Simpler iteration with each()
DB::table('users')->orderBy('id')->each(function ($user) {
    // called once per row
});
```

### WHERE conditions

FoxDB supports every common SQL condition. The basic form is `where(column, value)` which assumes `=`, or `where(column, operator, value)` for other comparisons:

```php
->where('active', 1)                    // WHERE active = 1
->where('age', '>', 18)                 // WHERE age > 18
->where('role', '!=', 'banned')         // WHERE role != 'banned'
->orWhere('role', 'admin')              // OR role = 'admin'
->whereNot('status', 'deleted')         // WHERE status != 'deleted'
```

**IN / NOT IN** — check if a column value is in a list:

```php
->whereIn('id', [1, 2, 3])
->whereNotIn('status', ['banned', 'pending'])
->orWhereIn('role', ['admin', 'mod'])
```

**BETWEEN** — check if a value falls in a range:

```php
->whereBetween('age', 18, 65)
->whereNotBetween('score', 0, 10)
```

**NULL checks:**

```php
->whereNull('deleted_at')       // only rows where deleted_at IS NULL
->whereNotNull('verified_at')   // only rows where verified_at is set
```

**Raw expressions** — when you need SQL that the builder cannot produce:

```php
->whereRaw('YEAR(created_at) = ? AND MONTH(created_at) = ?', [2024, 3])
```

**Date helpers** — compare against parts of a datetime column:

```php
->whereDate('created_at', '=', '2024-01-15')    // full date match
->whereYear('created_at', '=', 2024)
->whereMonth('created_at', '=', 1)
->whereDay('created_at', '=', 15)
->whereTime('created_at', '>', '08:00:00')
```

**Column-to-column comparison:**

```php
->whereColumn('updated_at', '>', 'created_at')
```

**Grouped conditions** — wrap a group of conditions in parentheses by passing a closure:

```php
// WHERE (role = 'admin' OR role = 'mod') AND active = 1
DB::table('users')
    ->where(function ($q) {
        $q->where('role', 'admin')->orWhere('role', 'mod');
    })
    ->where('active', 1)
    ->get();
```

**Subquery conditions:**

```php
// WHERE EXISTS (SELECT 1 FROM orders WHERE orders.user_id = users.id)
->whereExists(function ($q) {
    $q->table('orders')->whereColumn('user_id', 'users.id');
})
```

**Shorthand methods** — for common patterns, FoxDB provides shorter alternatives:

```php
->is('active', 1)       // same as where('active', 1)
->true('active')        // same as where('active', 1)
->false('active')       // same as where('active', 0)
->null('deleted_at')    // same as whereNull('deleted_at')
->notNull('email')      // same as whereNotNull('email')
->in('id', [1,2,3])     // same as whereIn('id', [...])
->notIn('id', [4,5])    // same as whereNotIn('id', [...])
->like('name', '%ali%') // WHERE name LIKE '%ali%'
->and('age', '>', 18)   // same as where()
->or('role', 'admin')   // same as orWhere()
```

### JOIN

```php
// INNER JOIN — only rows that have a match in both tables
DB::table('users')
    ->join('orders', 'orders.user_id', '=', 'users.id')
    ->select('users.name', 'orders.total', 'orders.status')
    ->get();

// LEFT JOIN — all users, even those with no orders
DB::table('users')
    ->leftJoin('orders', 'orders.user_id', '=', 'users.id')
    ->select('users.name', 'orders.total')
    ->get();

->rightJoin('table', 'a', '=', 'b')
->crossJoin('tags')

// Join against a subquery
->joinSub($subQuery, 'alias', 'alias.user_id', '=', 'users.id')
```

### ORDER, GROUP BY, HAVING, LIMIT

```php
// Sort results
->orderBy('name')                   // ASC by default
->orderBy('created_at', 'desc')
->orderByDesc('score')              // shorthand for desc
->latest()                          // ORDER BY created_at DESC
->oldest()                          // ORDER BY created_at ASC
->inRandomOrder()                   // ORDER BY RAND() — useful for random picks

// Sort by multiple columns
->orderBy('role')->orderBy('name', 'desc')

// Group results and filter groups
->groupBy('country')
->having('total_users', '>', 100)
->havingRaw('COUNT(*) > 100')

// Limit the number of results
->limit(10)
->offset(20)
->take(10)->skip(20)    // aliases — take and skip are identical to limit and offset
```

### Aggregates

Aggregate methods execute the query immediately and return a single value:

```php
$count    = DB::table('users')->count();
$active   = DB::table('users')->where('active', 1)->count();
$revenue  = DB::table('orders')->where('status', 'paid')->sum('total');
$avgScore = DB::table('users')->avg('score');
$lowest   = DB::table('products')->min('price');
$highest  = DB::table('products')->max('price');

// Check if any matching row exists
$exists = DB::table('users')->where('email', 'ali@example.com')->exists();  // bool
```

### INSERT

```php
// Insert a single row
DB::table('users')->insert([
    'name'  => 'Ali',
    'email' => 'ali@example.com',
]);

// Insert and get the auto-increment ID back
$id = DB::table('users')->insertGetId([
    'name'  => 'Ali',
    'email' => 'ali@example.com',
]);

// Insert multiple rows in one query
DB::table('users')->insertBatch([
    ['name' => 'Ali',  'email' => 'ali@example.com'],
    ['name' => 'Sara', 'email' => 'sara@example.com'],
    ['name' => 'Reza', 'email' => 'reza@example.com'],
]);
```

### UPDATE

```php
// Update matching rows — returns the number of affected rows
$affected = DB::table('users')
    ->where('id', 1)
    ->update(['name' => 'New Name', 'updated_at' => date('Y-m-d H:i:s')]);

// Increment or decrement a numeric column
DB::table('users')->where('id', 1)->increment('login_count');
DB::table('users')->where('id', 1)->increment('score', 10);
DB::table('users')->where('id', 1)->decrement('credits', 5);

// You can also update other columns at the same time
DB::table('users')->where('id', 1)->increment('score', 10, [
    'last_activity' => date('Y-m-d H:i:s'),
]);

// Update if found, insert if not
DB::table('settings')->updateOrInsert(
    ['key' => 'theme'],             // lookup condition
    ['value' => 'dark']             // value to set
);
```

### DELETE

```php
// Delete matching rows
DB::table('users')->where('id', $id)->delete();

// Delete with multiple conditions
DB::table('sessions')
    ->where('user_id', $userId)
    ->where('created_at', 'delete();

// Remove all rows from the table
DB::table('cache')->truncate();
```

### Pagination

Pagination is a common need in any application that displays lists. FoxDB handles the total count, offset, and metadata for you:

```php
$page   = (int) ($_GET['page'] ?? 1);
$result = DB::table('posts')
    ->where('published', 1)
    ->orderBy('created_at', 'desc')
    ->paginate(15, $page);
```

The returned object has these properties:

|
 Property 
|
 Type 
|
 Description 
|
|
---
|
---
|
---
|
|
`total`
|
 int 
|
 Total number of matching rows 
|
|
`per_page`
|
 int 
|
 Rows per page 
|
|
`current_page`
|
 int 
|
 The page you requested 
|
|
`last_page`
|
 int 
|
 Total number of pages 
|
|
`from`
|
 int 
|
 Row number of the first result on this page 
|
|
`to`
|
 int 
|
 Row number of the last result on this page 
|
|
`data`
|
 Collection 
|
 The rows for this page 
|

```php
// Use in an API response
return [
    'meta' => [
        'total'        => $result->total,
        'current_page' => $result->current_page,
        'last_page'    => $result->last_page,
    ],
    'data' => $result->data->toArray(),
];
```

### Raw SQL

When you need to run SQL that the builder cannot express, you can execute raw statements directly:

```php
// Select — returns array of stdClass
$rows = DB::select('SELECT * FROM users WHERE active = ? AND age > ?', [1, 18]);

// Select a single row
$user = DB::selectOne('SELECT * FROM users WHERE id = ?', [$id]);

// Insert
DB::insert('INSERT INTO logs (level, message) VALUES (?, ?)', ['info', 'User logged in']);

// Insert and get the new ID
$id = DB::insertGetId('INSERT INTO users (name) VALUES (?)', ['Ali']);

// Update
$affected = DB::update('UPDATE users SET active = ? WHERE last_login < ?', [0, '2023-01-01']);

// Delete
$deleted = DB::delete('DELETE FROM logs WHERE created_at < ?', ['2023-01-01']);

// Any statement (DDL, etc.)
DB::statement('ALTER TABLE users ADD COLUMN bio TEXT NULL');

// Raw expression inside a query
$stats = DB::table('products')
    ->select(DB::raw('category_id, COUNT(*) as count, AVG(price) as avg_price'))
    ->groupBy('category_id')
    ->having(DB::raw('COUNT(*)'), '>', 5)
    ->get();
```

### Transactions

Transactions let you group multiple operations so that either all succeed or all are rolled back. The easiest way is to pass a closure to `DB::transaction()` — FoxDB will automatically commit if the closure returns normally, or roll back if it throws:

```php
DB::transaction(function () use ($fromId, $toId, $amount) {
    DB::table('accounts')->where('id', $fromId)->decrement('balance', $amount);
    DB::table('accounts')->where('id', $toId)->increment('balance', $amount);
    DB::table('transfers')->insert([
        'from_id' => $fromId,
        'to_id'   => $toId,
        'amount'  => $amount,
    ]);
});
```

If you need more control, you can manage the transaction manually:

```php
DB::beginTransaction();

try {
    // ... your operations ...
    DB::commit();
} catch (\Throwable $e) {
    DB::rollBack();
    throw $e;
}

DB::inTransaction(); // bool — check if currently inside a transaction
```

### Debug helpers

When you need to inspect the SQL that FoxDB is generating, these methods let you do so without running the query (or while running it):

```php
// See the SQL and bindings without executing
$sql      = DB::table('users')->where('active', 1)->orderBy('name')->toSql();
$bindings = DB::table('users')->where('active', 1)->orderBy('name')->getBindings();

// Dump SQL and bindings to output, then continue execution
DB::table('users')->where('active', 1)->dump()->get();

// Dump SQL and bindings to output, then stop (useful during development)
DB::table('users')->where('active', 1)->dd();
```

---

## Eloquent ORM

The ORM layer lets you work with your database tables as PHP classes. Each table maps to a Model class, and each row in that table becomes an instance of that class. Instead of writing queries everywhere, you interact with your data through objects.

### Defining a Model

Create a class that extends `Foxdb\Eloquent\Model`. At minimum you only need the class — FoxDB will infer the table name from it. Everything else is optional:

```php
use Foxdb\Eloquent\Model;

class User extends Model
{
    // The database table. If not set, FoxDB auto-derives it:
    // User → users, UserProfile → user_profiles (snake_case + plural s)
    protected string $table = 'users';

    // The primary key column. Defaults to 'id'.
    protected string $primaryKey = 'id';

    // Columns that may be set via create() or fill().
    // Only columns listed here can be mass-assigned.
    protected array $fillable = ['name', 'email', 'age', 'is_active'];

    // Alternatively, use $guarded to blocklist instead of allowlist.
    // An empty guarded array means everything is allowed.
    // protected array $guarded = [];

    // Columns excluded from toArray() and toJson() output.
    // Use this for passwords, tokens, and other sensitive fields.
    protected array $hidden = ['password', 'remember_token'];

    // Set to false if the table does not have created_at / updated_at columns.
    protected bool $timestamps = true;

    // Automatically cast column values to PHP types on read.
    protected array $casts = [
        'is_active' => 'bool',
        'age'       => 'int',
        'score'     => 'float',
        'settings'  => 'array',    // stored as JSON, returned as array
        'born_at'   => 'datetime', // returned as a DateTime object
    ];

    // Use a specific named connection instead of the default.
    protected ?string $connection = null;
}
```

### Mass assignment

Mass assignment means setting multiple attributes at once via `create()` or `fill()`. FoxDB protects you from accidentally setting columns you did not intend to expose — for example, an `is_admin` field that a user might inject through a form.

You control this with two properties:

- **`$fillable`** — an allowlist. Only these columns can be mass-assigned.
- **`$guarded`** — a blocklist. Everything except these columns can be mass-assigned. Set it to `[]` to allow all columns.

```php
// $fillable = ['name', 'email'] — 'role' is blocked
User::create(['name' => 'Ali', 'email' => 'a@b.com', 'role' => 'admin']);
// role is silently ignored

// If you need to bypass the guard (e.g. in a seeder), use forceFill()
$user = new User();
$user->forceFill(['name' => 'Ali', 'role' => 'admin'])->save();
```

### CRUD operations

**Creating records:**

```php
// create() fills, saves, and returns the new model
$user = User::create(['name' => 'Ali', 'email' => 'ali@example.com', 'age' => 25]);
echo $user->id;          // the auto-increment ID from the database
echo $user->created_at;  // set automatically

// Alternatively, use new + save()
$user        = new User();
$user->name  = 'Ali';
$user->email = 'ali@example.com';
$user->save();
```

**Reading records:**

```php
// All rows — returns a Collection
$users = User::all();

// By primary key
$user = User::find(1);          // returns User or null
$user = User::findOrFail(1);    // returns User or throws ModelNotFoundException

// First matching row
$user = User::where('email', 'ali@example.com')->first();
$user = User::firstWhere('email', 'ali@example.com');   // shorthand

// You can chain any Builder method
$admins = User::where('role', 'admin')
              ->where('active', 1)
              ->orderBy('name')
              ->get();

// Aggregates
$count = User::where('active', 1)->count();
$avg   = User::avg('score');

// Check existence
$exists = User::exists(['email' => 'ali@example.com']); // bool
```

**Updating records:**

```php
// Change attributes and call save() — only the changed columns are written
$user       = User::findOrFail(1);
$user->name = 'New Name';
$user->save();

// Mass update via query — affects all matching rows
User::where('active', 0)->update(['score' => 0]);
```

**Deleting records:**

```php
// Delete a single model instance
$user = User::findOrFail(1);
$user->delete();

// Delete all rows matching a condition
User::where('created_at', 'delete();
```

**Reloading from the database:**

```php
// fresh() returns a new instance fetched from the DB, leaving the original untouched
$fresh = $user->fresh();

// refresh() updates the current instance in place
$user->refresh();
```

### Dirty tracking

FoxDB tracks which attributes have changed since the model was last loaded or saved. This is how it knows to only include changed columns in an UPDATE statement:

```php
$user = User::find(1);  // loaded: name = 'Ali'

$user->isDirty();        // false — nothing has changed yet

$user->name = 'New Name';

$user->isDirty();        // true
$user->isDirty('name');  // true
$user->isDirty('email'); // false — email was not changed

$user->getDirty();       // ['name' => 'New Name']

$user->save();           // UPDATE users SET name = ? WHERE id = ?
                         // email is NOT included in the query
```

### Casts

Casts tell FoxDB how to convert a raw database value (always a string or null from PDO) into a proper PHP type when you read an attribute. The cast is applied automatically — you never have to convert values manually.

|
 Cast type 
|
 Aliases 
|
 What you get 
|
|
---
|
---
|
---
|
|
`int`
|
`integer`
|
 PHP 
`int`
|
|
`float`
|
`double`
, 
`real`
|
 PHP 
`float`
|
|
`bool`
|
`boolean`
|
 PHP 
`bool`
|
|
`string`
|
 — 
|
 PHP 
`string`
|
|
`array`
|
`json`
|
 PHP 
`array`
 — decoded from JSON on read, encoded back to JSON on write 
|
|
`object`
|
 — 
|
`stdClass`
 — decoded from JSON 
|
|
`datetime`
|
`date`
|
`DateTime`
 object 
|
|
`immutable_datetime`
|
 — 
|
`DateTimeImmutable`
 object 
|

```php
class User extends Model
{
    protected array $casts = [
        'is_active' => 'bool',
        'age'       => 'int',
        'score'     => 'float',
        'settings'  => 'array',
        'born_at'   => 'datetime',
    ];
}

$user = User::find(1);

$user->is_active;  // true or false, not "1" or "0"
$user->age;        // 25 (int), not "25" (string)
$user->settings;   // ['theme' => 'dark', 'lang' => 'fa'] — decoded from JSON
$user->born_at;    // DateTime object — can call ->format(), ->diff(), etc.

// Casts work in reverse on write — the array is JSON-encoded before saving
$user->settings = ['theme' => 'light'];
$user->save();     // stores '{"theme":"light"}' in the database
```

### Soft Deletes

Sometimes you want to "delete" a record without actually removing it from the database — so you can restore it later, or keep a history of what was deleted. FoxDB supports this via the `HasSoftDeletes` trait.

When you call `delete()` on a model with soft deletes, FoxDB sets a `deleted_at` timestamp instead of issuing `DELETE`. All subsequent queries automatically exclude soft-deleted rows, so they are invisible to the rest of your application unless you explicitly ask for them.

Your table needs a nullable `deleted_at` column (use `$table->softDeletes()` in your migration).

```php
use Foxdb\Eloquent\Concerns\HasSoftDeletes;

class Post extends Model
{
    use HasSoftDeletes;
}
```

```php
$post = Post::find(1);
$post->delete();           // sets deleted_at — the row stays in the database

Post::find(1);             // returns null — soft-deleted rows are excluded by default
Post::count();             // does NOT count soft-deleted rows

$post->trashed();          // true — check if this instance has been soft-deleted

// Include soft-deleted rows in a query
$all = Post::withTrashed()->get();
$all = Post::withTrashed()->find(1);  // returns the soft-deleted post

// Query only the soft-deleted rows
$deleted = Post::onlyTrashed()->get();

// Restore a soft-deleted record
Post::withTrashed()->find(1)->restore();  // clears deleted_at
```

Soft deletes also work when `HasSoftDeletes` is applied on a parent model and inherited by a subclass:

```php
class BaseModel extends Model
{
    use HasSoftDeletes;
}

class Post extends BaseModel
{
    protected string $table = 'posts';
}

// The scope is applied correctly — Post inherits from BaseModel
Post::find(1);  // still excludes soft-deleted rows
```

### Relations

Relations describe how your tables are connected. You define them as methods on your model that return a relation object. FoxDB then uses these to build the correct JOIN or subquery automatically.

**HasMany** — one user has many posts (`posts.user_id` points to `users.id`):

```php
class User extends Model
{
    public function posts(): HasMany
    {
        // hasMany(related model, foreign key on related table, local key)
        return $this->hasMany(Post::class, 'user_id', 'id');
    }
}
```

**HasOne** — one user has one profile:

```php
public function profile(): HasOne
{
    return $this->hasOne(Profile::class, 'user_id', 'id');
}
```

**BelongsTo** — the post knows which user it belongs to (`posts.user_id` → `users.id`):

```php
class Post extends Model
{
    public function author(): BelongsTo
    {
        // belongsTo(related model, foreign key on THIS table, owner key on related table)
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
```

**BelongsToMany** — users can have many roles, roles can belong to many users, through a pivot table:

```php
public function roles(): BelongsToMany
{
    // belongsToMany(related, pivot table, FK for this model, FK for related model)
    return $this->belongsToMany(Role::class, 'user_role', 'user_id', 'role_id');
}
```

**HasManyThrough** — get all comments on a user's posts, without going through Post:

```php
public function comments(): HasManyThrough
{
    return $this->hasManyThrough(
        Comment::class,  // the final model you want
        Post::class,     // the intermediate model
        'user_id',       // FK on posts pointing to users
        'post_id',       // FK on comments pointing to posts
        'id',            // local key on users
        'id',            // local key on posts
    );
}
```

**Lazy loading** — relations are loaded the first time you access them and then cached on the instance:

```php
$user = User::find(1);

$posts   = $user->posts;    // runs a query, returns Collection
$posts   = $user->posts;    // uses the cached result — no second query

$author  = $post->author;   // User|null
$profile = $user->profile;  // Profile|null
```

**Pivot methods for BelongsToMany:**

```php
// Add a role to a user
$user->roles()->attach(3);
$user->roles()->attach([3, 5, 7]);

// Add with data on the pivot row
$user->roles()->attach(3, ['granted_at' => date('Y-m-d')]);

// Remove a role
$user->roles()->detach(3);
$user->roles()->detach();       // remove all

// Sync — attach the given IDs and detach everything else
$user->roles()->sync([3, 5]);

// Include pivot columns when loading the relation
$roles = $user->roles()->withPivot('granted_at', 'expires_at')->get();
echo $roles->first()->pivot->granted_at;
```

**BelongsTo helpers:**

```php
// Set the foreign key by passing the related model (does not save automatically)
$post->author()->associate($user);
$post->save();

// Clear the foreign key
$post->author()->dissociate();
$post->save();
```

### Eager Loading

The problem with lazy loading is that if you load 100 users and then access `$user->posts` for each one, you end up running 101 queries — one for the users and one per user for their posts. This is the N+1 problem.

Eager loading solves this by loading all the related data in one additional query:

```php
// Without eager loading — runs 1 + N queries
$users = User::all();
foreach ($users as $user) {
    echo $user->posts->count(); // query per user!
}

// With eager loading — runs exactly 2 queries
$users = User::with('posts')->get();
foreach ($users as $user) {
    echo $user->posts->count(); // no query — already loaded
}
```

You can eager-load multiple relations at once:

```php
$users = User::with('posts', 'profile', 'roles')->get();
```

You can also constrain what gets loaded — for example, only published posts:

```php
$users = User::with([
    'posts' => fn($query) => $query->where('published', 1)->orderBy('created_at', 'desc')
])->get();
```

Eager-loaded relations are included in `toArray()` output automatically:

```php
$data = User::with('posts')->get()->toArray();
// $data[0]['posts'] → array of post arrays
```

### Local Scopes

Local scopes let you define reusable query conditions on your model. Define a method prefixed with `scope` and it becomes chainable as a static call without the prefix:

```php
class User extends Model
{
    // Scope to filter only active users
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', 1);
    }

    // Scope with a parameter
    public function scopeRole(Builder $q, string $role): Builder
    {
        return $q->where('role', $role);
    }

    // Scope for recent records
    public function scopeRecent(Builder $q, int $days = 7): Builder
    {
        return $q->where('created_at', '>=', date('Y-m-d', strtotime("-{$days} days")));
    }
}
```

```php
// Use the scope — drop the 'scope' prefix and call as static
User::active()->get();
User::role('admin')->get();
User::recent(30)->get();

// Scopes are fully chainable with each other and with other Builder methods
User::active()
    ->role('mod')
    ->recent()
    ->orderBy('name')
    ->paginate(20, $page);
```

### Serialization

When you want to convert a model — or a collection of models — to an array or JSON (for an API response, for example), use `toArray()` or `toJson()`. These methods respect your `$hidden` fields, apply all `$casts`, and include any loaded relations.

```php
$user = User::with('posts')->find(1);

$arr  = $user->toArray();   // ['id' => 1, 'name' => 'Ali', 'posts' => [...], ...]
$json = $user->toJson();    // same data as a JSON string
$json = (string) $user;     // identical to toJson()

json_encode($user);         // also works — Model implements JsonSerializable
```

For a collection:

```php
$users = User::with('posts')->get();

$arr  = $users->toArray();       // array of arrays — correct
$json = json_encode($users);     // correct

// Use in an API response
return ['ok' => true, 'users' => $users->toArray()];
```

> **Important:** Do not use `(array) $model` to convert a model to an array. PHP's object cast exposes internal protected properties with null-byte prefixed keys (`\u0000*\u0000table`, etc.), which will corrupt your JSON output. Always use `->toArray()`.

---

## Collection

`Collection` wraps the array of rows returned by `get()` and most Model query methods. It implements `ArrayAccess`, `Countable`, `IteratorAggregate`, and `JsonSerializable`, so you can treat it like an array in most situations while also having a rich set of transformation methods. All transformation methods return a **new** Collection and leave the original unchanged.

```php
$users = User::all();  // Collection

// Basic access
$users->count();
$users->isEmpty();
$users->isNotEmpty();
$users->first();
$users->first(fn($u) => $u->role === 'admin');   // first matching
$users->last();
$users->get(2);         // item at index 2, null if missing
$users->contains('role', 'admin');
$users->contains(fn($u) => $u->age > 18);

// Iteration — works like a normal array
foreach ($users as $user) {
    echo $user->name;
}
$users[0];              // ArrayAccess read

// Filtering and transformation
$active   = $users->filter(fn($u) => $u->active);
$inactive = $users->reject(fn($u) => $u->active);   // inverse of filter
$names    = $users->map(fn($u) => (object)['name' => strtoupper($u->name)]);

$users->each(fn($u, $index) => processUser($u));
// Return false from the callback to stop early

// Sorting
$byName   = $users->sortBy('name');
$byScore  = $users->sortByDesc('score');

// Slicing
$first5   = $users->take(5);
$after10  = $users->skip(10);
$unique   = $users->unique('email');   // first occurrence wins
$reversed = $users->reverse();
$merged   = $users->merge($otherCollection);

// Split into chunks — returns an array of Collections
$chunks = $users->chunk(100);

// Extracting data
$names    = $users->pluck('name');               // ['Ali', 'Sara', ...]
$nameById = $users->pluck('name', 'id');         // [1 => 'Ali', 2 => 'Sara']
$byId     = $users->keyBy('id');                 // plain array keyed by id
$grouped  = $users->groupBy('role');             // plain array grouped by role value

// Aggregates — operate on a column across all items
$total    = $users->sum('score');
$average  = $users->avg('score');
$lowest   = $users->min('score');
$highest  = $users->max('score');

// Serialization
$arr  = $users->toArray();       // array of arrays — uses Model::toArray() per item
$json = $users->toJson();
$json = json_encode($users);     // identical
(string) $users;                 // pretty-printed JSON
```

---

## Schema Builder

The Schema Builder lets you define your database structure in PHP rather than writing DDL statements by hand. It automatically generates the correct SQL for your database driver.

### Creating a table

```php
use Foxdb\Schema;
use Foxdb\Schema\Blueprint;

Schema::create('users', function (Blueprint $table) {
    $table->id();                                      // BIGINT AUTO_INCREMENT PRIMARY KEY

    // String columns
    $table->string('name');                            // VARCHAR(255)
    $table->string('email', 255)->unique();
    $table->char('code', 10);
    $table->text('bio')->nullable();
    $table->mediumText('content')->nullable();
    $table->longText('body')->nullable();

    // Numeric columns
    $table->integer('age')->default(0);
    $table->bigInteger('views')->default(0);
    $table->tinyInteger('status')->default(1);
    $table->float('score', 8, 2)->nullable();
    $table->decimal('price', 10, 2)->default(0);

    // Boolean
    $table->boolean('is_active')->default(true);

    // Special types
    $table->json('settings')->nullable();              // stored as TEXT/JSON
    $table->enum('role', ['admin', 'user', 'mod'])->default('user');
    $table->uuid('uuid');
    $table->binary('data')->nullable();

    // Dates and times
    $table->date('born_at')->nullable();
    $table->time('opens_at')->nullable();
    $table->dateTime('published_at')->nullable();
    $table->timestamp('last_login')->nullable();

    // Convenience shortcuts
    $table->timestamps();                              // adds created_at + updated_at
    $table->softDeletes();                             // adds deleted_at

    // Foreign keys
    $table->foreignId('category_id');                  // BIGINT UNSIGNED NOT NULL
    $table->foreignIdFor(Category::class);             // derives column from model

    // Indexes
    $table->index('role');
    $table->index(['first_name', 'last_name'], 'idx_full_name');
    $table->unique(['tenant_id', 'email']);
    $table->primary(['tenant_id', 'user_id']);         // composite primary key

    // Foreign key constraints
    $table->foreign('category_id')
          ->references('id')
          ->on('categories')
          ->cascadeOnDelete();
});
```

### Modifying an existing table

```php
Schema::table('users', function (Blueprint $table) {
    // Add a new column (nullable so existing rows are not affected)
    $table->integer('score')->nullable()->after('email')->change();

    // Rename a column
    $table->renameColumn('bio', 'about');

    // Remove columns
    $table->dropColumn('old_field');
    $table->dropColumn(['field_a', 'field_b']);

    // Remove indexes and constraints
    $table->dropIndex('idx_name');
    $table->dropUnique('idx_email');
    $table->dropForeign('fk_category_id');
});
```

### Other schema operations

```php
Schema::drop('users');                          // drop the table (fails if it doesn't exist)
Schema::dropIfExists('users');                  // safe version — no error if missing
Schema::rename('old_table', 'new_table');

Schema::hasTable('users');                      // bool — check if table exists
Schema::hasColumn('users', 'email');            // bool — check if column exists
Schema::getColumnNames('users');                // array — all column names
```

---

## Migrations

Migrations are PHP classes that describe a change to your database schema. Each migration has an `up()` method that applies the change and a `down()` method that reverses it. This lets you version your schema alongside your code and roll back changes when needed.

### Writing a migration

```php
use Foxdb\Migrations\Migration;
use Foxdb\Schema;
use Foxdb\Schema\Blueprint;

class CreateUsersTable extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
}
```

### Running migrations

```php
use Foxdb\Migrations\Migrator;

// Point the Migrator at the folder containing your migration files
$migrator = new Migrator('/path/to/migrations');

// Run all migrations that have not been run yet
$migrator->run();

// Run at most 3 pending migrations
$migrator->run(3);

// Roll back the last batch of migrations
$migrator->rollback();

// Roll back the last 2 batches
$migrator->rollback(2);

// Roll back everything — brings the database back to a blank state
$migrator->reset();

// Reset and then run everything — useful for refreshing a dev database
$migrator->refresh();

// Check what has and hasn't been run yet
$migrator->status();               // array of migration status

// Check if anything is pending
if ($migrator->hasPendingMigrations()) {
    echo "Database is not up to date.";
}
```

---

## Query Log

The query log lets you see every SQL statement that FoxDB executes, along with its bindings and execution time. This is useful for debugging, performance profiling, and detecting N+1 issues.

```php
// Enable logging before running your queries
DB::enableQueryLog();

$users = User::where('active', 1)->with('posts')->get();
$count = DB::table('orders')->where('status', 'paid')->count();

// Retrieve the log
$log = DB::getQueryLog();  // array of QueryLogEntry objects

foreach ($log as $entry) {
    echo $entry->sql;       // SELECT * FROM `users` WHERE `active` = ?
    echo $entry->time;      // execution time in milliseconds
    var_dump($entry->bindings);
}

// Shortcuts
DB::getLastQuery();                   // the most recent QueryLogEntry
DB::getQueryCount();                  // total number of queries run
DB::getTotalQueryTime();              // sum of all execution times in ms
DB::getSlowQueries(50.0);            // all queries that took more than 50ms

DB::disableQueryLog();
DB::flushQueryLog();                  // clear the log without disabling it
```

You can also attach hooks that fire before or after every query — useful for logging to an external system, adding metrics, or profiling:

```php
DB::beforeQuery(function (string $sql, array $bindings) {
    // Called just before each query is executed
    $this->logger->debug('Running query', ['sql' => $sql]);
});

DB::afterQuery(function (string $sql, array $bindings, float $timeMs) {
    // Called after each query completes
    if ($timeMs > 100) {
        $this->logger->warning('Slow query detected', ['sql' => $sql, 'time' => $timeMs]);
    }
});
```

---

## Error Handling

FoxDB throws typed exceptions so you can handle different failure scenarios specifically:

```php
use Foxdb\Exceptions\QueryException;
use Foxdb\Exceptions\DatabaseException;
use Foxdb\Exceptions\ModelNotFoundException;

// findOrFail() throws ModelNotFoundException when no row matches
try {
    $user = User::findOrFail(999);
} catch (ModelNotFoundException $e) {
    // return a 404 response
}

// QueryException wraps any PDO error — gives you the SQL and bindings
try {
    DB::table('users')->where('nonexistent_column', 1)->get();
} catch (QueryException $e) {
    echo $e->getSql();              // the compiled SQL
    echo $e->getErrorCode();        // the database error code
    echo $e->getFormattedMessage(); // full formatted error string
    var_dump($e->getParams());      // the bindings array
}
```

If you prefer not to use exceptions — for example, in a legacy codebase — set `'throw_exceptions' => false` when registering the connection. Failed queries will return `false` instead of throwing.

---

## Running Tests

FoxDB includes a PHPUnit test suite split into two groups:

- **Unit tests** require no database and test SQL generation and model logic.
- **Integration tests** run real queries against a database. You choose which driver to use via an environment variable.

```bash
# Unit tests only — no database needed
vendor/bin/phpunit --testsuite=unit

# Integration tests with SQLite (no server required)
DB_DRIVER=sqlite vendor/bin/phpunit --testsuite=integration

# Integration tests with MySQL
DB_DRIVER=mysql DB_DATABASE=foxdb_test DB_PASSWORD=secret \
    vendor/bin/phpunit --testsuite=integration

# Integration tests with PostgreSQL
DB_DRIVER=pgsql DB_PORT=5432 DB_DATABASE=foxdb_test DB_PASSWORD=secret \
    vendor/bin/phpunit --testsuite=integration

# Run everything
DB_DRIVER=sqlite vendor/bin/phpunit --testsuite=all
```

CI runs all three drivers automatically on every pull request via GitHub Actions.

---

## License

Apache-2.0 — see [LICENSE](LICENSE).