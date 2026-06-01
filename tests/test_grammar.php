<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Foxdb\Query\Grammars\MySqlGrammar;
use Foxdb\Query\Grammars\PostgresGrammar;

// -----------------------------------------------------------------------
// Helpers
// -----------------------------------------------------------------------
function pass(string $msg): void  { echo "\033[32m✔ {$msg}\033[0m\n"; }
function fail(string $msg): void  { echo "\033[31m✘ {$msg}\033[0m\n"; exit(1); }

function assertSql(string $expected, string $actual, string $label): void
{
    $e = preg_replace('/\s+/', ' ', trim($expected));
    $a = preg_replace('/\s+/', ' ', trim($actual));

    if ($e !== $a) {
        echo "\033[31m✘ {$label}\033[0m\n";
        echo "  Expected : {$e}\n";
        echo "  Got      : {$a}\n";
        exit(1);
    }

    pass($label);
}

$g  = new MySqlGrammar();   // primary grammar under test
$pg = new PostgresGrammar(); // used only for Postgres-specific tests

echo "\n=== FoxDB Grammar Tests (MySQL) ===\n\n";

// -----------------------------------------------------------------------
// Helper: empty state
// -----------------------------------------------------------------------
function state(array $overrides = []): array
{
    return array_merge([
        'table'     => 'users',
        'columns'   => [],
        'wheres'    => [],
        'joins'     => [],
        'groups'    => [],
        'havings'   => [],
        'orders'    => [],
        'limit'     => null,
        'offset'    => null,
        'distinct'  => false,
        'aggregate' => null,
    ], $overrides);
}

// -----------------------------------------------------------------------
// 1. Basic SELECT *
// -----------------------------------------------------------------------
assertSql(
    'SELECT * FROM `users`',
    $g->compileSelect(state()),
    'SELECT *',
);

// -----------------------------------------------------------------------
// 2. SELECT specific columns
// -----------------------------------------------------------------------
assertSql(
    'SELECT `id`, `name`, `email` FROM `users`',
    $g->compileSelect(state(['columns' => ['id', 'name', 'email']])),
    'SELECT columns',
);

// -----------------------------------------------------------------------
// 3. SELECT DISTINCT
// -----------------------------------------------------------------------
assertSql(
    'SELECT DISTINCT `name` FROM `users`',
    $g->compileSelect(state(['columns' => ['name'], 'distinct' => true])),
    'SELECT DISTINCT',
);

// -----------------------------------------------------------------------
// 4. Column alias: "name as full_name"
// -----------------------------------------------------------------------
assertSql(
    'SELECT `name` AS `full_name` FROM `users`',
    $g->compileSelect(state(['columns' => ['name as full_name']])),
    'Column alias (AS)',
);

// -----------------------------------------------------------------------
// 5. Dot notation: "users.id"
// -----------------------------------------------------------------------
assertSql(
    'SELECT `users`.`id` FROM `users`',
    $g->compileSelect(state(['columns' => ['users.id']])),
    'Dot-notation column',
);

// -----------------------------------------------------------------------
// 6. wrapTable with alias
// -----------------------------------------------------------------------
assertSql(
    'SELECT * FROM `orders` AS `o`',
    $g->compileSelect(state(['table' => 'orders as o'])),
    'Table alias (AS)',
);

// -----------------------------------------------------------------------
// 7. WHERE basic
// -----------------------------------------------------------------------
assertSql(
    'SELECT * FROM `users` WHERE `status` = ?',
    $g->compileSelect(state([
        'wheres' => [
            ['type' => 'basic', 'column' => 'status', 'operator' => '=', 'boolean' => 'AND'],
        ],
    ])),
    'WHERE basic',
);

