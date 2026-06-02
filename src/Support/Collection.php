<?php

declare(strict_types=1);

namespace Foxdb\Support;

use ArrayAccess;
use ArrayIterator;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * Collection — a fluent wrapper around an array of row objects.
 *
 * Returned by Builder::get() and Model query methods.
 * All transformation methods return a new Collection instance
 * (immutable pipeline) leaving the original untouched.
 *
 * Usage:
 *   $users = DB::table('users')->get();   // Collection
 *
 *   $admins = $users
 *       ->filter(fn($u) => $u->role === 'admin')
 *       ->sortBy('name')
 *       ->take(5);
 *
 *   $names = $users->pluck('name');
 *   $map   = $users->keyBy('id');
 *
 * @template TValue of object
 * @implements ArrayAccess<int, TValue>
 * @implements IteratorAggregate<int, TValue>
 */
class Collection implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    /**
     * The underlying array of row objects.
     *
     * @var array<int, object>
     */
    protected array $items;

    /**
     * @param array<int, object> $items
     */
    public function __construct(array $items = [])
    {
        $this->items = array_values($items);
    }

    // -----------------------------------------------------------------------
    // Factory
    // -----------------------------------------------------------------------

    /**
     * Create a new Collection from an array of objects (or arrays auto-cast to stdClass).
     *
     * @param  array<int, object|array<string, mixed>> $items
     * @return static
     */
    public static function make(array $items = []): static
    {
        return new static(array_map(
            fn(mixed $item) => is_array($item) ? (object) $item : $item,
            $items,
        ));
    }

    // -----------------------------------------------------------------------
    // Basic access
    // -----------------------------------------------------------------------

    /**
     * Get all items as a plain array.
     *
     * @return array<int, object>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Get the first item, optionally matching a filter callback.
     * Returns null when the collection is empty or no item matches.
     *
     * @param  callable(object): bool|null $filter
     * @return object|null
     */
    public function first(?callable $filter = null): ?object
    {
        if ($filter === null) {
            return $this->items[0] ?? null;
        }

        foreach ($this->items as $item) {
            if ($filter($item)) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Get the last item, optionally matching a filter callback.
     * Returns null when the collection is empty or no item matches.
     *
     * @param  callable(object): bool|null $filter
     * @return object|null
     */
    public function last(?callable $filter = null): ?object
    {
        if ($filter === null) {
            return empty($this->items) ? null : end($this->items);
        }

        $found = null;
        foreach ($this->items as $item) {
            if ($filter($item)) {
                $found = $item;
            }
        }

        return $found;
    }

    /**
     * Get the item at the given zero-based index, or null if out of bounds.
     *
     * @param  int $index
     * @return object|null
     */
    public function get(int $index): ?object
    {
        return $this->items[$index] ?? null;
    }

    // -----------------------------------------------------------------------
    // Transformation — always returns a new Collection
    // -----------------------------------------------------------------------

    /**
     * Filter items using a callback. Returns a new Collection.
     *
     * @param  callable(object): bool $callback
     * @return static
     */
    public function filter(callable $callback): static
    {
        return new static(array_values(array_filter($this->items, $callback)));
    }

    /**
     * Reject items that match the callback (inverse of filter). Returns a new Collection.
     *
     * @param  callable(object): bool $callback
     * @return static
     */
    public function reject(callable $callback): static
    {
        return $this->filter(fn(object $item) => ! $callback($item));
    }

    /**
     * Apply a callback to each item. Returns a new Collection of the mapped values.
     * If the callback returns a non-object, it is cast to stdClass.
     *
     * @param  callable(object): mixed $callback
     * @return static
     */
    public function map(callable $callback): static
    {
        $result = array_map($callback, $this->items);

        return new static(array_map(
            fn(mixed $v) => is_object($v) ? $v : (object) ['value' => $v],
            $result,
        ));
    }

    /**
     * Apply a callback to each item and flatten one level.
     * The callback must return an array or Collection.
     *
     * @param  callable(object): array<int, object>|static $callback
     * @return static
     */
    public function flatMap(callable $callback): static
    {
        $result = [];

        foreach ($this->items as $item) {
            $mapped = $callback($item);
            $arr    = $mapped instanceof static ? $mapped->all() : (array) $mapped;
            array_push($result, ...$arr);
        }

        return new static($result);
    }

    /**
     * Iterate over each item, calling the callback. Returns $this (not a new instance).
     *
     * @param  callable(object, int): mixed $callback  Return false to stop iteration.
     * @return static
     */
    public function each(callable $callback): static
    {
        foreach ($this->items as $index => $item) {
            if ($callback($item, $index) === false) {
                break;
            }
        }

        return $this;
    }

    /**
     * Reduce the collection to a single value.
     *
     * @param  callable(mixed, object): mixed $callback
     * @param  mixed                          $initial
     * @return mixed
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return array_reduce($this->items, $callback, $initial);
    }

    // -----------------------------------------------------------------------
    // Pluck / key / group
    // -----------------------------------------------------------------------

    /**
     * Extract a single column as a plain array.
     * Optionally key the result by another column.
     *
     * @param  string      $column
     * @param  string|null $keyColumn
     * @return array<int|string, mixed>
     */
    public function pluck(string $column, ?string $keyColumn = null): array
    {
        if ($keyColumn !== null) {
            $result = [];
            foreach ($this->items as $item) {
                $result[$item->{$keyColumn}] = $item->{$column};
            }
            return $result;
        }

        return array_map(fn(object $item) => $item->{$column}, $this->items);
    }

    /**
     * Key the collection by a column value. Returns a plain array (not a Collection).
     * Duplicate keys keep the last occurrence.
     *
     * @param  string $column
     * @return array<string|int, object>
     */
    public function keyBy(string $column): array
    {
        $result = [];

        foreach ($this->items as $item) {
            $result[$item->{$column}] = $item;
        }

        return $result;
    }

    /**
     * Group items by a column value. Returns a plain array of arrays.
     *
     * @param  string $column
     * @return array<string|int, array<int, object>>
     */
    public function groupBy(string $column): array
    {
        $result = [];

        foreach ($this->items as $item) {
            $key            = $item->{$column};
            $result[$key][] = $item;
        }

        return $result;
    }

    // -----------------------------------------------------------------------
    // Counting / inspection
    // -----------------------------------------------------------------------

    /**
     * Return the number of items in the collection.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Determine whether the collection is empty.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Determine whether the collection has at least one item.
     *
     * @return bool
     */
    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    /**
     * Determine whether the collection contains an item matching the given criteria.
     *
     * Callable form : ->contains(fn($u) => $u->role === 'admin')
     * Column form   : ->contains('role', 'admin')
     *
     * @param  callable(object): bool|string $callbackOrColumn
     * @param  mixed                         $value
     * @return bool
     */
    public function contains(callable|string $callbackOrColumn, mixed $value = null): bool
    {
        if (is_callable($callbackOrColumn)) {
            foreach ($this->items as $item) {
                if ($callbackOrColumn($item)) {
                    return true;
                }
            }
            return false;
        }

        foreach ($this->items as $item) {
            if (($item->{$callbackOrColumn} ?? null) == $value) {
                return true;
            }
        }

        return false;
    }

    // -----------------------------------------------------------------------
    // Aggregates
    // -----------------------------------------------------------------------

    /**
     * Sum a numeric column across all items.
     *
     * @param  string $column
     * @return float|int
     */
    public function sum(string $column): float|int
    {
        return array_sum(
            array_map(fn(object $item) => (float) ($item->{$column} ?? 0), $this->items)
        );
    }

    /**
     * Calculate the average of a numeric column.
     * Returns 0.0 for an empty collection.
     *
     * @param  string $column
     * @return float
     */
    public function avg(string $column): float
    {
        if ($this->isEmpty()) {
            return 0.0;
        }

        return $this->sum($column) / $this->count();
    }

    /**
     * Get the minimum value of a column.
     * Returns null for an empty collection.
     *
     * @param  string $column
     * @return mixed
     */
    public function min(string $column): mixed
    {
        if ($this->isEmpty()) {
            return null;
        }

        return min(array_map(fn(object $item) => $item->{$column} ?? null, $this->items));
    }

    /**
     * Get the maximum value of a column.
     * Returns null for an empty collection.
     *
     * @param  string $column
     * @return mixed
     */
    public function max(string $column): mixed
    {
        if ($this->isEmpty()) {
            return null;
        }

        return max(array_map(fn(object $item) => $item->{$column} ?? null, $this->items));
    }

    // -----------------------------------------------------------------------
    // Sorting / slicing / uniqueness
    // -----------------------------------------------------------------------

    /**
     * Sort the collection by a column. Returns a new Collection.
     *
     * @param  string $column
     * @param  string $direction  'asc' | 'desc'
     * @return static
     */
    public function sortBy(string $column, string $direction = 'asc'): static
    {
        $items = $this->items;

        usort($items, function (object $a, object $b) use ($column, $direction): int {
            $va = $a->{$column} ?? null;
            $vb = $b->{$column} ?? null;

            $cmp = $va <=> $vb;

            return strtolower($direction) === 'desc' ? -$cmp : $cmp;
        });

        return new static($items);
    }

    /**
     * Sort the collection by a column descending. Returns a new Collection.
     *
     * @param  string $column
     * @return static
     */
    public function sortByDesc(string $column): static
    {
        return $this->sortBy($column, 'desc');
    }

    /**
     * Sort using a custom comparator callback. Returns a new Collection.
     *
     * @param  callable(object, object): int $callback
     * @return static
     */
    public function sortWith(callable $callback): static
    {
        $items = $this->items;
        usort($items, $callback);

        return new static($items);
    }

    /**
     * Return only unique items by a column. Keeps first occurrence.
     * Returns a new Collection.
     *
     * @param  string $column
     * @return static
     */
    public function unique(string $column): static
    {
        $seen  = [];
        $items = [];

        foreach ($this->items as $item) {
            $key = $item->{$column} ?? null;

            if (! in_array($key, $seen, strict: true)) {
                $seen[]  = $key;
                $items[] = $item;
            }
        }

        return new static($items);
    }

    /**
     * Take the first $n items. Returns a new Collection.
     *
     * @param  int $n
     * @return static
     */
    public function take(int $n): static
    {
        return new static(array_slice($this->items, 0, max(0, $n)));
    }

    /**
     * Skip the first $n items. Returns a new Collection.
     *
     * @param  int $n
     * @return static
     */
    public function skip(int $n): static
    {
        return new static(array_slice($this->items, max(0, $n)));
    }

    /**
     * Split the collection into chunks of $size items.
     * Returns an array of Collection instances.
     *
     * @param  int $size
     * @return array<int, static>
     * @throws InvalidArgumentException When $size < 1
     */
    public function chunk(int $size): array
    {
        if ($size < 1) {
            throw new InvalidArgumentException("Chunk size must be at least 1, got {$size}.");
        }

        return array_map(
            fn(array $chunk) => new static($chunk),
            array_chunk($this->items, $size),
        );
    }

    /**
     * Merge another Collection or array into this one. Returns a new Collection.
     *
     * @param  static|array<int, object> $other
     * @return static
     */
    public function merge(self|array $other): static
    {
        $otherItems = $other instanceof self ? $other->all() : $other;

        return new static(array_merge($this->items, $otherItems));
    }

    /**
     * Reverse the order of items. Returns a new Collection.
     *
     * @return static
     */
    public function reverse(): static
    {
        return new static(array_reverse($this->items));
    }

    /**
     * Return items at the given numeric indices. Returns a new Collection.
     *
     * @param  int ...$indices
     * @return static
     */
    public function only(int ...$indices): static
    {
        $items = [];

        foreach ($indices as $i) {
            if (isset($this->items[$i])) {
                $items[] = $this->items[$i];
            }
        }

        return new static($items);
    }

    // -----------------------------------------------------------------------
    // Conversion
    // -----------------------------------------------------------------------

    /**
     * Convert each item to an associative array. Returns a plain array of arrays.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(fn(object $item) => (array) $item, $this->items);
    }

    /**
     * Serialize the collection to a JSON string.
     *
     * @param  int $flags  json_encode flags
     * @return string
     */
    public function toJson(int $flags = 0): string
    {
        return (string) json_encode($this->toArray(), $flags);
    }

    /**
     * Return data for json_encode() — implements JsonSerializable.
     *
     * @return array<int, array<string, mixed>>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    // -----------------------------------------------------------------------
    // ArrayAccess
    // -----------------------------------------------------------------------

    /**
     * @param  int $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    /**
     * @param  int $offset
     * @return object|null
     */
    public function offsetGet(mixed $offset): ?object
    {
        return $this->items[$offset] ?? null;
    }

    /**
     * Collections are immutable via array access — this always throws.
     *
     * @throws \LogicException
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('Collection items cannot be set via array access. Use merge() instead.');
    }

    /**
     * Collections are immutable via array access — this always throws.
     *
     * @throws \LogicException
     */
    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('Collection items cannot be unset via array access. Use filter() instead.');
    }

    // -----------------------------------------------------------------------
    // IteratorAggregate
    // -----------------------------------------------------------------------

    /**
     * Allow foreach iteration over the collection.
     *
     * @return Traversable<int, object>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    // -----------------------------------------------------------------------
    // Magic
    // -----------------------------------------------------------------------

    /**
     * Return a human-readable representation for debugging.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
