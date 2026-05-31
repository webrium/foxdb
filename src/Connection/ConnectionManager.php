<?php

declare(strict_types=1);

namespace Foxdb\Connection;

use Foxdb\Exceptions\DatabaseException;

/**
 * Manages a pool of named database connections.
 *
 * Usage:
 *   $manager = new ConnectionManager();
 *   $manager->addConnection('main', [...config...]);
 *   $manager->addConnection('reports', [...config...]);
 *
 *   $conn = $manager->connection('main');
 *   $manager->use('reports');               // switch default
 *   $conn = $manager->connection();         // uses current default
 */
class ConnectionManager
{
    /**
     * Registered connection configurations, keyed by name.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $configs = [];

    /**
     * Resolved (live) Connection instances, keyed by name.
     *
     * @var array<string, Connection>
     */
    protected array $connections = [];

    /**
     * The name of the currently active (default) connection.
     *
     * @var string
     */
    protected string $default = 'main';

    // -----------------------------------------------------------------------
    // Configuration
    // -----------------------------------------------------------------------

    /**
     * Register a named connection configuration.
     *
     * The connection is created lazily on first use.
     *
     * @param  string               $name
     * @param  array<string, mixed> $config
     * @return static
     */
    public function addConnection(string $name, array $config): static
    {
        $this->configs[$name] = $config;

        // If an already-resolved connection with this name exists, purge it
        // so the next call to connection() gets a fresh instance.
        unset($this->connections[$name]);

        return $this;
    }

    /**
     * Set (or switch) the default connection name.
     *
     * @param  string $name
     * @return static
     *
     * @throws DatabaseException If the connection name was never registered
     */
    public function use(string $name): static
    {
        if (! isset($this->configs[$name])) {
            throw DatabaseException::connectionNotFound($name);
        }

        $this->default = $name;

        return $this;
    }

    // -----------------------------------------------------------------------
    // Resolution
    // -----------------------------------------------------------------------

    /**
     * Resolve and return a Connection instance by name.
     *
     * When $name is omitted the current default connection is used.
     *
     * @param  string|null $name
     * @return Connection
     *
     * @throws DatabaseException If the connection is not registered
     */
    public function connection(?string $name = null): Connection
    {
        $name ??= $this->default;

        if (! isset($this->connections[$name])) {
            $this->connections[$name] = $this->resolve($name);
        }

        return $this->connections[$name];
    }

    /**
     * Resolve a new Connection from the stored config.
     *
     * @param  string $name
     * @return Connection
     *
     * @throws DatabaseException
     */
    protected function resolve(string $name): Connection
    {
        if (! isset($this->configs[$name])) {
            throw DatabaseException::connectionNotFound($name);
        }

        return new Connection($name, $this->configs[$name]);
    }

    // -----------------------------------------------------------------------
    // Inspection helpers
    // -----------------------------------------------------------------------

    /**
     * Check whether a connection name has been registered.
     *
     * @param  string $name
     * @return bool
     */
    public function hasConnection(string $name): bool
    {
        return isset($this->configs[$name]);
    }

    /**
     * Check whether a connection has already been resolved (opened).
     *
     * @param  string $name
     * @return bool
     */
    public function isResolved(string $name): bool
    {
        return isset($this->connections[$name]);
    }

    /**
     * Get the name of the current default connection.
     *
     * @return string
     */
    public function getDefaultName(): string
    {
        return $this->default;
    }

    /**
     * Get all registered connection names.
     *
     * @return array<int, string>
     */
    public function getConnectionNames(): array
    {
        return array_keys($this->configs);
    }

    /**
     * Close and remove a resolved connection from the pool.
     * The config is kept, so the connection can be re-opened later.
     *
     * @param  string $name
     * @return static
     */
    public function disconnect(string $name): static
    {
        unset($this->connections[$name]);

        return $this;
    }

    /**
     * Close all resolved connections.
     *
     * @return static
     */
    public function disconnectAll(): static
    {
        $this->connections = [];

        return $this;
    }
}
