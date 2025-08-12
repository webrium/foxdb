<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../tests/config.php';

use Foxdb\DB;
use Foxdb\Exceptions\QueryException;
use Foxdb\Exceptions\DatabaseException;

echo "=== Foxdb Error Handling Example ===\n\n";

// Example 1: Default behavior (exceptions enabled)
echo "1. Testing with exceptions enabled (default):\n";
try {
    // This will throw an exception
    $result = DB::query("SELECT * FROM non_existent_table WHERE id = ?", [123]);
} catch (QueryException $e) {
    echo "✓ Caught QueryException: " . $e->getMessage() . "\n";
    echo "  SQL: " . $e->getSql() . "\n";
    echo "  Parameters: " . json_encode($e->getParams()) . "\n";
    echo "  Error Code: " . $e->getErrorCode() . "\n";
    echo "  Formatted Message:\n" . $e->getFormattedMessage() . "\n\n";
}

// Example 2: Disable exceptions for a specific connection
echo "2. Testing with exceptions disabled:\n";
DB::addConnection('no_exceptions', [
    'host' => 'db',
    'port' => '3306',
    'database' => 'test',
    'username' => 'root',
    'password' => '123456',
    'charset' => \Foxdb\Config::UTF8,
    'collation' => \Foxdb\Config::UTF8_GENERAL_CI,
    'fetch' => \Foxdb\Config::FETCH_CLASS,
    'throw_exceptions' => false
]);

DB::use('no_exceptions');
$result = DB::query("SELECT * FROM non_existent_table WHERE id = ?", [456]);
if ($result === false) {
    echo "✓ Query failed but no exception thrown (result: false)\n";
    echo "  Check error_log for details\n\n";
}

// Example 3: Switch back to main connection and test transaction errors
echo "3. Testing transaction error handling:\n";
DB::use('main');
try {
    DB::beginTransaction();
    
    // This will cause an error
    DB::query("INSERT INTO non_existent_table (name) VALUES (?)", ['Test']);
    
    DB::commit();
} catch (QueryException $e) {
    echo "✓ Caught QueryException in transaction: " . $e->getMessage() . "\n";
    DB::rollBack();
    echo "  Transaction rolled back\n\n";
}

// Example 4: Test with valid SQL but invalid data
echo "4. Testing with valid SQL but invalid data:\n";
try {
    // Assuming 'users' table exists but 'invalid_column' doesn't
    $result = DB::table('users')->where('invalid_column', 'test')->get();
} catch (QueryException $e) {
    echo "✓ Caught QueryException for invalid column: " . $e->getMessage() . "\n";
    echo "  SQL: " . $e->getSql() . "\n\n";
}

echo "=== Error Handling Test Complete ===\n"; 