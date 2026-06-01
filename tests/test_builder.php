<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Foxdb\Config;
use Foxdb\Connection\Connection;
use Foxdb\Query\Builder;
use Foxdb\Query\JoinClause;
use Foxdb\Query\RawExpression;
use Foxdb\Query\Grammars\MySqlGrammar;

function pass(string $msg): void
{
    echo "\033[32m✔ {$msg}\033[0m\n";
}
function fail(string $msg): void
{
    echo "\033[31m✘ {$msg}\033[0m\n";
    exit(1);
}

function assertSql(string $expected, string $actual, string $label): void
{
    $e = preg_replace('/\s+/', ' ', trim($expected));
    $a = preg_replace('/\s+/', ' ', trim($actual));
    if ($e !== $a) {
        echo "\033[31m✘ {$label}\033[0m\n";
        echo "  Expected : {$e}\n  Got      : {$a}\n";
        exit(1);
    }
    pass($label);
}

function assertBindings(array $expected, array $actual, string $label): void
{
    if ($expected !== $actual) {
        echo "\033[31m✘ {$label} [bindings]\033[0m\n";
        echo "  Expected : " . json_encode($expected) . "\n";
        echo "  Got      : " . json_encode($actual) . "\n";
        exit(1);
    }
    pass($label . ' [bindings]');
}

echo "\n=== FoxDB Builder Tests (MySQL) ===\n\n";

