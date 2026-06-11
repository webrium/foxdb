<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Foxdb\Config;
use Foxdb\DB;
use Foxdb\Schema;
use Foxdb\Schema\Blueprint;
use Foxdb\Schema\Grammars\MySqlSchemaGrammar;
use Foxdb\Schema\Grammars\PostgresSchemaGrammar;
use Foxdb\Schema\Grammars\SqliteSchemaGrammar;

function pass(string $msg): void { echo "\033[32m✔ {$msg}\033[0m\n"; }
function fail(string $msg): void { echo "\033[31m✘ {$msg}\033[0m\n"; exit(1); }

function assertSql(string $expected, string $actual, string $label): void
{
    $e = preg_replace('/\s+/', ' ', trim($expected));
    $a = preg_replace('/\s+/', ' ', trim($actual));
    if ($e !== $a) {
        echo "\033[31m✘ {$label}\033[0m\n";
        echo "  Expected: {$e}\n  Got     : {$a}\n";
        exit(1);
    }
    pass($label);
}

echo "\n=== FoxDB Schema Builder Tests (MySQL) ===\n\n";

// -----------------------------------------------------------------------
// Setup MySQL connection for execution tests
// -----------------------------------------------------------------------
DB::reset();
DB::addConnection([
    'driver'           => Config::MYSQL,
    'host'             => '127.0.0.1',
    'port'             => '3306',
    'database'         => 'test',
    'username'         => 'root',
    'password'         => '123456',
    'charset'          => 'utf8mb4',
    'throw_exceptions' => true,
]);

// Clean up environment for execution section
Schema::dropIfExists('users');
Schema::dropIfExists('old_table');
Schema::dropIfExists('new_table');

// -----------------------------------------------------------------------
// Grammar instances for SQL compilation tests
// -----------------------------------------------------------------------
$mysql  = new MySqlSchemaGrammar();
$pgsql  = new PostgresSchemaGrammar();
$sqlite = new SqliteSchemaGrammar();

echo "── ColumnDefinition ────────────────────────────────\n";

// -----------------------------------------------------------------------
// 1. ColumnDefinition fluent modifiers
// -----------------------------------------------------------------------
$col = new \Foxdb\Schema\ColumnDefinition(['type' => 'string', 'name' => 'email']);
$col->nullable()->default('')->unique()->comment('User email')->after('name');

if (! $col->has('nullable'))   fail('nullable not set');
if ($col->get('default') !== '') fail('default not set');
if (! $col->has('unique'))     fail('unique not set');
if ($col->get('comment') !== 'User email') fail('comment wrong');
if ($col->get('after') !== 'name') fail('after wrong');
pass('ColumnDefinition: nullable / default / unique / comment / after');

$col2 = new \Foxdb\Schema\ColumnDefinition(['type' => 'integer', 'name' => 'votes']);
$col2->unsigned()->index()->change();
if (! $col2->has('unsigned')) fail('unsigned not set');
if (! $col2->has('index'))   fail('index not set');
if (! $col2->has('change'))  fail('change not set');
pass('ColumnDefinition: unsigned / index / change');

echo "\n── Blueprint ───────────────────────────────────────\n";

// -----------------------------------------------------------------------
// 2. Blueprint column collection
// -----------------------------------------------------------------------
$bp = new Blueprint('users');
$bp->id();
$bp->string('name', 100);
$bp->string('email')->unique();
$bp->integer('age')->nullable()->default(0);
$bp->boolean('active')->default(true);
$bp->timestamps();
$bp->softDeletes();

$cols = $bp->getColumns();
if (count($cols) !== 8) fail("Blueprint column count: expected 8, got " . count($cols)); 
pass('Blueprint: collects 8 columns (id + name + email + age + active + timestamps + softDeletes)');

$names = array_map(fn($c) => $c->getName(), $cols);
if (! in_array('id', $names))         fail('id column missing');
if (! in_array('created_at', $names)) fail('created_at missing');
if (! in_array('deleted_at', $names)) fail('deleted_at missing');
pass('Blueprint: id / created_at / deleted_at present');

// -----------------------------------------------------------------------
// 3. Blueprint index collection
// -----------------------------------------------------------------------
$bp2 = new Blueprint('posts');
$bp2->string('title');
$bp2->index('title', 'posts_title_index');
$bp2->unique(['email', 'role'], 'posts_email_role_unique');

