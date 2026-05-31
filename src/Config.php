<?php

declare(strict_types=1);

namespace Foxdb;

/**
 * Configuration constants used when registering a connection.
 *
 * Usage:
 *   DB::addConnection('main', [
 *       'driver'    => Config::MYSQL,
 *       'charset'   => Config::UTF8MB4,
 *       'collation' => Config::UTF8MB4_UNICODE_CI,
 *       'fetch'     => Config::FETCH_OBJ,
 *   ]);
 */
final class Config
{
    // -----------------------------------------------------------------------
    // Drivers
    // -----------------------------------------------------------------------

    public const MYSQL  = 'mysql';
    public const PGSQL  = 'pgsql';
    public const SQLITE = 'sqlite';
    public const MSSQL  = 'sqlsrv';

    // -----------------------------------------------------------------------
    // Charsets
    // -----------------------------------------------------------------------

    public const UTF8    = 'utf8';
    public const UTF8MB4 = 'utf8mb4';
    public const LATIN1  = 'latin1';

    // -----------------------------------------------------------------------
    // Collations (MySQL / MariaDB)
    // -----------------------------------------------------------------------

    public const UTF8_GENERAL_CI       = 'utf8_general_ci';
    public const UTF8_UNICODE_CI       = 'utf8_unicode_ci';
    public const UTF8MB4_GENERAL_CI    = 'utf8mb4_general_ci';
    public const UTF8MB4_UNICODE_CI    = 'utf8mb4_unicode_ci';
    public const UTF8MB4_UNICODE_520_CI = 'utf8mb4_unicode_520_ci';

    // -----------------------------------------------------------------------
    // Fetch modes
    // -----------------------------------------------------------------------

    /** Fetch each row as a stdClass object (default). */
    public const FETCH_OBJ   = \PDO::FETCH_OBJ;

    /** Fetch each row as an associative array. */
    public const FETCH_ASSOC = \PDO::FETCH_ASSOC;

    /** Fetch each row as a numeric array. */
    public const FETCH_NUM   = \PDO::FETCH_NUM;

    /** Fetch each row as both associative and numeric array. */
    public const FETCH_BOTH  = \PDO::FETCH_BOTH;

    /**
     * Fetch each row as an instance of the model class.
     * Used internally by the Eloquent layer.
     */
    public const FETCH_CLASS = \PDO::FETCH_CLASS;

    // -----------------------------------------------------------------------
    // Default connection values
    // -----------------------------------------------------------------------

    public const DEFAULT_PORT_MYSQL  = '3306';
    public const DEFAULT_PORT_PGSQL  = '5432';
    public const DEFAULT_HOST        = '127.0.0.1';

    // Prevent instantiation — this is a constants-only class.
    private function __construct() {}
}
