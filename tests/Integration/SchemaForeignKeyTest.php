<?php

declare(strict_types=1);

namespace Foxdb\Tests\Integration;

use Foxdb\Schema;
use Foxdb\Schema\Blueprint;

/**
 * Regression coverage for Schema::create() double-emitting foreign key
 * constraints (once inlined in CREATE TABLE, once more via a redundant
 * ALTER TABLE ADD CONSTRAINT).
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

    public function testCreateWithForeignKeyActuallyEnforcesTheConstraint(): void
    {
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