// -----------------------------------------------------------------------
// 8. WHERE AND chaining
// -----------------------------------------------------------------------
assertSql(
    'SELECT * FROM `users` WHERE `age` > ? AND `active` = ?',
    $g->compileSelect(state([
        'wheres' => [
            ['type' => 'basic', 'column' => 'age',    'operator' => '>',  'boolean' => 'AND'],
            ['type' => 'basic', 'column' => 'active', 'operator' => '=',  'boolean' => 'AND'],
        ],
    ])),
    'WHERE AND chain',
);

// -----------------------------------------------------------------------
// 9. WHERE OR
// -----------------------------------------------------------------------
assertSql(
    'SELECT * FROM `users` WHERE `role` = ? OR `is_admin` = ?',
    $g->compileSelect(state([
        'wheres' => [
            ['type' => 'basic', 'column' => 'role',     'operator' => '=', 'boolean' => 'AND'],
            ['type' => 'basic', 'column' => 'is_admin', 'operator' => '=', 'boolean' => 'OR'],
        ],
    ])),
    'WHERE OR',
);

// -----------------------------------------------------------------------
// 10. WHERE IN
// -----------------------------------------------------------------------
assertSql(
    'SELECT * FROM `users` WHERE `id` IN (?, ?, ?)',
    $g->compileSelect(state([
        'wheres' => [
            ['type' => 'in', 'column' => 'id', 'values' => [1, 2, 3], 'boolean' => 'AND'],
        ],
    ])),
    'WHERE IN',
);

// -----------------------------------------------------------------------
// 11. WHERE NOT IN
// -----------------------------------------------------------------------
assertSql(
    'SELECT * FROM `users` WHERE `status` NOT IN (?, ?)',
    $g->compileSelect(state([
        'wheres' => [
            ['type' => 'notIn', 'column' => 'status', 'values' => ['a', 'b'], 'boolean' => 'AND'],
        ],
    ])),
    'WHERE NOT IN',
);

// -----------------------------------------------------------------------
// 12. WHERE NULL / NOT NULL
// -----------------------------------------------------------------------
assertSql(
    'SELECT * FROM `users` WHERE `deleted_at` IS NULL',
    $g->compileSelect(state([
        'wheres' => [['type' => 'null', 'column' => 'deleted_at', 'boolean' => 'AND']],
    ])),
    'WHERE IS NULL',
);

assertSql(
    'SELECT * FROM `users` WHERE `email` IS NOT NULL',
    $g->compileSelect(state([
        'wheres' => [['type' => 'notNull', 'column' => 'email', 'boolean' => 'AND']],
    ])),
    'WHERE IS NOT NULL',
);

// -----------------------------------------------------------------------
// 13. WHERE BETWEEN / NOT BETWEEN
// -----------------------------------------------------------------------
assertSql(
    'SELECT * FROM `users` WHERE `age` BETWEEN ? AND ?',
    $g->compileSelect(state([
        'wheres' => [['type' => 'between', 'column' => 'age', 'boolean' => 'AND']],
    ])),
    'WHERE BETWEEN',
);

assertSql(
    'SELECT * FROM `users` WHERE `score` NOT BETWEEN ? AND ?',
    $g->compileSelect(state([
        'wheres' => [['type' => 'notBetween', 'column' => 'score', 'boolean' => 'AND']],
    ])),
    'WHERE NOT BETWEEN',
);

// -----------------------------------------------------------------------
// 14. WHERE RAW
// -----------------------------------------------------------------------
assertSql(
    'SELECT * FROM `users` WHERE age > 18 AND active = 1',
    $g->compileSelect(state([
        'wheres' => [
            ['type' => 'raw', 'sql' => 'age > 18',    'boolean' => 'AND'],
            ['type' => 'raw', 'sql' => 'active = 1',  'boolean' => 'AND'],
        ],
    ])),
    'WHERE RAW',
);