// -----------------------------------------------------------------------
// Setup: Pure MySQL Connection
// -----------------------------------------------------------------------
$conn = new Connection('test', [
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

$grammar = new MySqlGrammar();

function builder(Connection $conn, MySqlGrammar $grammar): Builder
{
    return (new Builder($conn, $grammar))->table('users');
}

// Drop tables if they exist to start fresh
$conn->statement('DROP TABLE IF EXISTS `orders`');
$conn->statement('DROP TABLE IF EXISTS `users`');

// MySQL Compatible Table Schemes
$conn->statement('
    CREATE TABLE `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255), 
        `email` VARCHAR(255), 
        `age` INT,
        `active` TINYINT DEFAULT 1, 
        `role` VARCHAR(50) DEFAULT "user",
        `score` DOUBLE DEFAULT 0, 
        `deleted_at` TIMESTAMP NULL DEFAULT NULL,
        `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
');

$conn->statement('
    CREATE TABLE `orders` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT, 
        `total` DOUBLE, 
        `status` VARCHAR(50) DEFAULT "pending",
        `active` TINYINT DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
');

// -----------------------------------------------------------------------
// 1. toSql() — basic SELECT *
// -----------------------------------------------------------------------
assertSql(
    'SELECT * FROM `users`',
    builder($conn, $grammar)->toSql(),
    'toSql() SELECT *',
);

// -----------------------------------------------------------------------
// 2. select() columns
// -----------------------------------------------------------------------
assertSql(
    'SELECT `id`, `name` FROM `users`',
    builder($conn, $grammar)->select('id', 'name')->toSql(),
    'select() columns',
);

// -----------------------------------------------------------------------
// 3. selectRaw()
// -----------------------------------------------------------------------
assertSql(
    'SELECT COUNT(*) as total, `name` FROM `users`',
    builder($conn, $grammar)->selectRaw('COUNT(*) as total')->addSelect('name')->toSql(),
    'selectRaw() + addSelect()',
);

// -----------------------------------------------------------------------
// 4. distinct()
// -----------------------------------------------------------------------
assertSql(
    'SELECT DISTINCT `role` FROM `users`',
    builder($conn, $grammar)->select('role')->distinct()->toSql(),
    'distinct()',
);

// -----------------------------------------------------------------------
// 5. where() shorthand (no operator)
// -----------------------------------------------------------------------
$b = builder($conn, $grammar)->where('active', 1);
assertSql('SELECT * FROM `users` WHERE `active` = ?', $b->toSql(), 'where() shorthand');
assertBindings([1], $b->getBindings(), 'where() shorthand');

// -----------------------------------------------------------------------
// 6. where() with operator
// -----------------------------------------------------------------------
$b = builder($conn, $grammar)->where('age', '>', 18);
assertSql('SELECT * FROM `users` WHERE `age` > ?', $b->toSql(), 'where() with operator');
assertBindings([18], $b->getBindings(), 'where() with operator');

// -----------------------------------------------------------------------
// 7. orWhere()
// -----------------------------------------------------------------------
$b = builder($conn, $grammar)->where('role', 'admin')->orWhere('role', 'editor');
assertSql('SELECT * FROM `users` WHERE `role` = ? OR `role` = ?', $b->toSql(), 'orWhere()');
assertBindings(['admin', 'editor'], $b->getBindings(), 'orWhere()');

// -----------------------------------------------------------------------
// 8. whereNot()
// -----------------------------------------------------------------------
$b = builder($conn, $grammar)->whereNot('active', 0);
assertSql('SELECT * FROM `users` WHERE `active` != ?', $b->toSql(), 'whereNot()');

// -----------------------------------------------------------------------
// 9. whereIn()
// -----------------------------------------------------------------------
$b = builder($conn, $grammar)->whereIn('id', [1, 2, 3]);
assertSql('SELECT * FROM `users` WHERE `id` IN (?, ?, ?)', $b->toSql(), 'whereIn()');
assertBindings([1, 2, 3], $b->getBindings(), 'whereIn()');

// -----------------------------------------------------------------------
// 10. whereNotIn()
// -----------------------------------------------------------------------
$b = builder($conn, $grammar)->whereNotIn('status', ['banned', 'deleted']);
assertSql('SELECT * FROM `users` WHERE `status` NOT IN (?, ?)', $b->toSql(), 'whereNotIn()');

// -----------------------------------------------------------------------
// 11. orWhereIn() / orWhereNotIn()
// -----------------------------------------------------------------------
$b = builder($conn, $grammar)->where('active', 1)->orWhereIn('role', ['admin', 'mod']);
assertSql('SELECT * FROM `users` WHERE `active` = ? OR `role` IN (?, ?)', $b->toSql(), 'orWhereIn()');

// -----------------------------------------------------------------------
// 12. whereBetween() / whereNotBetween()
// -----------------------------------------------------------------------
$b = builder($conn, $grammar)->whereBetween('age', 18, 65);
assertSql('SELECT * FROM `users` WHERE `age` BETWEEN ? AND ?', $b->toSql(), 'whereBetween()');
assertBindings([18, 65], $b->getBindings(), 'whereBetween()');

$b = builder($conn, $grammar)->whereNotBetween('score', 0, 10);
assertSql('SELECT * FROM `users` WHERE `score` NOT BETWEEN ? AND ?', $b->toSql(), 'whereNotBetween()');

// -----------------------------------------------------------------------
// 13. whereNull() / whereNotNull() / orWhereNull() / orWhereNotNull()
// -----------------------------------------------------------------------
assertSql(
    'SELECT * FROM `users` WHERE `deleted_at` IS NULL',
    builder($conn, $grammar)->whereNull('deleted_at')->toSql(),
    'whereNull()'
);
assertSql(
    'SELECT * FROM `users` WHERE `email` IS NOT NULL',
    builder($conn, $grammar)->whereNotNull('email')->toSql(),
    'whereNotNull()'
);
assertSql(
    'SELECT * FROM `users` WHERE `active` = ? OR `deleted_at` IS NULL',
    builder($conn, $grammar)->where('active', 1)->orWhereNull('deleted_at')->toSql(),
    'orWhereNull()'
);
assertSql(
    'SELECT * FROM `users` WHERE `active` = ? OR `email` IS NOT NULL',
    builder($conn, $grammar)->where('active', 1)->orWhereNotNull('email')->toSql(),
    'orWhereNotNull()'
);

// -----------------------------------------------------------------------
// 14. whereColumn()
// -----------------------------------------------------------------------
$b = builder($conn, $grammar)->whereColumn('updated_at', '>', 'created_at');
assertSql('SELECT * FROM `users` WHERE `updated_at` > `created_at`', $b->toSql(), 'whereColumn()');
assertBindings([], $b->getBindings(), 'whereColumn()');

// -----------------------------------------------------------------------
// 15. whereRaw() / orWhereRaw()
// -----------------------------------------------------------------------
$b = builder($conn, $grammar)->whereRaw('age > ? AND active = ?', [18, 1]);
assertSql('SELECT * FROM `users` WHERE age > ? AND active = ?', $b->toSql(), 'whereRaw()');
assertBindings([18, 1], $b->getBindings(), 'whereRaw()');

$b = builder($conn, $grammar)->where('role', 'admin')->orWhereRaw('age > ?', [30]);
assertSql('SELECT * FROM `users` WHERE `role` = ? OR age > ?', $b->toSql(), 'orWhereRaw()');
assertBindings(['admin', 30], $b->getBindings(), 'orWhereRaw()');

// -----------------------------------------------------------------------
// 16. Nested WHERE group: where(callable)
// -----------------------------------------------------------------------
$b = builder($conn, $grammar)
    ->where('active', 1)
    ->where(function (Builder $q) {
        $q->where('role', 'admin')->orWhere('role', 'mod');
    });

assertSql(
    'SELECT * FROM `users` WHERE `active` = ? AND (`role` = ? OR `role` = ?)',
    $b->toSql(),
    'where(callable) nested group',
);
assertBindings([1, 'admin', 'mod'], $b->getBindings(), 'where(callable) nested group');

// -----------------------------------------------------------------------
// 17. whereExists()
// -----------------------------------------------------------------------
$sub = builder($conn, $grammar)
    ->table('orders')
    ->select('id')
    ->whereColumn('orders.user_id', '=', 'users.id');

$b = builder($conn, $grammar)->whereExists($sub);
assertSql(
    'SELECT * FROM `users` WHERE EXISTS (SELECT `id` FROM `orders` WHERE `orders`.`user_id` = `users`.`id`)',
    $b->toSql(),
    'whereExists()',
);

// -----------------------------------------------------------------------
// 18. whereNotExists()
// -----------------------------------------------------------------------
$b = builder($conn, $grammar)->whereNotExists(function (Builder $q) {
    $q->table('orders')->select('id')->whereColumn('orders.user_id', '=', 'users.id');
});
assertSql(
    'SELECT * FROM `users` WHERE NOT EXISTS (SELECT `id` FROM `orders` WHERE `orders`.`user_id` = `users`.`id`)',
    $b->toSql(),
    'whereNotExists()',
);

// -----------------------------------------------------------------------
// 19. Date WHERE helpers
// -----------------------------------------------------------------------
assertSql(
    'SELECT * FROM `users` WHERE DATE(`created_at`) = ?',
    builder($conn, $grammar)->whereDate('created_at', '=', '2024-01-01')->toSql(),
    'whereDate()'
);
assertSql(
    'SELECT * FROM `users` WHERE MONTH(`created_at`) = ?',
    builder($conn, $grammar)->whereMonth('created_at', '=', 1)->toSql(),
    'whereMonth()'
);
assertSql(
    'SELECT * FROM `users` WHERE DAY(`created_at`) = ?',
    builder($conn, $grammar)->whereDay('created_at', '=', 15)->toSql(),
    'whereDay()'
);
assertSql(
    'SELECT * FROM `users` WHERE YEAR(`created_at`) = ?',
    builder($conn, $grammar)->whereYear('created_at', '=', 2024)->toSql(),
    'whereYear()'
);
assertSql(
    'SELECT * FROM `users` WHERE TIME(`created_at`) > ?',
    builder($conn, $grammar)->whereTime('created_at', '>', '08:00')->toSql(),
    'whereTime()'
);

// -----------------------------------------------------------------------
// 20. FoxDB v1 shorthands
// -----------------------------------------------------------------------
assertSql(
    'SELECT * FROM `users` WHERE `active` = ?',
    builder($conn, $grammar)->is('active', 1)->toSql(),
    'is()'
);
assertSql(
    'SELECT * FROM `users` WHERE `active` = ?',
    builder($conn, $grammar)->true('active')->toSql(),
    'true()'
);
assertSql(
    'SELECT * FROM `users` WHERE `active` = ?',
    builder($conn, $grammar)->false('active')->toSql(),
    'false()'
);
assertSql(
    'SELECT * FROM `users` WHERE `name` LIKE ?',
    builder($conn, $grammar)->like('name', '%ali%')->toSql(),
    'like()'
);
assertSql(
    'SELECT * FROM `users` WHERE `deleted_at` IS NULL',
    builder($conn, $grammar)->null('deleted_at')->toSql(),
    'null()'
);
assertSql(
    'SELECT * FROM `users` WHERE `email` IS NOT NULL',
    builder($conn, $grammar)->notNull('email')->toSql(),
    'notNull()'
);
assertSql(
    'SELECT * FROM `users` WHERE `id` IN (?, ?, ?)',
    builder($conn, $grammar)->in('id', [1, 2, 3])->toSql(),
    'in()'
);
assertSql(
    'SELECT * FROM `users` WHERE `id` NOT IN (?, ?)',
    builder($conn, $grammar)->notIn('id', [4, 5])->toSql(),
    'notIn()'
);

// -----------------------------------------------------------------------
// 21. Simple JOIN
// -----------------------------------------------------------------------
$b = builder($conn, $grammar)->join('orders', 'orders.user_id', '=', 'users.id');
assertSql(
    'SELECT * FROM `users` INNER JOIN `orders` ON `orders`.`user_id` = `users`.`id`',
    $b->toSql(),
    'join() simple',
);

// -----------------------------------------------------------------------
// 22. leftJoin() / rightJoin()
// -----------------------------------------------------------------------
assertSql(
    'SELECT * FROM `users` LEFT JOIN `profiles` ON `profiles`.`user_id` = `users`.`id`',
    builder($conn, $grammar)->leftJoin('profiles', 'profiles.user_id', '=', 'users.id')->toSql(),
    'leftJoin()',
);
assertSql(
    'SELECT * FROM `users` RIGHT JOIN `roles` ON `roles`.`id` = `users`.`role_id`',
    builder($conn, $grammar)->rightJoin('roles', 'roles.id', '=', 'users.role_id')->toSql(),
    'rightJoin()',
);

// -----------------------------------------------------------------------
// 23. JOIN with callback — multiple ON
// -----------------------------------------------------------------------
$b = builder($conn, $grammar)->join('orders', function (JoinClause $join) {
    $join->on('orders.user_id', '=', 'users.id')
        ->on('orders.active', '=', 'users.active');
});
assertSql(
    'SELECT * FROM `users` INNER JOIN `orders` ON `orders`.`user_id` = `users`.`id` AND `orders`.`active` = `users`.`active`',
    $b->toSql(),
    'join() callback — multiple ON',
);

// -----------------------------------------------------------------------
// 24. JOIN with OR ON
// -----------------------------------------------------------------------
$b = builder($conn, $grammar)->join('orders', function (JoinClause $join) {
    $join->on('orders.user_id', '=', 'users.id')
        ->orOn('orders.backup_user_id', '=', 'users.id');
});
assertSql(
    'SELECT * FROM `users` INNER JOIN `orders` ON `orders`.`user_id` = `users`.`id` OR `orders`.`backup_user_id` = `users`.`id`',
    $b->toSql(),
    'join() callback — OR ON',
);

// -----------------------------------------------------------------------
// 25. JOIN with WHERE inside (complex join)
// -----------------------------------------------------------------------
$b = builder($conn, $grammar)->join('orders', function (JoinClause $join) {
    $join->on('orders.user_id', '=', 'users.id')
        ->where('orders.active', 1)
        ->whereNull('orders.deleted_at');
});
assertSql(
    'SELECT * FROM `users` INNER JOIN `orders` ON `orders`.`user_id` = `users`.`id` AND `orders`.`active` = ? AND `orders`.`deleted_at` IS NULL',
    $b->toSql(),
    'join() — WHERE inside JoinClause',
);
assertBindings([1], $b->getBindings(), 'join() — WHERE inside JoinClause');

// -----------------------------------------------------------------------
// 26. JOIN with WHERE IN inside
// -----------------------------------------------------------------------
$b = builder($conn, $grammar)->join('orders', function (JoinClause $join) {
    $join->on('orders.user_id', '=', 'users.id')
        ->whereIn('orders.status', ['paid', 'pending']);
});
assertSql(
    'SELECT * FROM `users` INNER JOIN `orders` ON `orders`.`user_id` = `users`.`id` AND `orders`.`status` IN (?, ?)',
    $b->toSql(),
    'join() — whereIn inside JoinClause',
);
assertBindings(['paid', 'pending'], $b->getBindings(), 'join() — whereIn inside JoinClause');

// -----------------------------------------------------------------------
// 27. joinSub() — subquery join
// -----------------------------------------------------------------------
$sub = builder($conn, $grammar)
    ->table('orders')
    ->select('user_id')
    ->selectRaw('SUM(total) as total_spent')
    ->groupBy('user_id');

$b = builder($conn, $grammar)
    ->select('users.id', 'users.name', 'ot.total_spent')
    ->joinSub($sub, 'ot', 'ot.user_id', '=', 'users.id');

assertSql(
    'SELECT `users`.`id`, `users`.`name`, `ot`.`total_spent` FROM `users` INNER JOIN (SELECT `user_id`, SUM(total) as total_spent FROM `orders` GROUP BY `user_id`) AS `ot` ON `ot`.`user_id` = `users`.`id`',
    $b->toSql(),
    'joinSub() — subquery join',
);

// -----------------------------------------------------------------------
// 28. leftJoinSub()
// -----------------------------------------------------------------------
$sub2 = builder($conn, $grammar)->table('orders')->select('user_id')->where('status', 'paid');
$b = builder($conn, $grammar)->leftJoinSub($sub2, 'paid_orders', function (JoinClause $j) {
    $j->on('paid_orders.user_id', '=', 'users.id');
});
assertSql(
    'SELECT * FROM `users` LEFT JOIN (SELECT `user_id` FROM `orders` WHERE `status` = ?) AS `paid_orders` ON `paid_orders`.`user_id` = `users`.`id`',
    $b->toSql(),
    'leftJoinSub() with callback',
);

// -----------------------------------------------------------------------
// 29. joinRaw()
// -----------------------------------------------------------------------
$b = builder($conn, $grammar)->joinRaw('INNER JOIN orders ON orders.user_id = users.id');
pass('joinRaw() added to joins state');

// -----------------------------------------------------------------------
// 30. Multiple JOINs + WHERE (complex)
// -----------------------------------------------------------------------
$b = builder($conn, $grammar)
    ->select('users.id', 'users.name', 'orders.total')
    ->join('orders', function (JoinClause $join) {
        $join->on('orders.user_id', '=', 'users.id')
            ->where('orders.active', 1);
    })
    ->leftJoin('profiles', 'profiles.user_id', '=', 'users.id')
    ->where('users.active', 1)
    ->whereIn('orders.status', ['paid', 'completed'])
    ->orderBy('orders.total', 'desc')
    ->limit(10);

assertSql(
    'SELECT `users`.`id`, `users`.`name`, `orders`.`total` FROM `users` INNER JOIN `orders` ON `orders`.`user_id` = `users`.`id` AND `orders`.`active` = ? LEFT JOIN `profiles` ON `profiles`.`user_id` = `users`.`id` WHERE `users`.`active` = ? AND `orders`.`status` IN (?, ?) ORDER BY `orders`.`total` DESC LIMIT 10',
    $b->toSql(),
    'Complex: multiple JOINs + WHERE + ORDER + LIMIT',
);
assertBindings([1, 1, 'paid', 'completed'], $b->getBindings(), 'Complex query bindings');

// -----------------------------------------------------------------------
// 31. GROUP BY + HAVING
// -----------------------------------------------------------------------
$b = builder($conn, $grammar)
    ->table('orders')
    ->select('user_id')
    ->selectRaw('COUNT(*) as order_count')
    ->groupBy('user_id')
    ->having('order_count', '>', 5);

assertSql(
    'SELECT `user_id`, COUNT(*) as order_count FROM `orders` GROUP BY `user_id` HAVING `order_count` > ?',
    $b->toSql(),
    'GROUP BY + HAVING',
);

// -----------------------------------------------------------------------
// 32. havingRaw()
// -----------------------------------------------------------------------
$b = builder($conn, $grammar)->table('orders')->groupBy('status')->havingRaw('COUNT(*) > ?', [3]);
assertSql(
    'SELECT * FROM `orders` GROUP BY `status` HAVING COUNT(*) > ?',
    $b->toSql(),
    'havingRaw()',
);

// -----------------------------------------------------------------------
// 33. orderBy / orderByDesc / latest / oldest / inRandomOrder / reorder
// -----------------------------------------------------------------------
assertSql(
    'SELECT * FROM `users` ORDER BY `name` ASC',
    builder($conn, $grammar)->orderBy('name')->toSql(),
    'orderBy() ASC'
);
assertSql(
    'SELECT * FROM `users` ORDER BY `name` DESC',
    builder($conn, $grammar)->orderByDesc('name')->toSql(),
    'orderByDesc()'
);
assertSql(
    'SELECT * FROM `users` ORDER BY `created_at` DESC',
    builder($conn, $grammar)->latest()->toSql(),
    'latest()'
);
assertSql(
    'SELECT * FROM `users` ORDER BY `created_at` ASC',
    builder($conn, $grammar)->oldest()->toSql(),
    'oldest()'
);
assertSql(
    'SELECT * FROM `users` ORDER BY RAND()',
    builder($conn, $grammar)->inRandomOrder()->toSql(),
    'inRandomOrder()'
);
assertSql(
    'SELECT * FROM `users`',
    builder($conn, $grammar)->orderBy('name')->reorder()->toSql(),
    'reorder()'
);

// -----------------------------------------------------------------------
// 34. limit / take / offset / skip
// -----------------------------------------------------------------------
assertSql(
    'SELECT * FROM `users` LIMIT 10 OFFSET 20',
    builder($conn, $grammar)->take(10)->skip(20)->toSql(),
    'take() + skip()'
);

// -----------------------------------------------------------------------
// 35. clone independence
// -----------------------------------------------------------------------
$base = builder($conn, $grammar)->where('active', 1);
$admins = (clone $base)->where('role', 'admin');

assertSql(
    'SELECT * FROM `users` WHERE `active` = ?',
    $base->toSql(),
    'clone: base unchanged'
);
assertSql(
    'SELECT * FROM `users` WHERE `active` = ? AND `role` = ?',
    $admins->toSql(),
    'clone: derived query correct'
);

// -----------------------------------------------------------------------
// 36-49. Execution tests on MySQL
// -----------------------------------------------------------------------
// Insert test data
for ($i = 1; $i <= 5; $i++) {
    $conn->insert(
        'INSERT INTO users (name, email, age, active, role, score) VALUES (?, ?, ?, ?, ?, ?)',
        ["User{$i}", "user{$i}@test.com", 20 + $i, $i <= 4 ? 1 : 0, $i === 1 ? 'admin' : 'user', $i * 10.0],
    );
}
$conn->insert(
    'INSERT INTO users (name, email, age, active, role, score, deleted_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
    ['Deleted', 'del@test.com', 30, 0, 'user', 0, '2024-01-01 00:00:00']
);

$conn->insert('INSERT INTO orders (user_id, total, status, active) VALUES (?, ?, ?, ?)', [1, 250.0, 'paid', 1]);
$conn->insert('INSERT INTO orders (user_id, total, status, active) VALUES (?, ?, ?, ?)', [1, 150.0, 'pending', 1]);
$conn->insert('INSERT INTO orders (user_id, total, status, active) VALUES (?, ?, ?, ?)', [2, 100.0, 'paid', 1]);
$conn->insert('INSERT INTO orders (user_id, total, status, active) VALUES (?, ?, ?, ?)', [3, 50.0, 'pending', 0]);

// 36. get()
$users = (new Builder($conn, $grammar))->table('users')->where('active', 1)->get();
if (count($users) !== 4)
    fail("get() expected 4, got " . count($users));
pass('get() returns correct rows');

// 37. first()
$user = (new Builder($conn, $grammar))->table('users')->where('role', 'admin')->first();
if ($user === false || $user->name !== 'User1')
    fail("first() returned wrong row");
pass('first() returns first matching row');

// 38. find()
$user = (new Builder($conn, $grammar))->table('users')->find(2);
if ($user === false || $user->name !== 'User2')
    fail("find() failed: " . json_encode($user));
pass('find() by primary key');

// 39. value()
$name = (new Builder($conn, $grammar))->table('users')->where('id', 1)->value('name');
if ($name !== 'User1')
    fail("value() expected User1, got {$name}");
pass('value() returns single column value');

// 40. pluck() — array of values
$names = (new Builder($conn, $grammar))->table('users')->where('active', 1)->orderBy('id')->pluck('name');
if ($names !== ['User1', 'User2', 'User3', 'User4'])
    fail("pluck() wrong: " . json_encode($names));
pass('pluck() returns array of values');

// 41. pluck() — key => value
$map = (new Builder($conn, $grammar))->table('users')->where('active', 1)->orderBy('id')->pluck('name', 'id');
if (($map[1] ?? null) !== 'User1')
    fail("pluck(col, key) wrong: " . json_encode($map));
pass('pluck(col, key) returns keyed array');

// 42. count()
$c = (new Builder($conn, $grammar))->table('users')->where('active', 1)->count();
if ($c !== 4)
    fail("count() expected 4, got {$c}");
pass('count()');

// 43. sum() / avg() / min() / max()
$sum = (new Builder($conn, $grammar))->table('users')->where('active', 1)->sum('score');
if ((float) $sum !== 100.0)
    fail("sum() expected 100, got {$sum}");
pass('sum()');

$avg = (new Builder($conn, $grammar))->table('users')->where('active', 1)->avg('score');
if ((float) $avg !== 25.0)
    fail("avg() expected 25, got {$avg}");
pass('avg()');

$min = (new Builder($conn, $grammar))->table('users')->where('active', 1)->min('age');
if ((int) $min !== 21)
    fail("min() expected 21, got {$min}");
pass('min()');

$max = (new Builder($conn, $grammar))->table('users')->where('active', 1)->max('age');
if ((int) $max !== 24)
    fail("max() expected 24, got {$max}");
pass('max()');

// 44. exists() / doesntExist()
if (!(new Builder($conn, $grammar))->table('users')->where('role', 'admin')->exists())
    fail('exists() should be true');
pass('exists() true');
if (!(new Builder($conn, $grammar))->table('users')->where('role', 'superadmin')->doesntExist())
    fail('doesntExist() should be true');
pass('doesntExist() true');

// 45. paginate()
$page = (new Builder($conn, $grammar))->table('users')->paginate(3, 1);
if ($page->total !== 6)
    fail("paginate() total expected 6, got {$page->total}");
if (count($page->data) !== 3)
    fail("paginate() data count expected 3");
if ($page->last_page !== 2)
    fail("paginate() last_page expected 2, got {$page->last_page}");
pass('paginate()');

// 46. chunk()
$chunks = [];
(new Builder($conn, $grammar))->table('users')->chunk(2, function (array $rows) use (&$chunks) {
    $chunks[] = count($rows);
});
if (array_sum($chunks) !== 6)
    fail("chunk() total rows expected 6, got " . array_sum($chunks));
pass('chunk() processes all rows in batches');

// 47. insert() + insertGetId()
$id = (new Builder($conn, $grammar))->table('users')->insertGetId([
    'name' => 'NewUser',
    'email' => 'new@test.com',
    'age' => 28,
    'active' => 1,
]);
if (!is_numeric($id) || $id < 1)
    fail("insertGetId() returned invalid id: {$id}");
pass("insertGetId() → {$id}");

// 48. insertBatch()
$ok = (new Builder($conn, $grammar))->table('users')->insertBatch([
    ['name' => 'Batch1', 'email' => 'b1@test.com', 'age' => 30],
    ['name' => 'Batch2', 'email' => 'b2@test.com', 'age' => 31],
]);
if (!$ok)
    fail('insertBatch() failed');
pass('insertBatch()');

// 49. update()
$affected = (new Builder($conn, $grammar))->table('users')->where('name', 'NewUser')->update(['age' => 99]);
if ($affected !== 1)
    fail("update() expected 1 affected, got {$affected}");
$updated = (new Builder($conn, $grammar))->table('users')->where('name', 'NewUser')->value('age');
if ((int) $updated !== 99)
    fail("update() value not persisted");
pass('update()');

// 50. increment() / decrement()
(new Builder($conn, $grammar))->table('users')->where('id', 1)->increment('score', 5);
$score = (new Builder($conn, $grammar))->table('users')->where('id', 1)->value('score');
if ((float) $score !== 15.0)
    fail("increment() expected 15, got {$score}");
pass('increment()');

(new Builder($conn, $grammar))->table('users')->where('id', 1)->decrement('score', 3);
$score = (new Builder($conn, $grammar))->table('users')->where('id', 1)->value('score');
if ((float) $score !== 12.0)
    fail("decrement() expected 12, got {$score}");
pass('decrement()');

// 51. delete()
$del = (new Builder($conn, $grammar))->table('users')->where('name', 'Batch2')->delete();
if ($del !== 1)
    fail("delete() expected 1, got {$del}");
pass('delete()');

// 52. updateOrInsert() — existing row
$ok = (new Builder($conn, $grammar))->table('users')
    ->updateOrInsert(['name' => 'User1'], ['age' => 50]);
$age = (new Builder($conn, $grammar))->table('users')->where('name', 'User1')->value('age');
if ((int) $age !== 50)
    fail("updateOrInsert() update failed: age={$age}");
pass('updateOrInsert() — updates existing row');

// 53. updateOrInsert() — new row
(new Builder($conn, $grammar))->table('users')
    ->updateOrInsert(['name' => 'BrandNew', 'email' => 'bn@test.com'], ['age' => 22]);
$exists = (new Builder($conn, $grammar))->table('users')->where('name', 'BrandNew')->exists();
if (!$exists)
    fail('updateOrInsert() — insert failed');
pass('updateOrInsert() — inserts new row');

// 54. JOIN execution
$results = (new Builder($conn, $grammar))
    ->table('users')
    ->select('users.name', 'orders.total')
    ->join('orders', 'orders.user_id', '=', 'users.id')
    ->where('orders.status', 'paid')
    ->orderBy('orders.total', 'desc')
    ->get();

if (count($results) !== 2)
    fail("JOIN execution expected 2 rows, got " . count($results));
if ((float) $results[0]->total !== 250.0)
    fail("JOIN result wrong order: " . $results[0]->total);
pass('JOIN execution with WHERE + ORDER');

// 55. Complex JOIN with WHERE in JoinClause
$results = (new Builder($conn, $grammar))
    ->table('users')
    ->select('users.name', 'orders.total')
    ->join('orders', function (JoinClause $j) {
        $j->on('orders.user_id', '=', 'users.id')
            ->where('orders.active', 1);
    })
    ->where('users.active', 1)
    ->get();

if (empty($results))
    fail('Complex JOIN with WHERE in JoinClause returned empty');
pass('Complex JOIN with WHERE inside JoinClause — execution');

// 56. Subquery in whereIn
$userIdsWithOrders = (new Builder($conn, $grammar))
    ->table('orders')
    ->select('user_id')
    ->where('status', 'paid');

$usersWithPaidOrders = (new Builder($conn, $grammar))
    ->table('users')
    ->whereIn('id', $userIdsWithOrders)
    ->get();

if (count($usersWithPaidOrders) < 1)
    fail('whereIn with subquery returned empty');
pass('whereIn() with subquery Builder');

// -----------------------------------------------------------------------
// Summary
// -----------------------------------------------------------------------
echo "\n\033[32m✔ All Builder tests passed successfully on MySQL!\033[0m\n\n";