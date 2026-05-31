<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Foxdb\Config;
use Foxdb\Connection\Connection;
use Foxdb\Connection\ConnectionManager;
use Foxdb\Exceptions\DatabaseException;
use Foxdb\Exceptions\QueryException;

// -----------------------------------------------------------------------
// Helper
// -----------------------------------------------------------------------
function pass(string $msg): void { echo "\033[32m✔ {$msg}\033[0m\n"; }
function fail(string $msg): void { echo "\033[31m✘ {$msg}\033[0m\n"; exit(1); }

echo "\n=== FoxDB Connection Layer Tests (MySQL/MariaDB) ===\n\n";

// کانفیگ پیش‌فرض برای دیتابیس تست مای‌اس‌کیوال
$mysqlConfig = [
    'driver'           => Config::MYSQL,
    'database'         => 'test',
    'fetch'            => Config::FETCH_OBJ,
    'throw_exceptions' => true,
    'host'             => '127.0.0.1',
    'port'             => 3306,
    'username'         => 'root',
    'password'         => '123456',
];

// -----------------------------------------------------------------------
// 1. ConnectionManager: register & resolve
// -----------------------------------------------------------------------
$manager = new ConnectionManager();
$manager->addConnection('main', $mysqlConfig);

$conn = $manager->connection('main');

if (! $conn instanceof Connection) {
    fail('connection() should return a Connection instance');
}
pass('ConnectionManager: addConnection + connection()');

// -----------------------------------------------------------------------
// 2. Re-using same instance (lazy singleton per name)
// -----------------------------------------------------------------------
$conn2 = $manager->connection('main');
if ($conn !== $conn2) {
    fail('Same connection name should return the same instance');
}
pass('ConnectionManager: returns same resolved instance');

// -----------------------------------------------------------------------
// 3. getDriverName / getDatabaseName
// -----------------------------------------------------------------------
if ($conn->getDriverName() !== Config::MYSQL) {
    fail('getDriverName() mismatch');
}
pass('Connection: getDriverName()');

if ($conn->getDatabaseName() !== 'test') {
    fail('getDatabaseName() mismatch');
}
pass('Connection: getDatabaseName()');

// -----------------------------------------------------------------------
// 4. statement() — DDL (MySQL Syntax)
// -----------------------------------------------------------------------
// حذف جدول قبلی برای ریست شدن Auto Increment در هر بار اجرای تست
$conn->statement('DROP TABLE IF EXISTS users');

