<?php

declare(strict_types=1);

namespace Foxdb\Tests\Integration;

use Foxdb\DB;

/**
 * QueryTest — tests Builder operations against a real DB (via DB::table()).
 */
class QueryTest extends IntegrationTestCase
{
    // -----------------------------------------------------------------------
    // Schema
    // -----------------------------------------------------------------------

    protected static function createSchema(): void
    {
        $ai   = static::autoIncrement();
        $bool = static::boolType();

        DB::statement("DROP TABLE IF EXISTS " . static::q('orders'));
        DB::statement("DROP TABLE IF EXISTS " . static::q('query_users'));

        DB::statement("
            CREATE TABLE " . static::q('query_users') . " (
                " . static::q('id')         . " {$ai},
                " . static::q('name')       . " VARCHAR(255),
                " . static::q('email')      . " VARCHAR(255),
                " . static::q('age')        . " INTEGER DEFAULT 0,
                " . static::q('active')     . " {$bool} DEFAULT 1,
                " . static::q('score')      . " DOUBLE PRECISION DEFAULT 0,
                " . static::q('role')       . " VARCHAR(50) DEFAULT 'user'
            )
        ");

        DB::statement("
            CREATE TABLE " . static::q('orders') . " (
                " . static::q('id')      . " {$ai},
                " . static::q('user_id') . " INTEGER,
                " . static::q('total')   . " DOUBLE PRECISION DEFAULT 0,
                " . static::q('status')  . " VARCHAR(50) DEFAULT 'pending'
            )
        ");
    }

    protected static function dropSchema(): void
    {
        DB::statement("DROP TABLE IF EXISTS " . static::q('orders'));
        DB::statement("DROP TABLE IF EXISTS " . static::q('query_users'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        static::truncate('query_users');
        static::truncate('orders');
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function table(): \Foxdb\Query\Builder
    {
        return DB::table('query_users');
    }

    private function seedUsers(): void
    {
        $this->table()->insert(['name' => 'Alice', 'email' => 'alice@t.com', 'age' => 30, 'active' => 1, 'score' => 90.0, 'role' => 'admin']);
        $this->table()->insert(['name' => 'Bob',   'email' => 'bob@t.com',   'age' => 25, 'active' => 1, 'score' => 50.0, 'role' => 'user']);
        $this->table()->insert(['name' => 'Carol', 'email' => 'carol@t.com', 'age' => 20, 'active' => 0, 'score' => 70.0, 'role' => 'user']);
        $this->table()->insert(['name' => 'Dave',  'email' => 'dave@t.com',  'age' => 40, 'active' => 1, 'score' => 60.0, 'role' => 'mod']);
    }

    // -----------------------------------------------------------------------
    // INSERT
    // -----------------------------------------------------------------------

    public function test_insert_single_row(): void
    {
        $result = $this->table()->insert(['name' => 'Alice', 'email' => 'a@t.com', 'age' => 25]);
        $this->assertTrue($result);
        $this->assertSame(1, $this->table()->count());
    }

    public function test_insert_get_id_returns_new_id(): void
    {
        $id = $this->table()->insertGetId(['name' => 'Alice', 'email' => 'a@t.com', 'age' => 25]);
        $this->assertGreaterThan(0, $id);
    }

    public function test_insert_batch(): void
    {
        $this->table()->insertBatch([
            ['name' => 'Alice', 'email' => 'a@t.com', 'age' => 25],
            ['name' => 'Bob',   'email' => 'b@t.com', 'age' => 30],
        ]);
        $this->assertSame(2, $this->table()->count());
    }

    // -----------------------------------------------------------------------
    // SELECT / WHERE
    // -----------------------------------------------------------------------

    public function test_get_returns_all_rows(): void
    {
        $this->seedUsers();
        $rows = $this->table()->get();
        $this->assertCount(4, $rows);
    }

    public function test_where_filters_rows(): void
    {
        $this->seedUsers();
        $rows = $this->table()->where('active', 1)->get();
        $this->assertCount(3, $rows);
    }

    public function test_where_with_operator(): void
    {
        $this->seedUsers();
        $rows = $this->table()->where('age', '>', 25)->get();
        $this->assertCount(2, $rows);
    }

    public function test_where_in(): void
    {
        $this->seedUsers();
        $rows = $this->table()->whereIn('role', ['admin', 'mod'])->get();
        $this->assertCount(2, $rows);
    }

    public function test_where_null(): void
    {
        $this->table()->insert(['name' => 'Alice', 'email' => null, 'age' => 25]);
        $rows = $this->table()->whereNull('email')->get();
        $this->assertCount(1, $rows);
    }

    public function test_or_where(): void
    {
        $this->seedUsers();
        $rows = $this->table()->where('role', 'admin')->orWhere('role', 'mod')->get();
        $this->assertCount(2, $rows);
    }

    public function test_where_between(): void
    {
        $this->seedUsers();
        $rows = $this->table()->whereBetween('age', 20, 25)->get();
        $this->assertCount(2, $rows);
    }

    public function test_first_returns_single_row(): void
    {
        $this->seedUsers();
        $row = $this->table()->orderBy('id')->first();
        $this->assertSame('Alice', $row->name);
    }

    public function test_first_returns_null_when_empty(): void
    {
        $this->assertFalse($this->table()->first());
    }

    public function test_value_returns_single_column(): void
    {
        $this->seedUsers();
        $name = $this->table()->orderBy('id')->value('name');
        $this->assertSame('Alice', $name);
    }

    public function test_pluck_returns_column_array(): void
    {
        $this->seedUsers();
        $names = $this->table()->orderBy('id')->pluck('name');
        $this->assertSame(['Alice', 'Bob', 'Carol', 'Dave'], $names);
    }

    // -----------------------------------------------------------------------
    // UPDATE
    // -----------------------------------------------------------------------

    public function test_update_rows(): void
    {
        $this->seedUsers();
        $affected = $this->table()->where('role', 'user')->update(['score' => 99.0]);

        $this->assertSame(2, $affected);
        $rows = $this->table()->where('score', 99.0)->get();
        $this->assertCount(2, $rows);
    }

    public function test_increment_decrement(): void
    {
        $this->table()->insert(['name' => 'Alice', 'email' => 'a@t.com', 'age' => 25, 'score' => 50.0]);
        $this->table()->increment('score', 10);

        $row = $this->table()->first();
        $this->assertSame(60.0, (float) $row->score);
    }

    public function test_update_or_insert(): void
    {
        $this->table()->updateOrInsert(['email' => 'a@t.com'], ['name' => 'Alice', 'age' => 25]);
        $this->assertSame(1, $this->table()->count());

        // Should update, not insert a second row
        $this->table()->updateOrInsert(['email' => 'a@t.com'], ['name' => 'Alice Updated', 'age' => 26]);
        $this->assertSame(1, $this->table()->count());

        $row = $this->table()->first();
        $this->assertSame('Alice Updated', $row->name);
    }

    // -----------------------------------------------------------------------
    // DELETE
    // -----------------------------------------------------------------------

    public function test_delete_rows(): void
    {
        $this->seedUsers();
        $affected = $this->table()->where('active', 0)->delete();

        $this->assertSame(1, $affected);
        $this->assertSame(3, $this->table()->count());
    }

    // -----------------------------------------------------------------------
    // Aggregates
    // -----------------------------------------------------------------------

    public function test_count(): void
    {
        $this->seedUsers();
        $this->assertSame(4, $this->table()->count());
    }

    public function test_count_with_where(): void
    {
        $this->seedUsers();
        $this->assertSame(3, $this->table()->where('active', 1)->count());
    }

    public function test_sum(): void
    {
        $this->seedUsers();
        $this->assertSame(270.0, (float) $this->table()->sum('score'));
    }

    public function test_avg(): void
    {
        $this->seedUsers();
        $this->assertSame(67.5, (float) $this->table()->avg('score'));
    }

    public function test_min_max(): void
    {
        $this->seedUsers();
        $this->assertSame(50.0, (float) $this->table()->min('score'));
        $this->assertSame(90.0, (float) $this->table()->max('score'));
    }

    public function test_exists_and_does_not_exist(): void
    {
        $this->assertFalse($this->table()->exists());
        $this->seedUsers();
        $this->assertTrue($this->table()->exists());
    }

    // -----------------------------------------------------------------------
    // ORDER / LIMIT / OFFSET
    // -----------------------------------------------------------------------

    public function test_order_by(): void
    {
        $this->seedUsers();
        $rows = $this->table()->orderBy('age', 'asc')->get();
        $this->assertSame('Carol', $rows->first()->name);
    }

    public function test_limit_offset(): void
    {
        $this->seedUsers();
        $rows = $this->table()->orderBy('id')->limit(2)->offset(1)->get();
        $this->assertCount(2, $rows);
        $this->assertSame('Bob', $rows->first()->name);
    }

    // -----------------------------------------------------------------------
    // JOIN
    // -----------------------------------------------------------------------

    public function test_inner_join(): void
    {
        $this->seedUsers();
        $aliceId = $this->table()->where('name', 'Alice')->value('id');
        DB::table('orders')->insert(['user_id' => $aliceId, 'total' => 100.0, 'status' => 'paid']);

        $rows = $this->table()
            ->select('query_users.name', 'orders.total')
            ->join('orders', 'orders.user_id', '=', 'query_users.id')
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame('Alice', $rows->first()->name);
    }

    public function test_left_join_includes_users_without_orders(): void
    {
        $this->seedUsers();

        $rows = $this->table()
            ->select('query_users.name', 'orders.total')
            ->leftJoin('orders', 'orders.user_id', '=', 'query_users.id')
            ->get();

        $this->assertCount(4, $rows);
    }

    // -----------------------------------------------------------------------
    // Paginate
    // -----------------------------------------------------------------------

    public function test_paginate_structure(): void
    {
        $this->seedUsers();
        $page = $this->table()->paginate(perPage: 2, page: 1);

        $this->assertSame(4, $page->total);
        $this->assertSame(2, $page->per_page);
        $this->assertSame(2, $page->last_page);
        $this->assertCount(2, $page->data);
    }

    public function test_paginate_empty_table(): void
    {
        $page = $this->table()->paginate(perPage: 10, page: 1);

        $this->assertSame(0, $page->total);
        $this->assertSame(1, $page->last_page);
        $this->assertSame(0, $page->from);
        $this->assertCount(0, $page->data);
    }
}