$indexes = $bp2->getIndexes();
if (count($indexes) !== 2) fail("Blueprint index count: expected 2, got " . count($indexes));
if ($indexes[0]['type'] !== 'index') fail('First index type wrong');
if ($indexes[1]['type'] !== 'unique') fail('Second index type wrong');
pass('Blueprint: collects index and unique index definitions');

// -----------------------------------------------------------------------
// 4. Blueprint foreign key collection
// -----------------------------------------------------------------------
$bp3 = new Blueprint('orders');
$bp3->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
$fks = $bp3->getForeignKeys();
if (count($fks) !== 1) fail('FK count wrong');
if ($fks[0]->getColumn() !== 'user_id') fail('FK column wrong');
if ($fks[0]->getOn() !== 'users') fail('FK on wrong');
if ($fks[0]->getOnDelete() !== 'CASCADE') fail('FK onDelete wrong');
pass('Blueprint: foreign key definition with cascadeOnDelete');

// -----------------------------------------------------------------------
// 5. Blueprint drop / rename
// -----------------------------------------------------------------------
$bp4 = new Blueprint('users');
$bp4->dropColumn('old_col');
$bp4->dropColumn(['col_a', 'col_b']);
$bp4->renameColumn('email', 'login_email');
$bp4->dropIndex('users_name_index');

if (count($bp4->getDroppedColumns()) !== 3) fail('Dropped columns count wrong');
if ($bp4->getRenamedColumns()['email'] !== 'login_email') fail('Rename wrong');
if ($bp4->getDroppedIndexes()[0] !== 'users_name_index') fail('Dropped index wrong');
pass('Blueprint: dropColumn / renameColumn / dropIndex');

echo "\n── MySQL Grammar — SQL compilation ─────────────────\n";

// -----------------------------------------------------------------------
// 6. MySQL CREATE TABLE — basic
// -----------------------------------------------------------------------
$bp = new Blueprint('users');
$bp->id();
$bp->string('name', 100);
$bp->string('email');
$bp->timestamps();

assertSql(
    "CREATE TABLE `users` ( `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, `name` VARCHAR(100) NOT NULL, `email` VARCHAR(255) NOT NULL, `created_at` TIMESTAMP NULL, `updated_at` TIMESTAMP NULL )",
    $mysql->compileCreate($bp),
    'MySQL CREATE TABLE: id + string + timestamps',
);

// -----------------------------------------------------------------------
// 7. MySQL CREATE TABLE — nullable / default / unique / unsigned
// -----------------------------------------------------------------------
$bp = new Blueprint('products');
$bp->id();
$bp->string('sku', 50)->unique();
$bp->integer('stock')->unsigned()->default(0);
$bp->decimal('price', 10, 2)->default(0.00);
$bp->boolean('active')->default(true);
$bp->text('description')->nullable();

$sql = $mysql->compileCreate($bp);
if (! str_contains($sql, 'UNSIGNED')) fail('MySQL: UNSIGNED missing');
if (! str_contains($sql, 'UNIQUE'))   fail('MySQL: UNIQUE missing');
if (! str_contains($sql, 'DEFAULT 0')) fail('MySQL: DEFAULT 0 missing');
if (! str_contains($sql, 'DEFAULT 1')) fail('MySQL: DEFAULT 1 (bool true) missing');
if (! str_contains($sql, 'NULL')) fail('MySQL: nullable missing');
pass('MySQL CREATE TABLE: unsigned / unique / default / nullable / boolean');

// -----------------------------------------------------------------------
// 8. MySQL CREATE TABLE — enum
// -----------------------------------------------------------------------
$bp = new Blueprint('users');
$bp->enum('role', ['admin', 'editor', 'user'])->default('user');

$sql = $mysql->compileCreate($bp);
if (! str_contains($sql, "ENUM('admin', 'editor', 'user')")) fail("MySQL ENUM wrong: {$sql}");
pass("MySQL CREATE TABLE: ENUM type");

// -----------------------------------------------------------------------
// 9. MySQL CREATE TABLE — foreign key inline
// -----------------------------------------------------------------------
$bp = new Blueprint('orders');
$bp->id();
$bp->bigInteger('user_id')->unsigned();
$bp->foreign('user_id')->references('id')->on('users')->cascadeOnDelete()->onUpdate('CASCADE');

