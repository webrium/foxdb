<?php

declare(strict_types=1);

namespace Foxdb;

use Foxdb\Connection\Connection;
use Foxdb\Schema\Blueprint;
use Foxdb\Schema\Grammars\MySqlSchemaGrammar;
use Foxdb\Schema\Grammars\PostgresSchemaGrammar;
use Foxdb\Schema\Grammars\SchemaGrammar;
use Foxdb\Schema\Grammars\SqliteSchemaGrammar;

/**
 * Schema — static facade for DDL (Data Definition Language) operations.
 *
 * Uses the active DB connection to determine the correct grammar,
 * then compiles and executes DDL statements.
 *
 * Quick start:
 *
 *   Schema::create('users', function (Blueprint $table) {
 *       $table->id();
 *       $table->string('name');
 *       $table->string('email')->unique();
 *       $table->timestamps();
 *   });
 *
 *   Schema::table('users', function (Blueprint $table) {
 *       $table->integer('age')->after('name')->nullable()->change();
 *       $table->dropColumn('old_field');
 *   });
 *
 *   Schema::drop('users');
 *   Schema::hasTable('users');   // bool
 */
final class Schema
{
    /**
     * Grammar instances cached per driver name.
     *
     * @var array<string, SchemaGrammar>
     */
    private static array $grammars = [];

    // Prevent instantiation.
    private function __construct() {}

    // -----------------------------------------------------------------------
    // Table operations
    // -----------------------------------------------------------------------

    /**
     * Create a new table using the given Blueprint callback.
     *
     * @param  string   $table
     * @param  callable(Blueprint): void $callback
     * @param  string|null $connection  Named connection (null = default)
     * @return void
     */
    public static function create(string $table, callable $callback, ?string $connection = null): void
    {
        $conn     = DB::connection($connection);
        $grammar  = static::grammarFor($conn);
        $blueprint = new Blueprint($table);

        $callback($blueprint);

        $sql = $grammar->compileCreate($blueprint);
        $conn->statement($sql);

        // Standalone indexes (CREATE INDEX …)
        foreach ($grammar->compileIndexes($blueprint) as $idx) {
            $conn->statement($idx);
        }

        // Foreign keys already inlined in CREATE TABLE for MySQL.
        // For PostgreSQL emit separately if any.
        foreach ($grammar->compileForeignKeys($blueprint) as $fk) {
            $conn->statement($fk);
        }

        // PostgreSQL column comments (separate COMMENT ON COLUMN statements)
        if ($grammar instanceof PostgresSchemaGrammar) {
            foreach ($grammar->compileColumnComments($blueprint) as $cmt) {
                $conn->statement($cmt);
            }
        }
    }

    /**
     * Modify an existing table using the given Blueprint callback.
     *
     * Supports: add columns, change columns, drop columns,
     *           rename columns, add/drop indexes, add/drop foreign keys.
     *
     * @param  string   $table
     * @param  callable(Blueprint): void $callback
     * @param  string|null $connection
     * @return void
     */
    public static function table(string $table, callable $callback, ?string $connection = null): void
    {
        $conn      = DB::connection($connection);
        $grammar   = static::grammarFor($conn);
        $blueprint = new Blueprint($table);

        $callback($blueprint);

        // ADD COLUMN
        foreach ($grammar->compileAdd($blueprint) as $sql) {
            $conn->statement($sql);
        }

        // MODIFY / ALTER COLUMN
        foreach ($grammar->compileChange($blueprint) as $sql) {
            $conn->statement($sql);
        }

        // DROP COLUMN
        foreach ($grammar->compileDrop($blueprint) as $sql) {
            $conn->statement($sql);
        }

        // RENAME COLUMN
        foreach ($grammar->compileRenameColumn($blueprint) as $sql) {
            $conn->statement($sql);
        }

        // DROP INDEX
        foreach ($grammar->compileDropIndexes($blueprint) as $sql) {
            $conn->statement($sql);
        }

        // ADD INDEX (standalone)
        foreach ($grammar->compileIndexes($blueprint) as $sql) {
            $conn->statement($sql);
        }

        // DROP FOREIGN KEY
        foreach ($grammar->compileDropForeignKeys($blueprint) as $sql) {
            $conn->statement($sql);
        }

        // ADD FOREIGN KEY
        foreach ($grammar->compileForeignKeys($blueprint) as $sql) {
            $conn->statement($sql);
        }
    }

