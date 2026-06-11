<?php

declare(strict_types=1);

namespace Foxdb\Tests\Unit;

use Foxdb\Eloquent\Model;
use Foxdb\Support\Collection;
use PHPUnit\Framework\TestCase;

/**
 * ModelTest — tests Model logic that requires no database connection.
 * Covers: fill, guard, dirty tracking, casts, toArray, toJson, hidden.
 */
class ModelTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /** @return Model anonymous instance with common cast/hidden config */
    private function makeModel(array $attributes = []): Model
    {
        return new class ($attributes) extends Model {
            protected string $table    = 'users';
            protected array  $fillable = ['name', 'email', 'age', 'is_active', 'score', 'settings'];
            protected array  $hidden   = ['email'];
            protected array  $casts    = [
                'age'       => 'int',
                'is_active' => 'bool',
                'score'     => 'float',
                'settings'  => 'array',
            ];
        };
    }

    // -----------------------------------------------------------------------
    // Table name
    // -----------------------------------------------------------------------

    public function test_explicit_table_name(): void
    {
        $m = $this->makeModel();
        $this->assertSame('users', $m->getTable());
    }

    public function test_auto_derived_table_name(): void
    {
        $m = new class extends Model {
            protected array $guarded = [];
        };
        // Anonymous class name is unpredictable; use a named subclass instead
        $m2 = new class extends Model {
            protected array $guarded = [];
        };
        // Just verify getTable() returns a non-empty string and doesn't throw
        $this->assertNotEmpty($m->getTable());
    }

    // -----------------------------------------------------------------------
    // Fill / guard
    // -----------------------------------------------------------------------

    public function test_fill_respects_fillable(): void
    {
        $m = $this->makeModel(['name' => 'Alice', 'email' => 'a@test.com']);
        $this->assertSame('Alice', $m->name);
        $this->assertSame('a@test.com', $m->email);
    }

    public function test_fill_blocks_non_fillable_column(): void
    {
        $m = new class (['secret' => 'blocked']) extends Model {
            protected string $table    = 'users';
            protected array  $fillable = ['name'];
        };
        $this->assertNull($m->getAttribute('secret'));
    }

    public function test_guarded_star_blocks_everything_except_fillable(): void
    {
        $m = new class (['name' => 'Alice', 'id' => 99]) extends Model {
            protected string $table    = 'users';
            protected array  $fillable = ['name'];
            protected array  $guarded  = ['*'];
        };
        $this->assertSame('Alice', $m->name);
        $this->assertNull($m->getAttribute('id'));
    }

    public function test_empty_guarded_allows_all(): void
    {
        $m = new class (['name' => 'Alice', 'role' => 'admin']) extends Model {
            protected string $table   = 'users';
            protected array  $guarded = [];
        };
        $this->assertSame('admin', $m->getAttribute('role'));
    }

    public function test_force_fill_bypasses_guard(): void
    {
        $m = new class extends Model {
            protected string $table    = 'users';
            protected array  $fillable = ['name'];
        };
        $m->forceFill(['name' => 'Alice', 'id' => 99]);
        $this->assertSame(99, $m->getAttribute('id'));
    }

    // -----------------------------------------------------------------------
    // Attribute get / set
    // -----------------------------------------------------------------------

    public function test_magic_get_and_set(): void
    {
        $m       = $this->makeModel();
        $m->name = 'Bob';
        $this->assertSame('Bob', $m->name);
    }

    public function test_isset_and_unset(): void
    {
        $m       = $this->makeModel(['name' => 'Alice']);
        $this->assertTrue(isset($m->name));
        unset($m->name);
        $this->assertFalse(isset($m->name));
    }

    public function test_get_attribute_returns_null_for_missing_key(): void
    {
        $m = $this->makeModel();
        $this->assertNull($m->getAttribute('nonexistent'));
    }

    // -----------------------------------------------------------------------
    // Dirty tracking
    // -----------------------------------------------------------------------

    public function test_is_dirty_after_change(): void
    {
        $m = $this->makeModel(['name' => 'Alice']);
        $m->syncOriginal();
        $this->assertFalse($m->isDirty());

        $m->name = 'Bob';
        $this->assertTrue($m->isDirty());
        $this->assertTrue($m->isDirty('name'));
        $this->assertFalse($m->isDirty('email'));
    }

    public function test_get_dirty_returns_changed_attributes(): void
    {
        $m = $this->makeModel(['name' => 'Alice', 'age' => 25]);
        $m->syncOriginal();
        $m->name = 'Bob';

        $dirty = $m->getDirty();
        $this->assertArrayHasKey('name', $dirty);
        $this->assertArrayNotHasKey('age', $dirty);
    }

    public function test_sync_original_clears_dirty(): void
    {
        $m = $this->makeModel(['name' => 'Alice']);
        $m->name = 'Bob';
        $m->syncOriginal();
        $this->assertFalse($m->isDirty());
    }

    // -----------------------------------------------------------------------
    // Casts
    // -----------------------------------------------------------------------

    public function test_cast_int(): void
    {
        $m = $this->makeModel(['age' => '25']);
        $this->assertSame(25, $m->age);
        $this->assertIsInt($m->age);
    }

    public function test_cast_bool(): void
    {
        $m = $this->makeModel(['is_active' => '1']);
        $this->assertTrue($m->is_active);
        $this->assertIsBool($m->is_active);
    }

    public function test_cast_float(): void
    {
        $m = $this->makeModel(['score' => '99']);
        $this->assertSame(99.0, $m->score);
        $this->assertIsFloat($m->score);
    }

    public function test_cast_array_from_json_string(): void
    {
        $m = $this->makeModel(['settings' => '{"theme":"dark"}']);
        $this->assertIsArray($m->settings);
        $this->assertSame('dark', $m->settings['theme']);
    }

    public function test_cast_null_value_is_not_cast(): void
    {
        $m = $this->makeModel(['settings' => null]);
        $this->assertNull($m->settings);
    }

    public function test_has_cast(): void
    {
        $m = $this->makeModel();
        $this->assertTrue($m->hasCast('age'));
        $this->assertFalse($m->hasCast('name'));
    }

    // -----------------------------------------------------------------------
    // toArray / hidden / toJson
    // -----------------------------------------------------------------------

    public function test_to_array_contains_attributes(): void
    {
        $m   = $this->makeModel(['name' => 'Alice', 'email' => 'a@test.com', 'age' => '30']);
        $arr = $m->toArray();

        $this->assertArrayHasKey('name', $arr);
        $this->assertArrayHasKey('age', $arr);
    }

    public function test_to_array_excludes_hidden_fields(): void
    {
        $m   = $this->makeModel(['name' => 'Alice', 'email' => 'secret@test.com']);
        $arr = $m->toArray();

        $this->assertArrayNotHasKey('email', $arr);
    }

    public function test_to_array_no_null_byte_keys(): void
    {
        $m   = $this->makeModel(['name' => 'Alice']);
        $arr = $m->toArray();

        foreach (array_keys($arr) as $key) {
            $this->assertStringNotContainsString("\0", $key, "Null-byte key: {$key}");
        }
    }

    public function test_to_json_is_valid_json(): void
    {
        $m    = $this->makeModel(['name' => 'Alice', 'age' => '25']);
        $json = $m->toJson();

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertSame('Alice', $decoded['name']);
    }

    public function test_to_string_returns_json(): void
    {
        $m = $this->makeModel(['name' => 'Alice']);
        $this->assertJson((string) $m);
    }

    public function test_to_array_applies_casts(): void
    {
        $m   = $this->makeModel(['age' => '30', 'is_active' => '1']);
        $arr = $m->toArray();

        $this->assertIsInt($arr['age']);
        $this->assertIsBool($arr['is_active']);
    }

    // -----------------------------------------------------------------------
    // exists / getKey
    // -----------------------------------------------------------------------

    public function test_new_model_does_not_exist(): void
    {
        $m = $this->makeModel();
        $this->assertFalse($m->isExists());
    }

    public function test_get_key_returns_primary_key_value(): void
    {
        $m = $this->makeModel();
        $m->forceFill(['id' => 42]);
        $this->assertSame(42, $m->getKey());
    }

    // -----------------------------------------------------------------------
    // fromRow (hydration)
    // -----------------------------------------------------------------------

    public function test_from_row_marks_model_as_existing(): void
    {
        $model = new class extends Model {
            protected string $table   = 'users';
            protected array  $guarded = [];
        };
        $hydrated = $model::fromRow((object) ['id' => 1, 'name' => 'Alice']);

        $this->assertTrue($hydrated->isExists());
        $this->assertSame('Alice', $hydrated->name);
        $this->assertFalse($hydrated->isDirty());
    }
}
