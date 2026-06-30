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

    // -----------------------------------------------------------------------
    // with() is order-independent when chained after select() / where()
    //
    // Regression coverage for: Model::select(...)->with(...) throwing
    // "Call to unknown method: Foxdb\Query\Builder::with()".
    //
    // Root cause: Model::__callStatic() forwarded unmatched static calls
    // (select, orderBy, limit, ...) straight to the raw Query\Builder
    // instead of through ModelBuilder, so the result of select() had no
    // knowledge of with() at all — only Model::with() (the entry point)
    // returned something that understood eager loading. These tests pin
    // down that with() now works no matter where it appears in the chain,
    // matching the order-independent behaviour Eloquent users expect from
    // frameworks like Laravel.
    // -----------------------------------------------------------------------

    public function test_with_after_select_eager_loads_relation(): void
    {
        $this->seedAll();

        // This exact chain — select() first, with() second — is what
        // originally raised "Call to unknown method: Builder::with()".
        $users = RelUser::select('id', 'name')->with('posts')->get();

        $alice = $users->first(fn($u) => $u->name === 'Alice');
        $this->assertInstanceOf(Collection::class, $alice->posts);
        $this->assertCount(2, $alice->posts);
    }

    public function test_with_after_select_and_where_returns_single_model(): void
    {
        $this->seedAll();

        // Mirrors the real-world case: select(...)->with([...])->where(...)->first()
        $user = RelUser::select('id', 'name')
            ->with('posts')
            ->where('name', 'Alice')
            ->first();

        $this->assertInstanceOf(RelUser::class, $user);
        $this->assertSame('Alice', $user->name);
        $this->assertInstanceOf(Collection::class, $user->posts);
        $this->assertCount(2, $user->posts);
    }

    public function test_with_after_where_before_select_still_works(): void
    {
        $this->seedAll();

        $user = RelUser::where('name', 'Alice')
            ->with('posts')
            ->select('id', 'name')
            ->first();

        $this->assertInstanceOf(RelUser::class, $user);
        $this->assertCount(2, $user->posts);
    }

    public function test_with_constraint_closure_applies_after_select(): void
    {
        $this->seedAll();

        // The reported scenario also relied on a constraint closure
        // (filtering the eager-loaded relation), not just a bare relation
        // name — make sure that keeps working through select() too.
        $alice = RelUser::select('id', 'name')
            ->with(['posts' => fn($q) => $q->where('title', 'Post 1')])
            ->where('name', 'Alice')
            ->first();

        $this->assertCount(1, $alice->posts);
        $this->assertSame('Post 1', $alice->posts->first()->title);
    }

    public function test_select_without_with_is_unaffected(): void
    {
        // Regression guard: plain select() with no with() in the chain
        // must keep returning normal model instances, not break or
        // silently turn into an EagerBuilder/Collection of relations.
        $this->seedAll();

        $user = RelUser::select('id', 'name')->where('name', 'Alice')->first();

        $this->assertInstanceOf(RelUser::class, $user);
        $this->assertSame('Alice', $user->name);
    }

    // -----------------------------------------------------------------------
    // N+1 verification — proves eager loading runs a constant number of
    // queries (2: one for parents, one whereIn() for the relation) no
    // matter how many parent rows are involved, instead of one extra
    // query per row (the classic N+1 problem).
    // -----------------------------------------------------------------------

    /**
     * Seed many users (more than the small fixture in seedAll()) so a
     * per-row query difference is unambiguous and not a fluke of a
     * tiny dataset.
     *
     * @return int Number of users seeded
     */
    private function seedManyUsersWithPosts(int $userCount = 10, int $postsPerUser = 3): int
    {
        for ($i = 1; $i <= $userCount; $i++) {
            $user = RelUser::create(['name' => "Bulk User {$i}"]);
            for ($j = 1; $j <= $postsPerUser; $j++) {
                RelPost::create(['user_id' => $user->getKey(), 'title' => "Bulk Post {$j}"]);
            }
        }

        return $userCount;
    }

    public function test_lazy_loading_issues_one_query_per_row(): void
    {
        $userCount = $this->seedManyUsersWithPosts(userCount: 10);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $users = RelUser::all();
        foreach ($users as $user) {
            $user->posts->count(); // lazy: one query per iteration
        }

        $queryCount = count(DB::getQueryLog());

        // 1 query to fetch the users + 1 query per user for posts.
        $this->assertSame(
            1 + $userCount,
            $queryCount,
            'Lazy loading should issue exactly one query per parent row (the N+1 pattern), confirming the baseline this test contrasts eager loading against.',
        );
    }

    public function test_eager_loading_issues_constant_query_count_regardless_of_row_count(): void
    {
        $userCount = $this->seedManyUsersWithPosts(userCount: 10);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $users = RelUser::with('posts')->get();
        foreach ($users as $user) {
            $user->posts->count(); // already eager-loaded — must not query again
        }

        $queryCount = count(DB::getQueryLog());

        // Exactly 2 queries total: 1 for users, 1 whereIn(...) for all posts —
        // independent of how many users were fetched. This is the N+1 fix.
        $this->assertSame(
            2,
            $queryCount,
            'Eager loading via with() must use a constant 2 queries (parents + one whereIn for the relation), not one query per row.',
        );

        $log = DB::getQueryLog();
        $this->assertStringContainsString('IN (', $log[1]->sql, 'The eager relation query should batch all parent keys into a single whereIn(...) clause.');
    }

    public function test_eager_loading_after_select_also_avoids_n_plus_1(): void
    {
        // Same as above, but through the select()->with() chain that was
        // previously broken — confirms the fix didn't just make the call
        // succeed, but that it still avoids N+1 once it does.
        $userCount = $this->seedManyUsersWithPosts(userCount: 10);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $users = RelUser::select('id', 'name')->with('posts')->get();
        foreach ($users as $user) {
            $user->posts->count();
        }

        $this->assertSame(2, count(DB::getQueryLog()));
    }

    public function test_eager_loading_belongs_to_avoids_n_plus_1(): void
    {
        // Inverse direction: many posts eager-loading their single author.
        $this->seedManyUsersWithPosts(userCount: 10, postsPerUser: 2);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $posts = RelPost::with('author')->get();
        foreach ($posts as $post) {
            $post->author->name;
        }

        $this->assertSame(2, count(DB::getQueryLog()));
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