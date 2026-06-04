<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Foxdb\Config;
use Foxdb\DB;
use Foxdb\Eloquent\Concerns\HasRelations;
use Foxdb\Eloquent\Model;
use Foxdb\Eloquent\Relations\BelongsTo;
use Foxdb\Eloquent\Relations\BelongsToMany;
use Foxdb\Eloquent\Relations\HasMany;
use Foxdb\Eloquent\Relations\HasManyThrough;
use Foxdb\Eloquent\Relations\HasOne;
use Foxdb\Support\Collection;

function pass(string $msg): void { echo "\033[32m✔ {$msg}\033[0m\n"; }
function fail(string $msg): void { echo "\033[31m✘ {$msg}\033[0m\n"; exit(1); }

echo "\n=== FoxDB Relations Tests (MySQL) ===\n\n";

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
DB::statement('DROP TABLE IF EXISTS `role_user`');
DB::statement('DROP TABLE IF EXISTS `post_tag`');
DB::statement('DROP TABLE IF EXISTS `roles`');
DB::statement('DROP TABLE IF EXISTS `tags`');
DB::statement('DROP TABLE IF EXISTS `comments`');
DB::statement('DROP TABLE IF EXISTS `posts`');
DB::statement('DROP TABLE IF EXISTS `profiles`');
DB::statement('DROP TABLE IF EXISTS `users`');
DB::statement('DROP TABLE IF EXISTS `countries`');

