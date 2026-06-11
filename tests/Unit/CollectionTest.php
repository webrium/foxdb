<?php

declare(strict_types=1);

namespace Foxdb\Tests\Unit;

use Foxdb\Eloquent\Model;
use Foxdb\Support\Collection;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * CollectionTest — covers Collection in isolation (no DB needed).
 */
class CollectionTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeItems(): Collection
    {
        return Collection::make([
            ['id' => 1, 'name' => 'Alice', 'role' => 'admin', 'age' => 30, 'score' => 90.0],
            ['id' => 2, 'name' => 'Bob',   'role' => 'user',  'age' => 25, 'score' => 50.0],
            ['id' => 3, 'name' => 'Carol', 'role' => 'user',  'age' => 20, 'score' => 70.0],
            ['id' => 4, 'name' => 'Dave',  'role' => 'mod',   'age' => 40, 'score' => 60.0],
        ]);
    }

    // -----------------------------------------------------------------------
    // Construction
    // -----------------------------------------------------------------------

    public function test_empty_constructor(): void
    {
        $c = new Collection();
        $this->assertCount(0, $c);
        $this->assertTrue($c->isEmpty());
    }

    public function test_make_from_array_of_arrays(): void
    {
        $c = Collection::make([['id' => 1], ['id' => 2]]);
        $this->assertCount(2, $c);
        $this->assertInstanceOf(\stdClass::class, $c->get(0));
    }

    public function test_make_from_array_of_objects(): void
    {
        $a = (object) ['id' => 1];
        $b = (object) ['id' => 2];
        $c = Collection::make([$a, $b]);
        $this->assertSame($a, $c->get(0));
    }

    // -----------------------------------------------------------------------
    // Basic access
    // -----------------------------------------------------------------------

    public function test_first_no_filter(): void
    {
        $this->assertSame('Alice', $this->makeItems()->first()->name);
    }

    public function test_first_with_filter(): void
    {
        $item = $this->makeItems()->first(fn($u) => $u->role === 'mod');
        $this->assertSame('Dave', $item->name);
    }

    public function test_first_no_match_returns_null(): void
    {
        $this->assertNull($this->makeItems()->first(fn($u) => $u->role === 'superadmin'));
    }

    public function test_last_no_filter(): void
    {
        $this->assertSame('Dave', $this->makeItems()->last()->name);
    }

    public function test_last_with_filter(): void
    {
        $item = $this->makeItems()->last(fn($u) => $u->role === 'user');
        $this->assertSame('Carol', $item->name);
    }

    public function test_get_by_index(): void
    {
        $this->assertSame('Bob', $this->makeItems()->get(1)->name);
    }

    public function test_get_out_of_bounds_returns_null(): void
    {
        $this->assertNull($this->makeItems()->get(999));
    }

    public function test_all_returns_raw_array(): void
    {
        $all = $this->makeItems()->all();
        $this->assertIsArray($all);
        $this->assertCount(4, $all);
    }

    public function test_is_not_empty(): void
    {
        $this->assertTrue($this->makeItems()->isNotEmpty());
        $this->assertFalse((new Collection())->isNotEmpty());
    }

    // -----------------------------------------------------------------------
    // Transformation
    // -----------------------------------------------------------------------

    public function test_filter(): void
    {
        $users = $this->makeItems()->filter(fn($u) => $u->role === 'user');
        $this->assertCount(2, $users);
        $this->assertSame('Bob', $users->get(0)->name);
    }

    public function test_reject(): void
    {
        $result = $this->makeItems()->reject(fn($u) => $u->role === 'user');
        $this->assertCount(2, $result);
        foreach ($result->all() as $item) {
            $this->assertNotSame('user', $item->role);
        }
    }

    public function test_map(): void
    {
        $names = $this->makeItems()->map(fn($u) => (object) ['name' => strtoupper($u->name)]);
        $this->assertSame('ALICE', $names->get(0)->name);
    }

    public function test_each_iterates_all_items(): void
    {
        $count = 0;
        $this->makeItems()->each(function ($item, $i) use (&$count) {
            $count++;
        });
        $this->assertSame(4, $count);
    }

    public function test_each_stops_on_false(): void
    {
        $count = 0;
        $this->makeItems()->each(function ($item, $i) use (&$count) {
            $count++;
            if ($i === 1) return false;
        });
        $this->assertSame(2, $count);
    }

    public function test_reduce(): void
    {
        $total = $this->makeItems()->reduce(fn($carry, $u) => $carry + $u->score, 0);
        $this->assertSame(270.0, $total);
    }

    // -----------------------------------------------------------------------
    // contains
    // -----------------------------------------------------------------------

    public function test_contains_with_closure(): void
    {
        $this->assertTrue($this->makeItems()->contains(fn($u) => $u->role === 'admin'));
        $this->assertFalse($this->makeItems()->contains(fn($u) => $u->role === 'ghost'));
    }

    public function test_contains_with_column_and_value(): void
    {
        $this->assertTrue($this->makeItems()->contains('name', 'Alice'));
        $this->assertFalse($this->makeItems()->contains('name', 'Nobody'));
    }

    /**
     * Regression: contains('key', ...) must compare against the 'key' column,
     * not treat 'key' as a callable. is_callable('key') returns true because
     * key() is a built-in PHP function.
     */
    public function test_contains_with_php_function_named_column(): void
    {
        $items = Collection::make([
            ['id' => 1, 'key' => 'footer'],
            ['id' => 2, 'key' => 'header'],
        ]);

        $this->assertTrue($items->contains('key', 'footer'));
        $this->assertFalse($items->contains('key', 'sidebar'));
    }

    // -----------------------------------------------------------------------
    // Pluck / keyBy / groupBy
    // -----------------------------------------------------------------------

    public function test_pluck(): void
    {
        $names = $this->makeItems()->pluck('name');
        $this->assertSame(['Alice', 'Bob', 'Carol', 'Dave'], $names);
    }

    public function test_pluck_with_key_column(): void
    {
        $map = $this->makeItems()->pluck('name', 'id');
        $this->assertSame('Alice', $map[1]);
        $this->assertSame('Dave', $map[4]);
    }

    public function test_key_by(): void
    {
        $map = $this->makeItems()->keyBy('id');
        $this->assertSame('Bob', $map[2]->name);
    }

    public function test_group_by(): void
    {
        $groups = $this->makeItems()->groupBy('role');
        $this->assertCount(2, $groups['user']);
        $this->assertCount(1, $groups['admin']);
    }

    // -----------------------------------------------------------------------
    // Aggregates
    // -----------------------------------------------------------------------

    public function test_sum(): void
    {
        $this->assertSame(270.0, $this->makeItems()->sum('score'));
    }

    public function test_avg(): void
    {
        $this->assertSame(67.5, $this->makeItems()->avg('score'));
    }

    public function test_min(): void
    {
        $this->assertSame(50.0, $this->makeItems()->min('score'));
    }

    public function test_max(): void
    {
        $this->assertSame(90.0, $this->makeItems()->max('score'));
    }

    public function test_sum_empty_collection(): void
    {
        // array_sum([]) returns int 0 in PHP — both 0 and 0.0 are acceptable
        $result = (new Collection())->sum('score');
        $this->assertSame(0, $result);
    }

    public function test_min_empty_returns_null(): void
    {
        $this->assertNull((new Collection())->min('score'));
    }

    // -----------------------------------------------------------------------
    // Sorting / slicing
    // -----------------------------------------------------------------------

    public function test_sort_by_asc(): void
    {
        $sorted = $this->makeItems()->sortBy('age');
        $this->assertSame('Carol', $sorted->first()->name);
    }

    public function test_sort_by_desc(): void
    {
        $sorted = $this->makeItems()->sortByDesc('score');
        $this->assertSame('Alice', $sorted->first()->name);
    }

    public function test_take(): void
    {
        $taken = $this->makeItems()->take(2);
        $this->assertCount(2, $taken);
        $this->assertSame('Alice', $taken->get(0)->name);
    }

    public function test_skip(): void
    {
        $skipped = $this->makeItems()->skip(2);
        $this->assertCount(2, $skipped);
        $this->assertSame('Carol', $skipped->get(0)->name);
    }

    public function test_chunk(): void
    {
        $chunks = $this->makeItems()->chunk(3);
        $this->assertCount(2, $chunks);
        $this->assertCount(3, $chunks[0]);
        $this->assertCount(1, $chunks[1]);
    }

    public function test_chunk_invalid_size_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->makeItems()->chunk(0);
    }

    public function test_unique(): void
    {
        $result = $this->makeItems()->unique('role');
        // admin, user, mod = 3 unique roles
        $this->assertCount(3, $result);
    }

    public function test_reverse(): void
    {
        $reversed = $this->makeItems()->reverse();
        $this->assertSame('Dave', $reversed->first()->name);
    }

    public function test_merge(): void
    {
        $a = Collection::make([['id' => 1]]);
        $b = Collection::make([['id' => 2]]);
        $merged = $a->merge($b);
        $this->assertCount(2, $merged);
    }

    // -----------------------------------------------------------------------
    // toArray / toJson / JsonSerializable
    // -----------------------------------------------------------------------

    public function test_to_array_on_stdclass_items(): void
    {
        $arr = $this->makeItems()->toArray();
        $this->assertIsArray($arr);
        $this->assertArrayHasKey('name', $arr[0]);
        $this->assertSame('Alice', $arr[0]['name']);
    }

    /**
     * This is the regression test for the null-byte bug:
     * Collection::toArray() must call Model::toArray(), not (array) cast.
     */
    public function test_to_array_on_model_items_no_null_byte_keys(): void
    {
        // Build a minimal anonymous model populated with attributes
        $model = new class (['id' => 1, 'name' => 'Alice']) extends Model {
            protected string $table   = 'users';
            protected array  $guarded = [];
        };
        // Simulate hydration (as Builder does after a real DB query)
        $model->syncOriginal();

        $collection = new Collection([$model]);
        $arr        = $collection->toArray();

        // Must NOT contain null-byte keys like "\u0000*\u0000table"
        foreach (array_keys($arr[0]) as $key) {
            $this->assertStringNotContainsString("\0", $key, "Null-byte key found: {$key}");
        }
        $this->assertSame('Alice', $arr[0]['name']);
    }

    public function test_json_serialize_produces_correct_json(): void
    {
        $c   = Collection::make([['id' => 1, 'name' => 'Alice']]);
        $arr = json_decode((string) json_encode($c), true);
        $this->assertSame('Alice', $arr[0]['name']);
    }

    public function test_to_string_is_valid_json(): void
    {
        $c    = Collection::make([['id' => 1]]);
        $json = (string) $c;
        $this->assertJson($json);
    }

    // -----------------------------------------------------------------------
    // ArrayAccess
    // -----------------------------------------------------------------------

    public function test_offset_exists(): void
    {
        $c = $this->makeItems();
        $this->assertTrue(isset($c[0]));
        $this->assertFalse(isset($c[99]));
    }

    public function test_offset_get(): void
    {
        $this->assertSame('Alice', $this->makeItems()[0]->name);
    }

    public function test_offset_set_throws(): void
    {
        $this->expectException(LogicException::class);
        $c    = $this->makeItems();
        $c[0] = (object) ['name' => 'X'];
    }

    public function test_offset_unset_throws(): void
    {
        $this->expectException(LogicException::class);
        $c = $this->makeItems();
        unset($c[0]);
    }

    // -----------------------------------------------------------------------
    // IteratorAggregate
    // -----------------------------------------------------------------------

    public function test_foreach_iterates(): void
    {
        $names = [];
        foreach ($this->makeItems() as $item) {
            $names[] = $item->name;
        }
        $this->assertSame(['Alice', 'Bob', 'Carol', 'Dave'], $names);
    }
}