    /**
     * Drop a table.
     *
     * @param  string      $table
     * @param  string|null $connection
     * @return void
     */
    public static function drop(string $table, ?string $connection = null): void
    {
        $conn    = DB::connection($connection);
        $grammar = static::grammarFor($conn);

        $conn->statement($grammar->compileDropTable($table));
    }

    /**
     * Drop a table if it exists.
     *
     * @param  string      $table
     * @param  string|null $connection
     * @return void
     */
    public static function dropIfExists(string $table, ?string $connection = null): void
    {
        $conn    = DB::connection($connection);
        $grammar = static::grammarFor($conn);

        $conn->statement($grammar->compileDropTableIfExists($table));
    }

    /**
     * Rename a table.
     *
     * @param  string      $from
     * @param  string      $to
     * @param  string|null $connection
     * @return void
     */
    public static function rename(string $from, string $to, ?string $connection = null): void
    {
        $conn    = DB::connection($connection);
        $grammar = static::grammarFor($conn);

        $conn->statement($grammar->compileRenameTable($from, $to));
    }

    // -----------------------------------------------------------------------
    // Introspection
    // -----------------------------------------------------------------------

    /**
     * Determine whether a table exists.
     *
     * @param  string      $table
     * @param  string|null $connection
     * @return bool
     */
    public static function hasTable(string $table, ?string $connection = null): bool
    {
        $conn     = DB::connection($connection);
        $grammar  = static::grammarFor($conn);
        $database = $conn->getDatabaseName();

        $sql = $grammar->compileTableExists($table, $database);
        $row = $conn->selectOne($sql);

        if ($row === false) {
            return false;
        }

        // SQLite PRAGMA returns rows for existing table; others return count
        $row = (array) $row;
        $val = array_values($row)[0] ?? 0;

        return (int) $val > 0;
    }

    /**
     * Determine whether a column exists on a table.
     *
     * @param  string      $table
     * @param  string      $column
     * @param  string|null $connection
     * @return bool
     */
    public static function hasColumn(string $table, string $column, ?string $connection = null): bool
    {
        $columns = static::getColumnNames($table, $connection);

        return in_array(strtolower($column), array_map('strtolower', $columns), strict: true);
    }

    /**
     * Get all column names for a table.
     *
     * @param  string      $table
     * @param  string|null $connection
     * @return array<int, string>
     */
    public static function getColumnNames(string $table, ?string $connection = null): array
    {
        $rows = static::getColumns($table, $connection);

        return array_column(
            array_map(fn(object $r) => (array) $r, $rows),
            'name',
        );
    }

    /**
     * Get full column information for a table.
     * Returns an array of objects with at minimum a 'name' property.
     *
     * @param  string      $table
     * @param  string|null $connection
     * @return array<int, object>
     */
    public static function getColumns(string $table, ?string $connection = null): array
    {
        $conn     = DB::connection($connection);
        $grammar  = static::grammarFor($conn);
        $database = $conn->getDatabaseName();

        $sql = $grammar->compileColumnListing($table, $database);

        return $conn->select($sql);
    }

    // -----------------------------------------------------------------------
    // Grammar resolution
    // -----------------------------------------------------------------------

    /**
     * Resolve (and cache) the correct SchemaGrammar for the given connection.
     *
     * @param  Connection $connection
     * @return SchemaGrammar
     */
    private static function grammarFor(Connection $connection): SchemaGrammar
    {
        $driver = $connection->getDriverName();

        if (! isset(static::$grammars[$driver])) {
            static::$grammars[$driver] = match ($driver) {
                Config::PGSQL   => new PostgresSchemaGrammar(),
                Config::SQLITE  => new SqliteSchemaGrammar(),
                default         => new MySqlSchemaGrammar(),
            };
        }

        return static::$grammars[$driver];
    }

    /**
     * Reset the cached grammar instances (useful for testing).
     *
     * @return void
     */
    public static function reset(): void
    {
        static::$grammars = [];
    }
}
