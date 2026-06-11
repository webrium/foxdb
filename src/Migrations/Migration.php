<?php

declare(strict_types=1);

namespace Foxdb\Migrations;

use Foxdb\Schema;
use Foxdb\Schema\Blueprint;

/**
 * Abstract Migration — base class for all migration files.
 *
 * Each migration file defines two methods:
 *   up()   — apply the migration (create table, add column, etc.)
 *   down() — reverse it (drop table, drop column, etc.)
 *
 * Usage:
 *   class CreateUsersTable extends Migration
 *   {
 *       public function up(): void
 *       {
 *           Schema::create('users', function (Blueprint $table) {
 *               $table->id();
 *               $table->string('name');
 *               $table->string('email')->unique();
 *               $table->timestamps();
 *           });
 *       }
 *
 *       public function down(): void
 *       {
 *           Schema::dropIfExists('users');
 *       }
 *   }
 */
abstract class Migration
{
    /**
     * The database connection name to run this migration on.
     * Null means the current default connection.
     *
     * @var string|null
     */
    public ?string $connection = null;

    /**
     * Apply the migration — create tables, add columns, etc.
     *
     * @return void
     */
    abstract public function up(): void;

    /**
     * Reverse the migration — drop tables, remove columns, etc.
     *
     * @return void
     */
    abstract public function down(): void;
}
