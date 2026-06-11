<?php

declare(strict_types=1);

namespace Foxdb\Tests\Unit;

use Foxdb\Query\Grammars\MySqlGrammar;
use Foxdb\Query\Grammars\PostgresGrammar;
use PHPUnit\Framework\TestCase;

/**
 * GrammarTest — verifies SQL compilation for MySQL and PostgreSQL.
 * No DB connection needed.
 */
class GrammarTest extends TestCase
{
    private MySqlGrammar   $mysql;
    private PostgresGrammar $pgsql;

    protected function setUp(): void
    {
        $this->mysql = new MySqlGrammar();
        $this->pgsql = new PostgresGrammar();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function norm(string $sql): string
    {
        return preg_replace('/\s+/', ' ', trim($sql));
    }

    private function state(array $overrides = []): array
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

    private function where(string $col, string $op = '='): array
    {
        return ['type' => 'basic', 'column' => $col, 'operator' => $op, 'boolean' => 'AND'];
    }

    // -----------------------------------------------------------------------
    // MySQL — identifiers
    // -----------------------------------------------------------------------

    public function test_mysql_wraps_column_with_backticks(): void
    {
        $sql = $this->mysql->compileSelect($this->state(['columns' => ['name']]));
        $this->assertStringContainsString('`name`', $sql);
    }

    public function test_mysql_handles_dot_notation(): void
    {
        $sql = $this->mysql->compileSelect($this->state(['columns' => ['users.id']]));
        $this->assertSame('SELECT `users`.`id` FROM `users`', $this->norm($sql));
    }

    public function test_mysql_handles_column_alias(): void
    {
        $sql = $this->mysql->compileSelect($this->state(['columns' => ['name as full_name']]));
        $this->assertSame('SELECT `name` AS `full_name` FROM `users`', $this->norm($sql));
    }

    public function test_mysql_handles_table_alias(): void
    {
        $sql = $this->mysql->compileSelect($this->state(['table' => 'users as u']));
        $this->assertSame('SELECT * FROM `users` AS `u`', $this->norm($sql));
    }

    // -----------------------------------------------------------------------
    // MySQL — SELECT variants
    // -----------------------------------------------------------------------

    public function test_mysql_select_star(): void
    {
        $this->assertSame(
            'SELECT * FROM `users`',
            $this->norm($this->mysql->compileSelect($this->state())),
        );
    }

    public function test_mysql_select_distinct(): void
    {
        $sql = $this->mysql->compileSelect($this->state(['columns' => ['email'], 'distinct' => true]));
        $this->assertSame('SELECT DISTINCT `email` FROM `users`', $this->norm($sql));
    }

    // -----------------------------------------------------------------------
    // MySQL — WHERE variants
    // -----------------------------------------------------------------------

    public function test_mysql_where_in(): void
    {
        $sql = $this->mysql->compileSelect($this->state([
            'wheres' => [['type' => 'in', 'column' => 'id', 'values' => [1, 2], 'boolean' => 'AND']],
        ]));
        $this->assertSame('SELECT * FROM `users` WHERE `id` IN (?, ?)', $this->norm($sql));
    }

    public function test_mysql_where_null(): void
    {
        $sql = $this->mysql->compileSelect($this->state([
            'wheres' => [['type' => 'null', 'column' => 'deleted_at', 'boolean' => 'AND']],
        ]));
        $this->assertSame('SELECT * FROM `users` WHERE `deleted_at` IS NULL', $this->norm($sql));
    }

    public function test_mysql_where_between(): void
    {
        $sql = $this->mysql->compileSelect($this->state([
            'wheres' => [['type' => 'between', 'column' => 'age', 'boolean' => 'AND']],
        ]));
        $this->assertSame('SELECT * FROM `users` WHERE `age` BETWEEN ? AND ?', $this->norm($sql));
    }

    public function test_mysql_where_nested(): void
    {
        $sql = $this->mysql->compileSelect($this->state([
            'wheres' => [[
                'type'    => 'nested',
                'boolean' => 'AND',
                'wheres'  => [
                    $this->where('role'),
                    ['type' => 'basic', 'column' => 'active', 'operator' => '=', 'boolean' => 'OR'],
                ],
            ]],
        ]));
        $this->assertSame('SELECT * FROM `users` WHERE (`role` = ? OR `active` = ?)', $this->norm($sql));
    }

    public function test_mysql_where_date_helpers(): void
    {
        foreach (['date' => 'DATE', 'month' => 'MONTH', 'year' => 'YEAR', 'day' => 'DAY', 'time' => 'TIME'] as $type => $fn) {
            $sql = $this->mysql->compileSelect($this->state([
                'wheres' => [['type' => $type, 'column' => 'created_at', 'operator' => '=', 'boolean' => 'AND']],
            ]));
            $this->assertStringContainsString("{$fn}(`created_at`)", $sql, "Failed for type: {$type}");
        }
    }

    // -----------------------------------------------------------------------
    // MySQL — aggregate
    // -----------------------------------------------------------------------

    public function test_mysql_count_aggregate(): void
    {
        $sql = $this->mysql->compileAggregateQuery('COUNT', '*', $this->state());
        $this->assertSame('SELECT COUNT(*) FROM `users`', $this->norm($sql));
    }

    public function test_mysql_count_distinct_aggregate(): void
    {
        $sql = $this->mysql->compileAggregateQuery('COUNT', 'email', $this->state(['distinct' => true]));
        $this->assertSame('SELECT COUNT(DISTINCT `email`) FROM `users`', $this->norm($sql));
    }

    // -----------------------------------------------------------------------
    // MySQL — INSERT / UPDATE / DELETE
    // -----------------------------------------------------------------------

    public function test_mysql_compile_insert(): void
    {
        $sql = $this->mysql->compileInsert('users', ['name' => 'Ali', 'email' => 'a@b.com']);
        $this->assertSame('INSERT INTO `users` (`name`, `email`) VALUES (?, ?)', $this->norm($sql));
    }

    public function test_mysql_compile_insert_batch(): void
    {
        $sql = $this->mysql->compileInsertBatch('users', [
            ['name' => 'Ali'],
            ['name' => 'Sara'],
        ]);
        $this->assertSame('INSERT INTO `users` (`name`) VALUES (?), (?)', $this->norm($sql));
    }

    public function test_mysql_compile_update(): void
    {
        $sql = $this->mysql->compileUpdate(
            'users',
            $this->state(['wheres' => [$this->where('id')]]),
            ['name' => 'Ali'],
        );
        $this->assertSame('UPDATE `users` SET `name` = ? WHERE `id` = ?', $this->norm($sql));
    }

    public function test_mysql_compile_update_with_limit(): void
    {
        $sql = $this->mysql->compileUpdate(
            'users',
            $this->state(['wheres' => [$this->where('active')], 'limit' => 5]),
            ['score' => 0],
        );
        $this->assertSame('UPDATE `users` SET `score` = ? WHERE `active` = ? LIMIT 5', $this->norm($sql));
    }

    public function test_mysql_compile_delete(): void
    {
        $sql = $this->mysql->compileDelete('users', $this->state(['wheres' => [$this->where('id')]]));
        $this->assertSame('DELETE FROM `users` WHERE `id` = ?', $this->norm($sql));
    }

    // -----------------------------------------------------------------------
    // MySQL — upsert / replace / lock
    // -----------------------------------------------------------------------

    public function test_mysql_upsert(): void
    {
        $sql = $this->mysql->compileUpsert(
            'users',
            ['email' => 'a@b.com', 'name' => 'Ali'],
            ['name'  => 'Ali'],
        );
        $this->assertSame(
            'INSERT INTO `users` (`email`, `name`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `name` = ?',
            $this->norm($sql),
        );
    }

    public function test_mysql_replace(): void
    {
        $sql = $this->mysql->compileReplace('sessions', ['id' => 1, 'data' => 'xyz']);
        $this->assertSame('REPLACE INTO `sessions` (`id`, `data`) VALUES (?, ?)', $this->norm($sql));
    }

    public function test_mysql_lock_for_update(): void
    {
        $sql = $this->mysql->compileLockForUpdate($this->state(['wheres' => [$this->where('id')]]));
        $this->assertStringEndsWith('FOR UPDATE', $this->norm($sql));
    }

    public function test_mysql_lock_shared(): void
    {
        $sql = $this->mysql->compileLockShared($this->state(['wheres' => [$this->where('id')]]));
        $this->assertStringEndsWith('LOCK IN SHARE MODE', $this->norm($sql));
    }

    // -----------------------------------------------------------------------
    // MySQL — ORDER BY + LIMIT + OFFSET
    // -----------------------------------------------------------------------

    public function test_mysql_order_limit_offset(): void
    {
        $sql = $this->mysql->compileSelect($this->state([
            'orders' => [['column' => 'name', 'direction' => 'ASC']],
            'limit'  => 10,
            'offset' => 20,
        ]));
        $this->assertSame('SELECT * FROM `users` ORDER BY `name` ASC LIMIT 10 OFFSET 20', $this->norm($sql));
    }

    // -----------------------------------------------------------------------
    // MySQL — validateOperator
    // -----------------------------------------------------------------------

    public function test_mysql_valid_operators_pass(): void
    {
        foreach (['=', '!=', '<>', '>', '<', '>=', '<=', 'LIKE', 'NOT LIKE'] as $op) {
            $this->assertSame($op, $this->mysql->validateOperator($op));
        }
    }

    public function test_mysql_invalid_operator_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->mysql->validateOperator('DROP');
    }