// -----------------------------------------------------------------------
// 15. WHERE column-to-column
// -----------------------------------------------------------------------
assertSql(
    'SELECT * FROM `orders` WHERE `total` > `minimum`',
    $g->compileSelect(state([
        'table'  => 'orders',
        'wheres' => [
            ['type' => 'column', 'first' => 'total', 'operator' => '>', 'second' => 'minimum', 'boolean' => 'AND'],
        ],
    ])),
    'WHERE column comparison',
);

// -----------------------------------------------------------------------
// 16. WHERE NESTED (grouped conditions)
// -----------------------------------------------------------------------
assertSql(
    'SELECT * FROM `users` WHERE (`age` > ? AND `role` = ?)',
    $g->compileSelect(state([
        'wheres' => [
            [
                'type'    => 'nested',
                'boolean' => 'AND',
                'wheres'  => [
                    ['type' => 'basic', 'column' => 'age',  'operator' => '>', 'boolean' => 'AND'],
                    ['type' => 'basic', 'column' => 'role', 'operator' => '=', 'boolean' => 'AND'],
                ],
            ],
        ],
    ])),
    'WHERE NESTED group',
);

// -----------------------------------------------------------------------
// 17. WHERE date helpers
// -----------------------------------------------------------------------
assertSql(
    'SELECT * FROM `posts` WHERE DATE(`created_at`) = ?',
    $g->compileSelect(state([
        'table'  => 'posts',
        'wheres' => [['type' => 'date', 'column' => 'created_at', 'operator' => '=', 'boolean' => 'AND']],
    ])),
    'WHERE DATE()',
);

assertSql(
    'SELECT * FROM `posts` WHERE MONTH(`created_at`) = ?',
    $g->compileSelect(state([
        'table'  => 'posts',
        'wheres' => [['type' => 'month', 'column' => 'created_at', 'operator' => '=', 'boolean' => 'AND']],
    ])),
    'WHERE MONTH()',
);

assertSql(
    'SELECT * FROM `posts` WHERE YEAR(`created_at`) = ?',
    $g->compileSelect(state([
        'table'  => 'posts',
        'wheres' => [['type' => 'year', 'column' => 'created_at', 'operator' => '=', 'boolean' => 'AND']],
    ])),
    'WHERE YEAR()',
);

assertSql(
    'SELECT * FROM `logs` WHERE TIME(`logged_at`) > ?',
    $g->compileSelect(state([
        'table'  => 'logs',
        'wheres' => [['type' => 'time', 'column' => 'logged_at', 'operator' => '>', 'boolean' => 'AND']],
    ])),
    'WHERE TIME()',
);

assertSql(
    'SELECT * FROM `posts` WHERE DAY(`created_at`) = ?',
    $g->compileSelect(state([
        'table'  => 'posts',
        'wheres' => [['type' => 'day', 'column' => 'created_at', 'operator' => '=', 'boolean' => 'AND']],
    ])),
    'WHERE DAY()',
);

// -----------------------------------------------------------------------
// 18. WHERE EXISTS / NOT EXISTS
// -----------------------------------------------------------------------
assertSql(
    'SELECT * FROM `users` WHERE EXISTS (SELECT 1 FROM `orders` WHERE `orders`.`user_id` = `users`.`id`)',
    $g->compileSelect(state([
        'wheres' => [[
            'type'    => 'exists',
            'sql'     => 'SELECT 1 FROM `orders` WHERE `orders`.`user_id` = `users`.`id`',
            'boolean' => 'AND',
        ]],
    ])),
    'WHERE EXISTS',
);

// -----------------------------------------------------------------------
// 19. INNER JOIN
// -----------------------------------------------------------------------
assertSql(
    'SELECT * FROM `users` INNER JOIN `orders` ON `orders`.`user_id` = `users`.`id`',
    $g->compileSelect(state([
        'joins' => [[
            'type'    => 'INNER',
            'table'   => 'orders',
            'clauses' => [[
                'type'     => 'ON',
                'first'    => 'orders.user_id',
                'operator' => '=',
                'second'   => 'users.id',
                'raw'      => false,
            ]],
        ]],
    ])),
    'INNER JOIN',
);

