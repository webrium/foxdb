<?php

declare(types=1);

require __DIR__ . '/../vendor/autoload.php';

use Foxdb\Config;
use Foxdb\DB;
use Foxdb\Debug\QueryLogEntry;
use Foxdb\Exceptions\DatabaseException;
use Foxdb\Query\Builder;

function pass(string $msg): void { echo "\033[32m✔ {$msg}\033[0m\n"; }
function fail(string $msg): void { echo "\033[31m✘ {$msg}\033[0m\n"; exit(1); }

echo "\n=== FoxDB DB Facade Tests (MySQL) ===\n\n";

// -----------------------------------------------------------------------
// Always reset before starting so tests are isolated
// -----------------------------------------------------------------------
DB::reset();

// -----------------------------------------------------------------------
// 1. addConnection() + table() entry point (MySQL Setup)
// -----------------------------------------------------------------------
DB::addConnection([
    'driver'           => Config::MYSQL,
    'host'             => '127.0.0.1',
    'port'             => '3306',
    'database'         => 'test',
    'username'         => 'root',
    'password'         => '123456',
    'charset'          => 'utf8mb4',
    'collation'        => 'utf8mb4_unicode_ci',
    'fetch'            => Config::FETCH_OBJ,
    'throw_exceptions' => true,
]);

$builder = DB::table('users');
if (! $builder instanceof Builder) {
    fail('DB::table() should return a Builder instance');
}
pass('DB::addConnection() + DB::table() returns Builder');

// Clean up old tables if they exist
DB::statement('DROP TABLE IF EXISTS `orders`');
DB::statement('DROP TABLE IF EXISTS `users`');

