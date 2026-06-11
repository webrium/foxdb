<?php

declare(strict_types=1);

namespace Foxdb\Tests\Integration;

use Foxdb\DB;
use PHPUnit\Framework\TestCase;

/**
 * Base class for all integration tests.
 *
 * Reads DB_DRIVER (and related env vars) to configure the connection.
 * Each test class gets a fresh schema via setUpBeforeClass() and
 * tears it down in tearDownAfterClass().
 *
 * Run only SQLite (no server needed):
 *   vendor/bin/phpunit --testsuite=integration
 *
 * Run against MySQL:
 *   DB_DRIVER=mysql DB_PASSWORD=secret vendor/bin/phpunit --testsuite=integration
 *
 * Run against PostgreSQL:
 *   DB_DRIVER=pgsql DB_PORT=5432 vendor/bin/phpunit --testsuite=integration
 */
abstract class IntegrationTestCase extends TestCase
{
    // -----------------------------------------------------------------------
    // Connection bootstrap
    // -----------------------------------------------------------------------

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $driver = strtolower((string) (getenv('DB_DRIVER') ?: 'sqlite'));

        DB::reset();

        if ($driver === 'sqlite') {
            DB::addConnection([
                'driver'   => 'sqlite',
                'database' => ':memory:',
            ]);
        } else {
            DB::addConnection([
                'driver'           => $driver,
                'host'             => getenv('DB_HOST')     ?: '127.0.0.1',
                'port'             => getenv('DB_PORT')     ?: ($driver === 'pgsql' ? '5432' : '3306'),
                'database'         => getenv('DB_DATABASE') ?: 'foxdb_test',
                'username'         => getenv('DB_USERNAME') ?: 'root',
                'password'         => getenv('DB_PASSWORD') ?: '',
                'charset'          => 'utf8mb4',
                'throw_exceptions' => true,
            ]);
        }

        static::createSchema();
    }

    public static function tearDownAfterClass(): void
    {
        static::dropSchema();
        DB::reset();
        parent::tearDownAfterClass();
    }

    // -----------------------------------------------------------------------
    // Schema helpers — override in subclass
    // -----------------------------------------------------------------------

    protected static function createSchema(): void {}

    protected static function dropSchema(): void {}

    // -----------------------------------------------------------------------
    // Cross-driver DDL helpers
    // -----------------------------------------------------------------------

    /**
     * Returns the correct auto-increment primary key definition for the active driver.
     */
    protected static function autoIncrement(): string
    {
        return match (strtolower((string) (getenv('DB_DRIVER') ?: 'sqlite'))) {
            'pgsql'  => 'SERIAL PRIMARY KEY',
            'mysql'  => 'INT AUTO_INCREMENT PRIMARY KEY',
            default  => 'INTEGER PRIMARY KEY AUTOINCREMENT',   // sqlite
        };
    }

    /**
     * Returns a boolean column type appropriate for the active driver.
     */
    protected static function boolType(): string
    {
        return match (strtolower((string) (getenv('DB_DRIVER') ?: 'sqlite'))) {
            'mysql'  => 'TINYINT(1)',
            'pgsql'  => 'BOOLEAN',
            default  => 'INTEGER',   // sqlite stores as 0/1
        };
    }

    /**
     * Quote an identifier for the active driver.
     */
    protected static function q(string $identifier): string
    {
        return match (strtolower((string) (getenv('DB_DRIVER') ?: 'sqlite'))) {
            'pgsql'  => '"' . $identifier . '"',
            default  => '`' . $identifier . '`',
        };
    }

    /**
     * Truncate a table (cross-driver).
     */
    protected static function truncate(string $table): void
    {
        $driver = strtolower((string) (getenv('DB_DRIVER') ?: 'sqlite'));
        $q      = static::q($table);

        match ($driver) {
            'sqlite' => DB::statement("DELETE FROM {$q}"),
            default  => DB::statement("TRUNCATE TABLE {$q}"),
        };
    }
}