// -----------------------------------------------------------------------
// 20. LEFT JOIN
// -----------------------------------------------------------------------
assertSql(
    'SELECT * FROM `users` LEFT JOIN `profiles` ON `profiles`.`user_id` = `users`.`id`',
    $g->compileSelect(state([
        'joins' => [[
            'type'    => 'LEFT',
            'table'   => 'profiles',
            'clauses' => [[
                'type'     => 'ON',
                'first'    => 'profiles.user_id',
                'operator' => '=',
                'second'   => 'users.id',
                'raw'      => false,
            ]],
        ]],
    ])),
    'LEFT JOIN',
);

// -----------------------------------------------------------------------
// 21. Multiple JOINs
// -----------------------------------------------------------------------
assertSql(
    'SELECT * FROM `users` INNER JOIN `orders` ON `orders`.`user_id` = `users`.`id` LEFT JOIN `payments` ON `payments`.`order_id` = `orders`.`id`',
    $g->compileSelect(state([
        'joins' => [
            [
                'type'    => 'INNER',
                'table'   => 'orders',
                'clauses' => [['type' => 'ON', 'first' => 'orders.user_id',    'operator' => '=', 'second' => 'users.id',   'raw' => false]],
            ],
            [
                'type'    => 'LEFT',
                'table'   => 'payments',
                'clauses' => [['type' => 'ON', 'first' => 'payments.order_id', 'operator' => '=', 'second' => 'orders.id', 'raw' => false]],
            ],
        ],
    ])),
    'Multiple JOINs',
);

// -----------------------------------------------------------------------
// 22. GROUP BY
// -----------------------------------------------------------------------
assertSql(
    'SELECT * FROM `orders` GROUP BY `status`',
    $g->compileSelect(state(['table' => 'orders', 'groups' => ['status']])),
    'GROUP BY single',
);

assertSql(
    'SELECT * FROM `orders` GROUP BY `year`, `month`',
    $g->compileSelect(state(['table' => 'orders', 'groups' => ['year', 'month']])),
    'GROUP BY multiple',
);

// -----------------------------------------------------------------------
// 23. HAVING
// -----------------------------------------------------------------------
assertSql(
    'SELECT * FROM `orders` GROUP BY `status` HAVING `total` > ?',
    $g->compileSelect(state([
        'table'   => 'orders',
        'groups'  => ['status'],
        'havings' => [['type' => 'basic', 'column' => 'total', 'operator' => '>', 'boolean' => 'AND']],
    ])),
    'HAVING basic',
);

assertSql(
    'SELECT * FROM `orders` HAVING COUNT(*) > 5',
    $g->compileSelect(state([
        'table'   => 'orders',
        'havings' => [['type' => 'raw', 'sql' => 'COUNT(*) > 5', 'boolean' => 'AND']],
    ])),
    'HAVING raw',
);

// -----------------------------------------------------------------------
// 24. ORDER BY
// -----------------------------------------------------------------------
assertSql(
    'SELECT * FROM `users` ORDER BY `name` ASC',
    $g->compileSelect(state([
        'orders' => [['column' => 'name', 'direction' => 'ASC']],
    ])),
    'ORDER BY ASC',
);

assertSql(
    'SELECT * FROM `users` ORDER BY `created_at` DESC',
    $g->compileSelect(state([
        'orders' => [['column' => 'created_at', 'direction' => 'DESC']],
    ])),
    'ORDER BY DESC',
);

assertSql(
    'SELECT * FROM `users` ORDER BY `name` ASC, `age` DESC',
    $g->compileSelect(state([
        'orders' => [
            ['column' => 'name', 'direction' => 'ASC'],
            ['column' => 'age',  'direction' => 'DESC'],
        ],
    ])),
    'ORDER BY multiple',
);