$ok = $conn->statement('
    CREATE TABLE users (
        id      INT AUTO_INCREMENT PRIMARY KEY,
        name    VARCHAR(255) NOT NULL,
        email   VARCHAR(255) NOT NULL,
        age     INT,
        active  TINYINT(1) DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
');

if (! $ok) fail('statement() CREATE TABLE failed');
pass('Connection: statement() DDL (MySQL)');

// -----------------------------------------------------------------------
// 5. insert()
// -----------------------------------------------------------------------
$inserted = $conn->insert(
    'INSERT INTO users (name, email, age, active) VALUES (?, ?, ?, ?)',
    ['Alice', 'alice@example.com', 30, true],
);

if (! $inserted) fail('insert() returned false');
pass('Connection: insert()');

// -----------------------------------------------------------------------
// 6. insertGetId()
// -----------------------------------------------------------------------
$id = $conn->insertGetId(
    'INSERT INTO users (name, email, age, active) VALUES (?, ?, ?, ?)',
    ['Bob', 'bob@example.com', 25, true],
);

if ((int)$id !== 2) fail("insertGetId() expected 2, got {$id}");
pass("Connection: insertGetId() → {$id}");

// Insert a third row
$conn->insert(
    'INSERT INTO users (name, email, age, active) VALUES (?, ?, ?, ?)',
    ['Charlie', 'charlie@example.com', 17, false],
);

// -----------------------------------------------------------------------
// 7. select() — returns array of objects
// -----------------------------------------------------------------------
$users = $conn->select('SELECT * FROM users');

if (count($users) !== 3) fail('select() expected 3 rows');
if (! is_object($users[0])) fail('select() rows should be objects');
if ($users[0]->name !== 'Alice') fail('First row name mismatch');
pass('Connection: select() returns array<object>');

// -----------------------------------------------------------------------
// 8. selectOne()
// -----------------------------------------------------------------------
$user = $conn->selectOne('SELECT * FROM users WHERE id = ?', [2]);

if ($user === false) fail('selectOne() returned false');
if ($user->name !== 'Bob') fail("selectOne() name mismatch: {$user->name}");
pass('Connection: selectOne()');

// -----------------------------------------------------------------------
// 9. selectOne() returns false for no match
// -----------------------------------------------------------------------
$none = $conn->selectOne('SELECT * FROM users WHERE id = ?', [999]);

if ($none !== false) fail('selectOne() should return false for missing row');
pass('Connection: selectOne() → false on no match');

// -----------------------------------------------------------------------
// 10. update() — returns affected rows
// -----------------------------------------------------------------------
$affected = $conn->update('UPDATE users SET age = ? WHERE id = ?', [31, 1]);

if ((int)$affected !== 1) fail("update() expected 1 affected, got {$affected}");
pass("Connection: update() → {$affected} affected");

// -----------------------------------------------------------------------
// 11. delete() — returns affected rows
// -----------------------------------------------------------------------
$deleted = $conn->delete('DELETE FROM users WHERE active = ?', [false]);

if ((int)$deleted !== 1) fail("delete() expected 1 affected, got {$deleted}");
pass("Connection: delete() → {$deleted} affected");

// -----------------------------------------------------------------------
// 12. affectingStatement()
// -----------------------------------------------------------------------
$rows = $conn->affectingStatement('UPDATE users SET age = age + 1', []);

if ((int)$rows !== 2) fail("affectingStatement() expected 2, got {$rows}");
pass("Connection: affectingStatement() → {$rows}");

// -----------------------------------------------------------------------
// 13. Transactions — commit
// -----------------------------------------------------------------------
$conn->transaction(function (Connection $c): void {
    $c->insert('INSERT INTO users (name, email) VALUES (?, ?)', ['Dave', 'dave@example.com']);
});

$count = $conn->selectOne('SELECT COUNT(*) AS cnt FROM users');
if ((int) $count->cnt !== 3) fail("After committed transaction expected 3 rows, got {$count->cnt}");
pass('Connection: transaction() commit');

// -----------------------------------------------------------------------
// 14. Transactions — rollback on exception
// -----------------------------------------------------------------------
try {
    $conn->transaction(function (Connection $c): void {
        $c->insert('INSERT INTO users (name, email) VALUES (?, ?)', ['Eve', 'eve@example.com']);
        throw new \RuntimeException('Simulated error');
    });
} catch (\RuntimeException) {
    // expected
}

$count = $conn->selectOne('SELECT COUNT(*) AS cnt FROM users');
if ((int) $count->cnt !== 3) fail("After rolled-back transaction expected 3 rows, got {$count->cnt}");
pass('Connection: transaction() rollback on exception');

// -----------------------------------------------------------------------
// 15. Nested transactions (savepoints)
// -----------------------------------------------------------------------
$conn->beginTransaction();
    $conn->insert('INSERT INTO users (name, email) VALUES (?, ?)', ['Frank', 'frank@example.com']);
    $conn->beginTransaction(); // savepoint trans1
        $conn->insert('INSERT INTO users (name, email) VALUES (?, ?)', ['Grace', 'grace@example.com']);
    $conn->rollBack(); // rollback to savepoint
$conn->commit();

$count = $conn->selectOne('SELECT COUNT(*) AS cnt FROM users');
if ((int) $count->cnt !== 4) fail("After nested transaction expected 4 rows, got {$count->cnt}");
pass('Connection: nested transactions (savepoints)');

// -----------------------------------------------------------------------
// 16. QueryException on bad SQL
// -----------------------------------------------------------------------
try {
    $conn->select('SELECT * FROM non_existent_table');
    fail('Should have thrown QueryException');
} catch (QueryException $e) {
    if (empty($e->getSql())) fail('QueryException::getSql() empty');
    if (empty($e->getMessage())) fail('QueryException::getMessage() empty');
    pass('Connection: throws QueryException on bad SQL');
}

// -----------------------------------------------------------------------
// 17. throw_exceptions = false → returns empty, no exception (MySQL)
// -----------------------------------------------------------------------
$mysqlConfigNoExceptions = $mysqlConfig;
$mysqlConfigNoExceptions['throw_exceptions'] = false;

$safeConn = new Connection('safe', $mysqlConfigNoExceptions);

$result = $safeConn->select('SELECT * FROM does_not_exist');
if (! is_array($result)) fail('throw_exceptions=false should return array');
pass('Connection: throw_exceptions=false silences errors');

// -----------------------------------------------------------------------
// 18. ConnectionManager: switch default + not-found exception
// -----------------------------------------------------------------------
$manager->addConnection('secondary', $mysqlConfig);
$manager->use('secondary');

if ($manager->getDefaultName() !== 'secondary') {
    fail('use() did not switch default');
}
pass('ConnectionManager: use() switches default');

try {
    $manager->use('does_not_exist');
    fail('Should throw DatabaseException for unknown connection');
} catch (DatabaseException $e) {
    pass('ConnectionManager: use() throws DatabaseException for unknown name');
}

// -----------------------------------------------------------------------
// 19. disconnect() purges resolved instance
// -----------------------------------------------------------------------
$manager->connection('secondary'); // resolve it
$manager->disconnect('secondary');
if ($manager->isResolved('secondary')) {
    fail('disconnect() should purge the resolved instance');
}
pass('ConnectionManager: disconnect()');

// -----------------------------------------------------------------------
// Summary
// -----------------------------------------------------------------------
echo "\n\033[32m✔ All MySQL connection tests passed!\033[0m\n\n";