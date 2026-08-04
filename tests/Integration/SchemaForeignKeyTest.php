<?php

declare(strict_types=1);

namespace Foxdb\Tests\Integration;

use Foxdb\Schema;
use Foxdb\Schema\Blueprint;

/**
 * Regression coverage for Schema::create() double-emitting foreign key
 * constraints.
 *
 * Schema::create() compiles the CREATE TABLE statement (which already
 * inlines every `$table->foreign()` as a column-level CONSTRAINT, see
 * SchemaGrammar::compileCreate()) and then, unconditionally, ALSO runs
 * SchemaGrammar::compileForeignKeys() — which re-emits the very same
 * constraints as separate `ALTER TABLE ... ADD CONSTRAINT` statements.
 * No grammar overrides compileForeignKeys() to suppress this for the
 * "already inlined at CREATE TABLE time" case, so every driver executes
 * a duplicate constraint statement:
 *
 *  - MySQL:  rejects the duplicate constraint name (errno 121,
 *            "Duplicate key on write or update").
 *  - SQLite: has no `ALTER TABLE ... ADD CONSTRAINT` syntax at all, so
 *            the statement is a hard syntax error.
 *
 * A table defined with `$table->foreign()` therefore cannot be created
 * via Schema::create() on any driver.
 */
class SchemaForeignKeyTest extends IntegrationTestCase
{
    public function testCreateWithForeignKeyDoesNotThrow(): void
    {
        $parent = 'sfk_test_parents';
        $child  = 'sfk_test_children';

        try {
            Schema::create($parent, function (Blueprint $t) {
                $t->id();
                $t->string('name')->nullable();
            });

            Schema::create($child, function (Blueprint $t) use ($parent) {
                $t->id();
                $t->bigInteger('parent_id')->unsigned();
                $t->foreign('parent_id')->references('id')->on($parent)->cascadeOnDelete();
            });

            $this->assertTrue(Schema::hasTable($child));
        } finally {
            Schema::dropIfExists($child);
            Schema::dropIfExists($parent);
        }
    }

    /**
     * SQLite never enforces foreign keys unless the connection issues
     * `PRAGMA foreign_keys = ON` — this library does not do so, which is a
     * separate, pre-existing gap unrelated to the double-emission bug this
     * file targets. Skipped here rather than silently asserting something
     * false for that driver.
     */
    public function testCreateWithForeignKeyActuallyEnforcesTheConstraint(): void
    {
        if (strtolower((string) (getenv('DB_DRIVER') ?: 'sqlite')) === 'sqlite') {
            $this->markTestSkipped('SQLite FK enforcement requires PRAGMA foreign_keys=ON, which this library does not set (separate gap).');
        }

        $parent = 'sfk_test_parents2';
        $child  = 'sfk_test_children2';

        try {
            Schema::create($parent, function (Blueprint $t) {
                $t->id();
            });

            Schema::create($child, function (Blueprint $t) use ($parent) {
                $t->id();
                $t->bigInteger('parent_id')->unsigned();
                $t->foreign('parent_id')->references('id')->on($parent);
            });

            // A reference to a non-existent parent row must be rejected —
            // proof the constraint is really enforced by the DB, not just
            // that CREATE TABLE happened to succeed some other way.
            $threw = false;
            try {
                \Foxdb\DB::table($child)->insert(['parent_id' => 999999]);
            } catch (\Throwable $e) {
                $threw = true;
            }
            $this->assertTrue($threw, 'Expected the foreign key constraint to reject an orphan reference.');
        } finally {
            Schema::dropIfExists($child);
            Schema::dropIfExists($parent);
        }
    }

    public function testCreateWithMultipleForeignKeysOnSameTableDoesNotThrow(): void
    {
        $a = 'sfk_test_a';
        $b = 'sfk_test_b';
        $c = 'sfk_test_c';

        try {
            Schema::create($a, function (Blueprint $t) {
                $t->id();
            });
            Schema::create($b, function (Blueprint $t) {
                $t->id();
            });

            Schema::create($c, function (Blueprint $t) use ($a, $b) {
                $t->id();
                $t->bigInteger('a_id')->unsigned();
                $t->bigInteger('b_id')->unsigned();
                $t->foreign('a_id')->references('id')->on($a);
                $t->foreign('b_id')->references('id')->on($b);
            });

            $this->assertTrue(Schema::hasTable($c));
        } finally {
            Schema::dropIfExists($c);
            Schema::dropIfExists($b);
            Schema::dropIfExists($a);
        }
    }
}