// -----------------------------------------------------------------------
// 25. ORDER BY RAW
// -----------------------------------------------------------------------
assertSql(
    'SELECT * FROM `users` ORDER BY FIELD(`status`, \'active\', \'pending\')',
    $g->compileSelect(state([
        'orders' => [['raw' => "FIELD(`status`, 'active', 'pending')"]],
    ])),
    'ORDER BY RAW',
);

// -----------------------------------------------------------------------
// 26. LIMIT / OFFSET
// -----------------------------------------------------------------------
assertSql(
    'SELECT * FROM `users` LIMIT 10',
    $g->compileSelect(state(['limit' => 10])),
    'LIMIT',
);

assertSql(
    'SELECT * FROM `users` LIMIT 10 OFFSET 20',
    $g->compileSelect(state(['limit' => 10, 'offset' => 20])),
    'LIMIT + OFFSET',
);

// -----------------------------------------------------------------------
// 27. Aggregate: COUNT(*)
// -----------------------------------------------------------------------
assertSql(
    'SELECT COUNT(*) FROM `users`',
    $g->compileAggregateQuery('COUNT', '*', state()),
    'Aggregate COUNT(*)',
);

// -----------------------------------------------------------------------
// 28. Aggregate: SUM with WHERE
// -----------------------------------------------------------------------
assertSql(
    'SELECT SUM(`amount`) FROM `orders` WHERE `status` = ?',
    $g->compileAggregateQuery('SUM', 'amount', state([
        'table'  => 'orders',
        'wheres' => [['type' => 'basic', 'column' => 'status', 'operator' => '=', 'boolean' => 'AND']],
    ])),
    'Aggregate SUM with WHERE',
);

// -----------------------------------------------------------------------
// 29. Aggregate: COUNT DISTINCT
// -----------------------------------------------------------------------
assertSql(
    'SELECT COUNT(DISTINCT `email`) FROM `users`',
    $g->compileAggregateQuery('COUNT', 'email', state(['distinct' => true])),
    'Aggregate COUNT DISTINCT',
);

// -----------------------------------------------------------------------
// 30. compileInsert
// -----------------------------------------------------------------------
assertSql(
    'INSERT INTO `users` (`name`, `email`, `age`) VALUES (?, ?, ?)',
    $g->compileInsert('users', ['name' => 'Ali', 'email' => 'ali@test.com', 'age' => 25]),
    'INSERT single row',
);

// -----------------------------------------------------------------------
// 31. compileInsertBatch
// -----------------------------------------------------------------------
assertSql(
    'INSERT INTO `users` (`name`, `email`) VALUES (?, ?), (?, ?)',
    $g->compileInsertBatch('users', [
        ['name' => 'Ali',  'email' => 'ali@test.com'],
        ['name' => 'Sara', 'email' => 'sara@test.com'],
    ]),
    'INSERT batch (multiple rows)',
);

// -----------------------------------------------------------------------
// 32. compileUpdate
// -----------------------------------------------------------------------
assertSql(
    'UPDATE `users` SET `name` = ?, `email` = ? WHERE `id` = ?',
    $g->compileUpdate('users',
        state(['wheres' => [['type' => 'basic', 'column' => 'id', 'operator' => '=', 'boolean' => 'AND']]]),
        ['name' => 'Ali', 'email' => 'ali@test.com'],
    ),
    'UPDATE with WHERE',
);

// -----------------------------------------------------------------------
// 33. compileUpdate with LIMIT (MySQL supports it)
// -----------------------------------------------------------------------
assertSql(
    'UPDATE `users` SET `active` = ? WHERE `status` = ? LIMIT 5',
    $g->compileUpdate('users',
        state([
            'wheres' => [['type' => 'basic', 'column' => 'status', 'operator' => '=', 'boolean' => 'AND']],
            'limit'  => 5,
        ]),
        ['active' => 1],
    ),
    'UPDATE with WHERE + LIMIT',
);

