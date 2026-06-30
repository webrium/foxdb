# FoxDB

![Webrium FoxDB Cover](https://repository-images.githubusercontent.com/305963460/5261e7d1-7f5a-449a-bad7-8d95fbba1b19)

<div align="center">

[![Latest Stable Version](http://poser.pugx.org/webrium/foxdb/v)](https://packagist.org/packages/webrium/foxdb)
[![Total Downloads](http://poser.pugx.org/webrium/foxdb/downloads)](https://packagist.org/packages/webrium/foxdb)
[![License](http://poser.pugx.org/webrium/foxdb/license)](https://packagist.org/packages/webrium/foxdb)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://www.php.net)
[![Tests](https://github.com/webrium/foxdb/actions/workflows/tests.yml/badge.svg)](https://github.com/webrium/foxdb/actions/workflows/tests.yml)

**A standalone PHP database library — Query Builder, Eloquent ORM, Schema Builder, Migrations, and Seeders.**

[**webrium.dev**](https://webrium.dev) · [Documentation](https://webrium.dev/docs/v5/database/introduction) · [GitHub](https://github.com/webrium)

</div>

---

FoxDB is the database layer of the [Webrium](https://github.com/webrium) framework, available as a standalone package. It gives you a fluent query builder for writing SQL without strings, an Eloquent-style ORM for working with your data as objects, a schema builder for managing your database structure in PHP, and a migration and seeder system for versioning and populating your database. All of this runs on top of PDO with no external dependencies beyond the driver itself.

Supports **MySQL**, **PostgreSQL**, and **SQLite**, with per-driver SQL generation handled internally — write your queries and migrations once.

## Requirements

- PHP **8.1** or higher
- PDO extension for your chosen database: `pdo_mysql`, `pdo_pgsql`, or `pdo_sqlite`

## Installation

```bash
composer require webrium/foxdb
```

## Quick Start

```php
use Foxdb\DB;

DB::addConnection([
    'driver'   => 'mysql',
    'host'     => '127.0.0.1',
    'database' => 'my_db',
    'username' => 'root',
    'password' => 'secret',
]);

// Query builder
$users = DB::table('users')->where('active', 1)->get();

// Eloquent ORM
use Foxdb\Eloquent\Model;

class User extends Model
{
    protected array $fillable = ['name', 'email'];
}

$user = User::create(['name' => 'Ali', 'email' => 'ali@example.com']);
$admins = User::where('role', 'admin')->get();
```

## What's Included

- **Query Builder** — fluent, parameterized queries: selects, joins, aggregates, raw SQL, transactions
- **Eloquent ORM** — models with mass assignment protection, dirty tracking, attribute casting, soft deletes, and full JSON serialization
- **Relations** — `hasOne`, `hasMany`, `belongsTo`, `belongsToMany`, `hasManyThrough`, with lazy and eager loading
- **Collection** — map, filter, sort, chunk, paginate, and JSON-serialize query results
- **Schema Builder** — create and modify tables with a fluent Blueprint API instead of writing DDL by hand
- **Migrations** — version your schema with `up()` / `down()` methods, rollback, and refresh
- **Seeders** — repeatable scripts for populating your database with default or test data
- **Query Log & Hooks** — log every query, measure execution time, detect slow queries, attach `beforeQuery` / `afterQuery` callbacks

## Documentation

The complete documentation for FoxDB lives at **[webrium.dev/docs/v5/database](https://webrium.dev/docs/v5/database/introduction)**:

- **[Introduction](https://webrium.dev/docs/v5/database/introduction)** — design goals, namespace, standalone setup
- **[Connections](https://webrium.dev/docs/v5/database/connections)** — registering connections, multi-connection, raw SQL, transactions, query log
- **[Query Builder](https://webrium.dev/docs/v5/database/query-builder)** — selects, where conditions, joins, aggregates, writes
- **[Eloquent ORM](https://webrium.dev/docs/v5/database/eloquent-orm)** — models, CRUD, mass assignment, dirty tracking, scopes
- **[Relationships](https://webrium.dev/docs/v5/database/relationships)** — all relation types, eager loading, pivot methods
- **[Collections](https://webrium.dev/docs/v5/database/collections)** — the fluent API for result sets
- **[Casts & Serialization](https://webrium.dev/docs/v5/database/casts-serialization)** — attribute casts, `toArray()`, `toJson()`
- **[Pagination](https://webrium.dev/docs/v5/database/pagination)** — paginating large result sets
- **[Migrations, Schema & Seeders](https://webrium.dev/docs/v5/database/migrations-schema)** — full DDL and data-evolution workflow

The same documentation is also available as plain Markdown in the **[webrium/docs](https://github.com/webrium/docs)** repository.

## Running Tests

```bash
# Unit tests only — no database needed
vendor/bin/phpunit --testsuite=unit

# Integration tests with SQLite (no server required)
DB_DRIVER=sqlite vendor/bin/phpunit --testsuite=integration

# Integration tests with MySQL
DB_DRIVER=mysql DB_DATABASE=foxdb_test DB_PASSWORD=secret \
    vendor/bin/phpunit --testsuite=integration

# Integration tests with PostgreSQL
DB_DRIVER=pgsql DB_PORT=5432 DB_DATABASE=foxdb_test DB_PASSWORD=secret \
    vendor/bin/phpunit --testsuite=integration

# Run everything
DB_DRIVER=sqlite vendor/bin/phpunit --testsuite=all
```

CI runs all three drivers automatically on every pull request via GitHub Actions.

## Part of the Webrium Ecosystem

FoxDB is one of four packages that make up the full [Webrium Framework](https://github.com/webrium/webrium):

- **[`webrium/core`](https://github.com/webrium/core)** — routing, controllers, requests/responses, sessions, validation, and more
- **[`webrium/foxdb`](https://github.com/webrium/foxdb)** — this package
- **[`webrium/view`](https://github.com/webrium/view)** — Blade-compatible templating engine with hybrid static caching
- **[`webrium/console`](https://github.com/webrium/console)** — the `webrium` CLI toolkit

Each is independently usable, except `webrium/console` which is framework-coupled.

## License

MIT — see [LICENSE](LICENSE).
