<?php

declare(strict_types=1);

namespace Foxdb\Tests\Integration;

use Foxdb\DB;
use Foxdb\Schema;
use Foxdb\Schema\Blueprint;
use Foxdb\Exceptions\DatabaseException;

/**
 * Covers Connection::commit()/rollBack() against DDL statements that
 * implicitly commit the active transaction (CREATE TABLE, ALTER TABLE,
 * DROP TABLE, ...). This is MySQL-specific behavior: running this suite
 * with DB_DRIVER=mysql is what actually exercises the implicit-commit
 * path; on SQLite/PostgreSQL these statements stay fully transactional,
 * so the same tests simply confirm no regression in normal behavior.
 *
 * Each test uses its own uniquely-named table and drops it itself (in a
 * finally block), so tests stay independent of each other and of
 * execution order — important here since a DDL implicit commit means a
 * table created by one test cannot be cleaned up by wrapping it in a
 * transaction that later gets rolled back.
 *
 * Run against MySQL (recommended for this file):
 *   DB_DRIVER=mysql vendor/bin/phpunit --testsuite=integration
 *      --filter=TransactionDdlTest
 */
class TransactionDdlTest extends IntegrationTestCase
{
    public function testCreateTableInsideTransactionCommitsSuccessfully(): void
    {
        $table = 'transaction_ddl_test_create';
        $conn  = DB::connection();

        try {
            $result = $conn->transaction(function () use ($table) {
                Schema::create($table, function (Blueprint $t) {
                    $t->id();
                    $t->string('name')->nullable();
                });

                return 'done';
            });

            $this->assertSame('done', $result);
            $this->assertTrue(Schema::hasTable($table));
        } finally {
            Schema::dropIfExists($table);
        }
    }

    /**
     * inTransaction() must reflect the real driver state. After a DDL
     * statement implicitly commits (MySQL) it should report false even
     * though commit()/rollBack() have not been called yet.
     */
    public function testInTransactionReflectsRealDriverStateDuringDdl(): void
    {
        $table = 'transaction_ddl_test_state';
        $conn  = DB::connection();

        try {
            $conn->beginTransaction();

            $this->assertTrue($conn->inTransaction());

            Schema::create($table, function (Blueprint $t) {
                $t->id();
            });

            $driver = strtolower((string) (getenv('DB_DRIVER') ?: 'sqlite'));
            if ($driver === 'mysql') {
                // MySQL already auto-committed; nothing left to be "in".
                $this->assertFalse($conn->inTransaction());
            } else {
                // SQLite/PostgreSQL keep DDL transactional.
                $this->assertTrue($conn->inTransaction());
            }

            $conn->commit();
        } finally {
            Schema::dropIfExists($table);
        }
    }

    /**
     * A rollback attempted after an implicit commit can no longer undo
     * anything. Rather than letting PDO throw a generic "no active
     * transaction" error, Connection::rollBack() should raise a clear,
     * descriptive DatabaseException.
     */
    public function testRollbackAfterImplicitCommitThrowsDescriptiveException(): void
    {
        $driver = strtolower((string) (getenv('DB_DRIVER') ?: 'sqlite'));

        if ($driver !== 'mysql') {
            $this->markTestSkipped('Implicit commit on DDL is MySQL-specific behavior.');
        }

        $table = 'transaction_ddl_test_rollback';
        $conn  = DB::connection();

        try {
            $conn->beginTransaction();

            Schema::create($table, function (Blueprint $t) {
                $t->id();
            });

            $this->expectException(DatabaseException::class);
            $this->expectExceptionMessageMatches('/implicitly committed/');

            $conn->rollBack();
        } finally {
            Schema::dropIfExists($table);
        }
    }

    /**
     * Mixing a DML write with a later DDL statement inside one transaction:
     * the DML write becomes permanent the moment the DDL statement runs
     * (MySQL implicit commit), so a later rollBack() cannot erase it. This
     * documents the real, expected behavior rather than masking it.
     */
    public function testDmlBeforeDdlIsNotRolledBackOnMysql(): void
    {
        $driver = strtolower((string) (getenv('DB_DRIVER') ?: 'sqlite'));

        if ($driver !== 'mysql') {
            $this->markTestSkipped('Implicit commit on DDL is MySQL-specific behavior.');
        }

        $table = 'transaction_ddl_test_dml';

        try {
            Schema::create($table, function (Blueprint $t) {
                $t->id();
                $t->string('name')->nullable();
            });

            $conn = DB::connection();
            $conn->beginTransaction();

            DB::table($table)->insert(['name' => 'should-survive']);

            // This DDL statement implicitly commits everything above,
            // including the insert.
            Schema::table($table, function (Blueprint $t) {
                $t->string('extra')->nullable();
            });

            try {
                $conn->rollBack();
            } catch (DatabaseException $e) {
                // Expected: rollback can no longer be honored.
            }

            $this->assertSame(
                1,
                DB::table($table)->where('name', 'should-survive')->count()
            );
        } finally {
            Schema::dropIfExists($table);
        }
    }
}