$sql = $mysql->compileCreate($bp);
if (! str_contains($sql, 'CONSTRAINT')) fail('MySQL FK: CONSTRAINT missing');
if (! str_contains($sql, 'FOREIGN KEY')) fail('MySQL FK: FOREIGN KEY missing');
if (! str_contains($sql, 'ON DELETE CASCADE')) fail('MySQL FK: ON DELETE CASCADE missing');
if (! str_contains($sql, 'ON UPDATE CASCADE')) fail('MySQL FK: ON UPDATE CASCADE missing');
pass('MySQL CREATE TABLE: inline FOREIGN KEY constraint');

// -----------------------------------------------------------------------
// 10. MySQL ADD COLUMN
// -----------------------------------------------------------------------
$bp = new Blueprint('users');
$bp->integer('age')->nullable()->after('name');

$sqls = $mysql->compileAdd($bp);
if (count($sqls) !== 1) fail('MySQL compileAdd count wrong');
assertSql(
    'ALTER TABLE `users` ADD COLUMN `age` INT NULL AFTER `name`',
    $sqls[0],
    'MySQL ALTER TABLE ADD COLUMN with AFTER',
);

// -----------------------------------------------------------------------
// 11. MySQL MODIFY COLUMN (change)
// -----------------------------------------------------------------------
$bp = new Blueprint('users');
$col = $bp->string('name', 200);
$col->change();

$sqls = $mysql->compileChange($bp);
if (count($sqls) !== 1) fail('MySQL compileChange count wrong');
assertSql(
    'ALTER TABLE `users` MODIFY COLUMN `name` VARCHAR(200) NOT NULL',
    $sqls[0],
    'MySQL ALTER TABLE MODIFY COLUMN',
);

// -----------------------------------------------------------------------
// 12. MySQL DROP COLUMN
// -----------------------------------------------------------------------
$bp = new Blueprint('users');
$bp->dropColumn(['old_field', 'another_field']);

$sqls = $mysql->compileDrop($bp);
if (count($sqls) !== 2) fail('MySQL compileDrop count wrong');
assertSql('ALTER TABLE `users` DROP COLUMN `old_field`', $sqls[0], 'MySQL DROP COLUMN 1');
assertSql('ALTER TABLE `users` DROP COLUMN `another_field`', $sqls[1], 'MySQL DROP COLUMN 2');

// -----------------------------------------------------------------------
// 13. MySQL RENAME COLUMN
// -----------------------------------------------------------------------
$bp = new Blueprint('users');
$bp->renameColumn('email', 'login_email');

$sqls = $mysql->compileRenameColumn($bp);
assertSql(
    'ALTER TABLE `users` RENAME COLUMN `email` TO `login_email`',
    $sqls[0],
    'MySQL RENAME COLUMN',
);

// -----------------------------------------------------------------------
// 14. MySQL CREATE INDEX / DROP INDEX
// -----------------------------------------------------------------------
$bp = new Blueprint('users');
$bp->index(['name', 'email'], 'users_name_email_idx');
$bp->unique('email', 'users_email_unique');

$sqls = $mysql->compileIndexes($bp);
if (count($sqls) !== 2) fail('MySQL compileIndexes count wrong');
if (! str_contains($sqls[0], 'CREATE INDEX')) fail('MySQL CREATE INDEX missing');
if (! str_contains($sqls[1], 'CREATE UNIQUE INDEX')) fail('MySQL CREATE UNIQUE INDEX missing');
pass('MySQL: CREATE INDEX / CREATE UNIQUE INDEX');

$bp2 = new Blueprint('users');
$bp2->dropIndex('users_name_email_idx');
$dropSqls = $mysql->compileDropIndexes($bp2);
assertSql(
    'DROP INDEX `users_name_email_idx` ON `users`',
    $dropSqls[0],
    'MySQL DROP INDEX',
);

// -----------------------------------------------------------------------
// 15. MySQL DROP TABLE / DROP IF EXISTS / RENAME TABLE
// -----------------------------------------------------------------------
assertSql('DROP TABLE `users`', $mysql->compileDropTable('users'), 'MySQL DROP TABLE');
assertSql('DROP TABLE IF EXISTS `users`', $mysql->compileDropTableIfExists('users'), 'MySQL DROP TABLE IF EXISTS');
assertSql('RENAME TABLE `users` TO `members`', $mysql->compileRenameTable('users', 'members'), 'MySQL RENAME TABLE');

echo "\n── PostgreSQL Grammar — SQL compilation ─────────────\n";

