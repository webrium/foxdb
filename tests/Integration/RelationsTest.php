<?php

declare(strict_types=1);

namespace Foxdb\Tests\Integration;

use Foxdb\DB;
use Foxdb\Eloquent\Model;
use Foxdb\Eloquent\Relations\BelongsTo;
use Foxdb\Eloquent\Relations\HasMany;
use Foxdb\Eloquent\Relations\HasOne;
use Foxdb\Support\Collection;

/**
 * RelationsTest — tests hasMany, belongsTo, hasOne, and eager loading.
 */
class RelationsTest extends IntegrationTestCase
{
    // -----------------------------------------------------------------------
    // Schema
    // -----------------------------------------------------------------------

    protected static function createSchema(): void
    {
        $ai = static::autoIncrement();

        DB::statement("DROP TABLE IF EXISTS " . static::q('rel_comments'));
        DB::statement("DROP TABLE IF EXISTS " . static::q('rel_profiles'));
        DB::statement("DROP TABLE IF EXISTS " . static::q('rel_posts'));
        DB::statement("DROP TABLE IF EXISTS " . static::q('rel_users'));

        DB::statement("
            CREATE TABLE " . static::q('rel_users') . " (
                " . static::q('id')   . " {$ai},
                " . static::q('name') . " VARCHAR(255)
            )
        ");

        DB::statement("
            CREATE TABLE " . static::q('rel_profiles') . " (
                " . static::q('id')      . " {$ai},
                " . static::q('user_id') . " INTEGER,
                " . static::q('bio')     . " TEXT
            )
        ");

        DB::statement("
            CREATE TABLE " . static::q('rel_posts') . " (
                " . static::q('id')      . " {$ai},
                " . static::q('user_id') . " INTEGER,
                " . static::q('title')   . " VARCHAR(255)
            )
        ");

        DB::statement("
            CREATE TABLE " . static::q('rel_comments') . " (
                " . static::q('id')      . " {$ai},
                " . static::q('post_id') . " INTEGER,
                " . static::q('body')    . " TEXT
            )
        ");
    }

    protected static function dropSchema(): void
    {
        DB::statement("DROP TABLE IF EXISTS " . static::q('rel_comments'));
        DB::statement("DROP TABLE IF EXISTS " . static::q('rel_profiles'));
        DB::statement("DROP TABLE IF EXISTS " . static::q('rel_posts'));
        DB::statement("DROP TABLE IF EXISTS " . static::q('rel_users'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        static::truncate('rel_comments');
        static::truncate('rel_profiles');
        static::truncate('rel_posts');
        static::truncate('rel_users');
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function seedAll(): array
    {
        $alice = RelUser::create(['name' => 'Alice']);
        $bob   = RelUser::create(['name' => 'Bob']);

        RelProfile::create(['user_id' => $alice->getKey(), 'bio' => 'Alice bio']);

        $p1 = RelPost::create(['user_id' => $alice->getKey(), 'title' => 'Post 1']);
        $p2 = RelPost::create(['user_id' => $alice->getKey(), 'title' => 'Post 2']);
        RelPost::create(['user_id' => $bob->getKey(),   'title' => 'Post 3']);

        RelComment::create(['post_id' => $p1->getKey(), 'body' => 'Comment A']);
        RelComment::create(['post_id' => $p1->getKey(), 'body' => 'Comment B']);
        RelComment::create(['post_id' => $p2->getKey(), 'body' => 'Comment C']);

        return [$alice, $bob, $p1, $p2];
    }

    // -----------------------------------------------------------------------
    // hasMany — lazy
    // -----------------------------------------------------------------------

    public function test_has_many_lazy_load(): void
    {
        [$alice] = $this->seedAll();

        $posts = $alice->posts;
        $this->assertInstanceOf(Collection::class, $posts);
        $this->assertCount(2, $posts);
    }

    public function test_has_many_returns_empty_collection_when_no_related(): void
    {
        $bob = RelUser::create(['name' => 'Bob']);
        $this->assertCount(0, $bob->posts);
    }

    // -----------------------------------------------------------------------
    // belongsTo — lazy
    // -----------------------------------------------------------------------

    public function test_belongs_to_lazy_load(): void
    {
        [$alice, , $p1] = $this->seedAll();
        $author = $p1->author;

        $this->assertInstanceOf(RelUser::class, $author);
        $this->assertSame('Alice', $author->name);
    }

    // -----------------------------------------------------------------------
    // hasOne — lazy
    // -----------------------------------------------------------------------

    public function test_has_one_lazy_load(): void
    {
        [$alice] = $this->seedAll();
        $profile = $alice->profile;

        $this->assertInstanceOf(RelProfile::class, $profile);
        $this->assertSame('Alice bio', $profile->bio);
    }

    public function test_has_one_returns_null_when_missing(): void
    {
        $bob = RelUser::create(['name' => 'Bob']);
        $this->assertNull($bob->profile);
    }

    // -----------------------------------------------------------------------
    // Eager loading — with()
    // -----------------------------------------------------------------------

    public function test_eager_load_has_many(): void
    {
        $this->seedAll();

        $users = RelUser::with('posts')->get();

        foreach ($users as $user) {
            $this->assertTrue(isset($user->posts), "posts not set on user {$user->name}");
            $this->assertInstanceOf(Collection::class, $user->posts);
        }

        $alice = $users->first(fn($u) => $u->name === 'Alice');
        $this->assertCount(2, $alice->posts);
    }

    public function test_eager_load_belongs_to(): void
    {
        $this->seedAll();

        $posts = RelPost::with('author')->get();

        foreach ($posts as $post) {
            $this->assertInstanceOf(RelUser::class, $post->author);
        }
    }

    public function test_eager_load_has_one(): void
    {
        $this->seedAll();

        $users = RelUser::with('profile')->get();
        $alice = $users->first(fn($u) => $u->name === 'Alice');

        $this->assertInstanceOf(RelProfile::class, $alice->profile);
        $this->assertSame('Alice bio', $alice->profile->bio);
    }

    // -----------------------------------------------------------------------
    // toArray includes loaded relations
    // -----------------------------------------------------------------------

    public function test_to_array_includes_eager_loaded_relations(): void
    {
        $this->seedAll();

        $users = RelUser::with('posts')->get();
        $arr   = $users->toArray();

        $alice = null;
        foreach ($arr as $row) {
            if ($row['name'] === 'Alice') {
                $alice = $row;
                break;
            }
        }

        $this->assertNotNull($alice);
        $this->assertArrayHasKey('posts', $alice);
        $this->assertIsArray($alice['posts']);
        $this->assertCount(2, $alice['posts']);
    }
}

// -----------------------------------------------------------------------
// Models
// -----------------------------------------------------------------------

class RelUser extends Model
{
    protected string $table    = 'rel_users';
    protected array  $fillable = ['name'];
    protected bool   $timestamps = false;

    public function posts(): HasMany
    {
        return $this->hasMany(RelPost::class, 'user_id', 'id');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(RelProfile::class, 'user_id', 'id');
    }
}

class RelProfile extends Model
{
    protected string $table    = 'rel_profiles';
    protected array  $fillable = ['user_id', 'bio'];
    protected bool   $timestamps = false;
}

class RelPost extends Model
{
    protected string $table    = 'rel_posts';
    protected array  $fillable = ['user_id', 'title'];
    protected bool   $timestamps = false;

    public function author(): BelongsTo
    {
        return $this->belongsTo(RelUser::class, 'user_id', 'id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(RelComment::class, 'post_id', 'id');
    }
}

class RelComment extends Model
{
    protected string $table    = 'rel_comments';
    protected array  $fillable = ['post_id', 'body'];
    protected bool   $timestamps = false;
}