// Schema Definition
DB::statement('CREATE TABLE `countries` (`id` INT AUTO_INCREMENT PRIMARY KEY, `name` VARCHAR(255) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
DB::statement('CREATE TABLE `users`    (`id` INT AUTO_INCREMENT PRIMARY KEY, `country_id` INT NOT NULL, `name` VARCHAR(255) NOT NULL, `email` VARCHAR(255) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
DB::statement('CREATE TABLE `profiles` (`id` INT AUTO_INCREMENT PRIMARY KEY, `user_id` INT NOT NULL, `bio` TEXT, `avatar` VARCHAR(255)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
DB::statement('CREATE TABLE `posts`    (`id` INT AUTO_INCREMENT PRIMARY KEY, `user_id` INT NOT NULL, `title` VARCHAR(255) NOT NULL, `published` TINYINT(1) DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
DB::statement('CREATE TABLE `comments` (`id` INT AUTO_INCREMENT PRIMARY KEY, `post_id` INT NOT NULL, `user_id` INT NOT NULL, `body` TEXT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
DB::statement('CREATE TABLE `tags`     (`id` INT AUTO_INCREMENT PRIMARY KEY, `name` VARCHAR(100) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
DB::statement('CREATE TABLE `post_tag` (`post_id` INT NOT NULL, `tag_id` INT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
DB::statement('CREATE TABLE `roles`    (`id` INT AUTO_INCREMENT PRIMARY KEY, `name` VARCHAR(100) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
DB::statement('CREATE TABLE `role_user`(`user_id` INT NOT NULL, `role_id` INT NOT NULL, `assigned_at` VARCHAR(50)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');

// Seed data
DB::insert('INSERT INTO countries (id, name) VALUES (1, ?)', ['Iran']);
DB::insert('INSERT INTO countries (id, name) VALUES (2, ?)', ['Germany']);

DB::insert('INSERT INTO users (id, country_id, name, email) VALUES (1, 1, ?, ?)', ['Alice', 'alice@test.com']);
DB::insert('INSERT INTO users (id, country_id, name, email) VALUES (2, 1, ?, ?)', ['Bob',   'bob@test.com']);
DB::insert('INSERT INTO users (id, country_id, name, email) VALUES (3, 2, ?, ?)', ['Clara', 'clara@test.com']);

DB::insert('INSERT INTO profiles (id, user_id, bio, avatar) VALUES (1, 1, ?, ?)', ['Alice bio', 'alice.jpg']);
DB::insert('INSERT INTO profiles (id, user_id, bio, avatar) VALUES (2, 2, ?, ?)', ['Bob bio',   'bob.jpg']);

DB::insert('INSERT INTO posts (id, user_id, title, published) VALUES (1, 1, ?, 1)', ['Alice Post 1']);
DB::insert('INSERT INTO posts (id, user_id, title, published) VALUES (2, 1, ?, 1)', ['Alice Post 2']);
DB::insert('INSERT INTO posts (id, user_id, title, published) VALUES (3, 1, ?, 0)', ['Alice Draft']);
DB::insert('INSERT INTO posts (id, user_id, title, published) VALUES (4, 2, ?, 1)', ['Bob Post 1']);

DB::insert('INSERT INTO comments (id, post_id, user_id, body) VALUES (1, 1, 2, ?)', ['Nice post!']);
DB::insert('INSERT INTO comments (id, post_id, user_id, body) VALUES (2, 1, 3, ?)', ['Agreed!']);
DB::insert('INSERT INTO comments (id, post_id, user_id, body) VALUES (3, 2, 2, ?)', ['Great!']);
DB::insert('INSERT INTO comments (id, post_id, user_id, body) VALUES (4, 4, 1, ?)', ['Thanks!']);

DB::insert('INSERT INTO tags (id, name) VALUES (1, ?)', ['php']);
DB::insert('INSERT INTO tags (id, name) VALUES (2, ?)', ['mysql']);
DB::insert('INSERT INTO tags (id, name) VALUES (3, ?)', ['redis']);

DB::insert('INSERT INTO post_tag (post_id, tag_id) VALUES (1, 1)');
DB::insert('INSERT INTO post_tag (post_id, tag_id) VALUES (1, 2)');
DB::insert('INSERT INTO post_tag (post_id, tag_id) VALUES (2, 1)');
DB::insert('INSERT INTO post_tag (post_id, tag_id) VALUES (4, 3)');

DB::insert('INSERT INTO roles (id, name) VALUES (1, ?)', ['admin']);
DB::insert('INSERT INTO roles (id, name) VALUES (2, ?)', ['editor']);
DB::insert('INSERT INTO roles (id, name) VALUES (3, ?)', ['viewer']);

DB::insert('INSERT INTO role_user (user_id, role_id, assigned_at) VALUES (1, 1, ?)', ['2024-01-01']);
DB::insert('INSERT INTO role_user (user_id, role_id, assigned_at) VALUES (1, 2, ?)', ['2024-01-02']);
DB::insert('INSERT INTO role_user (user_id, role_id, assigned_at) VALUES (2, 3, ?)', ['2024-01-03']);

// -----------------------------------------------------------------------
// Model definitions
// -----------------------------------------------------------------------
class Country extends Model
{
    protected string $table      = 'countries';
    protected array  $guarded    = [];
    protected bool   $timestamps = false;

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'country_id');
    }

    public function posts(): HasManyThrough
    {
        return $this->hasManyThrough(Post::class, User::class, 'country_id', 'user_id');
    }
}

class User extends Model
{
    protected string $table      = 'users';
    protected array  $guarded    = [];
    protected bool   $timestamps = false;

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id');
    }
}

class Profile extends Model
{
    protected string $table      = 'profiles';
    protected array  $guarded    = [];
    protected bool   $timestamps = false;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

class Post extends Model
{
    protected string $table      = 'posts';
    protected array  $guarded    = [];
    protected bool   $timestamps = false;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'post_tag', 'post_id', 'tag_id');
    }
}

class Comment extends Model
{
    protected string $table      = 'comments';
    protected array  $guarded    = [];
    protected bool   $timestamps = false;

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}

class Tag extends Model
{
    protected string $table      = 'tags';
    protected array  $guarded    = [];
    protected bool   $timestamps = false;
}

class Role extends Model
{
    protected string $table      = 'roles';
    protected array  $guarded    = [];
    protected bool   $timestamps = false;
}

echo "── HasOne ──────────────────────────────────────────\n";

// -----------------------------------------------------------------------
// 1. HasOne — lazy load
// -----------------------------------------------------------------------
$user    = User::find(1);
$profile = $user->profile;

if (! ($profile instanceof Profile)) fail("HasOne lazy: expected Profile, got " . gettype($profile));
if ($profile->bio !== 'Alice bio')   fail("HasOne lazy wrong bio: {$profile->bio}");
pass('HasOne — lazy load ($user->profile)');

// Cached — second access should not re-query
$profile2 = $user->profile;
if ($profile !== $profile2) fail('HasOne lazy: should return cached result');
pass('HasOne — result cached on second access');

// User with no profile
$user3   = User::find(3);
$profile = $user3->profile;
if ($profile !== null) fail('HasOne — no profile should return null');
pass('HasOne — returns null when no related row');

// -----------------------------------------------------------------------
// 2. HasOne — eager load
// -----------------------------------------------------------------------
$users = User::with('profile')->get();
if (! ($users instanceof Collection)) fail('with() should return Collection');

foreach ($users as $u) {
    if (! $u->relationLoaded('profile')) fail("User {$u->id} missing eager profile");
}

$alice = $users->first(fn($u) => $u->name === 'Alice');
if ($alice->profile === null || $alice->profile->bio !== 'Alice bio') {
    fail("Eager HasOne wrong: " . ($alice->profile->bio ?? 'null'));
}

$clara = $users->first(fn($u) => $u->name === 'Clara');
if ($clara->profile !== null) fail("Clara should have null profile in eager load");

pass('HasOne — eager load (User::with("profile")->get())');

echo "\n── HasMany ─────────────────────────────────────────\n";

// -----------------------------------------------------------------------
// 3. HasMany — lazy load
// -----------------------------------------------------------------------
$user  = User::find(1);
$posts = $user->posts;

if (! ($posts instanceof Collection)) fail('HasMany lazy: should return Collection');
if ($posts->count() !== 3) fail("HasMany lazy: expected 3, got {$posts->count()}");
if (! ($posts->first() instanceof Post)) fail('HasMany: items should be Post instances');
pass('HasMany — lazy load ($user->posts), returns Collection<Post>');

// -----------------------------------------------------------------------
// 4. HasMany — constrained lazy
// -----------------------------------------------------------------------
$published = $user->posts()->where('published', 1)->get();
if ($published->count() !== 2) fail("HasMany constrained: expected 2, got {$published->count()}");
pass('HasMany — constrained ($user->posts()->where()->get())');

// User with no posts
$user3 = User::find(3);
$posts = $user3->posts;
if (! ($posts instanceof Collection)) fail('HasMany no results: should return empty Collection');
if ($posts->count() !== 0) fail('HasMany no results: should be empty');
pass('HasMany — empty Collection when no related rows');

// -----------------------------------------------------------------------
// 5. HasMany — eager load
// -----------------------------------------------------------------------
$users = User::with('posts')->get();

$alice = $users->first(fn($u) => $u->name === 'Alice');
$bob   = $users->first(fn($u) => $u->name === 'Bob');
$clara = $users->first(fn($u) => $u->name === 'Clara');

if ($alice->posts->count() !== 3) fail("Eager HasMany Alice: expected 3, got {$alice->posts->count()}");
if ($bob->posts->count()   !== 1) fail("Eager HasMany Bob: expected 1, got {$bob->posts->count()}");
if ($clara->posts->count() !== 0) fail("Eager HasMany Clara: expected 0, got {$clara->posts->count()}");
pass('HasMany — eager load assigns correct posts to each user');

// -----------------------------------------------------------------------
// 6. HasMany — eager load with constraint
// -----------------------------------------------------------------------
$users = User::with(['posts' => fn($q) => $q->where('published', 1)])->get();
$alice = $users->first(fn($u) => $u->name === 'Alice');
if ($alice->posts->count() !== 2) fail("Eager constrained: expected 2, got {$alice->posts->count()}");
pass('HasMany — eager load with constraint callback');

echo "\n── BelongsTo ───────────────────────────────────────\n";

// -----------------------------------------------------------------------
// 7. BelongsTo — lazy load
// -----------------------------------------------------------------------
$post   = Post::find(1);
$author = $post->user;

if (! ($author instanceof User)) fail("BelongsTo lazy: expected User, got " . gettype($author));
if ($author->name !== 'Alice')   fail("BelongsTo lazy wrong: {$author->name}");
pass('BelongsTo — lazy load ($post->user)');

// -----------------------------------------------------------------------
// 8. BelongsTo — eager load
// -----------------------------------------------------------------------
$posts = Post::with('user')->get();

foreach ($posts as $p) {
    if (! $p->relationLoaded('user')) fail("Post {$p->id} missing eager user");
    if (! ($p->user instanceof User)) fail("Post {$p->id} user should be User instance");
}

$post1 = $posts->first(fn($p) => $p->id == 1);
if ($post1->user->name !== 'Alice') fail("Eager BelongsTo wrong: {$post1->user->name}");
pass('BelongsTo — eager load (Post::with("user")->get())');

// -----------------------------------------------------------------------
// 9. BelongsTo — associate() / dissociate()
// -----------------------------------------------------------------------
$profile = Profile::find(1);
$user3   = User::find(3);

$profile->user()->associate($user3);
if ((int)$profile->user_id !== 3) fail("associate() did not set FK: {$profile->user_id}");
pass('BelongsTo::associate() sets FK on parent');

$profile->user()->dissociate();
if ($profile->user_id !== null) fail("dissociate() did not clear FK");
pass('BelongsTo::dissociate() clears FK');

// Restore
$profile->forceFill(['user_id' => 1]);

echo "\n── BelongsToMany ───────────────────────────────────\n";

// -----------------------------------------------------------------------
// 10. BelongsToMany — lazy load
// -----------------------------------------------------------------------
$user  = User::find(1);
$roles = $user->roles;

if (! ($roles instanceof Collection))    fail('BelongsToMany lazy: should return Collection');
if ($roles->count() !== 2)               fail("BelongsToMany lazy: expected 2, got {$roles->count()}");
if (! ($roles->first() instanceof Role)) fail('BelongsToMany: items should be Role instances');

$roleNames = $roles->pluck('name');
if (! in_array('admin', $roleNames))  fail('BelongsToMany: missing admin role');
if (! in_array('editor', $roleNames)) fail('BelongsToMany: missing editor role');
pass('BelongsToMany — lazy load ($user->roles), returns Collection<Role>');

// User with one role
$user2     = User::find(2);
$roles2    = $user2->roles;
if ($roles2->count() !== 1) fail("BelongsToMany user2: expected 1, got {$roles2->count()}");
pass('BelongsToMany — correct count per user');

// User with no roles
$user3  = User::find(3);
$roles3 = $user3->roles;
if ($roles3->count() !== 0) fail('BelongsToMany no roles: should be empty');
pass('BelongsToMany — empty Collection for user with no roles');

// -----------------------------------------------------------------------
// 11. BelongsToMany — eager load
// -----------------------------------------------------------------------
$users = User::with('roles')->get();

$alice = $users->first(fn($u) => $u->name === 'Alice');
$bob   = $users->first(fn($u) => $u->name === 'Bob');
$clara = $users->first(fn($u) => $u->name === 'Clara');

if ($alice->roles->count() !== 2) fail("Eager BelongsToMany Alice: expected 2, got {$alice->roles->count()}");
if ($bob->roles->count()   !== 1) fail("Eager BelongsToMany Bob: expected 1, got {$bob->roles->count()}");
if ($clara->roles->count() !== 0) fail("Eager BelongsToMany Clara: expected 0, got {$clara->roles->count()}");
pass('BelongsToMany — eager load assigns correct roles to each user');

// -----------------------------------------------------------------------
// 12. BelongsToMany — attach()
// -----------------------------------------------------------------------
$user3 = User::find(3);
$user3->roles()->attach(1);

$roles = $user3->roles;
if ($roles->count() !== 1) fail("attach() expected 1 role, got {$roles->count()}");
pass('BelongsToMany::attach() adds row to pivot');

// -----------------------------------------------------------------------
// 13. BelongsToMany — detach()
// -----------------------------------------------------------------------
$user3->roles()->detach(1);

$user3->unsetRelation('roles');
$roles = $user3->roles;
if ($roles->count() !== 0) fail("detach() expected 0 roles, got {$roles->count()}");
pass('BelongsToMany::detach() removes row from pivot');

// -----------------------------------------------------------------------
// 14. BelongsToMany — sync()
// -----------------------------------------------------------------------
$user = User::find(1);
$result = $user->roles()->sync([1, 3]);

$user->unsetRelation('roles');
$roles = $user->roles;
$ids   = array_map('intval', $roles->pluck('id'));
sort($ids);
if ($ids !== [1, 3]) fail("sync() wrong IDs: " . json_encode($ids));
pass('BelongsToMany::sync() replaces pivot rows');

// -----------------------------------------------------------------------
// 15. BelongsToMany — toggle()
// -----------------------------------------------------------------------
$user->unsetRelation('roles');
// Current: [1, 3] — toggle [1, 2] → detach 1, attach 2 → result: [2, 3]
$result = $user->roles()->toggle([1, 2]);

$user->unsetRelation('roles');
$roles = $user->roles;
$ids   = array_map('intval', $roles->pluck('id'));
sort($ids);
if ($ids !== [2, 3]) fail("toggle() wrong result: " . json_encode($ids));
pass('BelongsToMany::toggle() attaches missing, detaches existing');

// -----------------------------------------------------------------------
// 16. BelongsToMany — isAttached()
// -----------------------------------------------------------------------
$user->unsetRelation('roles');
if (! $user->roles()->isAttached(2))  fail('isAttached(2) should be true');
if ($user->roles()->isAttached(1))    fail('isAttached(1) should be false after toggle');
pass('BelongsToMany::isAttached()');

// -----------------------------------------------------------------------
// 17. BelongsToMany — withPivot()
// -----------------------------------------------------------------------
// Restore: sync to [1, 2] with assigned_at
$user->roles()->sync([1, 2]);

$roles = $user->roles()->withPivot('assigned_at')->get();
$first = $roles->first();
if (! isset($first->pivot)) fail('withPivot(): pivot object missing on result');
pass('BelongsToMany::withPivot() exposes pivot columns');

// -----------------------------------------------------------------------
// 18. BelongsToMany — updateExistingPivot()
// -----------------------------------------------------------------------
$affected = $user->roles()->updateExistingPivot(1, ['assigned_at' => '2025-06-01']);
if ($affected < 0) fail("updateExistingPivot() failed: {$affected}");
pass('BelongsToMany::updateExistingPivot()');

echo "\n── HasManyThrough ──────────────────────────────────\n";

// -----------------------------------------------------------------------
// 19. HasManyThrough — lazy load
// -----------------------------------------------------------------------
$country = Country::find(1);
$posts   = $country->posts;

if (! ($posts instanceof Collection)) fail('HasManyThrough lazy: should return Collection');
if ($posts->count() !== 4) fail("HasManyThrough lazy: expected 4, got {$posts->count()}");
if (! ($posts->first() instanceof Post)) fail('HasManyThrough: items should be Post instances');
pass('HasManyThrough — lazy load ($country->posts), returns Collection<Post>');

$country2 = Country::find(2);
$posts2   = $country2->posts;
if ($posts2->count() !== 0) fail("HasManyThrough Germany: expected 0, got {$posts2->count()}");
pass('HasManyThrough — empty Collection when no related rows');

// -----------------------------------------------------------------------
// 20. HasManyThrough — eager load
// -----------------------------------------------------------------------
$countries = Country::with('posts')->get();

$iran    = $countries->first(fn($c) => $c->name === 'Iran');
$germany = $countries->first(fn($c) => $c->name === 'Germany');

if ($iran->posts->count() !== 4) fail("Eager HasManyThrough Iran: expected 4, got {$iran->posts->count()}");
if ($germany->posts->count() !== 0) fail("Eager HasManyThrough Germany: expected 0, got {$germany->posts->count()}");
pass('HasManyThrough — eager load assigns correct posts to each country');

echo "\n── Multiple eager relations ────────────────────────\n";

// -----------------------------------------------------------------------
// 21. Multiple relations in one with() call
// -----------------------------------------------------------------------
$users = User::with('profile', 'posts', 'roles')->get();

foreach ($users as $u) {
    if (! $u->relationLoaded('profile')) fail("User {$u->id} missing eager profile");
    if (! $u->relationLoaded('posts'))   fail("User {$u->id} missing eager posts");
    if (! $u->relationLoaded('roles'))   fail("User {$u->id} missing eager roles");
}

$alice = $users->first(fn($u) => $u->name === 'Alice');
if ($alice->profile === null)    fail('Multiple eager: Alice profile null');
if ($alice->posts->count() < 1)  fail('Multiple eager: Alice posts empty');
if ($alice->roles->count() < 1)  fail('Multiple eager: Alice roles empty');
pass('User::with("profile", "posts", "roles")->get() — all 3 eager');

// -----------------------------------------------------------------------
// 22. Nested eager loading via array syntax
// -----------------------------------------------------------------------
$posts = Post::with('user', 'comments')->get();

$post1 = $posts->first(fn($p) => $p->id == 1);
if (! $post1->relationLoaded('user'))     fail("Post1 missing user");
if (! $post1->relationLoaded('comments')) fail("Post1 missing comments");
if ($post1->comments->count() !== 2)      fail("Post1 comments expected 2, got {$post1->comments->count()}");
if ($post1->user->name !== 'Alice')       fail("Post1 user wrong: {$post1->user->name}");
pass('Post::with("user", "comments")->get() — multiple relations');

// -----------------------------------------------------------------------
// 23. EagerBuilder::first()
// -----------------------------------------------------------------------
$user = User::with('posts')->where('name', 'Alice')->first();
if ($user === null) fail('EagerBuilder::first() returned null');
if (! ($user instanceof User)) fail('EagerBuilder::first() should return User');
if (! $user->relationLoaded('posts')) fail('EagerBuilder::first() missing posts');
if ($user->posts->count() !== 3) fail("EagerBuilder::first() posts count wrong: {$user->posts->count()}");
pass('EagerBuilder::first() loads relations on single result');

// -----------------------------------------------------------------------
// 24. relationLoaded / unsetRelation / getRelation
// -----------------------------------------------------------------------
$user = User::find(1);
if ($user->relationLoaded('posts')) fail('relationLoaded should be false before access');

$_ = $user->posts; 
if (! $user->relationLoaded('posts')) fail('relationLoaded should be true after access');
pass('relationLoaded() false before, true after lazy load');

$cached = $user->getRelation('posts');
if (! ($cached instanceof Collection)) fail('getRelation() wrong type');
pass('getRelation() returns cached Collection');

$user->unsetRelation('posts');
if ($user->relationLoaded('posts')) fail('unsetRelation() did not clear cache');
pass('unsetRelation() clears cached relation');

// -----------------------------------------------------------------------
// 25. N+1 query prevention — eager vs lazy query count
// -----------------------------------------------------------------------
DB::enableQueryLog();
DB::flushQueryLog();

$users = User::with('posts')->get();
$count = DB::getQueryCount();
if ($count !== 2) fail("Eager should use 2 queries, used {$count}");
pass("Eager loading uses exactly 2 queries (not N+1) [{$count} queries]");

DB::flushQueryLog();

$users = User::all();
foreach ($users as $u) {
    $_ = $u->posts; 
}
$lazyCount = DB::getQueryCount();
if ($lazyCount < 4) fail("Lazy N+1 expected >=4 queries, got {$lazyCount}");
pass("Lazy loading causes N+1 ({$lazyCount} queries vs 2 for eager)");

DB::disableQueryLog();

// -----------------------------------------------------------------------
// Summary
// -----------------------------------------------------------------------
echo "\n\033[32m✔ All Relations tests passed on MySQL!\033[0m\n\n";