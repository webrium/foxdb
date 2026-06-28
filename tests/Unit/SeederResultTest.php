<?php

declare(strict_types=1);

namespace Foxdb\Tests\Unit;

use Foxdb\Seeders\SeederResult;
use PHPUnit\Framework\TestCase;

/**
 * SeederResultTest — verifies the immutable result value object.
 */
class SeederResultTest extends TestCase
{
    public function test_constructor_stores_all_fields(): void
    {
        $r = new SeederResult('UsersSeeder', true, 12.34, '');

        $this->assertSame('UsersSeeder', $r->name);
        $this->assertTrue($r->success);
        $this->assertSame(12.34, $r->timeMs);
        $this->assertSame('', $r->error);
    }

    public function test_ok_helper_creates_successful_result(): void
    {
        $r = SeederResult::ok('UsersSeeder', 5.0);

        $this->assertTrue($r->success);
        $this->assertSame('UsersSeeder', $r->name);
        $this->assertSame(5.0, $r->timeMs);
        $this->assertSame('', $r->error);
    }

    public function test_fail_helper_creates_failed_result(): void
    {
        $r = SeederResult::fail('UsersSeeder', 3.5, 'boom');

        $this->assertFalse($r->success);
        $this->assertSame('UsersSeeder', $r->name);
        $this->assertSame(3.5, $r->timeMs);
        $this->assertSame('boom', $r->error);
    }

    public function test_toString_shows_ok_for_success(): void
    {
        $r = SeederResult::ok('UsersSeeder', 1.234);
        $s = $r->toString();

        $this->assertStringContainsString('[OK]', $s);
        $this->assertStringContainsString('seed UsersSeeder', $s);
        $this->assertStringContainsString('1.23 ms', $s);
        $this->assertStringNotContainsString('—', $s);
    }

    public function test_toString_shows_failed_with_error(): void
    {
        $r = SeederResult::fail('UsersSeeder', 2.0, 'connection lost');
        $s = $r->toString();

        $this->assertStringContainsString('[FAILED]', $s);
        $this->assertStringContainsString('seed UsersSeeder', $s);
        $this->assertStringContainsString('2.00 ms', $s);
        $this->assertStringContainsString('connection lost', $s);
    }

    public function test_toString_omits_dash_when_error_is_empty(): void
    {
        // A failed result with no error message should not show a trailing " — ".
        $r = new SeederResult('UsersSeeder', false, 1.0, '');
        $this->assertStringNotContainsString('—', $r->toString());
    }

    public function test_properties_are_readonly(): void
    {
        $r = SeederResult::ok('UsersSeeder', 1.0);

        $this->expectException(\Error::class);
        // @phpstan-ignore-next-line — intentional violation for the test
        $r->name = 'Other';
    }
}