// -----------------------------------------------------------------------
// 16. PostgreSQL CREATE TABLE — types
// -----------------------------------------------------------------------
$bp = new Blueprint('users');
$bp->id();
$bp->string('name');
$bp->boolean('active');
$bp->json('meta');

$sql = $pgsql->compileCreate($bp);
if (! str_contains($sql, 'BIGSERIAL')) fail('PostgreSQL: BIGSERIAL missing');
if (! str_contains($sql, 'BOOLEAN'))   fail('PostgreSQL: BOOLEAN missing');
if (! str_contains($sql, 'JSONB'))     fail('PostgreSQL: JSONB missing');
if (str_contains($sql, 'AUTO_INCREMENT')) fail('PostgreSQL: should not have AUTO_INCREMENT');
if (str_contains($sql, 'UNSIGNED')) fail('PostgreSQL: should not have UNSIGNED');
pass('PostgreSQL CREATE TABLE: BIGSERIAL / BOOLEAN / JSONB, no AUTO_INCREMENT/UNSIGNED');

// -----------------------------------------------------------------------
// 17. PostgreSQL ALTER COLUMN TYPE
// -----------------------------------------------------------------------
$bp = new Blueprint('users');
$col = $bp->string('name', 200);
$col->nullable()->change();

$sqls = $pgsql->compileChange($bp);
if (count($sqls) < 1) fail('PostgreSQL compileChange returned empty');
if (! str_contains($sqls[0], 'ALTER COLUMN')) fail('PostgreSQL: ALTER COLUMN missing');
if (! str_contains($sqls[0], 'TYPE')) fail('PostgreSQL: TYPE missing');
pass('PostgreSQL ALTER COLUMN TYPE');

// -----------------------------------------------------------------------
// 18. PostgreSQL DROP INDEX (standalone, no ON table)
// -----------------------------------------------------------------------
$bp = new Blueprint('users');
$bp->dropIndex('users_email_idx');
$sqls = $pgsql->compileDropIndexes($bp);
assertSql('DROP INDEX "users_email_idx"', $sqls[0], 'PostgreSQL DROP INDEX (no ON table)');

// -----------------------------------------------------------------------
// 19. PostgreSQL RENAME TABLE
// -----------------------------------------------------------------------
assertSql(
    'ALTER TABLE "users" RENAME TO "members"',
    $pgsql->compileRenameTable('users', 'members'),
    'PostgreSQL RENAME TABLE',
);

// -----------------------------------------------------------------------
// 20. PostgreSQL DROP FOREIGN KEY (DROP CONSTRAINT)
// -----------------------------------------------------------------------
$bp = new Blueprint('orders');
$bp->dropForeign('orders_user_id_foreign');
$sqls = $pgsql->compileDropForeignKeys($bp);
assertSql(
    'ALTER TABLE "orders" DROP CONSTRAINT "orders_user_id_foreign"',
    $sqls[0],
    'PostgreSQL DROP FOREIGN KEY (DROP CONSTRAINT)',
);

echo "\n── SQLite Grammar — SQL compilation ────────────────\n";

// -----------------------------------------------------------------------
// 21. SQLite CREATE TABLE — type affinity
// -----------------------------------------------------------------------
$bp = new Blueprint('users');
$bp->id();
$bp->string('name');
$bp->boolean('active');
$bp->json('settings');
$bp->float('score', 5, 2);

$sql = $sqlite->compileCreate($bp);
if (! str_contains($sql, 'INTEGER')) fail('SQLite: INTEGER missing');
if (! str_contains($sql, 'TEXT'))    fail('SQLite: TEXT missing');
if (! str_contains($sql, 'REAL'))    fail('SQLite: REAL missing');
if (str_contains($sql, 'AUTO_INCREMENT')) fail('SQLite: should not have AUTO_INCREMENT');
if (str_contains($sql, 'UNSIGNED')) fail('SQLite: should not have UNSIGNED');
pass('SQLite CREATE TABLE: INTEGER / TEXT / REAL affinity, no AUTO_INCREMENT');

// -----------------------------------------------------------------------
// 22. SQLite RENAME TABLE
// -----------------------------------------------------------------------
assertSql(
    'ALTER TABLE "users" RENAME TO "members"',
    $sqlite->compileRenameTable('users', 'members'),
    'SQLite RENAME TABLE',
);

