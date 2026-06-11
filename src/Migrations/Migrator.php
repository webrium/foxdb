<?php

declare(strict_types=1);

namespace Foxdb\Migrations;

use Foxdb\DB;
use Throwable;

/**
 * Migrator — loads migration files, determines what needs to run,
 * executes them in order, and records the results.
 *
 * Usage:
 *   $migrator = new Migrator(__DIR__ . '/migrations');
 *
 *   // Run all pending migrations
 *   $results = $migrator->run();
 *
 *   // Roll back the last batch
 *   $results = $migrator->rollback();
 *
 *   // Roll back all migrations
 *   $results = $migrator->reset();
 *
 *   // Status of all migrations
 *   $status = $migrator->status();
 */
class Migrator
{
    /**
     * The migration repository that tracks run migrations.
     *
     * @var MigrationRepository
     */
    protected MigrationRepository $repository;

    /**
     * The directory where migration files live.
     *
     * @var string
     */
    protected string $path;

    /**
     * @param string      $path        Path to the migrations directory
     * @param string      $table       Migrations tracking table (default 'migrations')
     * @param string|null $connection  Named connection (null = default)
     */
    public function __construct(
        string  $path,
        string  $table      = 'migrations',
        ?string $connection = null,
    ) {
        $this->path       = rtrim($path, '/\\');
        $this->repository = new MigrationRepository($table, $connection);
    }

    // -----------------------------------------------------------------------
    // Run
    // -----------------------------------------------------------------------

    /**
     * Run all pending migrations.
     *
     * @param  int|null $steps  Limit to this many migrations (null = all)
     * @return array<int, MigrationResult>
     */
    public function run(?int $steps = null): array
    {
        $this->repository->createRepository();

        $pending = $this->getPendingMigrations();

        if (empty($pending)) {
            return [];
        }

        if ($steps !== null) {
            $pending = array_slice($pending, 0, $steps);
        }

        $batch   = $this->repository->getNextBatchNumber();
        $results = [];

        foreach ($pending as $file) {
            $result = $this->runUp($file, $batch);
            $results[] = $result;

            if (! $result->success) {
                break; // Stop on first failure
            }
        }

        return $results;
    }

    /**
     * Roll back the last batch of migrations.
     *
     * @param  int|null $steps  Number of individual migrations to roll back
     *                          (null = entire last batch)
     * @return array<int, MigrationResult>
     */
    public function rollback(?int $steps = null): array
    {
        if (! $this->repository->repositoryExists()) {
            return [];
        }

        if ($steps !== null) {
            // Roll back the last N individual migrations across any batch
            $toRollback = array_slice(
                array_reverse($this->repository->getAll()),
                0,
                $steps,
            );
        } else {
            $toRollback = $this->repository->getLast();
        }

        if (empty($toRollback)) {
            return [];
        }

        $results = [];

        foreach ($toRollback as $record) {
            $result    = $this->runDown($record->migration);
            $results[] = $result;

            if (! $result->success) {
                break;
            }
        }

        return $results;
    }

    /**
     * Roll back ALL migrations (reverse order).
     *
     * @return array<int, MigrationResult>
     */
    public function reset(): array
    {
        if (! $this->repository->repositoryExists()) {
            return [];
        }

        $all = array_reverse($this->repository->getAll());

        if (empty($all)) {
            return [];
        }

        $results = [];

        foreach ($all as $record) {
            $result    = $this->runDown($record->migration);
            $results[] = $result;
        }

        return $results;
    }

    /**
     * Roll back all migrations and re-run them from scratch.
     *
     * @return array{down: array<MigrationResult>, up: array<MigrationResult>}
     */
    public function refresh(): array
    {
        $down = $this->reset();
        $up   = $this->run();

        return ['down' => $down, 'up' => $up];
    }

    // -----------------------------------------------------------------------
    // Status
    // -----------------------------------------------------------------------

    /**
     * Get the status of every migration file in the migrations directory.
     *
     * @return array<int, array{name: string, ran: bool, batch: int|null}>
     */
    public function status(): array
    {
        $this->repository->createRepository();

        $ran    = $this->getRanIndex();
        $files  = $this->getMigrationFiles();
        $status = [];

        foreach ($files as $name) {
            $status[] = [
                'name'  => $name,
                'ran'   => isset($ran[$name]),
                'batch' => $ran[$name] ?? null,
            ];
        }

        return $status;
    }

    /**
     * Get only the pending (not yet run) migration names.
     *
     * @return array<int, string>
     */
    public function getPendingMigrations(): array
    {
        $this->repository->createRepository();

        $all = $this->getMigrationFiles();
        $ran = $this->repository->getRan();

        return array_values(array_diff($all, $ran));
    }

    /**
     * Determine whether there are pending migrations.
     *
     * @return bool
     */
    public function hasPendingMigrations(): bool
    {
        $this->repository->createRepository();

        return ! empty($this->getPendingMigrations());
    }

