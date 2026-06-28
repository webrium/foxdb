<?php

declare(strict_types=1);

namespace Foxdb\Seeders;

use Foxdb\DB;
use Throwable;

/**
 * SeederRunner — loads seeder files from a directory, instantiates them,
 * and executes their run() method.
 *
 * Unlike migrations, seeders are NOT tracked between runs. Each call to
 * run() / runAll() / runClass() executes the seeder fresh.
 *
 * Usage:
 *   $runner = new SeederRunner(__DIR__ . '/seeders');
 *
 *   // Run all seeders in the directory (sorted by file name)
 *   $results = $runner->runAll();
 *
 *   // Run a single seeder by class name
 *   $result  = $runner->runClass(UsersSeeder::class);
 *
 *   // Run a single seeder by file name (without .php)
 *   $result  = $runner->runFile('UsersSeeder');
 *
 *   // List discovered seeder files (without .php)
 *   $files   = $runner->getSeederFiles();
 */
class SeederRunner
{
    /**
     * The directory where seeder files live.
     *
     * @var string
     */
    protected string $path;

    /**
     * Optional default connection name used when a seeder doesn't set one.
     *
     * @var string|null
     */
    protected ?string $connection;

    /**
     * Whether to wrap each seeder in a transaction.
     * Defaults to true; can be disabled for engines that don't support DDL
     * inside transactions or when the caller wants partial inserts to persist.
     *
     * @var bool
     */
    protected bool $useTransaction = true;

    /**
     * @param string      $path        Path to the seeders directory
     * @param string|null $connection  Named connection (null = default)
     */
    public function __construct(string $path, ?string $connection = null)
    {
        $this->path       = rtrim($path, '/\\');
        $this->connection = $connection;
    }

    // -----------------------------------------------------------------------
    // Configuration
    // -----------------------------------------------------------------------

    /**
     * Enable or disable wrapping each seeder in a transaction.
     *
     * @param  bool $enable
     * @return self
     */
    public function useTransaction(bool $enable): self
    {
        $this->useTransaction = $enable;
        return $this;
    }

    // -----------------------------------------------------------------------
    // Run
    // -----------------------------------------------------------------------

    /**
     * Run every seeder file found in the seeders directory, sorted by file name.
     *
     * @return array<int, SeederResult>
     */
    public function runAll(): array
    {
        $files   = $this->getSeederFiles();
        $results = [];

        foreach ($files as $name) {
            $result    = $this->runFile($name);
            $results[] = $result;

            if (! $result->success) {
                break; // Stop on first failure
            }
        }

        return $results;
    }

    /**
     * Run a single seeder by file name (without the .php extension).
     *
     * @param  string $name
     * @return SeederResult
     */
    public function runFile(string $name): SeederResult
    {
        $start = hrtime(true);

        try {
            $class = $this->resolveFromFile($name);
            return $this->execute($class, $start);
        } catch (Throwable $e) {
            return SeederResult::fail($name, $this->elapsedMs($start), $e->getMessage());
        }
    }

    /**
     * Run a single seeder by its class name.
     *
     * If the class is already autoloadable (real namespace, composer-loaded),
     * it is instantiated directly. Otherwise the runner looks for a file
     * named after the class (with or without namespace prefix stripped) inside
     * the seeders directory and requires it.
     *
     * @param  string $class
     * @return SeederResult
     */
    public function runClass(string $class): SeederResult
    {
        $start = hrtime(true);

        try {
            if (! class_exists($class)) {
                // Try to load by file name in the seeders directory.
                // Strip namespace if present: "App\Seeders\Foo" -> "Foo".
                $shortName = ($pos = strrpos($class, '\\')) !== false
                    ? substr($class, $pos + 1)
                    : $class;

                $path = $this->path . DIRECTORY_SEPARATOR . $shortName . '.php';

                if (! file_exists($path)) {
                    throw new \RuntimeException("Seeder class [{$class}] not found.");
                }

                require_once $path;

                // Resolve either the original FQCN or the bare class name.
                if (! class_exists($class) && class_exists($shortName)) {
                    $class = $shortName;
                }

                if (! class_exists($class)) {
                    throw new \RuntimeException("Seeder class [{$class}] not found.");
                }
            }

            return $this->execute($class, $start);
        } catch (Throwable $e) {
            return SeederResult::fail($class, $this->elapsedMs($start), $e->getMessage());
        }
    }

    // -----------------------------------------------------------------------
    // File discovery
    // -----------------------------------------------------------------------

    /**
     * Get all seeder file names (without .php) from the seeders directory,
     * sorted alphabetically.
     *
     * @return array<int, string>
     */
    public function getSeederFiles(): array
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
     * Get the seeders directory path.
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    // -----------------------------------------------------------------------
    // Internal helpers
    // -----------------------------------------------------------------------

    /**
     * Require a seeder file and return its fully qualified class name.
     *
     * The class name is derived from the file name itself (we assume the
     * file is named after the class, just like Laravel-style seeders).
     *
     * @param  string $name  File name without .php
     * @return string        Fully qualified class name
     *
     * @throws \RuntimeException If the file or class is not found
     */
    protected function resolveFromFile(string $name): string
    {
        $path = $this->path . DIRECTORY_SEPARATOR . $name . '.php';

        if (! file_exists($path)) {
            throw new \RuntimeException("Seeder file not found: {$path}");
        }

        require_once $path;

        // Try the bare name first, then a couple of common namespaces.
        $candidates = [
            $name,
            "App\\Seeders\\{$name}",
            "Database\\Seeders\\{$name}",
        ];

        foreach ($candidates as $candidate) {
            if (class_exists($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException(
            "Seeder class for file [{$name}] not found. " .
            "Tried: " . implode(', ', $candidates)
        );
    }

    /**
     * Instantiate and execute a seeder class, returning a result.
     *
     * @param  string $class
     * @param  int    $startNs  hrtime(true) value captured before this call
     * @return SeederResult
     */
    protected function execute(string $class, int $startNs): SeederResult
    {
        /** @var Seeder $seeder */
        $seeder = new $class();

        if (! $seeder instanceof Seeder) {
            throw new \RuntimeException(
                "Class [{$class}] does not extend " . Seeder::class
            );
        }

        $seeder->setRunner($this);

        $connection = $seeder->connection ?? $this->connection;
        $conn       = DB::connection($connection);

        if ($this->useTransaction) {
            $conn->transaction(function () use ($seeder): void {
                $seeder->run();
            });
        } else {
            $seeder->run();
        }

        return SeederResult::ok($class, $this->elapsedMs($startNs));
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
}
