<?php

declare(strict_types=1);

namespace Foxdb\Tests\Integration;

use Foxdb\DB;
use Foxdb\Eloquent\Model;
use Foxdb\Exceptions\ModelNotFoundException;
use Foxdb\Query\Builder;
use Foxdb\Support\Collection;

/**
 * ModelCrudTest — create / read / update / delete operations on a real DB.
 */
class ModelCrudTest extends IntegrationTestCase
{
    // -----------------------------------------------------------------------
    // Schema
    // -----------------------------------------------------------------------

    protected static function createSchema(): void
    {
        $ai   = static::autoIncrement();
        $bool = static::boolType();
        $dt   = static::datetimeType();
        $boolDefault = static::boolDefault(true);

        DB::statement("DROP TABLE IF EXISTS " . static::q('users'));
        DB::statement("
            CREATE TABLE " . static::q('users') . " (
                " . static::q('id')         . " {$ai},
                " . static::q('name')       . " VARCHAR(255) NOT NULL,
                " . static::q('email')      . " VARCHAR(255) NOT NULL,
                " . static::q('age')        . " INTEGER DEFAULT 0,
                " . static::q('is_active')  . " {$bool} DEFAULT {$boolDefault},
                " . static::q('score')      . " DOUBLE PRECISION DEFAULT 0,
                " . static::q('settings')   . " TEXT DEFAULT NULL,
                " . static::q('created_at') . " {$dt} DEFAULT NULL,
                " . static::q('updated_at') . " {$dt} DEFAULT NULL
            )
        ");
    }

    protected static function dropSchema(): void
    {
        DB::statement("DROP TABLE IF EXISTS " . static::q('users'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        static::truncate('users');
    }

    // -----------------------------------------------------------------------
    // Model definition
    // -----------------------------------------------------------------------

    private function userModel(): string
    {
        return new class extends Model {
            protected string $table    = 'users';
            protected array  $fillable = ['name', 'email', 'age', 'is_active', 'score', 'settings'];
            protected array  $hidden   = ['settings'];
            protected array  $casts    = [
                'age'       => 'int',
                'is_active' => 'bool',
                'score'     => 'float',
                'settings'  => 'array',
            ];

            public function scopeActive(Builder $q): Builder
            {
                return $q->where('is_active', 1);
            }
        };
        // Note: anonymous classes can't be used in static context easily,
        // so we use a named helper below.
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function seedUsers(): void
    {
        TestUser::create(['name' => 'Alice', 'email' => 'alice@test.com', 'age' => 30, 'score' => 90.0]);
        TestUser::create(['name' => 'Bob',   'email' => 'bob@test.com',   'age' => 25, 'score' => 50.0]);
        TestUser::create(['name' => 'Carol', 'email' => 'carol@test.com', 'age' => 20, 'score' => 70.0]);
    }

    // -----------------------------------------------------------------------
    // CREATE
    // -----------------------------------------------------------------------

    public function test_create_inserts_row_and_returns_model(): void
    {
        $user = TestUser::create(['name' => 'Alice', 'email' => 'alice@test.com', 'age' => 25]);

        $this->assertTrue($user->isExists());
        $this->assertNotNull($user->getKey());
        $this->assertSame('Alice', $user->name);
    }

    public function test_create_sets_timestamps(): void
    {
        $user = TestUser::create(['name' => 'Alice', 'email' => 'a@test.com', 'age' => 20]);
        $this->assertNotNull($user->created_at);
        $this->assertNotNull($user->updated_at);
    }

    public function test_save_performs_insert_for_new_model(): void
    {
        $user        = new TestUser();
        $user->name  = 'Bob';
        $user->email = 'bob@test.com';
        $user->age   = 30;

        $result = $user->save();

        $this->assertTrue($result);
        $this->assertTrue($user->isExists());
    }

    // -----------------------------------------------------------------------
    // READ
    // -----------------------------------------------------------------------

    public function test_find_returns_correct_model(): void
    {
        $created = TestUser::create(['name' => 'Alice', 'email' => 'a@test.com', 'age' => 25]);
        $found   = TestUser::find($created->getKey());

        $this->assertNotNull($found);
        $this->assertSame('Alice', $found->name);
    }

    public function test_find_returns_null_for_missing_id(): void
    {
        $this->assertNull(TestUser::find(99999));
    }

    public function test_find_or_fail_throws_on_missing(): void
    {
        $this->expectException(ModelNotFoundException::class);
        TestUser::findOrFail(99999);
    }

    public function test_all_returns_collection(): void
    {
        $this->seedUsers();
        $users = TestUser::all();

        $this->assertInstanceOf(Collection::class, $users);
        $this->assertCount(3, $users);
    }

    public function test_where_filters_correctly(): void
    {
        $this->seedUsers();
        $users = TestUser::where('age', '>', 22)->get();

        $this->assertCount(2, $users);
    }

    public function test_first_where_returns_single_model(): void
    {
        $this->seedUsers();
        $user = TestUser::firstWhere('email', 'bob@test.com');

        $this->assertNotNull($user);
        $this->assertSame('Bob', $user->name);
    }

    public function test_exists_returns_true_when_found(): void
    {
        $this->seedUsers();
        $this->assertTrue(TestUser::exists(['email' => 'alice@test.com']));
        $this->assertFalse(TestUser::exists(['email' => 'nobody@test.com']));
    }

    // -----------------------------------------------------------------------
    // UPDATE
    // -----------------------------------------------------------------------

    public function test_save_performs_update_for_existing_model(): void
    {
        $user       = TestUser::create(['name' => 'Alice', 'email' => 'a@test.com', 'age' => 25]);
        $user->name = 'Alice Updated';
        $user->save();

        $fresh = TestUser::find($user->getKey());
        $this->assertSame('Alice Updated', $fresh->name);
    }

    public function test_save_only_updates_dirty_columns(): void
    {
        $user = TestUser::create(['name' => 'Alice', 'email' => 'a@test.com', 'age' => 25]);
        $user->name = 'Alice Updated';

        // age was not changed — dirty only has name
        $dirty = $user->getDirty();
        $this->assertArrayHasKey('name', $dirty);
        $this->assertArrayNotHasKey('age', $dirty);

        $user->save();
        $fresh = TestUser::find($user->getKey());
        $this->assertSame(25, $fresh->age);  // unchanged
    }

    public function test_update_via_query_builder(): void
    {
        $this->seedUsers();
        TestUser::where('name', 'Alice')->update(['score' => 100.0]);

        $alice = TestUser::firstWhere('name', 'Alice');
        $this->assertSame(100.0, $alice->score);
    }

    // -----------------------------------------------------------------------
    // DELETE
    // -----------------------------------------------------------------------

    public function test_delete_removes_row(): void
    {
        $user = TestUser::create(['name' => 'Alice', 'email' => 'a@test.com', 'age' => 25]);
        $id   = $user->getKey();
        $user->delete();

        $this->assertNull(TestUser::find($id));
        $this->assertFalse($user->isExists());
    }

    public function test_delete_via_query_builder(): void
    {
        $this->seedUsers();
        TestUser::where('age', '<', 22)->delete();

        $this->assertCount(2, TestUser::all());
    }

    // -----------------------------------------------------------------------
    // Casts round-trip
    // -----------------------------------------------------------------------

    public function test_cast_bool_round_trip(): void
    {
        $user = TestUser::create(['name' => 'Alice', 'email' => 'a@test.com', 'age' => 25, 'is_active' => true]);
        $fresh = TestUser::find($user->getKey());

        $this->assertIsBool($fresh->is_active);
        $this->assertTrue($fresh->is_active);
    }

    public function test_cast_float_round_trip(): void
    {
        $user  = TestUser::create(['name' => 'Alice', 'email' => 'a@test.com', 'age' => 25, 'score' => 75.5]);
        $fresh = TestUser::find($user->getKey());

        $this->assertIsFloat($fresh->score);
        $this->assertSame(75.5, $fresh->score);
    }

    public function test_cast_array_json_round_trip(): void
    {
        $settings = ['theme' => 'dark', 'lang' => 'fa'];
        $user     = TestUser::create(['name' => 'Alice', 'email' => 'a@test.com', 'age' => 25, 'settings' => $settings]);
        $fresh    = TestUser::find($user->getKey());

        $this->assertIsArray($fresh->settings);
        $this->assertSame('dark', $fresh->settings['theme']);
    }

    // -----------------------------------------------------------------------
    // toArray / JSON serialization
    // -----------------------------------------------------------------------

    public function test_to_array_no_null_byte_keys(): void
    {
        TestUser::create(['name' => 'Alice', 'email' => 'a@test.com', 'age' => 25]);
        $arr = TestUser::all()->toArray();

        foreach (array_keys($arr[0]) as $key) {
            $this->assertStringNotContainsString("\0", $key, "Null-byte key found: {$key}");
        }
    }

    public function test_to_array_excludes_hidden_fields(): void
    {
        TestUser::create(['name' => 'Alice', 'email' => 'a@test.com', 'age' => 25, 'settings' => ['x' => 1]]);
        $arr = TestUser::all()->toArray();

        $this->assertArrayNotHasKey('settings', $arr[0]);
    }

    public function test_collection_json_encode_works_correctly(): void
    {
        TestUser::create(['name' => 'Alice', 'email' => 'a@test.com', 'age' => 25]);
        $users  = TestUser::all();
        $result = ['ok' => true, 'users' => $users->toArray()];
        $json   = json_encode($result);

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertTrue($decoded['ok']);
        $this->assertSame('Alice', $decoded['users'][0]['name']);
    }

    // -----------------------------------------------------------------------
    // json_encode / return model directly — regression tests
    //
    // Before the fix:
    //   $user = User::where(...)->first() returned object|false (Builder type)
    //   json_encode($user) produced {} because Model did not implement
    //   JsonSerializable and PHP only serializes public properties.
    // -----------------------------------------------------------------------

    public function test_json_encode_on_model_from_where_first_is_not_empty(): void
    {
        TestUser::create(['name' => 'Alice', 'email' => 'a@test.com', 'age' => 25]);

        $user = TestUser::where('name', 'Alice')->first();

        // Must be a model instance, not false or null
        $this->assertInstanceOf(TestUser::class, $user);

        // json_encode($model) must produce valid, non-empty JSON
        $json = json_encode($user);
        $this->assertJson($json);

        $decoded = json_decode($json, true);
        $this->assertNotEmpty($decoded, 'json_encode($user) returned {} — JsonSerializable not implemented');
        $this->assertSame('Alice', $decoded['name']);
        $this->assertSame(25, $decoded['age']);
    }

    public function test_return_model_from_where_first_in_api_response(): void
    {
        TestUser::create(['name' => 'Alice', 'email' => 'a@test.com', 'age' => 25]);

        $user = TestUser::where('name', 'Alice')->first();

        // Simulate what a controller does: return the model inside an array
        $response = ['ok' => true, 'user' => $user];
        $json     = json_encode($response);

        $this->assertJson($json);
        $decoded = json_decode($json, true);

        $this->assertTrue($decoded['ok']);
        $this->assertIsArray($decoded['user']);
        $this->assertSame('Alice', $decoded['user']['name']);
        $this->assertArrayNotHasKey('settings', $decoded['user']); // hidden field excluded
    }

    public function test_json_encode_respects_hidden_fields(): void
    {
        TestUser::create(['name' => 'Alice', 'email' => 'a@test.com', 'age' => 25, 'settings' => ['x' => 1]]);

        $user = TestUser::where('name', 'Alice')->first();
        $json = json_encode($user);
        $decoded = json_decode($json, true);

        // 'settings' is in $hidden — must not appear in json_encode output
        $this->assertArrayNotHasKey('settings', $decoded);
        // 'name' is not hidden — must appear
        $this->assertArrayHasKey('name', $decoded);
    }

    public function test_json_encode_on_model_from_find(): void
    {
        $created = TestUser::create(['name' => 'Alice', 'email' => 'a@test.com', 'age' => 25]);

        $user    = TestUser::find($created->getKey());
        $json    = json_encode($user);
        $decoded = json_decode($json, true);

        $this->assertNotEmpty($decoded);
        $this->assertSame('Alice', $decoded['name']);
    }

    public function test_where_first_returns_model_instance_not_false(): void
    {
        TestUser::create(['name' => 'Alice', 'email' => 'a@test.com', 'age' => 25]);

        // Must return the model, not the Builder's false
        $user = TestUser::where('name', 'Alice')->first();
        $this->assertInstanceOf(TestUser::class, $user);

        // Must return null (not false) when nothing matches
        $nobody = TestUser::where('name', 'Nobody')->first();
        $this->assertNull($nobody);
    }

    // -----------------------------------------------------------------------
    // Local scope
    // -----------------------------------------------------------------------

    public function test_local_scope(): void
    {
        TestUser::create(['name' => 'Alice', 'email' => 'a@test.com', 'age' => 25, 'is_active' => 1]);
        TestUser::create(['name' => 'Bob',   'email' => 'b@test.com', 'age' => 30, 'is_active' => 0]);

        $active = TestUser::active()->get();
        $this->assertCount(1, $active);
        $this->assertSame('Alice', $active->first()->name);
    }

    /**
     * Regression: chaining a second local scope after the first one used
     * to fail with "Call to undefined method Foxdb\Query\Builder::adult()"
     * because the first scope call returned a raw Query\Builder instead of
     * a model-aware ModelBuilder, so nothing could resolve scopeAdult()
     * as a scope anymore.
     */
    public function test_chained_local_scopes(): void
    {
        TestUser::create(['name' => 'Alice', 'email' => 'a@test.com', 'age' => 25, 'is_active' => 1]); // active adult
        TestUser::create(['name' => 'Bob',   'email' => 'b@test.com', 'age' => 15, 'is_active' => 1]); // active minor
        TestUser::create(['name' => 'Carol', 'email' => 'c@test.com', 'age' => 30, 'is_active' => 0]); // inactive adult

        $result = TestUser::active()->adult()->get();

        $this->assertCount(1, $result);
        $this->assertSame('Alice', $result->first()->name);
    }

    /**
     * Regression: a scope must also stay chainable with ordinary Builder
     * methods that come after it (where, orderBy, ...), not just other
     * scopes.
     */
    public function test_local_scope_chained_with_builder_methods(): void
    {
        TestUser::create(['name' => 'Alice', 'email' => 'a@test.com', 'age' => 25, 'is_active' => 1]);
        TestUser::create(['name' => 'Bob',   'email' => 'b@test.com', 'age' => 30, 'is_active' => 1]);

        $result = TestUser::active()->where('age', 30)->orderByDesc('age')->get();

        $this->assertCount(1, $result);
        $this->assertSame('Bob', $result->first()->name);
    }

    // -----------------------------------------------------------------------
    // Pagination
    // -----------------------------------------------------------------------

    public function test_paginate_returns_correct_structure(): void
    {
        $this->seedUsers();
        $page = TestUser::query()->paginate(perPage: 2, page: 1);

        $this->assertSame(3, $page->total);
        $this->assertSame(2, $page->per_page);
        $this->assertSame(1, $page->current_page);
        $this->assertSame(2, $page->last_page);
        $this->assertSame(1, $page->from);
        $this->assertSame(2, $page->to);
        $this->assertCount(2, $page->data);
    }

    public function test_paginate_page_2(): void
    {
        $this->seedUsers();
        $page = TestUser::query()->paginate(perPage: 2, page: 2);

        $this->assertSame(2, $page->current_page);
        $this->assertCount(1, $page->data);
    }

    public function test_paginate_data_to_array_has_no_null_byte_keys(): void
    {
        $this->seedUsers();
        $page = TestUser::query()->paginate(perPage: 2, page: 1);
        $arr  = $page->data->toArray();

        foreach (array_keys($arr[0]) as $key) {
            $this->assertStringNotContainsString("\0", $key, "Null-byte key in paginate: {$key}");
        }
    }

    // -----------------------------------------------------------------------
    // fresh / refresh
    // -----------------------------------------------------------------------

    public function test_fresh_returns_new_instance(): void
    {
        $user = TestUser::create(['name' => 'Alice', 'email' => 'a@test.com', 'age' => 25]);
        $user->name = 'Dirty'; // not saved

        $fresh = $user->fresh();
        $this->assertSame('Alice', $fresh->name);
        $this->assertSame('Dirty', $user->name);  // original untouched
    }

    public function test_refresh_updates_in_place(): void
    {
        $user = TestUser::create(['name' => 'Alice', 'email' => 'a@test.com', 'age' => 25]);
        TestUser::where('id', $user->getKey())->update(['name' => 'Alice2']);

        $user->refresh();
        $this->assertSame('Alice2', $user->name);
    }
}

// -----------------------------------------------------------------------
// Named model used by tests (anonymous classes can't use static methods)
// -----------------------------------------------------------------------
class TestUser extends Model
{
    protected string $table    = 'users';
    protected array  $fillable = ['name', 'email', 'age', 'is_active', 'score', 'settings'];
    protected array  $hidden   = ['settings'];
    protected array  $casts    = [
        'age'       => 'int',
        'is_active' => 'bool',
        'score'     => 'float',
        'settings'  => 'array',
    ];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', 1);
    }

    public function scopeAdult(Builder $q): Builder
    {
        return $q->where('age', '>=', 18);
    }
}