    // -----------------------------------------------------------------------
    // PostgreSQL — identifiers
    // -----------------------------------------------------------------------

    public function test_pgsql_wraps_column_with_double_quotes(): void
    {
        $sql = $this->pgsql->compileSelect($this->state(['columns' => ['name', 'email']]));
        $this->assertSame('SELECT "name", "email" FROM "users"', $this->norm($sql));
    }

    // -----------------------------------------------------------------------
    // PostgreSQL — upsert ON CONFLICT
    // -----------------------------------------------------------------------

    public function test_pgsql_upsert_on_conflict(): void
    {
        $sql = $this->pgsql->compileUpsert(
            'users',
            ['email' => 'a@b.com', 'name' => 'Ali'],
            ['name'  => 'Ali'],
            'email',
        );
        $this->assertStringContainsString('ON CONFLICT', $sql);
        $this->assertStringContainsString('DO UPDATE SET', $sql);
    }

    // -----------------------------------------------------------------------
    // PostgreSQL — UPDATE strips LIMIT/ORDER
    // -----------------------------------------------------------------------

    public function test_pgsql_update_strips_limit_and_order(): void
    {
        $sql = $this->pgsql->compileUpdate(
            'users',
            $this->state([
                'wheres' => [$this->where('active')],
                'limit'  => 5,
                'orders' => [['column' => 'id', 'direction' => 'ASC']],
            ]),
            ['score' => 0],
        );
        $this->assertStringNotContainsString('LIMIT', $sql);
        $this->assertStringNotContainsString('ORDER BY', $sql);
    }

    // -----------------------------------------------------------------------
    // PostgreSQL — RETURNING
    // -----------------------------------------------------------------------

    public function test_pgsql_returning_star(): void
    {
        $base = $this->pgsql->compileInsert('users', ['name' => 'Ali']);
        $sql  = $this->pgsql->withReturning($base);
        $this->assertStringEndsWith('RETURNING *', $this->norm($sql));
    }

    public function test_pgsql_returning_columns(): void
    {
        $base = $this->pgsql->compileInsert('users', ['name' => 'Ali']);
        $sql  = $this->pgsql->withReturning($base, ['id', 'name']);
        $this->assertStringContainsString('RETURNING "id", "name"', $this->norm($sql));
    }

    // -----------------------------------------------------------------------
    // parameters() helper
    // -----------------------------------------------------------------------

    public function test_parameters_placeholder(): void
    {
        $this->assertSame('?, ?, ?', $this->mysql->parameters(3));
        $this->assertSame('?',       $this->mysql->parameters(1));
    }
}