// -----------------------------------------------------------------------
// 34. compileDelete
// -----------------------------------------------------------------------
assertSql(
    'DELETE FROM `users` WHERE `id` = ?',
    $g->compileDelete('users',
        state(['wheres' => [['type' => 'basic', 'column' => 'id', 'operator' => '=', 'boolean' => 'AND']]]),
    ),
    'DELETE with WHERE',
);

// -----------------------------------------------------------------------
// 35. compileDelete with ORDER + LIMIT (MySQL)
// -----------------------------------------------------------------------
assertSql(
    'DELETE FROM `logs` WHERE `level` = ? ORDER BY `created_at` ASC LIMIT 100',
    $g->compileDelete('logs', state([
        'wheres' => [['type' => 'basic', 'column' => 'level', 'operator' => '=', 'boolean' => 'AND']],
        'orders' => [['column' => 'created_at', 'direction' => 'ASC']],
        'limit'  => 100,
    ])),
    'DELETE with WHERE + ORDER + LIMIT (MySQL)',
);

// -----------------------------------------------------------------------
// 36. MySQL upsert: ON DUPLICATE KEY UPDATE
// -----------------------------------------------------------------------
assertSql(
    'INSERT INTO `users` (`email`, `name`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `name` = ?',
    $g->compileUpsert(
        'users',
        ['email' => 'a@b.com', 'name' => 'Ali'],
        ['name'  => 'Ali'],
    ),
    'MySQL upsert (ON DUPLICATE KEY UPDATE)',
);

// -----------------------------------------------------------------------
// 37. MySQL REPLACE INTO
// -----------------------------------------------------------------------
assertSql(
    'REPLACE INTO `sessions` (`id`, `data`) VALUES (?, ?)',
    $g->compileReplace('sessions', ['id' => 1, 'data' => 'xyz']),
    'MySQL REPLACE INTO',
);

// -----------------------------------------------------------------------
// 38. MySQL LOCK FOR UPDATE
// -----------------------------------------------------------------------
assertSql(
    'SELECT * FROM `users` WHERE `id` = ? FOR UPDATE',
    $g->compileLockForUpdate(state([
        'wheres' => [['type' => 'basic', 'column' => 'id', 'operator' => '=', 'boolean' => 'AND']],
    ])),
    'MySQL FOR UPDATE lock',
);

// -----------------------------------------------------------------------
// 39. MySQL LOCK IN SHARE MODE
// -----------------------------------------------------------------------
assertSql(
    'SELECT * FROM `users` WHERE `id` = ? LOCK IN SHARE MODE',
    $g->compileLockShared(state([
        'wheres' => [['type' => 'basic', 'column' => 'id', 'operator' => '=', 'boolean' => 'AND']],
    ])),
    'MySQL LOCK IN SHARE MODE',
);

// -----------------------------------------------------------------------
// 40. Full complex query
// -----------------------------------------------------------------------
assertSql(
    'SELECT `u`.`id`, `u`.`name` FROM `users` AS `u` INNER JOIN `orders` AS `o` ON `o`.`user_id` = `u`.`id` WHERE `u`.`active` = ? AND `o`.`total` > ? GROUP BY `u`.`id` HAVING `total` > ? ORDER BY `u`.`name` ASC LIMIT 20 OFFSET 40',
    $g->compileSelect([
        'table'     => 'users as u',
        'columns'   => ['u.id', 'u.name'],
        'distinct'  => false,
        'aggregate' => null,
        'joins'     => [[
            'type'    => 'INNER',
            'table'   => 'orders as o',
            'clauses' => [['type' => 'ON', 'first' => 'o.user_id', 'operator' => '=', 'second' => 'u.id', 'raw' => false]],
        ]],
        'wheres' => [
            ['type' => 'basic', 'column' => 'u.active', 'operator' => '=',  'boolean' => 'AND'],
            ['type' => 'basic', 'column' => 'o.total',  'operator' => '>',  'boolean' => 'AND'],
        ],
        'groups'  => ['u.id'],
        'havings' => [['type' => 'basic', 'column' => 'total', 'operator' => '>', 'boolean' => 'AND']],
        'orders'  => [['column' => 'u.name', 'direction' => 'ASC']],
        'limit'   => 20,
        'offset'  => 40,
    ]),
    'Full complex SELECT query',
);

