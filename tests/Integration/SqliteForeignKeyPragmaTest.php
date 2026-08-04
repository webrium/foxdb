<?php

declare(strict_types=1);

namespace Foxdb\Tests\Integration;

use Foxdb\DB;

/**
 * SQLite does not enforce FOREIGN KEY constraints unless a connection
 * issues `PRAGMA foreign_keys = ON`. Connection::createPdo() must set
 * this for every SQLite connection it opens.
 *
 * Run:
 *   DB_DRIVER=sqlite vendor/bin/phpunit --testsuite=integration
 *      --filter=SqliteForeignKeyPragmaTest
 */
class SqliteForeignKeyPragmaTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (strtolower((string) (getenv('DB_DRIVER') ?: 'sqlite')) !== 'sqlite') {
            $this->markTestSkipped('SQLite-specific.');
        }
    }

    public function testForeignKeysPragmaIsEnabledOnConnect(): void
    {
        $row = DB::connection()->selectOne('PRAGMA foreign_keys');

        $this->assertSame(1, (int) ((array) $row)['foreign_keys']);
    }
}
