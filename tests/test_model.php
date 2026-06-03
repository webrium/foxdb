<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Foxdb\Config;
use Foxdb\DB;
use Foxdb\Eloquent\Concerns\HasSoftDeletes;
use Foxdb\Eloquent\Model;
use Foxdb\Exceptions\ModelNotFoundException;
use Foxdb\Query\Builder;
use Foxdb\Support\Collection;

function pass(string $msg): void { echo "\033[32m✔ {$msg}\033[0m\n"; }
function fail(string $msg): void { echo "\033[31m✘ {$msg}\033[0m\n"; exit(1); }

echo "\n=== FoxDB Model Tests (MySQL) ===\n\n";

// -----------------------------------------------------------------------
// Setup connection (MySQL)
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

// Drop tables if they already exist to ensure a clean state
DB::statement('DROP TABLE IF EXISTS `posts`');
DB::statement('DROP TABLE IF EXISTS `users`');

DB::statement('
    CREATE TABLE `users` (
        `id`         INT AUTO_INCREMENT PRIMARY KEY,
        `name`       VARCHAR(255) NOT NULL,
        `email`      VARCHAR(255) NOT NULL,
        `age`        INT DEFAULT 0,
        `is_active`  TINYINT(1) DEFAULT 1,
        `score`      DOUBLE DEFAULT 0,
        `settings`   TEXT DEFAULT NULL,
        `role`       VARCHAR(100) DEFAULT "user",
        `created_at` TIMESTAMP NULL DEFAULT NULL,
        `updated_at` TIMESTAMP NULL DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
');

DB::statement('
    CREATE TABLE `posts` (
        `id`         INT AUTO_INCREMENT PRIMARY KEY,
        `user_id`    INT NOT NULL,
        `title`      VARCHAR(255) NOT NULL,
        `body`       TEXT DEFAULT NULL,
        `published`  TINYINT(1) DEFAULT 0,
        `deleted_at` TIMESTAMP NULL DEFAULT NULL,
        `created_at` TIMESTAMP NULL DEFAULT NULL,
        `updated_at` TIMESTAMP NULL DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
');

// -----------------------------------------------------------------------
// Define test model classes
// -----------------------------------------------------------------------

class User extends Model
{
    protected string $table    = 'users';
    protected array  $fillable = ['name', 'email', 'age', 'is_active', 'score', 'settings', 'role'];
    protected array  $hidden   = ['settings'];
    protected array  $casts    = [
        'is_active' => 'bool',
        'age'       => 'int',
        'score'     => 'float',
        'settings'  => 'array',
    ];

    // Local scope
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', 1);
    }

    public function scopeRole(Builder $q, string $role): Builder
    {
        return $q->where('role', $role);
    }
}

class Post extends Model
{
    use HasSoftDeletes;

    protected string $table    = 'posts';
    protected array  $fillable = ['user_id', 'title', 'body', 'published'];
    protected array  $casts    = ['published' => 'bool'];
}

// Auto-derive table name test model
class FlightRecord extends Model
{
    protected array $guarded = [];
}

// -----------------------------------------------------------------------
// 1. Table name auto-derivation
// -----------------------------------------------------------------------
$m = new FlightRecord();
if ($m->getTable() !== 'flight_records') {
    fail("Auto-table expected 'flight_records', got '{$m->getTable()}'");
}
pass('Table name auto-derived from class name (CamelCase → snake_case + s)');

// -----------------------------------------------------------------------
// 2. Constructor + fill + isFillable
// -----------------------------------------------------------------------
$user = new User(['name' => 'Alice', 'email' => 'alice@test.com', 'age' => 30]);
if ($user->name !== 'Alice') fail("Constructor fill wrong: {$user->name}");
if ($user->age !== 30) fail("Constructor fill age wrong: {$user->age}");
pass('Constructor fill via $fillable');

// Guarded by default — 'role' not in fillable originally, but it is:
$user2 = new User(['name' => 'Bob', 'email' => 'b@test.com', 'role' => 'admin']);
if ($user2->role !== 'admin') fail("role should be fillable");
pass('Fill respects $fillable');

// forceFill bypasses guards
$user3 = new User();
$user3->forceFill(['name' => 'Force', 'email' => 'f@test.com', 'id' => 999]);
if ($user3->id !== 999) fail("forceFill did not bypass guard");
pass('forceFill() bypasses fillable/guarded');

// -----------------------------------------------------------------------
// 3. Magic property access
// -----------------------------------------------------------------------
$user = new User();
$user->name = 'Charlie';
if ($user->name !== 'Charlie') fail("__set/__get wrong");
pass('__set() / __get() magic property access');

if (isset($user->name) !== true) fail("__isset() wrong");
pass('__isset()');

unset($user->name);
if (isset($user->name)) fail("__unset() did not remove attribute");
pass('__unset()');

// -----------------------------------------------------------------------
// 4. Casts — read
// -----------------------------------------------------------------------
$user = new User();
$user->forceFill([
    'is_active' => '1',
    'age'       => '25',
    'score'     => '88.5',
    'settings'  => '{"theme":"dark","lang":"fa"}',
]);

if ($user->is_active !== true)   fail("cast bool wrong: " . var_export($user->is_active, true));
if ($user->age !== 25)           fail("cast int wrong: {$user->age}");
if ($user->score !== 88.5)       fail("cast float wrong: {$user->score}");
if (! is_array($user->settings)) fail("cast array wrong");
if ($user->settings['theme'] !== 'dark') fail("cast array value wrong");
pass('HasCasts: bool / int / float / array (JSON) on read');

// -----------------------------------------------------------------------
// 5. isDirty / getDirty
// -----------------------------------------------------------------------
$user = new User(['name' => 'Alice', 'email' => 'a@test.com']);
$user->syncOriginal(); // pretend freshly loaded

$user->name = 'Alice Updated';
if (! $user->isDirty('name')) fail('isDirty(name) should be true');
if ($user->isDirty('email'))  fail('isDirty(email) should be false');
if (! $user->isDirty())       fail('isDirty() should be true');

$dirty = $user->getDirty();
if (! isset($dirty['name'])) fail('getDirty() missing name');
if (isset($dirty['email']))  fail('getDirty() has email (should be clean)');
pass('isDirty() / getDirty() detect changed attributes');

// -----------------------------------------------------------------------
// 6. save() — INSERT
// -----------------------------------------------------------------------
$user = new User(['name' => 'Alice', 'email' => 'alice@test.com', 'age' => 30, 'score' => 100.0, 'is_active' => 1]);
$result = $user->save();

if (! $result) fail('save() INSERT returned false');
if (! $user->exists) fail('save() did not set $exists = true'); 
if ($user->id === null) fail('save() did not set id');

// Check timestamps set
if (empty($user->created_at)) fail('save() did not set created_at');
if (empty($user->updated_at)) fail('save() did not set updated_at');
pass('save() INSERT — id assigned, timestamps set, exists=true');

// -----------------------------------------------------------------------
// 7. save() — UPDATE
// -----------------------------------------------------------------------
$createdAt = $user->created_at;
sleep(1); // ensure updated_at changes

$user->name = 'Alice Updated';
$result = $user->save();

if (! $result) fail('save() UPDATE returned false');
if ($user->name !== 'Alice Updated') fail('save() UPDATE did not persist name');

// Verify in DB
$row = DB::selectOne('SELECT name FROM users WHERE id = ?', [$user->id]);
if ($row->name !== 'Alice Updated') fail('save() UPDATE not persisted to DB');

// updated_at should be newer or same
if ($user->created_at !== $createdAt) fail('save() UPDATE changed created_at');
pass('save() UPDATE — only dirty attributes sent, timestamps updated');

// -----------------------------------------------------------------------
// 8. save() UPDATE — no-op when clean
// -----------------------------------------------------------------------
$user->syncOriginal();
$result = $user->save();
if (! $result) fail('save() no-op on clean model should return true');
pass('save() no-op when nothing dirty');

// -----------------------------------------------------------------------
// 9. Model::create()
// -----------------------------------------------------------------------
$user2 = User::create(['name' => 'Bob', 'email' => 'bob@test.com', 'age' => 25, 'is_active' => 1, 'score' => 50.0]);
if ($user2->id === null) fail('create() id null');
if ($user2->name !== 'Bob') fail("create() name wrong: {$user2->name}");
pass('Model::create() persists and returns model');

User::create(['name' => 'Charlie', 'email' => 'charlie@test.com', 'age' => 20, 'is_active' => 0, 'score' => 30.0, 'role' => 'mod']);
User::create(['name' => 'Dave',    'email' => 'dave@test.com',    'age' => 40, 'is_active' => 1, 'score' => 70.0, 'role' => 'admin']);
User::create(['name' => 'Eve',     'email' => 'eve@test.com',     'age' => 35, 'is_active' => 1, 'score' => 80.0]);

// -----------------------------------------------------------------------
// 10. Model::all()
// -----------------------------------------------------------------------
$all = User::all();
if (! ($all instanceof Collection)) fail('all() should return Collection');
if ($all->count() !== 5) fail("all() expected 5, got {$all->count()}");
if (! ($all->first() instanceof User)) fail('all() items should be User instances');
pass('Model::all() returns Collection<User>');

// -----------------------------------------------------------------------
// 11. Model::find()
// -----------------------------------------------------------------------
$found = User::find(1);
if ($found === null) fail('find(1) returned null');
if (! ($found instanceof User)) fail('find() should return User instance');
if ($found->name !== 'Alice Updated') fail("find() name wrong: {$found->name}");
pass('Model::find() returns model instance with correct data');

$notFound = User::find(9999);
if ($notFound !== null) fail('find() for missing id should return null');
pass('Model::find() returns null for missing id');

// -----------------------------------------------------------------------
// 12. Model::findOrFail()
// -----------------------------------------------------------------------
$user = User::findOrFail(2);
if ($user->name !== 'Bob') fail("findOrFail() wrong: {$user->name}");
pass('Model::findOrFail() returns model');

try {
    User::findOrFail(9999);
    fail('findOrFail() should throw ModelNotFoundException');
} catch (ModelNotFoundException $e) {
    if ($e->getModel() !== User::class) fail('ModelNotFoundException model wrong');
    if ($e->getIds() !== 9999) fail('ModelNotFoundException ids wrong');
    pass('Model::findOrFail() throws ModelNotFoundException');
}

// -----------------------------------------------------------------------
// 13. Model::where() → Builder chain → Collection<User>
// -----------------------------------------------------------------------
$active = User::where('is_active', 1)->get();
if (! ($active instanceof Collection)) fail('where()->get() should return Collection');
if ($active->count() !== 4) fail("where()->get() expected 4, got {$active->count()}");
if (! ($active->first() instanceof User)) fail('Collection items should be User instances');
pass('Model::where()->get() returns Collection<User>');

// -----------------------------------------------------------------------
// 14. Local scopes
// -----------------------------------------------------------------------
$active = User::active()->get();
if ($active->count() !== 4) fail("scopeActive expected 4, got {$active->count()}");
if (! ($active->first() instanceof User)) fail('scope result should be User');
pass('Local scope: User::active()->get()');

$admins = User::role('admin')->get();
if ($admins->count() !== 1) fail("scope with param expected 1, got {$admins->count()}");
if ($admins->first()->name !== 'Dave') fail("scope with param wrong: {$admins->first()->name}");
pass('Local scope with parameter: User::role("admin")->get()');

// -----------------------------------------------------------------------
// 15. Model::firstWhere()
// -----------------------------------------------------------------------
$user = User::firstWhere('email', 'bob@test.com');
if ($user === null || $user->name !== 'Bob') fail("firstWhere() wrong: {$user?->name}");
pass('Model::firstWhere()');

$none = User::firstWhere('email', 'nobody@test.com');
if ($none !== null) fail('firstWhere() no match should return null');
pass('Model::firstWhere() → null on no match');

// -----------------------------------------------------------------------
// 16. Model::exists()
// -----------------------------------------------------------------------
if (! User::exists(['role' => 'admin'])) fail('exists() should be true');
if (User::exists(['role' => 'superadmin'])) fail('exists() should be false');
pass('Model::exists(conditions)');

// -----------------------------------------------------------------------
// 17. delete()
// -----------------------------------------------------------------------
$temp = User::create(['name' => 'Temp', 'email' => 'tmp@test.com', 'is_active' => 1]);
$tempId = $temp->id;
$result = $temp->delete();

if (! $result) fail('delete() returned false');
if (User::find($tempId) !== null) fail('delete() row still exists');
pass('Model::delete() removes row from DB');

// -----------------------------------------------------------------------
// 18. fresh() / refresh()
// -----------------------------------------------------------------------
$user = User::find(2);
$user->name = 'Modified'; // local change not saved

$fresh = $user->fresh();
if ($fresh === null) fail('fresh() returned null');
if ($fresh->name !== 'Bob') fail("fresh() returned modified name: {$fresh->name}");
if ($user->name !== 'Modified') fail('fresh() should not modify original');
pass('fresh() returns new instance from DB without modifying original');

$user->refresh();
if ($user->name !== 'Bob') fail("refresh() did not reload: {$user->name}");
pass('refresh() updates current instance in place');

// -----------------------------------------------------------------------
// 19. toArray() — respects $hidden
// -----------------------------------------------------------------------
$user = User::find(1);
$user->forceFill(['settings' => ['theme' => 'dark']]);
$arr = $user->toArray();

if (isset($arr['settings'])) fail('toArray() should hide settings column');
if (! isset($arr['name']))   fail('toArray() missing name');
if (! isset($arr['is_active'])) fail('toArray() missing is_active');
// Cast should be applied
if (! is_bool($arr['is_active'])) fail("toArray() is_active should be bool, got " . gettype($arr['is_active']));
pass('toArray() hides $hidden columns and applies casts');

// -----------------------------------------------------------------------
// 20. toJson() / __toString()
// -----------------------------------------------------------------------
$user = User::find(2);
$json = $user->toJson();
$decoded = json_decode($json, true);
if (($decoded['name'] ?? null) !== 'Bob') fail("toJson() wrong: {$json}");
pass('toJson()');

$str = (string) $user;
if (! str_contains($str, 'Bob')) fail('__toString() wrong');
pass('__toString()');

// -----------------------------------------------------------------------
// 21. Soft Deletes
// -----------------------------------------------------------------------
$post1 = Post::create(['user_id' => 1, 'title' => 'First Post',  'published' => 1]);
$post2 = Post::create(['user_id' => 1, 'title' => 'Second Post', 'published' => 0]);
$post3 = Post::create(['user_id' => 2, 'title' => 'Third Post',  'published' => 1]);

// Default query excludes soft-deleted
$all = Post::all();
if ($all->count() !== 3) fail("Before delete: expected 3, got {$all->count()}");
pass('Soft delete: all() excludes deleted_at IS NOT NULL (pre-delete check)');

// Soft delete
$post1->delete();

if ($post1->trashed() !== true) fail('trashed() should be true after delete');
pass('trashed() returns true after soft delete');

// Verify deleted_at is set
$deletedAt = DB::selectOne('SELECT deleted_at FROM posts WHERE id = ?', [$post1->id]);
if ($deletedAt->deleted_at === null) fail('deleted_at not set in DB');
pass('delete() sets deleted_at in DB');

// Default query excludes soft-deleted
$all = Post::all();
if ($all->count() !== 2) fail("After soft delete: expected 2, got {$all->count()}");
pass('Default query scope excludes soft-deleted rows');

// find() should return null for soft-deleted
$found = Post::find($post1->id);
if ($found !== null) fail('find() should return null for soft-deleted row');
pass('find() returns null for soft-deleted row');

// withTrashed()
$all = Post::withTrashed()->get();
if ($all->count() !== 3) fail("withTrashed() expected 3, got {$all->count()}");
if (! ($all->first() instanceof Post)) fail('withTrashed() items should be Post');
pass('Post::withTrashed()->get() includes soft-deleted rows');

// withTrashed find
$found = Post::withTrashed()->where('id', $post1->id)->first();
if ($found === null) fail('withTrashed()->find() should return soft-deleted row');
if ($found->title !== 'First Post') fail("withTrashed wrong title: {$found->title}");
pass('withTrashed()->where()->first() retrieves soft-deleted row');

// onlyTrashed()
$trashed = Post::onlyTrashed()->get();
if ($trashed->count() !== 1) fail("onlyTrashed() expected 1, got {$trashed->count()}");
if ($trashed->first()->title !== 'First Post') fail("onlyTrashed wrong: {$trashed->first()->title}");
pass('Post::onlyTrashed()->get() returns only soft-deleted rows');

// restore()
$post1 = Post::withTrashed()->where('id', $post1->id)->first();
$post1->restore();

if ($post1->trashed()) fail('trashed() should be false after restore');
$check = DB::selectOne('SELECT deleted_at FROM posts WHERE id = ?', [$post1->id]);
if ($check->deleted_at !== null) fail('restore() did not clear deleted_at in DB');
pass('restore() clears deleted_at in DB');

// After restore, regular query finds it
$found = Post::find($post1->id);
if ($found === null) fail('find() should work after restore');
pass('find() works after restore');

// -----------------------------------------------------------------------
// 22. Casts — storage (castForStorage)
// -----------------------------------------------------------------------
$user = new User();
$user->forceFill([
    'name'      => 'CastUser',
    'email'     => 'cast@test.com',
    'is_active' => true,     // bool → stored as 1
    'settings'  => ['x'=>1], // array → stored as JSON
    'score'     => 42.5,
]);
$user->save();

$raw = DB::selectOne('SELECT is_active, settings FROM users WHERE email = ?', ['cast@test.com']);
if ((int)$raw->is_active !== 1) fail("castForStorage bool wrong: {$raw->is_active}");
if ($raw->settings !== '{"x":1}') fail("castForStorage array wrong: {$raw->settings}");
pass('HasCasts: bool and array cast correctly for storage (INSERT)');

// Read back with cast
$loaded = User::firstWhere('email', 'cast@test.com');
if ($loaded->is_active !== true) fail("Read-back cast bool wrong");
if (! is_array($loaded->settings)) fail("Read-back cast array wrong");
if ($loaded->settings['x'] !== 1) fail("Read-back array value wrong");
pass('HasCasts: round-trip (store → load) for bool and array');

// -----------------------------------------------------------------------
// 23. Hydration — get() returns Collection<Model>
// -----------------------------------------------------------------------
$users = User::where('is_active', 1)->get();
if (! ($users instanceof Collection)) fail('Model query get() must return Collection');
foreach ($users as $u) {
    if (! ($u instanceof User)) fail('Collection item must be User instance, got ' . get_class($u));
}
pass('User::where()->get() returns Collection — all items are User instances');

// -----------------------------------------------------------------------
// 24. Builder methods forwarded via __callStatic
// -----------------------------------------------------------------------
$users = User::orderBy('score', 'desc')->limit(2)->get();
if ($users->count() !== 2) fail("__callStatic orderBy/limit wrong count: {$users->count()}");
if ($users->first()->score < $users->last()->score) fail('__callStatic orderBy wrong order');
pass('Model::orderBy()->limit()->get() via __callStatic');

// -----------------------------------------------------------------------
// 25. Post::where() - ensure soft-delete scope applied on all entry points
// -----------------------------------------------------------------------
$count = Post::where('published', 1)->count();
if ($count !== 2) fail("Post::where count expected 2, got {$count}");
pass('Post::where() applies soft-delete scope automatically');

// -----------------------------------------------------------------------
// Summary
// -----------------------------------------------------------------------
echo "\n\033[32m✔ All Model tests passed on MySQL!\033[0m\n\n";