// -----------------------------------------------------------------------
// 41. validateOperator — valid
// -----------------------------------------------------------------------
foreach (['=', '!=', '<>', '>', '<', '>=', '<=', 'LIKE', 'NOT LIKE', 'REGEXP'] as $op) {
    try {
        $g->validateOperator($op);
    } catch (\InvalidArgumentException $e) {
        fail("validateOperator should accept [{$op}]");
    }
}
pass('validateOperator: accepts all valid operators');

// -----------------------------------------------------------------------
// 42. validateOperator — invalid throws
// -----------------------------------------------------------------------
try {
    $g->validateOperator('DROP');
    fail('validateOperator should reject [DROP]');
} catch (\InvalidArgumentException) {
    pass('validateOperator: rejects invalid operator [DROP]');
}

// -----------------------------------------------------------------------
// 43. parameters() helper
// -----------------------------------------------------------------------
if ($g->parameters(3) !== '?, ?, ?') {
    fail('parameters(3) mismatch');
}
pass('parameters(3) → ?, ?, ?');

// -----------------------------------------------------------------------
// 44. PostgreSQL — double-quote identifiers
// -----------------------------------------------------------------------
assertSql(
    'SELECT "id", "name" FROM "users"',
    $pg->compileSelect(state(['columns' => ['id', 'name']])),
    'Postgres: double-quote identifiers',
);

// -----------------------------------------------------------------------
// 45. PostgreSQL — upsert ON CONFLICT DO UPDATE
// -----------------------------------------------------------------------
assertSql(
    'INSERT INTO "users" ("email", "name") VALUES (?, ?) ON CONFLICT ("id") DO UPDATE SET "name" = EXCLUDED."name"',
    $pg->compileUpsert(
        'users',
        ['email' => 'a@b.com', 'name' => 'Ali'],
        ['name'  => 'Ali'],
        'id',
    ),
    'Postgres: upsert ON CONFLICT DO UPDATE',
);

// -----------------------------------------------------------------------
// 46. PostgreSQL — UPDATE has no ORDER BY / LIMIT
// -----------------------------------------------------------------------
assertSql(
    'UPDATE "users" SET "active" = ? WHERE "status" = ?',
    $pg->compileUpdate('users',
        state([
            'wheres' => [['type' => 'basic', 'column' => 'status', 'operator' => '=', 'boolean' => 'AND']],
            'limit'  => 5,
            'orders' => [['column' => 'id', 'direction' => 'ASC']],
        ]),
        ['active' => 1],
    ),
    'Postgres: UPDATE strips ORDER BY + LIMIT',
);

// -----------------------------------------------------------------------
// 47. PostgreSQL — RETURNING clause
// -----------------------------------------------------------------------
$baseSql = $pg->compileInsert('users', ['name' => 'Ali', 'email' => 'ali@test.com']);
assertSql(
    'INSERT INTO "users" ("name", "email") VALUES (?, ?) RETURNING *',
    $pg->withReturning($baseSql),
    'Postgres: RETURNING *',
);

assertSql(
    'INSERT INTO "users" ("name", "email") VALUES (?, ?) RETURNING "id", "name"',
    $pg->withReturning($baseSql, ['id', 'name']),
    'Postgres: RETURNING columns',
);

// -----------------------------------------------------------------------
// Summary
// -----------------------------------------------------------------------
echo "\n\033[32m✔ All grammar tests passed!\033[0m\n\n";
