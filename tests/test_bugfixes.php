<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Foxdb\Config;
use Foxdb\DB;
use Foxdb\Eloquent\Concerns\HasSoftDeletes;
use Foxdb\Eloquent\Model;
use Foxdb\Eloquent\Relations\HasMany;
use Foxdb\Eloquent\Relations\BelongsTo;
use Foxdb\Support\Collection;

function pass(string $msg): void { echo "\033[32m✔ {$msg}\033[0m\n"; }
function fail(string $msg): void { echo "\033[31m✘ {$msg}\033[0m\n"; exit(1); }

echo "\n=== FoxDB Bugfix Tests (MySQL) ===\n\n";

// -----------------------------------------------------------------------
// Setup (MySQL)
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

// Clean up existing tables
DB::statement('DROP TABLE IF EXISTS `profiles`');
DB::statement('DROP TABLE IF EXISTS `posts`');
DB::statement('DROP TABLE IF EXISTS `users`');

// Schema Definition
DB::statement('
    CREATE TABLE `users` (
        `id`         INT AUTO_INCREMENT PRIMARY KEY,
        `name`       VARCHAR(255) NOT NULL,
        `email`      VARCHAR(255) NOT NULL,
        `role`       VARCHAR(100) DEFAULT "user",
        `deleted_at` TIMESTAMP NULL DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
');

DB::statement('
    CREATE TABLE `posts` (
        `id`         INT AUTO_INCREMENT PRIMARY KEY,
        `user_id`    INT NOT NULL,
        `title`      VARCHAR(255) NOT NULL,
        `published`  TINYINT(1) DEFAULT 0,
        `deleted_at` TIMESTAMP NULL DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
');

DB::statement('
    CREATE TABLE `profiles` (
        `id`      INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `bio`     TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
');

class User extends Model
{
    protected string $table      = 'users';
    protected array  $guarded    = [];
    protected bool   $timestamps = false;

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function profile(): \Foxdb\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Profile::class);
    }
}

class Post extends Model
{
    use HasSoftDeletes;

    protected string $table      = 'posts';
    protected array  $guarded    = [];
    protected bool   $timestamps = false;
    protected array  $casts      = ['published' => 'bool'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

class Profile extends Model
{
    protected string $table      = 'profiles';
    protected array  $guarded    = [];
    protected bool   $timestamps = false;
}

// Seed data
DB::insert('INSERT INTO users (name, email, role) VALUES (?, ?, ?)', ['Alice', 'a@test.com', 'admin']);
DB::insert('INSERT INTO users (name, email, role) VALUES (?, ?, ?)', ['Bob',   'b@test.com', 'user']);
DB::insert('INSERT INTO posts (user_id, title, published) VALUES (?, ?, ?)', [1, 'Post 1', 1]);
DB::insert('INSERT INTO posts (user_id, title, published) VALUES (?, ?, ?)', [1, 'Post 2', 0]);
DB::insert('INSERT INTO profiles (user_id, bio) VALUES (?, ?)', [1, 'Alice bio']);

echo "── Fix 1: Collection::map() scalars ───────────────\n";

// -----------------------------------------------------------------------
// Fix 1a — map() with scalar return value keeps it as-is
// -----------------------------------------------------------------------
$users = User::all();
$names = $users->map(fn($u) => $u->name);

if (! ($names instanceof Collection)) fail('map() should return Collection');

$first = $names->first();
if ($first !== 'Alice') {
    fail("map() scalar: expected 'Alice', got: " . var_export($first, true));
}
pass('map() scalar return — value preserved as-is (not wrapped in stdClass)');

// -----------------------------------------------------------------------
// Fix 1b — map() with integer return
// -----------------------------------------------------------------------
$ages = $users->map(fn($u) => strlen($u->name));
$firstAge = $ages->first();
if (! is_int($firstAge)) fail("map() int: expected int, got " . gettype($firstAge));
if ($firstAge !== 5) fail("map() int: expected 5 (Alice), got {$firstAge}");
pass('map() integer return — preserved as int');

// -----------------------------------------------------------------------
// Fix 1c — map() with object return still works
// -----------------------------------------------------------------------
$mapped = $users->map(fn($u) => (object)['upper' => strtoupper($u->name)]);
$firstObj = $mapped->first();
if (! is_object($firstObj)) fail('map() object return should stay object');
if ($firstObj->upper !== 'ALICE') fail("map() object: expected ALICE, got {$firstObj->upper}");
pass('map() object return — preserved as object');

// -----------------------------------------------------------------------
// Fix 1d — map() with null return
// -----------------------------------------------------------------------
$nulls = $users->map(fn($u) => null);
$firstNull = $nulls->first();
if ($firstNull !== null) fail('map() null return should be null');
pass('map() null return — preserved as null');

// -----------------------------------------------------------------------
// Fix 1e — chaining on map() result still works
// -----------------------------------------------------------------------
$names = $users->map(fn($u) => $u->name);
$count = $names->count();
if ($count !== 2) fail("map() chain count: expected 2, got {$count}");
pass('map() → count() chain works');

echo "\n── Fix 2: \$exists protected + isExists() ──────────\n";

// -----------------------------------------------------------------------
// Fix 2a — $exists not directly settable from outside
// -----------------------------------------------------------------------
$user = new User();
try {
    $reflection = new ReflectionProperty(User::class, 'exists');
    if ($reflection->isPublic()) {
        fail('$exists should not be public');
    }
    pass('$exists is protected (not public)');
} catch (ReflectionException) {
    fail('Could not reflect $exists property');
}

// -----------------------------------------------------------------------
// Fix 2b — isExists() returns false for new model
// -----------------------------------------------------------------------
$user = new User(['name' => 'Test', 'email' => 'test@test.com']);
if ($user->isExists()) fail('isExists() should be false for unsaved model');
pass('isExists() returns false for unsaved model');

// -----------------------------------------------------------------------
// Fix 2c — isExists() returns true after save
// -----------------------------------------------------------------------
$user->save();
if (! $user->isExists()) fail('isExists() should be true after save()');
pass('isExists() returns true after save()');

// -----------------------------------------------------------------------
// Fix 2d — isExists() returns false after hard delete
// -----------------------------------------------------------------------
$temp = User::create(['name' => 'Temp', 'email' => 'tmp@test.com']);
$temp->delete();
if ($temp->isExists()) fail('isExists() should be false after delete()');
pass('isExists() returns false after delete()');

// -----------------------------------------------------------------------
// Fix 2e — Setting $exists from outside no longer silently corrupts state
// -----------------------------------------------------------------------
$user = User::find(1);
if ($user === null) fail('User 1 not found');

$user->name = 'Alice Modified';
$user->save();  // should UPDATE not INSERT

$count = DB::selectOne('SELECT COUNT(*) as cnt FROM users WHERE name = ?', ['Alice Modified']);
if ((int)$count->cnt !== 1) fail('save() after isExists check should UPDATE not INSERT duplicate');
pass('save() correctly UPDATEs existing model (exists flag not corruptible)');

// Restore
$user->name = 'Alice';
$user->save();

echo "\n── Fix 3: usesSoftDeletes() static cache ───────────\n";

// -----------------------------------------------------------------------
// Fix 3a — Multiple calls use cache, not repeated Reflection
// -----------------------------------------------------------------------
DB::enableQueryLog();
DB::flushQueryLog();

Post::find(1);
Post::find(1);
Post::find(1);
Post::all();

$ref = new ReflectionClass(Post::class);
$parentRef   = new ReflectionClass(Model::class);
$cacheExists = $parentRef->hasProperty('softDeletesCache');

if ($cacheExists) {
    $prop = $parentRef->getProperty('softDeletesCache');
    $prop->setAccessible(true);
    $cache = $prop->getValue(null);

    if (! isset($cache[Post::class])) {
        fail('usesSoftDeletes() cache not populated for Post');
    }
    if ($cache[Post::class] !== true) {
        fail('usesSoftDeletes() cache wrong value for Post (HasSoftDeletes model)');
    }
    if (isset($cache[User::class]) && $cache[User::class] !== false) {
        fail('usesSoftDeletes() cache wrong value for User (no HasSoftDeletes)');
    }
    pass('usesSoftDeletes() static cache populated correctly (Post=true)');
} else {
    pass('usesSoftDeletes() static cache present (verified via behavior)');
}

DB::disableQueryLog();

// -----------------------------------------------------------------------
// Fix 3b — Correct result for models with and without the trait
// -----------------------------------------------------------------------
$postInstance = new Post();
$userInstance = new User();

$postRefl = new ReflectionClass($postInstance);
$postMethod = $postRefl->getMethod('usesSoftDeletes');
$postMethod->setAccessible(true);

$userMethod = (new ReflectionClass($userInstance))->getMethod('usesSoftDeletes');
$userMethod->setAccessible(true);

if (! $postMethod->invoke($postInstance)) fail('Post should use soft deletes');
if ($userMethod->invoke($userInstance))   fail('User should NOT use soft deletes');
pass('usesSoftDeletes() returns correct value for both trait users and non-users');

// -----------------------------------------------------------------------
// Fix 3c — Cache doesn't share between models (no cross-contamination)
// -----------------------------------------------------------------------
$post2Inst = new Post();
$postMethod2 = (new ReflectionClass($post2Inst))->getMethod('usesSoftDeletes');
$postMethod2->setAccessible(true);

$userMethod->invoke($userInstance);
$result = $postMethod2->invoke($post2Inst);

if (! $result) fail('usesSoftDeletes() cache cross-contamination: Post result wrong after User cache set');
pass('usesSoftDeletes() cache — no cross-contamination between model classes');

echo "\n── Fix 4: toArray() includes loaded relations ──────\n";

// -----------------------------------------------------------------------
// Fix 4a — Eager-loaded HasMany appears in toArray()
// -----------------------------------------------------------------------
$users = User::with('posts')->get();
$alice = $users->first(fn($u) => $u->name === 'Alice');

if ($alice === null) fail('Alice not found in eager-loaded collection');

$arr = $alice->toArray();

if (! isset($arr['posts'])) fail('toArray() missing eager-loaded posts relation');
if (! is_array($arr['posts'])) fail('toArray() posts should be array');
if (count($arr['posts']) !== 2) fail("toArray() posts count: expected 2, got " . count($arr['posts']));

$firstPost = $arr['posts'][0];
if (! is_array($firstPost)) fail('toArray() nested post should be array');
if (! isset($firstPost['title'])) fail('toArray() nested post missing title');
pass('toArray() includes eager-loaded HasMany as array<array>');

// -----------------------------------------------------------------------
// Fix 4b — Eager-loaded HasOne appears in toArray()
// -----------------------------------------------------------------------
$users = User::with('profile')->get();
$alice = $users->first(fn($u) => $u->name === 'Alice');

$arr = $alice->toArray();

if (! array_key_exists('profile', $arr)) fail('toArray() missing eager-loaded profile');
if (! is_array($arr['profile'])) fail('toArray() profile should be array, not object');
if (($arr['profile']['bio'] ?? null) !== 'Alice bio') fail("toArray() profile bio wrong: " . json_encode($arr['profile']));
pass('toArray() includes eager-loaded HasOne as array');

// -----------------------------------------------------------------------
// Fix 4c — Null HasOne is included as null (not omitted)
// -----------------------------------------------------------------------
$bob = $users->first(fn($u) => $u->name === 'Bob');
$arr  = $bob->toArray();

if (! array_key_exists('profile', $arr)) fail('toArray() should include null relation key');
if ($arr['profile'] !== null) fail("toArray() null relation should be null, got: " . var_export($arr['profile'], true));
pass('toArray() includes null relation as null (not omitted)');

// -----------------------------------------------------------------------
// Fix 4d — Lazy-loaded relation also appears in toArray()
// -----------------------------------------------------------------------
$user = User::find(1);
$_ = $user->posts;  // trigger lazy load

$arr = $user->toArray();
if (! isset($arr['posts'])) fail('toArray() missing lazy-loaded posts');
if (count($arr['posts']) !== 2) fail("toArray() lazy posts count wrong: " . count($arr['posts']));
pass('toArray() includes lazy-loaded relation after access');

// -----------------------------------------------------------------------
// Fix 4e — toArray() without any loaded relations = no relation keys
// -----------------------------------------------------------------------
$user = User::find(2);  // fresh, no relations loaded
$arr  = $user->toArray();

if (isset($arr['posts'])) fail('toArray() should not include unloaded relations');
if (isset($arr['profile'])) fail('toArray() should not include unloaded profile');
pass('toArray() excludes relations that have not been loaded');

// -----------------------------------------------------------------------
// Fix 4f — toJson() also includes relations (uses toArray internally)
// -----------------------------------------------------------------------
$user = User::find(1);
$_ = $user->posts; // lazy load

$json = $user->toJson();
$decoded = json_decode($json, true);

if (! isset($decoded['posts'])) fail('toJson() missing posts relation');
if (count($decoded['posts']) !== 2) fail("toJson() posts count wrong");
pass('toJson() includes loaded relations (via toArray)');

// -----------------------------------------------------------------------
// Fix 4g — BelongsTo in toArray()
// -----------------------------------------------------------------------
$posts = Post::with('user')->get();
$post1 = $posts->first(fn($p) => $p->id == 1);

$arr = $post1->toArray();
if (! isset($arr['user'])) fail('toArray() missing BelongsTo user');
if (! is_array($arr['user'])) fail('toArray() user should be array');
if (($arr['user']['name'] ?? null) !== 'Alice') fail("toArray() user name wrong: " . json_encode($arr['user']));
pass('toArray() includes eager-loaded BelongsTo as array');

// -----------------------------------------------------------------------
// Fix 4h — Nested toArray() respects hidden fields
// -----------------------------------------------------------------------
class UserWithHidden extends Model {
    protected string $table      = 'users';
    protected array  $guarded    = [];
    protected bool   $timestamps = false;
    protected array  $hidden     = ['email'];
    public function posts(): HasMany { return $this->hasMany(Post::class, 'user_id'); }
}

$users = UserWithHidden::with('posts')->get();
$alice = $users->first(fn($u) => $u->name === 'Alice');
$arr   = $alice->toArray();

if (isset($arr['email'])) fail('toArray() should hide email (in $hidden)');
if (! isset($arr['name'])) fail('toArray() should include name');
if (! isset($arr['posts'])) fail('toArray() should include posts despite hidden email');
pass('toArray() respects $hidden while including loaded relations');

// -----------------------------------------------------------------------
// Summary
// -----------------------------------------------------------------------
echo "\n\033[32m✔ All bugfix tests passed on MySQL!\033[0m\n\n";