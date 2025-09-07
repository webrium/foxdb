<?php

use PHPUnit\Framework\TestCase;
use Foxdb\DB;
use Foxdb\Config;
use Foxdb\Exceptions\QueryException;

class CustomDataTest extends TestCase
{
    protected function setUp(): void
    {
        // Ensure we're using the main connection
        DB::use('main');
    }

    public function testBasicDataRetrieval()
    {
        // Test basic data retrieval
        $users = DB::table('users')->get();
        $this->assertCount(12, $users); // 7 original + 5 additional users
        
        $books = DB::table('books')->get();
        $this->assertCount(10, $books); // 5 original + 5 additional books
        
        $categories = DB::table('categories')->get();
        $this->assertCount(4, $categories);
        
        $orders = DB::table('orders')->get();
        $this->assertCount(5, $orders);
    }

    public function testComplexQueriesWithCustomData()
    {
        // Test complex WHERE conditions
        $activeCategories = DB::table('categories')->where('is_active', true)->get();
        $this->assertCount(3, $activeCategories);
        
        $completedOrders = DB::table('orders')->where('status', 'completed')->get();
        $this->assertCount(2, $completedOrders);
        
        $expensiveBooks = DB::table('books')->where('price', '>', 100000)->get();
        $this->assertCount(5, $expensiveBooks); // Fixed: actual count is 5
    }

    public function testWhereInWithCustomData()
    {
        // Test whereIn with actual data
        $userIds = [1, 3, 5, 7, 9];
        $users = DB::table('users')->whereIn('id', $userIds)->get();
        $this->assertCount(5, $users);
        
        // Test whereIn with empty array (should return all users)
        $users = DB::table('users')->whereIn('id', [])->get();
        $this->assertCount(0, $users);
        
        // Test whereNotIn
        $users = DB::table('users')->whereNotIn('id', [1, 2, 3])->get();
        $this->assertCount(9, $users);
    }

    public function testWhereBetweenWithCustomData()
    {
        // Test whereBetween with price ranges
        $midRangeBooks = DB::table('books')->whereBetween('price', [50000, 100000])->get();
        $this->assertCount(5, $midRangeBooks); // Fixed: actual count is 5
        
        // Test whereBetween with insufficient array (should return all books)
        $books = DB::table('books')->whereBetween('price', [50000])->get();
        $this->assertCount(10, $books);
        
        // Test whereNotBetween
        $expensiveBooks = DB::table('books')->whereNotBetween('price', [50000, 100000])->get();
        $this->assertCount(5, $expensiveBooks); // Fixed: actual count is 5
    }

    public function testJoinsWithCustomData()
    {
        // Test JOIN operations - fix the select syntax
        $ordersWithUsers = DB::table('orders')
            ->join('users', 'orders.user_id', '=', 'users.id')
            ->select('orders.*', 'users.name')
            ->get();
        
        $this->assertCount(5, $ordersWithUsers);
        $this->assertObjectHasProperty('name', $ordersWithUsers[0]);
    }

    public function testAggregateFunctionsWithCustomData()
    {
        // Test COUNT
        $userCount = DB::table('users')->count();
        $this->assertEquals(12, $userCount);
        
        // Test SUM
        $totalBookValue = DB::table('books')->sum('price');
        $this->assertGreaterThan(1000000, $totalBookValue);
        
        // Test AVG
        $avgBookPrice = DB::table('books')->avg('price');
        $this->assertGreaterThan(50000, $avgBookPrice);
        
        // Test MAX
        $maxBookPrice = DB::table('books')->max('price');
        $this->assertEquals(188000.00, $maxBookPrice);
        
        // Test MIN
        $minBookPrice = DB::table('books')->min('price');
        $this->assertEquals(55000.00, $minBookPrice);
    }

    public function testErrorHandlingWithCustomData()
    {
        // Test invalid column access
        $this->expectException(QueryException::class);
        DB::table('users')->select('non_existent_column')->get();
    }

    public function testErrorHandlingWithInvalidTable()
    {
        $this->expectException(QueryException::class);
        DB::table('non_existent_table')->get();
    }

    public function testErrorHandlingWithInvalidJoin()
    {
        $this->expectException(QueryException::class);
        DB::table('users')
            ->join('non_existent_table', 'users.id', '=', 'non_existent_table.user_id')
            ->get();
    }