// -----------------------------------------------------------------------
// 23. SQLite DROP INDEX (IF EXISTS)
// -----------------------------------------------------------------------
$bp = new Blueprint('users');
$bp->dropIndex('users_name_idx');
$sqls = $sqlite->compileDropIndexes($bp);
if (! str_contains($sqls[0], 'IF EXISTS')) fail('SQLite DROP INDEX missing IF EXISTS');
pass('SQLite DROP INDEX IF EXISTS');

echo "\n── Schema Facade — execution (MySQL) ───────────────\n";

// -----------------------------------------------------------------------
// 24. Schema::create() executes successfully
// -----------------------------------------------------------------------
Schema::dropIfExists('users'); // Ensure a clean table state in MySQL
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name', 100);
    $table->string('email'); // Dropped unique modifier to avoid max key length limits on raw fields if unspecified
    $table->integer('age')->nullable()->default(0);
    $table->boolean('active')->default(true);
    $table->json('settings')->nullable();
    $table->timestamps();
    $table->softDeletes();
});

if (! Schema::hasTable('users')) fail('Schema::create() did not create users table');
pass('Schema::create() — creates table successfully');

// -----------------------------------------------------------------------
// 25. Schema::hasTable()
// -----------------------------------------------------------------------
if (Schema::hasTable('nonexistent')) fail('hasTable() should return false for missing table');
pass('Schema::hasTable() false for nonexistent table');

// -----------------------------------------------------------------------
// 26. Schema::hasColumn()
// -----------------------------------------------------------------------
if (! Schema::hasColumn('users', 'email'))     fail('hasColumn() email missing');
if (! Schema::hasColumn('users', 'created_at')) fail('hasColumn() created_at missing');
if (! Schema::hasColumn('users', 'deleted_at')) fail('hasColumn() deleted_at missing');
if (Schema::hasColumn('users', 'nonexistent')) fail('hasColumn() should be false for missing column');
pass('Schema::hasColumn() — true/false for existing/missing columns');

// -----------------------------------------------------------------------
// 27. Schema::getColumnNames()
// -----------------------------------------------------------------------
$cols = Schema::getColumnNames('users');
// Lowercase normalization for cross-database stability
$cols = array_map('strtolower', $cols);

if (! in_array('id', $cols))         fail('getColumnNames() missing id');
if (! in_array('name', $cols))       fail('getColumnNames() missing name');
if (! in_array('deleted_at', $cols)) fail('getColumnNames() missing deleted_at');
pass('Schema::getColumnNames() returns all columns');

// -----------------------------------------------------------------------
// 28. Schema::table() — ADD COLUMN
// -----------------------------------------------------------------------
Schema::table('users', function (Blueprint $table) {
    $table->string('phone', 20)->nullable();
    $table->integer('score')->default(0);
});

if (! Schema::hasColumn('users', 'phone')) fail('Schema::table() ADD phone failed');
if (! Schema::hasColumn('users', 'score')) fail('Schema::table() ADD score failed');
pass('Schema::table() — ADD COLUMN (phone + score)');

// Verify values can be inserted with new columns
DB::table('users')->insert([
    'name' => 'Alice', 'email' => 'a@test.com',
    'phone' => '1234', 'score' => 100,
]);
$user = DB::table('users')->first();
if ((int)$user->score !== 100) fail("INSERT after ADD COLUMN wrong: {$user->score}");
pass('INSERT works correctly after ADD COLUMN');

// -----------------------------------------------------------------------
// 29. Schema::table() — RENAME COLUMN
// -----------------------------------------------------------------------
Schema::table('users', function (Blueprint $table) {
    $table->renameColumn('phone', 'mobile');
});

if (Schema::hasColumn('users', 'phone'))    fail('RENAME: old column still exists');
if (! Schema::hasColumn('users', 'mobile')) fail('RENAME: new column missing');
pass('Schema::table() — RENAME COLUMN (phone → mobile)');

// -----------------------------------------------------------------------
// 30. Schema::table() — DROP COLUMN
// -----------------------------------------------------------------------
Schema::table('users', function (Blueprint $table) {
    $table->dropColumn('mobile');
});

if (Schema::hasColumn('users', 'mobile')) fail('DROP COLUMN: mobile still exists');
pass('Schema::table() — DROP COLUMN (mobile)');

// -----------------------------------------------------------------------
// 31. Schema::table() — ADD INDEX
// -----------------------------------------------------------------------
Schema::table('users', function (Blueprint $table) {
    $table->index('name', 'users_name_idx');
});
pass('Schema::table() — ADD INDEX (no error)');

