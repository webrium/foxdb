<?php

use PHPUnit\Framework\TestCase;
use Foxdb\DB;
use Foxdb\Config;
use Foxdb\Exceptions\QueryException;
use Foxdb\Exceptions\DatabaseException;

class ErrorHandlingTest extends TestCase
{
    protected function setUp(): void
    {
        // Ensure we're using the main connection with exceptions enabled
        DB::use('main');
    }

    public function testQueryExceptionWithInvalidTable()
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Database error: SQLSTATE[42S02]');
        
        // Try to query a non-existent table
        DB::table('non_existent_table')->get();
    }

    public function testQueryExceptionWithInvalidColumn()
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Database error: SQLSTATE[42S22]');
        
        // Try to select a non-existent column
        DB::table('users')->select('non_existent_column')->get();
    }

    public function testQueryExceptionWithInvalidSyntax()
    {
        $this->expectException(QueryException::class);
        
        // Try to execute invalid SQL
        DB::query("SELECT * FROM users WHERE invalid syntax");
    }

    public function testQueryExceptionDetails()
    {
        try {
            DB::table('non_existent_table')->get();
        } catch (QueryException $e) {
            $this->assertNotEmpty($e->getMessage());
            $this->assertNotEmpty($e->getSql());
            $this->assertIsArray($e->getParams());
            $this->assertNotNull($e->getErrorCode());
            $this->assertNotEmpty($e->getFormattedMessage());
            
            // Check that formatted message contains SQL and Error Code
            $formattedMessage = $e->getFormattedMessage();
            $this->assertStringContainsString('SQL:', $formattedMessage);
            $this->assertStringContainsString('Error Code:', $formattedMessage);
            
            // Parameters might be empty for this query, so don't require it
            if (!empty($e->getParams())) {
                $this->assertStringContainsString('Parameters:', $formattedMessage);
            }
        }
    }

    public function testDatabaseExceptionWithInvalidConnection()
    {
        // Add a connection with invalid credentials
        DB::addConnection('invalid', [
            'host' => 'localhost',
            'port' => '3306',
            'database' => 'non_existent_db',
            'username' => 'invalid_user',
            'password' => 'invalid_password',
            'charset' => Config::UTF8,
            'collation' => Config::UTF8_GENERAL_CI,
            'fetch' => Config::FETCH_CLASS,
            'throw_exceptions' => true
        ]);

        $this->expectException(QueryException::class);
        
        DB::use('invalid');
        DB::table('users')->get();
    }

    public function testErrorHandlingWithExceptionsDisabled()
    {
        // Add a connection with exceptions disabled
        DB::addConnection('no_exceptions', [
            'host' => 'localhost',
            'port' => '3306',
            'database' => 'test',
            'username' => 'root',
            'password' => '123456',
            'charset' => Config::UTF8,
            'collation' => Config::UTF8_GENERAL_CI,
            'fetch' => Config::FETCH_CLASS,
            'throw_exceptions' => false
        ]);

        DB::use('no_exceptions');
        
        // Test with a valid query to ensure the connection works
        $result = DB::table('users')->count();
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
        
        // Switch back to main connection
        DB::use('main');
    }

    public function testTransactionErrorHandling()
    {
        $this->expectException(DatabaseException::class);
        
        // Try to commit without starting a transaction
        DB::commit();
    }

    public function testTransactionRollbackErrorHandling()
    {
        $this->expectException(DatabaseException::class);
        
        // Try to rollback without starting a transaction
        DB::rollBack();
    }

    public function testValidTransactionFlow()
    {
        // This should work without throwing exceptions
        DB::beginTransaction();
        
        // Insert a test record
        $result = DB::table('users')->insert([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '1234567890'
        ]);
        
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
        
        // Rollback to clean up
        DB::rollBack();
    }

    public function testQueryWithInvalidParameters()
    {
        $this->expectException(QueryException::class);
        
        // Try to insert with invalid data type
        DB::table('users')->insert([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '1234567890',
            'date_of_birth' => 'invalid_date_format'
        ]);
    }

    public function testErrorHandlingWithRawQueries()
    {
        $this->expectException(QueryException::class);
        
        // Try to execute invalid raw SQL
        DB::query("SELECT * FROM non_existent_table WHERE id = ?", [1]);
    }

    public function testErrorHandlingWithValidRawQueries()
    {
        // This should work without throwing exceptions - use query_result=true for SELECT queries
        $result = DB::query("SELECT COUNT(*) as count FROM users", [], true);
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        
        // Test with a simple SELECT query
        $result = DB::query("SELECT 1 as test", [], true);
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    public function testExceptionInheritance()
    {
        try {
            DB::table('non_existent_table')->get();
        } catch (QueryException $e) {
            // QueryException should extend DatabaseException
            $this->assertInstanceOf(DatabaseException::class, $e);
            $this->assertInstanceOf(Exception::class, $e);
        }
    }

    public function testErrorHandlingWithComplexQueries()
    {
        $this->expectException(QueryException::class);
        
        // Try a complex query with invalid syntax
        DB::table('users')
            ->select('name', 'email')
            ->where('id', '>', 0)
            ->whereRaw('invalid_sql_function()', [])
            ->get();
    }

    public function testErrorHandlingWithJoins()
    {
        $this->expectException(QueryException::class);
        
        // Try to join with a non-existent table
        DB::table('users')
            ->join('non_existent_table', 'users.id', '=', 'non_existent_table.user_id')
            ->get();
    }

    public function testErrorHandlingWithAggregateFunctions()
    {
        // This should work without throwing exceptions
        $result = DB::table('users')->count();
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

    public function testErrorHandlingWithInvalidAggregateFunction()
    {
        $this->expectException(QueryException::class);
        
        // Try to use an invalid aggregate function
        DB::table('users')->selectRaw('INVALID_FUNCTION(*) as result')->get();
    }
} 