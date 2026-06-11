<?php

declare(strict_types=1);

namespace Foxdb\Migrations;

use Foxdb\DB;
use Foxdb\Schema;
use Foxdb\Schema\Blueprint;

/**
 * MigrationRepository — manages the `migrations` tracking table.
 *
 * Records which migration files have been run and in which batch,
 * so the Migrator knows what still needs to be applied and what to
 * roll back.
 *
 * Table structure:
 *   id         INT AUTO_INCREMENT PK
 *   migration  VARCHAR(255)  — file name without .php extension
 *   batch      INT           — batch number (incremented each migrate run)
 */
class MigrationRepository
{
    /**
     * The migrations table name.
     *
     * @var string
     */
    protected string $table;

    /**
     * The connection name to use for the repository.
     *
     * @var string|null
     */
    protected ?string $connection;

    /**
     * @param string      $table      Migrations tracking table name (default 'migrations')
     * @param string|null $connection Named connection, null = default
     */
    public function __construct(string $table = 'migrations', ?string $connection = null)
    {
        $this->table      = $table;
        $this->connection = $connection;
    }

    // -----------------------------------------------------------------------
    // Repository table lifecycle
    // -----------------------------------------------------------------------

    /**
     * Create the migrations table if it does not already exist.
     *
     * @return void
     */
    public function createRepository(): void
    {
        if (Schema::hasTable($this->table, $this->connection)) {
            return;
        }

        Schema::create($this->table, function (Blueprint $t) {
            $t->id();
            $t->string('migration', 255);
            $t->integer('batch');
        }, $this->connection);
    }

    /**
     * Drop the migrations table.
     *
     * @return void
     */
    public function deleteRepository(): void
    {
        Schema::dropIfExists($this->table, $this->connection);
    }

    /**
     * Determine whether the migrations table exists.
     *
     * @return bool
     */
    public function repositoryExists(): bool
    {
        return Schema::hasTable($this->table, $this->connection);
    }

    // -----------------------------------------------------------------------
    // Reading migration state
    // -----------------------------------------------------------------------

    /**
     * Get the names of all migrations that have been run.
     *
     * @return array<int, string>
     */
    public function getRan(): array
    {
        return DB::table($this->table, $this->connection)
            ->orderBy('batch')
            ->orderBy('migration')
            ->pluck('migration');
    }

    /**
     * Get all migration records for the last batch.
     *
     * @return array<int, object>
     */
    public function getLast(): array
    {
        $batch = $this->getLastBatchNumber();

        if ($batch === 0) {
            return [];
        }

        return DB::table($this->table, $this->connection)
            ->where('batch', $batch)
            ->orderByDesc('migration')
            ->get()
            ->all();
    }

    /**
     * Get all migration records for a specific batch.
     *
     * @param  int $batch
     * @return array<int, object>
     */
    public function getBatch(int $batch): array
    {
        return DB::table($this->table, $this->connection)
            ->where('batch', $batch)
            ->orderByDesc('migration')
            ->get()
            ->all();
    }

    /**
     * Get all migration records ordered by batch + name.
     *
     * @return array<int, object>
     */
    public function getAll(): array
    {
        return DB::table($this->table, $this->connection)
            ->orderBy('batch')
            ->orderBy('migration')
            ->get()
            ->all();
    }

    /**
     * Get the last batch number that was run.
     * Returns 0 if no migrations have been run yet.
     *
     * @return int
     */
    public function getLastBatchNumber(): int
    {
        $result = DB::table($this->table, $this->connection)->max('batch');

        return (int) $result;
    }

    /**
     * Get the next batch number (last + 1).
     *
     * @return int
     */
    public function getNextBatchNumber(): int
    {
        return $this->getLastBatchNumber() + 1;
    }

    // -----------------------------------------------------------------------
    // Recording migration results
    // -----------------------------------------------------------------------

    /**
     * Log that a migration has been run.
     *
     * @param  string $migration  Migration name (file name without .php)
     * @param  int    $batch      Batch number
     * @return void
     */
    public function log(string $migration, int $batch): void
    {
        DB::table($this->table, $this->connection)->insert([
            'migration' => $migration,
            'batch'     => $batch,
        ]);
    }

    /**
     * Remove a migration log entry (used during rollback).
     *
     * @param  string $migration
     * @return void
     */
    public function delete(string $migration): void
    {
        DB::table($this->table, $this->connection)
            ->where('migration', $migration)
            ->delete();
    }

    /**
     * Get the migrations table name.
     *
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }
}