// -----------------------------------------------------------------------
// Setup: MySQL Schemas
// -----------------------------------------------------------------------
DB::statement('
    CREATE TABLE `users` (
        `id`         INT AUTO_INCREMENT PRIMARY KEY,
        `name`       VARCHAR(255) NOT NULL,
        `email`      VARCHAR(255) NOT NULL,
        `age`        INT DEFAULT 0,
        `active`     TINYINT DEFAULT 1,
        `role`       VARCHAR(50) DEFAULT "user",
        `score`      DOUBLE DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
');

DB::statement('
    CREATE TABLE `orders` (
        `id`         INT AUTO_INCREMENT PRIMARY KEY,
        `user_id`    INT NOT NULL,
        `total`      DOUBLE DEFAULT 0,
        `status`     VARCHAR(50) DEFAULT "pending"
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
');
pass('DB::statement() DDL (MySQL Compatible)');

// -----------------------------------------------------------------------
// 2. DB::insert() — raw
// -----------------------------------------------------------------------
$ok = DB::insert(
    'INSERT INTO users (name, email, age, active, role, score) VALUES (?, ?, ?, ?, ?, ?)',
    ['Alice', 'alice@test.com', 30, 1, 'admin', 100.0],
);
if (! $ok) fail('DB::insert() failed');
pass('DB::insert() raw');

// -----------------------------------------------------------------------
// 3. DB::insertGetId() — raw
// -----------------------------------------------------------------------
$id = DB::insertGetId(
    'INSERT INTO users (name, email, age, active, role, score) VALUES (?, ?, ?, ?, ?, ?)',
    ['Bob', 'bob@test.com', 25, 1, 'user', 50.0],
);
if ((int)$id !== 2) fail("DB::insertGetId() expected 2, got {$id}");
pass("DB::insertGetId() → {$id}");

// Insert more rows via Builder
DB::table('users')->insert(['name' => 'Charlie', 'email' => 'c@test.com', 'age' => 20, 'active' => 1, 'role' => 'user', 'score' => 30.0]);
DB::table('users')->insert(['name' => 'Dave',    'email' => 'd@test.com', 'age' => 40, 'active' => 0, 'role' => 'user', 'score' => 10.0]);
DB::table('users')->insert(['name' => 'Eve',     'email' => 'e@test.com', 'age' => 35, 'active' => 1, 'role' => 'mod',  'score' => 70.0]);

DB::insert('INSERT INTO orders (user_id, total, status) VALUES (?, ?, ?)', [1, 250.0, 'paid']);
DB::insert('INSERT INTO orders (user_id, total, status) VALUES (?, ?, ?)', [1, 100.0, 'pending']);
DB::insert('INSERT INTO orders (user_id, total, status) VALUES (?, ?, ?)', [2, 80.0,  'paid']);

// -----------------------------------------------------------------------
// 4. DB::select() — raw
// -----------------------------------------------------------------------
$users = DB::select('SELECT * FROM users WHERE active = ?', [1]);
if (count($users) !== 4) fail('DB::select() expected 4, got ' . count($users));
if (! is_object($users[0])) fail('DB::select() rows should be objects');
pass('DB::select() raw');

// -----------------------------------------------------------------------
// 5. DB::selectOne() — raw
// -----------------------------------------------------------------------
$user = DB::selectOne('SELECT * FROM users WHERE id = ?', [1]);
if ($user === false || $user->name !== 'Alice') fail('DB::selectOne() wrong row');
pass('DB::selectOne() raw');

// -----------------------------------------------------------------------
// 6. DB::selectOne() — no match returns false
// -----------------------------------------------------------------------
$none = DB::selectOne('SELECT * FROM users WHERE id = ?', [999]);
if ($none !== false) fail('DB::selectOne() should return false for no match');
pass('DB::selectOne() → false on no match');

// -----------------------------------------------------------------------
// 7. DB::update() — raw
// -----------------------------------------------------------------------
$affected = DB::update('UPDATE users SET score = ? WHERE id = ?', [200.0, 1]);
if ($affected !== 1) fail("DB::update() expected 1, got {$affected}");
pass('DB::update() raw');

// -----------------------------------------------------------------------
// 8. DB::delete() — raw
// -----------------------------------------------------------------------
DB::insert('INSERT INTO users (name, email) VALUES (?, ?)', ['Temp', 'tmp@test.com']);
$deleted = DB::delete('DELETE FROM users WHERE name = ?', ['Temp']);
if ($deleted !== 1) fail("DB::delete() expected 1, got {$deleted}");
pass('DB::delete() raw');

// -----------------------------------------------------------------------
// 9. DB::affectingStatement()
// -----------------------------------------------------------------------
$rows = DB::affectingStatement('UPDATE users SET score = score + 1 WHERE active = ?', [1]);
if ($rows !== 4) fail("DB::affectingStatement() expected 4, got {$rows}");
pass('DB::affectingStatement()');

// -----------------------------------------------------------------------
// 10. DB::raw() → RawExpression in Builder
// -----------------------------------------------------------------------
$result = DB::table('users')
    ->select('name', DB::raw('score * 2 as double_score'))
    ->where('id', 1)
    ->first();

if ($result === false) fail('DB::raw() query returned false');
if (! property_exists($result, 'double_score')) fail('DB::raw() column missing');
pass('DB::raw() creates RawExpression usable in select()');

// -----------------------------------------------------------------------
// 11. DB::table() — full Builder chain
// -----------------------------------------------------------------------
$users = DB::table('users')
    ->where('active', 1)
    ->orderBy('score', 'desc')
    ->limit(3)
    ->get();

if (count($users) !== 3) fail('Builder chain via DB::table() failed: got ' . count($users));
pass('DB::table() full Builder chain (where + orderBy + limit + get)');

// -----------------------------------------------------------------------
// 12. DB::table() — count / sum / avg / min / max
// -----------------------------------------------------------------------
$count = DB::table('users')->where('active', 1)->count();
if ($count !== 4) fail("count() expected 4, got {$count}");
pass('DB::table()->count()');

$sum = DB::table('users')->where('active', 1)->sum('score');
if ((float)$sum <= 0) fail("sum() expected > 0, got {$sum}");
pass('DB::table()->sum()');

$avg = DB::table('users')->where('active', 1)->avg('age');
if ((float)$avg <= 0) fail("avg() expected > 0, got {$avg}");
pass('DB::table()->avg()');

$min = DB::table('users')->where('active', 1)->min('age');
$max = DB::table('users')->where('active', 1)->max('age');
if ((int)$min >= (int)$max) fail("min/max wrong: {$min} >= {$max}");
pass('DB::table()->min() / max()');

// -----------------------------------------------------------------------
// 13. DB::table() — exists / doesntExist
// -----------------------------------------------------------------------
if (! DB::table('users')->where('role', 'admin')->exists()) fail('exists() should be true');
if (! DB::table('users')->where('role', 'superadmin')->doesntExist()) fail('doesntExist() should be true');
pass('DB::table()->exists() / doesntExist()');

// -----------------------------------------------------------------------
// 14. DB::table() — find / value / pluck
// -----------------------------------------------------------------------
$user = DB::table('users')->find(2);
if ($user === false || $user->name !== 'Bob') fail("find() wrong: {$user->name}");
pass('DB::table()->find()');

$name = DB::table('users')->where('id', 1)->value('name');
if ($name !== 'Alice') fail("value() expected Alice, got {$name}");
pass('DB::table()->value()');

$names = DB::table('users')->where('active', 1)->orderBy('id')->pluck('name');
if (! in_array('Alice', $names)) fail("pluck() missing Alice: " . json_encode($names));
pass('DB::table()->pluck()');

$map = DB::table('users')->where('active', 1)->orderBy('id')->pluck('name', 'id');
if (($map[1] ?? null) !== 'Alice') fail("pluck(col, key) wrong: " . json_encode($map));
pass('DB::table()->pluck(col, key)');

// -----------------------------------------------------------------------
// 15. DB::table() — insertBatch
// -----------------------------------------------------------------------
$ok = DB::table('users')->insertBatch([
    ['name' => 'Batch1', 'email' => 'b1@test.com', 'age' => 22, 'active' => 1, 'role' => 'user', 'score' => 5.0],
    ['name' => 'Batch2', 'email' => 'b2@test.com', 'age' => 23, 'active' => 1, 'role' => 'user', 'score' => 6.0],
]);
if (! $ok) fail('insertBatch() failed');
$c = DB::table('users')->count();
if ($c !== 7) fail("After insertBatch expected 7 users, got {$c}");
pass('DB::table()->insertBatch()');

// -----------------------------------------------------------------------
// 16. DB::table() — update
// -----------------------------------------------------------------------
$affected = DB::table('users')->where('name', 'Bob')->update(['score' => 999.0]);
if ($affected !== 1) fail("update() expected 1, got {$affected}");
$score = DB::table('users')->where('name', 'Bob')->value('score');
if ((float)$score !== 999.0) fail("update() not persisted: {$score}");
pass('DB::table()->update()');

// -----------------------------------------------------------------------
// 17. DB::table() — increment / decrement
// -----------------------------------------------------------------------
DB::table('users')->where('id', 1)->increment('age', 5);
$age = DB::table('users')->where('id', 1)->value('age');
if ((int)$age !== 35) fail("increment() expected 35, got {$age}");
pass('DB::table()->increment()');

DB::table('users')->where('id', 1)->decrement('age', 3);
$age = DB::table('users')->where('id', 1)->value('age');
if ((int)$age !== 32) fail("decrement() expected 32, got {$age}");
pass('DB::table()->decrement()');

// -----------------------------------------------------------------------
// 18. DB::table() — delete
// -----------------------------------------------------------------------
DB::table('users')->where('name', 'Batch2')->delete();
$c = DB::table('users')->count();
if ($c !== 6) fail("After delete expected 6, got {$c}");
pass('DB::table()->delete()');

// -----------------------------------------------------------------------
// 19. DB::table() — JOIN
// -----------------------------------------------------------------------
$results = DB::table('users')
    ->select('users.name', 'orders.total', 'orders.status')
    ->join('orders', 'orders.user_id', '=', 'users.id')
    ->where('orders.status', 'paid')
    ->orderBy('orders.total', 'desc')
    ->get();

if (count($results) !== 2) fail("JOIN expected 2, got " . count($results));
if ((float)$results[0]->total !== 250.0) fail('JOIN result order wrong');
pass('DB::table() JOIN with WHERE + ORDER');

// -----------------------------------------------------------------------
// 20. DB::table() — paginate
// -----------------------------------------------------------------------
$page = DB::table('users')->paginate(3, 1);
if ($page->total !== 6) fail("paginate() total expected 6, got {$page->total}");
if ($page->last_page !== 2) fail("paginate() last_page expected 2, got {$page->last_page}");
if (count($page->data) !== 3) fail("paginate() data count expected 3");
pass('DB::table()->paginate()');

// -----------------------------------------------------------------------
// 21. DB::table() — chunk
// -----------------------------------------------------------------------
$total = 0;
DB::table('users')->chunk(2, function (array $rows) use (&$total) {
    $total += count($rows);
});
if ($total !== 6) fail("chunk() expected 6, got {$total}");
pass('DB::table()->chunk()');

// -----------------------------------------------------------------------
// 22. DB::table() — whereIn with subquery
// -----------------------------------------------------------------------
$sub = DB::table('orders')->select('user_id')->where('status', 'paid');
$usersWithPaidOrders = DB::table('users')->whereIn('id', $sub)->get();
if (count($usersWithPaidOrders) < 1) fail('whereIn(subquery) returned empty');
pass('DB::table()->whereIn() with subquery Builder');

// -----------------------------------------------------------------------
// 23. DB::table() — whereExists
// -----------------------------------------------------------------------
$usersWithOrders = DB::table('users')
    ->whereExists(function (Builder $q) {
        $q->table('orders')
          ->select('id')
          ->whereColumn('orders.user_id', '=', 'users.id');
    })
    ->get();

if (count($usersWithOrders) < 1) fail('whereExists() returned empty');
pass('DB::table()->whereExists()');

// -----------------------------------------------------------------------
// 24. DB::table() — nested where group
// -----------------------------------------------------------------------
$results = DB::table('users')
    ->where('active', 1)
    ->where(function (Builder $q) {
        $q->where('role', 'admin')->orWhere('role', 'mod');
    })
    ->get();

if (count($results) !== 2) fail("Nested where expected 2, got " . count($results));
pass('DB::table() nested where group');

// -----------------------------------------------------------------------
// 25. DB::transaction() — commit
// -----------------------------------------------------------------------
DB::transaction(function () {
    DB::table('users')->insert([
        'name' => 'TxUser', 'email' => 'tx@test.com', 'age' => 28, 'active' => 1,
    ]);
});

$exists = DB::table('users')->where('name', 'TxUser')->exists();
if (! $exists) fail('transaction() commit: row not found');
pass('DB::transaction() commit');

// -----------------------------------------------------------------------
// 26. DB::transaction() — rollback on exception
// -----------------------------------------------------------------------
try {
    DB::transaction(function () {
        DB::table('users')->insert([
            'name' => 'RollbackUser', 'email' => 'rb@test.com',
        ]);
        throw new \RuntimeException('Simulated error');
    });
} catch (\RuntimeException) {
    // expected
}

$exists = DB::table('users')->where('name', 'RollbackUser')->exists();
if ($exists) fail('transaction() rollback: row should not exist');
pass('DB::transaction() rollback on exception');

// -----------------------------------------------------------------------
// 27. DB::beginTransaction() / commit() — manual
// -----------------------------------------------------------------------
DB::beginTransaction();
DB::table('users')->insert(['name' => 'ManualTx', 'email' => 'mtx@test.com']);
DB::commit();

$exists = DB::table('users')->where('name', 'ManualTx')->exists();
if (! $exists) fail('Manual beginTransaction/commit failed');
pass('DB::beginTransaction() / DB::commit() manual');

// -----------------------------------------------------------------------
// 28. DB::beginTransaction() / rollBack() — manual
// -----------------------------------------------------------------------
DB::beginTransaction();
DB::table('users')->insert(['name' => 'ManualRollback', 'email' => 'mr@test.com']);
DB::rollBack();

$exists = DB::table('users')->where('name', 'ManualRollback')->exists();
if ($exists) fail('Manual rollBack() failed — row should not exist');
pass('DB::beginTransaction() / DB::rollBack() manual');

// -----------------------------------------------------------------------
// 29. DB::inTransaction()
// -----------------------------------------------------------------------
if (DB::inTransaction()) fail('inTransaction() should be false before begin');
DB::beginTransaction();
if (! DB::inTransaction()) fail('inTransaction() should be true after begin');
DB::rollBack();
if (DB::inTransaction()) fail('inTransaction() should be false after rollback');
pass('DB::inTransaction()');

// -----------------------------------------------------------------------
// 30. Nested transactions (savepoints) via manual API (MySQL Supports Savepoints)
// -----------------------------------------------------------------------
DB::beginTransaction();
    DB::table('users')->insert(['name' => 'Outer', 'email' => 'outer@test.com']);
    DB::beginTransaction(); // Creates a Savepoint in MySQL
        DB::table('users')->insert(['name' => 'Inner', 'email' => 'inner@test.com']);
    DB::rollBack();         // Rolls back to the inner savepoint
DB::commit();

if (! DB::table('users')->where('name', 'Outer')->exists()) fail('Nested tx: outer not committed');
if (DB::table('users')->where('name', 'Inner')->exists())   fail('Nested tx: inner should be rolled back');
pass('Nested transactions via DB (savepoints)');

// -----------------------------------------------------------------------
// 31. Multiple connections (MySQL Secondary Setup)
// -----------------------------------------------------------------------
DB::addConnection([
    'driver'    => Config::MYSQL,
    'host'      => '127.0.0.1',
    'port'      => '3306',
    'database'  => 'test_secondary', // Dynamic secondary DB
    'username'  => 'root',
    'password'  => '123456',
    'charset'   => 'utf8mb4',
], 'secondary');

DB::statement('DROP TABLE IF EXISTS `logs`', 'secondary');
DB::statement('CREATE TABLE `logs` (`id` INT AUTO_INCREMENT PRIMARY KEY, `msg` VARCHAR(255))', 'secondary');
DB::insert('INSERT INTO logs (msg) VALUES (?)', ['hello'], 'secondary');

$log = DB::selectOne('SELECT * FROM logs WHERE id = ?', [1], 'secondary');
if ($log === false || $log->msg !== 'hello') fail('Multiple connections: secondary read failed');
pass('Multiple connections: separate data isolation');

// Main connection doesn't have logs table
try {
    DB::table('logs')->count();
    fail('Main connection should not have logs table');
} catch (\Foxdb\Exceptions\QueryException) {
    pass('Multiple connections: main has no logs table (isolation confirmed)');
}

// -----------------------------------------------------------------------
// 32. DB::use() — switch default connection
// -----------------------------------------------------------------------
DB::use('secondary');
$log = DB::table('logs')->first();
if ($log === false || $log->msg !== 'hello') fail('DB::use() did not switch default');
pass('DB::use() switches default connection');

DB::use('main'); // restore

// -----------------------------------------------------------------------
// 33. DB::hasConnection() / getDefaultConnection()
// -----------------------------------------------------------------------
if (! DB::hasConnection('main'))      fail('hasConnection(main) should be true');
if (! DB::hasConnection('secondary')) fail('hasConnection(secondary) should be true');
if (DB::hasConnection('nonexistent')) fail('hasConnection(nonexistent) should be false');
pass('DB::hasConnection()');

if (DB::getDefaultConnection() !== 'main') fail("getDefaultConnection() expected 'main'");
pass('DB::getDefaultConnection()');

// -----------------------------------------------------------------------
// 34. DB::disconnect() — closes but config preserved
// MySQL preserves configuration after disconnect. We verify transparent reconnect.
// -----------------------------------------------------------------------
DB::disconnect('secondary');
try {
    // Reconnecting to verified MySQL instance should seamlessly create a new connection
    DB::statement('SELECT 1', 'secondary');
    pass('DB::disconnect() + transparent reconnect (config preserved)');
} catch (\Throwable $e) {
    fail('DB::disconnect() reconnect failed: ' . $e->getMessage());
}

// -----------------------------------------------------------------------
// 35. DB::enableQueryLog() / getQueryLog() via Facade
// -----------------------------------------------------------------------
DB::use('main');
DB::enableQueryLog();
DB::table('users')->where('active', 1)->count();
DB::table('users')->where('role', 'admin')->first();

$log = DB::getQueryLog();
if (count($log) < 2) fail("Query log expected >= 2 entries, got " . count($log));
if (! $log[0] instanceof QueryLogEntry) fail('Query log entry wrong type');
pass('DB::enableQueryLog() / DB::getQueryLog()');

if (DB::getQueryCount() < 2) fail('getQueryCount() expected >= 2');
pass('DB::getQueryCount()');

if (DB::getTotalQueryTime() <= 0) fail('getTotalQueryTime() should be > 0');
pass('DB::getTotalQueryTime()');

$slow = DB::getSlowQueries(0.0);
if (count($slow) < 2) fail('getSlowQueries(0) should return all logged queries');
pass('DB::getSlowQueries()');

$last = DB::getLastQuery();
if ($last === null) fail('getLastQuery() returned null');
pass('DB::getLastQuery()');

DB::flushQueryLog();
if (DB::getQueryCount() !== 0) fail('flushQueryLog() did not clear');
pass('DB::flushQueryLog()');

DB::disableQueryLog();

// -----------------------------------------------------------------------
// 36. DB::beforeQuery() / DB::afterQuery() hooks via Facade
// -----------------------------------------------------------------------
$beforeFired = false;
$afterFired  = false;

DB::beforeQuery(function (string $sql, array $bindings) use (&$beforeFired): void {
    $beforeFired = true;
});

DB::afterQuery(function (QueryLogEntry $entry) use (&$afterFired): void {
    $afterFired = true;
});

DB::table('users')->count();

if (! $beforeFired) fail('DB::beforeQuery() hook not fired');
if (! $afterFired)  fail('DB::afterQuery() hook not fired');
pass('DB::beforeQuery() / DB::afterQuery() hooks');

// -----------------------------------------------------------------------
// 37. Grammar cached per driver (internal check via multiple calls)
// -----------------------------------------------------------------------
$b1 = DB::table('users');
$b2 = DB::table('orders');
$sql1 = $b1->where('active', 1)->toSql();
$sql2 = $b2->where('status', 'paid')->toSql();
if (! str_contains($sql1, 'users')) fail("toSql() wrong table: {$sql1}");
if (! str_contains($sql2, 'orders')) fail("toSql() wrong table: {$sql2}");
pass('Grammar cached per driver, Builder per table independent');

// -----------------------------------------------------------------------
// 38. DB::reset() clears all state
// -----------------------------------------------------------------------
DB::reset();
try {
    DB::table('users')->count();
    fail('After reset(), DB::table() should throw (no connection registered)');
} catch (DatabaseException $e) {
    pass('DB::reset() clears connections — throws DatabaseException on next use');
}

// -----------------------------------------------------------------------
// Summary
// -----------------------------------------------------------------------
echo "\n\033[32m✔ All DB Facade tests passed successfully on MySQL!\033[0m\n\n";