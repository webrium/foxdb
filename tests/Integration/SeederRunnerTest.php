<?php

declare(strict_types=1);

namespace Foxdb\Tests\Integration;

use Foxdb\DB;
use Foxdb\Schema;
use Foxdb\Schema\Blueprint;
use Foxdb\Seeders\SeederRunner;

/**
 * SeederRunnerTest — end-to-end tests for the SeederRunner using a real
 * database connection. Covers:
 *
 *   - File discovery (sorted, ignores non-PHP)
 *   - runAll() executes every seeder in order
 *   - runFile() executes a single seeder
 *   - runClass() executes by FQCN
 *   - $this->call() chains through the runner
 *   - Transaction wrapping rolls back on error
 *   - useTransaction(false) disables the wrapper
 *   - Errors are captured into SeederResult, not thrown
 */
class SeederRunnerTest extends IntegrationTestCase
{
    /**
     * Temporary directory where each test writes its seeder files.
     *
     * @var string
     */
    private string $tmpDir;

    // -----------------------------------------------------------------------
    // Schema
    // -----------------------------------------------------------------------

    protected static function createSchema(): void
    {
        Schema::create('seed_users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
        });

        Schema::create('seed_roles', function (Blueprint $t) {
            $t->id();
            $t->string('name');
        });
    }

    protected static function dropSchema(): void
    {
        Schema::dropIfExists('seed_users');
        Schema::dropIfExists('seed_roles');
    }

    // -----------------------------------------------------------------------
    // Per-test setup / teardown
    // -----------------------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir = sys_get_temp_dir() . '/foxdb_seeders_' . uniqid('', true);
        mkdir($this->tmpDir);

        static::truncate('seed_users');
        static::truncate('seed_roles');
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            foreach (glob($this->tmpDir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($this->tmpDir);
        }

        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // File discovery
    // -----------------------------------------------------------------------

    public function test_getSeederFiles_returns_empty_for_missing_dir(): void
    {
        $runner = new SeederRunner($this->tmpDir . '/does-not-exist');
        $this->assertSame([], $runner->getSeederFiles());
    }

    public function test_getSeederFiles_returns_empty_when_dir_has_no_php_files(): void
    {
        file_put_contents($this->tmpDir . '/readme.txt', 'not a seeder');

        $runner = new SeederRunner($this->tmpDir);
        $this->assertSame([], $runner->getSeederFiles());
    }

    public function test_getSeederFiles_returns_php_files_sorted(): void
    {
        file_put_contents($this->tmpDir . '/ZSeeder.php', '<?php');
        file_put_contents($this->tmpDir . '/ASeeder.php', '<?php');
        file_put_contents($this->tmpDir . '/MSeeder.php', '<?php');
        file_put_contents($this->tmpDir . '/notes.md', 'ignored');

        $runner = new SeederRunner($this->tmpDir);
        $this->assertSame(['ASeeder', 'MSeeder', 'ZSeeder'], $runner->getSeederFiles());
    }

    public function test_getPath_returns_normalized_path(): void
    {
        $runner = new SeederRunner($this->tmpDir . '/');
        // Trailing slashes are stripped.
        $this->assertSame(rtrim($this->tmpDir, '/\\'), $runner->getPath());
    }

    // -----------------------------------------------------------------------
    // runFile / runAll / runClass
    // -----------------------------------------------------------------------

    public function test_runFile_executes_seeder_and_persists_inserts(): void
    {
        $this->writeSeeder('UsersSeeder_File', <<<'PHP'
            <?php
            use Foxdb\DB;
            use Foxdb\Seeders\Seeder;
            class UsersSeeder_File extends Seeder {
                public function run(): void {
                    DB::table('seed_users')->insert(['name' => 'Ali']);
                }
            }
        PHP);

        $runner = new SeederRunner($this->tmpDir);
        $result = $runner->runFile('UsersSeeder_File');

        $this->assertTrue($result->success, $result->error);
        $this->assertSame('UsersSeeder_File', $result->name);
        $this->assertGreaterThan(0.0, $result->timeMs);
        $this->assertSame('', $result->error);
        $this->assertSame(1, (int) DB::table('seed_users')->count());
    }

    public function test_runFile_returns_failed_result_for_missing_file(): void
    {
        $runner = new SeederRunner($this->tmpDir);
        $result = $runner->runFile('NoSuchSeeder');

        $this->assertFalse($result->success);
        $this->assertSame('NoSuchSeeder', $result->name);
        $this->assertStringContainsString('not found', $result->error);
    }

    public function test_runFile_returns_failed_result_when_class_missing(): void
    {
        // PHP file exists but defines a class with a different name.
        file_put_contents(
            $this->tmpDir . '/MismatchedSeeder.php',
            "<?php class SomethingElse {}"
        );

        $runner = new SeederRunner($this->tmpDir);
        $result = $runner->runFile('MismatchedSeeder');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not found', $result->error);
    }

    public function test_runFile_returns_failed_result_when_class_does_not_extend_Seeder(): void
    {
        file_put_contents(
            $this->tmpDir . '/NotASeeder.php',
            "<?php class NotASeeder { public function run(): void {} }"
        );

        $runner = new SeederRunner($this->tmpDir);
        $result = $runner->runFile('NotASeeder');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('does not extend', $result->error);
    }

    public function test_runAll_executes_every_file_in_alphabetical_order(): void
    {
        $this->writeSeeder('A_RolesSeeder', <<<'PHP'
            <?php
            use Foxdb\DB;
            use Foxdb\Seeders\Seeder;
            class A_RolesSeeder extends Seeder {
                public function run(): void {
                    DB::table('seed_roles')->insert(['name' => 'admin']);
                }
            }
        PHP);

        $this->writeSeeder('B_UsersSeeder', <<<'PHP'
            <?php
            use Foxdb\DB;
            use Foxdb\Seeders\Seeder;
            class B_UsersSeeder extends Seeder {
                public function run(): void {
                    DB::table('seed_users')->insert(['name' => 'Ali']);
                }
            }
        PHP);

        $runner  = new SeederRunner($this->tmpDir);
        $results = $runner->runAll();

        $this->assertCount(2, $results);
        $this->assertSame('A_RolesSeeder', $results[0]->name);
        $this->assertSame('B_UsersSeeder', $results[1]->name);
        $this->assertTrue($results[0]->success);
        $this->assertTrue($results[1]->success);

        $this->assertSame(1, (int) DB::table('seed_roles')->count());
        $this->assertSame(1, (int) DB::table('seed_users')->count());
    }

    public function test_runAll_stops_on_first_failure(): void
    {
        $this->writeSeeder('A_OkSeeder', <<<'PHP'
            <?php
            use Foxdb\DB;
            use Foxdb\Seeders\Seeder;
            class A_OkSeeder extends Seeder {
                public function run(): void {
                    DB::table('seed_users')->insert(['name' => 'first']);
                }
            }
        PHP);

        $this->writeSeeder('B_BrokenSeeder', <<<'PHP'
            <?php
            use Foxdb\Seeders\Seeder;
            class B_BrokenSeeder extends Seeder {
                public function run(): void {
                    throw new \RuntimeException('intentional');
                }
            }
        PHP);

        $this->writeSeeder('C_NeverRunsSeeder', <<<'PHP'
            <?php
            use Foxdb\DB;
            use Foxdb\Seeders\Seeder;
            class C_NeverRunsSeeder extends Seeder {
                public function run(): void {
                    DB::table('seed_users')->insert(['name' => 'never']);
                }
            }
        PHP);

        $runner  = new SeederRunner($this->tmpDir);
        $results = $runner->runAll();

        $this->assertCount(2, $results, 'runAll must stop after the first failure');
        $this->assertTrue($results[0]->success);
        $this->assertFalse($results[1]->success);
        $this->assertStringContainsString('intentional', $results[1]->error);

        $rows = DB::table('seed_users')->get()->all();
        $this->assertCount(1, $rows);
        $this->assertSame('first', $rows[0]->name);
    }

    public function test_runClass_executes_by_fqcn_without_a_file(): void
    {
        // Define a seeder class directly, no file needed.
        eval(<<<'PHP'
            namespace Foxdb\Tests\Integration\Inline {
                use Foxdb\DB;
                use Foxdb\Seeders\Seeder;
                class InlineSeeder extends Seeder {
                    public function run(): void {
                        DB::table('seed_users')->insert(['name' => 'inline']);
                    }
                }
            }
        PHP);

        $runner = new SeederRunner($this->tmpDir);
        $result = $runner->runClass('Foxdb\\Tests\\Integration\\Inline\\InlineSeeder');

        $this->assertTrue($result->success, $result->error);
        $this->assertSame(1, (int) DB::table('seed_users')->count());
    }

    public function test_runClass_returns_failed_result_for_missing_class(): void
    {
        $runner = new SeederRunner($this->tmpDir);
        $result = $runner->runClass('NoSuchClassAnywhere');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not found', $result->error);
    }

    // -----------------------------------------------------------------------
    // $this->call() chaining
    // -----------------------------------------------------------------------

    public function test_seeder_can_call_other_seeders_via_runner(): void
    {
        $this->writeSeeder('Chain_RolesSeeder', <<<'PHP'
            <?php
            use Foxdb\DB;
            use Foxdb\Seeders\Seeder;
            class Chain_RolesSeeder extends Seeder {
                public function run(): void {
                    DB::table('seed_roles')->insert(['name' => 'editor']);
                }
            }
        PHP);

        $this->writeSeeder('Chain_MasterSeeder', <<<'PHP'
            <?php
            use Foxdb\DB;
            use Foxdb\Seeders\Seeder;
            class Chain_MasterSeeder extends Seeder {
                public function run(): void {
                    DB::table('seed_users')->insert(['name' => 'root']);
                    $this->call('Chain_RolesSeeder');
                }
            }
        PHP);

        $runner = new SeederRunner($this->tmpDir);
        // Run only the master; it should chain to RolesSeeder.
        $result = $runner->runFile('Chain_MasterSeeder');

        $this->assertTrue($result->success, $result->error);
        $this->assertSame(1, (int) DB::table('seed_users')->count());
        $this->assertSame(1, (int) DB::table('seed_roles')->count());
    }

    // -----------------------------------------------------------------------
    // Transaction wrapping
    // -----------------------------------------------------------------------

    public function test_transaction_wrapping_rolls_back_on_error(): void
    {
        // Skip on SQLite memory if it cannot handle this scenario reliably.
        $this->writeSeeder('Tx_HalfwaySeeder', <<<'PHP'
            <?php
            use Foxdb\DB;
            use Foxdb\Seeders\Seeder;
            class Tx_HalfwaySeeder extends Seeder {
                public function run(): void {
                    DB::table('seed_users')->insert(['name' => 'inserted-before-fail']);
                    throw new \RuntimeException('boom');
                }
            }
        PHP);

        $runner = new SeederRunner($this->tmpDir);
        $result = $runner->runFile('Tx_HalfwaySeeder');

        $this->assertFalse($result->success);
        // The insert must be rolled back by the surrounding transaction.
        $this->assertSame(
            0,
            (int) DB::table('seed_users')->count(),
            'Inserts before the exception should have been rolled back'
        );
    }

    public function test_useTransaction_false_keeps_inserts_when_error_happens(): void
    {
        $this->writeSeeder('NoTx_HalfwaySeeder', <<<'PHP'
            <?php
            use Foxdb\DB;
            use Foxdb\Seeders\Seeder;
            class NoTx_HalfwaySeeder extends Seeder {
                public function run(): void {
                    DB::table('seed_users')->insert(['name' => 'kept']);
                    throw new \RuntimeException('boom');
                }
            }
        PHP);

        $runner = (new SeederRunner($this->tmpDir))->useTransaction(false);
        $result = $runner->runFile('NoTx_HalfwaySeeder');

        $this->assertFalse($result->success);
        // Without a transaction, the insert before the throw should persist.
        $this->assertSame(1, (int) DB::table('seed_users')->count());
    }

    public function test_useTransaction_returns_self_for_chaining(): void
    {
        $runner = new SeederRunner($this->tmpDir);
        $this->assertSame($runner, $runner->useTransaction(false));
        $this->assertSame($runner, $runner->useTransaction(true));
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Write a seeder file into the per-test temp directory.
     *
     * The contents may use leading indentation (heredoc-friendly); it is
     * trimmed before writing so the PHP open tag ends up at column 0.
     */
    private function writeSeeder(string $name, string $contents): void
    {
        $normalized = ltrim(preg_replace('/^[ \t]+/m', '', $contents) ?? $contents);
        file_put_contents($this->tmpDir . '/' . $name . '.php', $normalized);
    }
}
