<?php

declare(strict_types=1);

namespace Foxdb\Seeders;

/**
 * Abstract Seeder — base class for all database seeders.
 *
 * Seeders populate the database with test or default data. Unlike
 * migrations, seeders are not tracked: they can be run any number
 * of times and should be written to be idempotent when that matters.
 *
 * Usage:
 *   class UsersSeeder extends Seeder
 *   {
 *       public function run(): void
 *       {
 *           DB::table('users')->insert([
 *               'name'  => 'Admin',
 *               'email' => 'admin@example.com',
 *           ]);
 *
 *           // Optionally call other seeders
 *           $this->call(RolesSeeder::class);
 *       }
 *   }
 */
abstract class Seeder
{
    /**
     * The database connection name to run this seeder on.
     * Null means the current default connection.
     *
     * @var string|null
     */
    public ?string $connection = null;

    /**
     * The SeederRunner currently executing this seeder.
     * Injected by the runner so $this->call() can delegate back to it.
     *
     * @var SeederRunner|null
     */
    protected ?SeederRunner $runner = null;

    /**
     * Apply the seeder — insert default or test data.
     *
     * @return void
     */
    abstract public function run(): void;

    /**
     * Inject the runner that is executing this seeder.
     *
     * @param  SeederRunner $runner
     * @return void
     */
    public function setRunner(SeederRunner $runner): void
    {
        $this->runner = $runner;
    }

    /**
     * Run one or more other seeders from inside this seeder.
     *
     * @param  string|array<int, string> $seeders Class name(s) of seeders to run
     * @return array<int, SeederResult>
     */
    public function call(string|array $seeders): array
    {
        $seeders = is_array($seeders) ? $seeders : [$seeders];
        $results = [];

        foreach ($seeders as $class) {
            $results[] = $this->runner !== null
                ? $this->runner->runClass($class)
                : SeederResult::fail($class, 0.0, 'No runner attached to seeder.');
        }

        return $results;
    }
}
