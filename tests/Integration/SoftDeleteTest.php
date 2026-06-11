<?php

declare(strict_types=1);

namespace Foxdb\Tests\Integration;

use Foxdb\DB;
use Foxdb\Eloquent\Concerns\HasSoftDeletes;
use Foxdb\Eloquent\Model;

/**
 * SoftDeleteTest — tests HasSoftDeletes trait against a real DB.
 */
class SoftDeleteTest extends IntegrationTestCase
{
    // -----------------------------------------------------------------------
    // Schema
    // -----------------------------------------------------------------------

    protected static function createSchema(): void
    {
        $ai = static::autoIncrement();

        DB::statement("DROP TABLE IF EXISTS " . static::q('soft_posts'));
        DB::statement("
            CREATE TABLE " . static::q('soft_posts') . " (
                " . static::q('id')         . " {$ai},
                " . static::q('title')      . " VARCHAR(255),
                " . static::q('deleted_at') . " DATETIME DEFAULT NULL
            )
        ");
    }

    protected static function dropSchema(): void
    {
        DB::statement("DROP TABLE IF EXISTS " . static::q('soft_posts'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        static::truncate('soft_posts');
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function seed(): void
    {
        SoftPost::create(['title' => 'Post 1']);
        SoftPost::create(['title' => 'Post 2']);
        SoftPost::create(['title' => 'Post 3']);
    }

    // -----------------------------------------------------------------------
    // Tests
    // -----------------------------------------------------------------------

    public function test_delete_sets_deleted_at(): void
    {
        $post = SoftPost::create(['title' => 'Post 1']);
        $post->delete();

        $this->assertNull(SoftPost::find($post->getKey()));
        $this->assertNotNull(
            SoftPost::withTrashed()->where('id', $post->getKey())->value('deleted_at')
        );
    }

    public function test_soft_deleted_rows_excluded_from_normal_queries(): void
    {
        $this->seed();
        $post = SoftPost::first();
        $post->delete();

        $this->assertCount(2, SoftPost::all());
    }

    public function test_with_trashed_includes_deleted_rows(): void
    {
        $this->seed();
        SoftPost::first()->delete();

        $this->assertCount(3, SoftPost::withTrashed()->get());
    }

    public function test_only_trashed_returns_only_deleted_rows(): void
    {
        $this->seed();
        SoftPost::first()->delete();

        $trashed = SoftPost::onlyTrashed()->get();
        $this->assertCount(1, $trashed);
    }

    public function test_restore_clears_deleted_at(): void
    {
        $post = SoftPost::create(['title' => 'Post 1']);
        $id   = $post->getKey();
        $post->delete();

        $this->assertNull(SoftPost::find($id));

        SoftPost::withTrashed()->where('id', $id)->first()->restore();

        $this->assertNotNull(SoftPost::find($id));
    }

    public function test_count_excludes_soft_deleted(): void
    {
        $this->seed();
        SoftPost::first()->delete();

        $this->assertSame(2, SoftPost::query()->count());
        $this->assertSame(3, SoftPost::withTrashed()->count());
    }
}

// -----------------------------------------------------------------------
// Model
// -----------------------------------------------------------------------
class SoftPost extends Model
{
    use HasSoftDeletes;

    protected string $table    = 'soft_posts';
    protected array  $fillable = ['title'];
    protected bool   $timestamps = false;
}
