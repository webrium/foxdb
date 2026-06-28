<?php

declare(strict_types=1);

namespace Foxdb\Tests\Unit;

use Foxdb\Seeders\Seeder;
use Foxdb\Seeders\SeederResult;
use Foxdb\Seeders\SeederRunner;
use PHPUnit\Framework\TestCase;

/**
 * Anonymous-style seeder helpers can't be used because Seeder is abstract
 * and PHPUnit lives in a different namespace; named test fixtures keep
 * the class names predictable for assertions.
 */
class SeederTest_FakeSeeder extends Seeder
{
    public bool $ran = false;

    public function run(): void
    {
        $this->ran = true;
    }
}

class SeederTest_CallingSeeder extends Seeder
{
    public array $calls = [];

    public function run(): void
    {
        // not used; tests invoke ->call() directly
    }

    public function doCall(string|array $what): array
    {
        return $this->call($what);
    }
}

/**
 * SeederTest — verifies the abstract Seeder's $this->call() helper and
 * runner injection without touching a real database.
 */
class SeederTest extends TestCase
{
    public function test_setRunner_attaches_runner_used_by_call(): void
    {
        $seeder = new SeederTest_CallingSeeder();

        // Build a real SeederRunner pointing at an empty temp dir.
        // We mock by overriding runClass via a stub subclass:
        $runner = new class('/tmp') extends SeederRunner {
            public array $called = [];
            public function runClass(string $class): SeederResult
            {
                $this->called[] = $class;
                return SeederResult::ok($class, 0.0);
            }
        };

        $seeder->setRunner($runner);
        $results = $seeder->doCall('SomeOtherSeeder');

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]->success);
        $this->assertSame('SomeOtherSeeder', $results[0]->name);
        $this->assertSame(['SomeOtherSeeder'], $runner->called);
    }

    public function test_call_accepts_array_and_delegates_each(): void
    {
        $seeder = new SeederTest_CallingSeeder();

        $runner = new class('/tmp') extends SeederRunner {
            public array $called = [];
            public function runClass(string $class): SeederResult
            {
                $this->called[] = $class;
                return SeederResult::ok($class, 0.0);
            }
        };

        $seeder->setRunner($runner);
        $results = $seeder->doCall(['A', 'B', 'C']);

        $this->assertCount(3, $results);
        $this->assertSame(['A', 'B', 'C'], $runner->called);
        foreach ($results as $r) {
            $this->assertTrue($r->success);
        }
    }

    public function test_call_returns_failed_result_when_runner_missing(): void
    {
        $seeder = new SeederTest_CallingSeeder();
        // Intentionally do NOT setRunner.

        $results = $seeder->doCall('OrphanSeeder');

        $this->assertCount(1, $results);
        $this->assertFalse($results[0]->success);
        $this->assertSame('OrphanSeeder', $results[0]->name);
        $this->assertStringContainsString('No runner', $results[0]->error);
    }

    public function test_connection_defaults_to_null(): void
    {
        $seeder = new SeederTest_FakeSeeder();
        $this->assertNull($seeder->connection);
    }
}