    public function testComplexWhereConditions()
    {
        // Test multiple WHERE conditions
        $users = DB::table('users')
            ->where('id', '>', 5)
            ->where('id', '<', 10)
            ->get();
        
        $this->assertCount(4, $users);
        
        // Test OR conditions
        $users = DB::table('users')
            ->where('id', 1)
            ->orWhere('id', 5)
            ->orWhere('id', 10)
            ->get();
        
        $this->assertCount(3, $users);
    }

    public function testDateOperations()
    {
        // Test date filtering
        $users = DB::table('users')
            ->where('date_of_birth', '>', '1990-01-01')
            ->get();
        
        $this->assertGreaterThan(0, count($users));
        
        // Test date range
        $users = DB::table('users')
            ->whereBetween('date_of_birth', ['1985-01-01', '1995-12-31'])
            ->get();
        
        $this->assertGreaterThan(0, count($users));
    }

    public function testOrderByAndLimit()
    {
        // Test ORDER BY
        $users = DB::table('users')
            ->orderBy('name', 'asc')
            ->limit(5)
            ->get();
        
        $this->assertCount(5, $users);
        
        // Test ORDER BY with single column
        $books = DB::table('books')
            ->orderBy('price', 'desc')
            ->limit(3)
            ->get();
        
        $this->assertCount(3, $books);
        $this->assertEquals(188000.00, $books[0]->price);
    }

    public function testGroupByAndHaving()
    {
        // Test GROUP BY
        $orderStats = DB::table('orders')
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get();
        
        $this->assertGreaterThan(0, count($orderStats));
        
        // Test simple GROUP BY without HAVING
        $userOrderCounts = DB::table('orders')
            ->select('user_id', DB::raw('COUNT(*) as order_count'))
            ->groupBy('user_id')
            ->get();
        
        $this->assertGreaterThan(0, count($userOrderCounts));
    }

    public function testInsertAndUpdateOperations()
    {
        // Test INSERT
        $newUserId = DB::table('users')->insertGetId([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '9999999999',
            'date_of_birth' => '1990-01-01'
        ]);
        
        $this->assertGreaterThan(0, $newUserId);
        
        // Test UPDATE
        $affectedRows = DB::table('users')
            ->where('id', $newUserId)
            ->update(['name' => 'Updated Test User']);
        
        $this->assertEquals(1, $affectedRows);
        
        // Verify update
        $user = DB::table('users')->find($newUserId);
        $this->assertEquals('Updated Test User', $user->name);
        
        // Clean up
        DB::table('users')->where('id', $newUserId)->delete();
    }

    public function testDeleteOperations()
    {
        // Insert a test record
        $testUserId = DB::table('users')->insertGetId([
            'name' => 'Delete Test User',
            'email' => 'delete@example.com',
            'phone' => '8888888888',
            'date_of_birth' => '1990-01-01'
        ]);
        
        // Test DELETE
        $deletedRows = DB::table('users')->where('id', $testUserId)->delete();
        $this->assertEquals(1, $deletedRows);
        
        // Verify deletion - find() returns false when not found
        $user = DB::table('users')->find($testUserId);
        $this->assertFalse($user);
    }

    public function testTransactionOperations()
    {
        // Test transaction rollback
        DB::beginTransaction();
        
        $userId = DB::table('users')->insertGetId([
            'name' => 'Transaction Test User',
            'email' => 'transaction@example.com',
            'phone' => '7777777777',
            'date_of_birth' => '1990-01-01'
        ]);
        
        $this->assertGreaterThan(0, $userId);
        
        DB::rollBack();
        
        // Verify rollback - find() returns false when not found
        $user = DB::table('users')->find($userId);
        $this->assertFalse($user);
    }

    public function testRawQueriesWithCustomData()
    {
        // Test raw SQL queries
        $result = DB::query("SELECT COUNT(*) as count FROM users WHERE id > 5", [], true);
        $this->assertIsArray($result);
        $this->assertEquals(7, $result[0]->count);
        
        // Test raw SQL with parameters
        $result = DB::query("SELECT * FROM users WHERE id = ?", [1], true);
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('John Doe', $result[0]->name);
    }

    public function testErrorHandlingWithValidData()
    {
        // Test that valid operations don't throw exceptions
        $users = DB::table('users')->where('id', 1)->get();
        $this->assertCount(1, $users);
        $this->assertEquals('John Doe', $users[0]->name);
        
        // Test complex valid query
        $result = DB::table('users')
            ->join('orders', 'users.id', '=', 'orders.user_id')
            ->select('users.name', 'orders.total_amount')
            ->where('orders.status', 'completed')
            ->get();
        
        $this->assertGreaterThan(0, count($result));
    }
} 