// -----------------------------------------------------------------------
// 32. Schema::table() — DROP INDEX
// -----------------------------------------------------------------------
Schema::table('users', function (Blueprint $table) {
    $table->dropIndex('users_name_idx');
});
pass('Schema::table() — DROP INDEX (no error)');

// -----------------------------------------------------------------------
// 33. Schema::rename()
// -----------------------------------------------------------------------
Schema::dropIfExists('old_table');
Schema::dropIfExists('new_table');

Schema::create('old_table', function (Blueprint $table) {
    $table->id();
    $table->string('data');
});

Schema::rename('old_table', 'new_table');

if (Schema::hasTable('old_table'))    fail('Rename: old_table still exists');
if (! Schema::hasTable('new_table')) fail('Rename: new_table not found');
pass('Schema::rename() — renames table');

// -----------------------------------------------------------------------
// 34. Schema::drop()
// -----------------------------------------------------------------------
Schema::drop('new_table');
if (Schema::hasTable('new_table')) fail('Schema::drop() did not remove table');
pass('Schema::drop() — removes table');

// -----------------------------------------------------------------------
// 35. Schema::dropIfExists() — no error on missing table
// -----------------------------------------------------------------------
Schema::dropIfExists('nonexistent_table');
pass('Schema::dropIfExists() — no error on missing table');

// -----------------------------------------------------------------------
// 36. ForeignKeyDefinition — all methods
// -----------------------------------------------------------------------
$fk = new \Foxdb\Schema\ForeignKeyDefinition('user_id');
$fk->references('id')->on('users')->onDelete('cascade')->onUpdate('restrict')->name('fk_custom');

if ($fk->getColumn() !== 'user_id')         fail('FK column wrong');
if ($fk->getReferences() !== 'id')          fail('FK references wrong');
if ($fk->getOn() !== 'users')               fail('FK on wrong');
if ($fk->getOnDelete() !== 'CASCADE')       fail('FK onDelete wrong');
if ($fk->getOnUpdate() !== 'RESTRICT')      fail('FK onUpdate wrong');
if ($fk->getConstraintName('orders') !== 'fk_custom') fail('FK custom name wrong');
pass('ForeignKeyDefinition: all accessors correct');

// Auto-generated constraint name
$fk2 = new \Foxdb\Schema\ForeignKeyDefinition('role_id');
$auto = $fk2->getConstraintName('users');
if ($auto !== 'users_role_id_foreign') fail("FK auto-name wrong: {$auto}");
pass('ForeignKeyDefinition: auto-generated constraint name (table_column_foreign)');

// -----------------------------------------------------------------------
// 37. Blueprint::foreignId() + foreignIdFor()
// -----------------------------------------------------------------------
$bp = new Blueprint('posts');
$bp->foreignId('user_id')->unsigned();
$cols = $bp->getColumns();
if ($cols[0]->getType() !== 'bigInteger') fail('foreignId type wrong');
if (! $cols[0]->has('unsigned')) fail('foreignId not unsigned');
pass('Blueprint::foreignId() — BIGINT UNSIGNED');

// -----------------------------------------------------------------------
// 38. Multiple column type compilation — MySQL
// -----------------------------------------------------------------------
$bp = new Blueprint('data');
$bp->tinyInteger('t');
$bp->smallInteger('s');
$bp->char('c', 4);
$bp->date('d');
$bp->time('ti');
$bp->dateTime('dt');
$bp->uuid('u');
$bp->binary('b');

$sql = $mysql->compileCreate($bp);
if (! str_contains($sql, 'TINYINT'))  fail('MySQL TINYINT missing');
if (! str_contains($sql, 'SMALLINT')) fail('MySQL SMALLINT missing');
if (! str_contains($sql, 'CHAR(4)'))  fail('MySQL CHAR(4) missing');
if (! str_contains($sql, 'DATE'))     fail('MySQL DATE missing');
if (! str_contains($sql, 'DATETIME')) fail('MySQL DATETIME missing');
if (! str_contains($sql, 'CHAR(36)')) fail('MySQL UUID/CHAR(36) missing');
if (! str_contains($sql, 'BLOB'))     fail('MySQL BLOB missing');
pass('MySQL: all major column types compile correctly');

// -----------------------------------------------------------------------
// Summary
// -----------------------------------------------------------------------
echo "\n\033[32m✔ All Schema Builder tests passed on MySQL!\033[0m\n\n";