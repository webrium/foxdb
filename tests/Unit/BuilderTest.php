<?php

declare(strict_types=1);

namespace Foxdb\Tests\Unit;

use Foxdb\Query\Builder;
use Foxdb\Query\Grammars\MySqlGrammar;
use Foxdb\Query\Grammars\PostgresGrammar;
use Foxdb\Query\Grammars\Grammar;
use PHPUnit\Framework\TestCase;

/**
 * BuilderTest — verifies SQL generation without touching a real database.
 *
 * Uses a mock Connection so Builder can compile SQL and collect bindings
 * without needing PDO.
 */
class BuilderTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function builder(Grammar $grammar = null): Builder
    {
        $conn = $this->createMock(\Foxdb\Contracts\ConnectionInterface::class);
        $g    = $grammar ?? new MySqlGrammar();
        return (new Builder($conn, $g))->table('users');
    }

    private function normalizeSql(string $sql): string
    {
        return preg_replace('/\s+/', ' ', trim($sql));
    }

    private function assertSql(string $expected, Builder $builder): void
    {
        $this->assertSame(
            $this->normalizeSql($expected),
            $this->normalizeSql($builder->toSql()),
        );
    }

    // -----------------------------------------------------------------------
    // SELECT basics
    // -----------------------------------------------------------------------

    public function test_select_star(): void
    {
        $this->assertSql('SELECT * FROM `users`', $this->builder());
    }

    public function test_select_specific_columns(): void
    {
        $this->assertSql(
            'SELECT `id`, `name` FROM `users`',
            $this->builder()->select('id', 'name'),
        );
    }

    public function test_select_distinct(): void
    {
        $this->assertSql(
            'SELECT DISTINCT `email` FROM `users`',
            $this->builder()->select('email')->distinct(),
        );
    }

    public function test_add_select(): void
    {
        $this->assertSql(
            'SELECT `id`, `name` FROM `users`',
            $this->builder()->select('id')->addSelect('name'),
        );
    }

    // -----------------------------------------------------------------------
    // WHERE
    // -----------------------------------------------------------------------

    public function test_where_equals(): void
    {
        $b = $this->builder()->where('active', 1);
        $this->assertSql('SELECT * FROM `users` WHERE `active` = ?', $b);
        $this->assertSame([1], $b->getBindings());
    }

    public function test_where_with_explicit_operator(): void
    {
        $b = $this->builder()->where('age', '>', 18);
        $this->assertSql('SELECT * FROM `users` WHERE `age` > ?', $b);
        $this->assertSame([18], $b->getBindings());
    }

    public function test_where_and_chain(): void
    {
        $b = $this->builder()->where('active', 1)->where('age', '>', 18);
        $this->assertSql('SELECT * FROM `users` WHERE `active` = ? AND `age` > ?', $b);
        $this->assertSame([1, 18], $b->getBindings());
    }

    public function test_or_where(): void
    {
        $b = $this->builder()->where('role', 'admin')->orWhere('role', 'mod');
        $this->assertSql('SELECT * FROM `users` WHERE `role` = ? OR `role` = ?', $b);
    }

    public function test_where_in(): void
    {
        $b = $this->builder()->whereIn('id', [1, 2, 3]);
        $this->assertSql('SELECT * FROM `users` WHERE `id` IN (?, ?, ?)', $b);
        $this->assertSame([1, 2, 3], $b->getBindings());
    }

    public function test_where_not_in(): void
    {
        $b = $this->builder()->whereNotIn('status', ['banned', 'pending']);
        $this->assertSql('SELECT * FROM `users` WHERE `status` NOT IN (?, ?)', $b);
    }

    public function test_where_null(): void
    {
        $this->assertSql(
            'SELECT * FROM `users` WHERE `deleted_at` IS NULL',
            $this->builder()->whereNull('deleted_at'),
        );
    }

    public function test_where_not_null(): void
    {
        $this->assertSql(
            'SELECT * FROM `users` WHERE `email` IS NOT NULL',
            $this->builder()->whereNotNull('email'),
        );
    }

    public function test_where_between(): void
    {
        $b = $this->builder()->whereBetween('age', 18, 65);
        $this->assertSql('SELECT * FROM `users` WHERE `age` BETWEEN ? AND ?', $b);
        $this->assertSame([18, 65], $b->getBindings());
    }

    public function test_where_not_between(): void
    {
        $b = $this->builder()->whereNotBetween('score', 0, 50);
        $this->assertSql('SELECT * FROM `users` WHERE `score` NOT BETWEEN ? AND ?', $b);
    }

    public function test_where_nested_group(): void
    {
        $b = $this->builder()->where(function ($q) {
            $q->where('role', 'admin')->orWhere('role', 'mod');
        })->where('active', 1);

        $this->assertSql(
            'SELECT * FROM `users` WHERE (`role` = ? OR `role` = ?) AND `active` = ?',
            $b,
        );
    }

    /**
     * Regression: column names that collide with built-in PHP function names
     * (key, list, count, current, ...) were wrongly detected as callables by
     * is_callable(), routing them into whereNested() and triggering
     * "Calling key() on an object is deprecated". Only Closures are nested
     * groups now — a plain string is always a column name.
     */
    public function test_where_with_php_function_named_column(): void
    {
        $b = $this->builder()->where('key', 'footer_settings');
        $this->assertSql('SELECT * FROM `users` WHERE `key` = ?', $b);
        $this->assertSame(['footer_settings'], $b->getBindings());
    }

    public function test_where_with_count_column(): void
    {
        $b = $this->builder()->where('count', '>', 5);
        $this->assertSql('SELECT * FROM `users` WHERE `count` > ?', $b);
    }

    public function test_nested_group_still_works_with_closure(): void
    {
        // A real Closure must still produce a nested group
        $b = $this->builder()->where(fn($q) => $q->where('a', 1)->orWhere('b', 2));
        $this->assertSql('SELECT * FROM `users` WHERE (`a` = ? OR `b` = ?)', $b);
    }

    public function test_where_raw(): void
    {
        $b = $this->builder()->whereRaw('age > 18 AND active = 1');
        $this->assertSql('SELECT * FROM `users` WHERE age > 18 AND active = 1', $b);
    }

    public function test_where_not(): void
    {
        $b = $this->builder()->whereNot('status', 'banned');
        $this->assertSql('SELECT * FROM `users` WHERE `status` != ?', $b);
    }

    // -----------------------------------------------------------------------
    // ORDER / LIMIT / OFFSET
    // -----------------------------------------------------------------------

    public function test_order_by_asc(): void
    {
        $this->assertSql(
            'SELECT * FROM `users` ORDER BY `name` ASC',
            $this->builder()->orderBy('name'),
        );
    }

    public function test_order_by_desc(): void
    {
        $this->assertSql(
            'SELECT * FROM `users` ORDER BY `created_at` DESC',
            $this->builder()->orderBy('created_at', 'desc'),
        );
    }

    public function test_latest_oldest(): void
    {
        $this->assertSql(
            'SELECT * FROM `users` ORDER BY `created_at` DESC',
            $this->builder()->latest(),
        );
        $this->assertSql(
            'SELECT * FROM `users` ORDER BY `created_at` ASC',
            $this->builder()->oldest(),
        );
    }

    public function test_limit_offset(): void
    {
        $this->assertSql(
            'SELECT * FROM `users` LIMIT 10 OFFSET 20',
            $this->builder()->limit(10)->offset(20),
        );
    }

    // -----------------------------------------------------------------------
    // JOIN
    // -----------------------------------------------------------------------

    public function test_inner_join(): void
    {
        $b = $this->builder()->join('orders', 'orders.user_id', '=', 'users.id');
        $this->assertSql(
            'SELECT * FROM `users` INNER JOIN `orders` ON `orders`.`user_id` = `users`.`id`',
            $b,
        );
    }

    public function test_left_join(): void
    {
        $b = $this->builder()->leftJoin('profiles', 'profiles.user_id', '=', 'users.id');
        $this->assertSql(
            'SELECT * FROM `users` LEFT JOIN `profiles` ON `profiles`.`user_id` = `users`.`id`',
            $b,
        );
    }

    // -----------------------------------------------------------------------
    // GROUP BY / HAVING
    // -----------------------------------------------------------------------

    public function test_group_by(): void
    {
        $b = $this->builder()->groupBy('role');
        $this->assertSql('SELECT * FROM `users` GROUP BY `role`', $b);
    }

    public function test_having(): void
    {
        $b = $this->builder()->groupBy('role')->having('count', '>', 5);
        $this->assertSql('SELECT * FROM `users` GROUP BY `role` HAVING `count` > ?', $b);
    }

    // -----------------------------------------------------------------------
    // toSql + bindings consistency
    // -----------------------------------------------------------------------

    public function test_bindings_match_placeholders(): void
    {
        $b = $this->builder()
            ->where('active', 1)
            ->whereIn('role', ['admin', 'mod'])
            ->whereBetween('age', 18, 65);

        $placeholders = substr_count($b->toSql(), '?');
        $this->assertCount($placeholders, $b->getBindings());
    }

    // -----------------------------------------------------------------------
    // PostgreSQL identifiers
    // -----------------------------------------------------------------------

    public function test_postgres_uses_double_quotes(): void
    {
        $conn = $this->createMock(\Foxdb\Contracts\ConnectionInterface::class);
        $b    = (new Builder($conn, new PostgresGrammar()))->table('users')->select('id', 'name');
        $this->assertSql('SELECT "id", "name" FROM "users"', $b);
    }

    // -----------------------------------------------------------------------
    // Shorthands (v1 compatibility)
    // -----------------------------------------------------------------------

    public function test_is_shorthand(): void
    {
        $b = $this->builder()->is('active', 1);
        $this->assertSql('SELECT * FROM `users` WHERE `active` = ?', $b);
    }

    public function test_true_false_shorthands(): void
    {
        $this->assertSql(
            'SELECT * FROM `users` WHERE `active` = ?',
            $this->builder()->true('active'),
        );
        $this->assertSql(
            'SELECT * FROM `users` WHERE `active` = ?',
            $this->builder()->false('active'),
        );
    }
}