    // -----------------------------------------------------------------------
    // File discovery
    // -----------------------------------------------------------------------

    /**
     * Get all migration names from the migrations directory, sorted by name.
     * Convention: files must match the pattern YYYY_MM_DD_HHMMSS_name.php
     *
     * @return array<int, string>
     */
    public function getMigrationFiles(): array
    {
        if (! is_dir($this->path)) {
            return [];
        }

        $files = glob($this->path . DIRECTORY_SEPARATOR . '*.php');

        if ($files === false || empty($files)) {
            return [];
        }

        $names = array_map(
            fn(string $f) => pathinfo($f, PATHINFO_FILENAME),
            $files,
        );

        sort($names);

        return $names;
    }

    /**
     * Resolve a migration name to its full file path.
     *
     * @param  string $name
     * @return string
     */
    protected function resolvePath(string $name): string
    {
        return $this->path . DIRECTORY_SEPARATOR . $name . '.php';
    }

    // -----------------------------------------------------------------------
    // Execution helpers
    // -----------------------------------------------------------------------

    /**
     * Execute a single migration's up() method.
     *
     * @param  string $name
     * @param  int    $batch
     * @return MigrationResult
     */
    protected function runUp(string $name, int $batch): MigrationResult
    {
        $start = hrtime(true);

        try {
            $migration = $this->resolve($name);

            $conn = DB::connection($migration->connection ?? null);
            $conn->transaction(function () use ($migration): void {
                $migration->up();
            });

            $this->repository->log($name, $batch);

            return new MigrationResult(
                name     : $name,
                direction: 'up',
                success  : true,
                timeMs   : $this->elapsedMs($start),
            );
        } catch (Throwable $e) {
            return new MigrationResult(
                name     : $name,
                direction: 'up',
                success  : false,
                timeMs   : $this->elapsedMs($start),
                error    : $e->getMessage(),
            );
        }
    }

    /**
     * Execute a single migration's down() method.
     *
     * @param  string $name
     * @return MigrationResult
     */
    protected function runDown(string $name): MigrationResult
    {
        $start = hrtime(true);

        try {
            $migration = $this->resolve($name);

            $conn = DB::connection($migration->connection ?? null);
            $conn->transaction(function () use ($migration): void {
                $migration->down();
            });

            $this->repository->delete($name);

            return new MigrationResult(
                name     : $name,
                direction: 'down',
                success  : true,
                timeMs   : $this->elapsedMs($start),
            );
        } catch (Throwable $e) {
            return new MigrationResult(
                name     : $name,
                direction: 'down',
                success  : false,
                timeMs   : $this->elapsedMs($start),
                error    : $e->getMessage(),
            );
        }
    }

    /**
     * Load and instantiate a migration class from a file.
     *
     * @param  string $name  Migration file name without .php
     * @return Migration
     *
     * @throws \RuntimeException If the file does not exist
     */
    protected function resolve(string $name): Migration
    {
        $path = $this->resolvePath($name);

        if (! file_exists($path)) {
            throw new \RuntimeException("Migration file not found: {$path}");
        }

        require_once $path;

        // Convert file name to class name:
        // 2024_01_15_000001_create_users_table → CreateUsersTable
        $class = $this->fileNameToClassName($name);

        if (! class_exists($class)) {
            throw new \RuntimeException(
                "Migration class [{$class}] not found in file [{$path}]."
            );
        }

        return new $class();
    }

    /**
     * Convert a migration file name to a PascalCase class name.
     *
     * 2024_01_15_000001_create_users_table → CreateUsersTable
     *
     * @param  string $name
     * @return string
     */
    protected function fileNameToClassName(string $name): string
    {
        // Strip leading timestamp: YYYY_MM_DD_HHMMSS_
        $withoutTimestamp = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $name) ?? $name;

        // snake_case → PascalCase
        return str_replace('_', '', ucwords($withoutTimestamp, '_'));
    }

    /**
     * Build a name → batch lookup from the ran migrations.
     *
     * @return array<string, int>
     */
    protected function getRanIndex(): array
    {
        $index = [];
        foreach ($this->repository->getAll() as $record) {
            $index[$record->migration] = (int) $record->batch;
        }
        return $index;
    }

    /**
     * Calculate elapsed milliseconds from an hrtime(true) start value.
     *
     * @param  int $startNs
     * @return float
     */
    private function elapsedMs(int $startNs): float
    {
        return (hrtime(true) - $startNs) / 1_000_000;
    }

    // -----------------------------------------------------------------------
    // Accessors
    // -----------------------------------------------------------------------

    /**
     * Get the underlying MigrationRepository.
     *
     * @return MigrationRepository
     */
    public function getRepository(): MigrationRepository
    {
        return $this->repository;
    }

    /**
     * Get the migrations directory path.
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }
}
