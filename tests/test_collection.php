<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Foxdb\Config;
use Foxdb\DB;
use Foxdb\Support\Collection;

function pass(string $msg): void { echo "\033[32m✔ {$msg}\033[0m\n"; }
function fail(string $msg): void { echo "\033[31m✘ {$msg}\033[0m\n"; exit(1); }

echo "\n=== FoxDB Collection Tests (MySQL) ===\n\n";

// -----------------------------------------------------------------------
// Helpers
// -----------------------------------------------------------------------
function makeUsers(): Collection
{
    return Collection::make([
        ['id' => 1, 'name' => 'Alice',   'role' => 'admin', 'age' => 30, 'score' => 100.0, 'active' => 1],
        ['id' => 2, 'name' => 'Bob',     'role' => 'user',  'age' => 25, 'score' => 50.0,  'active' => 1],
        ['id' => 3, 'name' => 'Charlie', 'role' => 'user',  'age' => 20, 'score' => 30.0,  'active' => 0],
        ['id' => 4, 'name' => 'Dave',    'role' => 'mod',   'age' => 40, 'score' => 70.0,  'active' => 1],
        ['id' => 5, 'name' => 'Eve',     'role' => 'user',  'age' => 35, 'score' => 80.0,  'active' => 1],
    ]);
}

// -----------------------------------------------------------------------
// 1. Construction
// -----------------------------------------------------------------------
$c = new Collection();
if ($c->count() !== 0) fail('Empty constructor should give count 0');
pass('new Collection() — empty');

$c = Collection::make([
    ['id' => 1, 'name' => 'Alice'],
    ['id' => 2, 'name' => 'Bob'],
]);
if ($c->count() !== 2) fail('Collection::make() count wrong');
if (! ($c->get(0) instanceof stdClass)) fail('Collection::make() items should be stdClass');
pass('Collection::make() from array-of-arrays');

// -----------------------------------------------------------------------
// 2. all()
// -----------------------------------------------------------------------
$all = makeUsers()->all();
if (count($all) !== 5) fail('all() count wrong');
pass('all() returns raw array');

// -----------------------------------------------------------------------
// 3. first() / last()
// -----------------------------------------------------------------------
$users = makeUsers();

$first = $users->first();
if ($first === null || $first->name !== 'Alice') fail("first() wrong: {$first?->name}");
pass('first() — no filter');

$first = $users->first(fn($u) => $u->role === 'mod');
if ($first === null || $first->name !== 'Dave') fail("first(filter) wrong: {$first?->name}");
pass('first(callable filter)');

$none = $users->first(fn($u) => $u->role === 'superadmin');
if ($none !== null) fail('first() with no match should return null');
pass('first() — no match returns null');

$last = $users->last();
if ($last === null || $last->name !== 'Eve') fail("last() wrong: {$last?->name}");
pass('last() — no filter');

$last = $users->last(fn($u) => $u->role === 'user');
if ($last === null || $last->name !== 'Eve') fail("last(filter) wrong: {$last?->name}");
pass('last(callable filter)');

// -----------------------------------------------------------------------
// 4. get() by index
// -----------------------------------------------------------------------
$item = $users->get(2);
if ($item === null || $item->name !== 'Charlie') fail("get(2) wrong: {$item?->name}");
pass('get(index)');

if ($users->get(999) !== null) fail('get() out of bounds should return null');
pass('get() out of bounds → null');

// -----------------------------------------------------------------------
// 5. count() / isEmpty() / isNotEmpty()
// -----------------------------------------------------------------------
if ($users->count() !== 5) fail('count() wrong');
pass('count()');

if ($users->isEmpty()) fail('isEmpty() should be false');
pass('isEmpty() false on non-empty');

if (! (new Collection())->isEmpty()) fail('isEmpty() should be true on empty');
pass('isEmpty() true on empty');

if (! $users->isNotEmpty()) fail('isNotEmpty() should be true');
pass('isNotEmpty()');

// -----------------------------------------------------------------------
// 6. filter()
// -----------------------------------------------------------------------
$active = $users->filter(fn($u) => $u->active === 1);
if ($active->count() !== 4) fail("filter() expected 4, got {$active->count()}");
if ($users->count() !== 5) fail('filter() mutated original — should be immutable');
pass('filter() — immutable, returns new Collection');

$admins = $users->filter(fn($u) => $u->role === 'admin');
if ($admins->count() !== 1 || $admins->first()->name !== 'Alice') fail('filter() wrong result');
pass('filter() by role');

// -----------------------------------------------------------------------
// 7. reject()
// -----------------------------------------------------------------------
$inactive = $users->reject(fn($u) => $u->active === 1);
if ($inactive->count() !== 1) fail("reject() expected 1, got {$inactive->count()}");
pass('reject() — inverse of filter');

// -----------------------------------------------------------------------
// 8. map()
// -----------------------------------------------------------------------
$names = $users->map(fn($u) => (object)['name' => strtoupper($u->name)]);
if ($names->first()->name !== 'ALICE') fail("map() wrong: {$names->first()->name}");
if ($users->first()->name !== 'Alice') fail('map() mutated original');
pass('map() — immutable transformation');

// -----------------------------------------------------------------------
// 9. each()
// -----------------------------------------------------------------------
$visited = [];
$result  = $users->each(function ($u) use (&$visited) { $visited[] = $u->name; });
if (count($visited) !== 5) fail("each() visited " . count($visited) . " items");
if (! ($result instanceof Collection)) fail('each() should return $this');
pass('each() iterates all items, returns static');

// each() stops on false
$stopped = [];
$users->each(function ($u) use (&$stopped) {
    $stopped[] = $u->name;
    if ($u->name === 'Bob') return false;
});
if (count($stopped) !== 2) fail("each() stop on false: expected 2, got " . count($stopped));
pass('each() stops when callback returns false');

// -----------------------------------------------------------------------
// 10. reduce()
// -----------------------------------------------------------------------
$total = $users->reduce(fn($carry, $u) => $carry + $u->score, 0);
if ((float)$total !== 330.0) fail("reduce() expected 330, got {$total}");
pass('reduce()');

// -----------------------------------------------------------------------
// 11. flatMap()
// -----------------------------------------------------------------------
$doubled = $users->take(2)->flatMap(fn($u) => [$u, $u]);
if ($doubled->count() !== 4) fail("flatMap() expected 4, got {$doubled->count()}");
pass('flatMap()');

// -----------------------------------------------------------------------
// 12. pluck()
// -----------------------------------------------------------------------
$names = $users->pluck('name');
if ($names !== ['Alice', 'Bob', 'Charlie', 'Dave', 'Eve']) {
    fail("pluck() wrong: " . json_encode($names));
}
pass('pluck(column)');

$map = $users->pluck('name', 'id');
$alice = $map[1] ?? $map['1'] ?? null;
$dave  = $map[4] ?? $map['4'] ?? null;
if ($alice !== 'Alice' || $dave !== 'Dave') {
    fail("pluck(col, key) wrong: " . json_encode($map));
}
pass('pluck(column, keyColumn)');

// -----------------------------------------------------------------------
// 13. keyBy()
// -----------------------------------------------------------------------
$keyed = $users->keyBy('id');
if (! isset($keyed[2]) || $keyed[2]->name !== 'Bob') fail("keyBy() wrong");
if (count($keyed) !== 5) fail("keyBy() count wrong");
pass('keyBy()');

// -----------------------------------------------------------------------
// 14. groupBy()
// -----------------------------------------------------------------------
$grouped = $users->groupBy('role');
if (! isset($grouped['user']) || count($grouped['user']) !== 3) {
    fail("groupBy() user count wrong: " . json_encode(array_map('count', $grouped)));
}
if (! isset($grouped['admin']) || count($grouped['admin']) !== 1) {
    fail("groupBy() admin count wrong");
}
pass('groupBy()');

// -----------------------------------------------------------------------
// 15. contains()
// -----------------------------------------------------------------------
if (! $users->contains(fn($u) => $u->name === 'Alice')) fail('contains(callable) should be true');
if ($users->contains(fn($u) => $u->name === 'Zara'))   fail('contains(callable) should be false');
pass('contains(callable)');

if (! $users->contains('role', 'admin')) fail("contains(col, val) should be true");
if ($users->contains('role', 'superadmin')) fail("contains(col, val) should be false");
pass('contains(column, value)');

// -----------------------------------------------------------------------
// 16. Aggregates: sum / avg / min / max
// -----------------------------------------------------------------------
$sum = $users->sum('score');
if ((float)$sum !== 330.0) fail("sum() expected 330, got {$sum}");
pass('sum()');

$avg = $users->avg('score');
if (abs((float)$avg - 66.0) > 0.001) fail("avg() expected 66, got {$avg}");
pass('avg()');

if ((new Collection())->avg('score') !== 0.0) fail('avg() empty should return 0.0');
pass('avg() on empty collection → 0.0');

$min = $users->min('age');
if ((int)$min !== 20) fail("min() expected 20, got {$min}");
pass('min()');

$max = $users->max('age');
if ((int)$max !== 40) fail("max() expected 40, got {$max}");
pass('max()');

if ((new Collection())->min('score') !== null) fail('min() empty should return null');
pass('min() on empty → null');

// -----------------------------------------------------------------------
// 17. sortBy() / sortByDesc() / sortWith()
// -----------------------------------------------------------------------
$sorted = $users->sortBy('age');
if ($sorted->first()->name !== 'Charlie') fail("sortBy() wrong first: {$sorted->first()->name}");
if ($sorted->last()->name !== 'Dave') fail("sortBy() wrong last: {$sorted->last()->name}");
if ($users->first()->name !== 'Alice') fail('sortBy() mutated original');
pass('sortBy() ascending — immutable');

$sorted = $users->sortByDesc('score');
if ($sorted->first()->name !== 'Alice') fail("sortByDesc() wrong: {$sorted->first()->name}");
pass('sortByDesc()');

$sorted = $users->sortWith(fn($a, $b) => $b->age <=> $a->age);
if ($sorted->first()->name !== 'Dave') fail("sortWith() wrong: {$sorted->first()->name}");
pass('sortWith() custom comparator');

// -----------------------------------------------------------------------
// 18. unique()
// -----------------------------------------------------------------------
$dupes = Collection::make([
    ['id' => 1, 'role' => 'admin'],
    ['id' => 2, 'role' => 'user'],
    ['id' => 3, 'role' => 'user'],
    ['id' => 4, 'role' => 'admin'],
]);
$unique = $dupes->unique('role');
if ($unique->count() !== 2) fail("unique() expected 2, got {$unique->count()}");
pass('unique() keeps first occurrence');

// -----------------------------------------------------------------------
// 19. take() / skip()
// -----------------------------------------------------------------------
$taken = $users->take(3);
if ($taken->count() !== 3) fail("take(3) wrong count");
if ($taken->last()->name !== 'Charlie') fail("take(3) wrong last: {$taken->last()->name}");
pass('take()');

$skipped = $users->skip(3);
if ($skipped->count() !== 2) fail("skip(3) wrong count: {$skipped->count()}");
if ($skipped->first()->name !== 'Dave') fail("skip(3) wrong first: {$skipped->first()->name}");
pass('skip()');

// Edge cases
if ($users->take(0)->count() !== 0) fail('take(0) should be empty');
pass('take(0) → empty');

if ($users->skip(0)->count() !== 5) fail('skip(0) should return all');
pass('skip(0) → all items');

// -----------------------------------------------------------------------
// 20. chunk()
// -----------------------------------------------------------------------
$chunks = $users->chunk(2);
if (count($chunks) !== 3) fail("chunk(2) expected 3 chunks, got " . count($chunks));
if ($chunks[0]->count() !== 2) fail("chunk(2) first chunk count wrong");
if ($chunks[2]->count() !== 1) fail("chunk(2) last chunk count wrong (remainder)");
pass('chunk() splits correctly with remainder');

try {
    $users->chunk(0);
    fail('chunk(0) should throw InvalidArgumentException');
} catch (\InvalidArgumentException) {
    pass('chunk(0) throws InvalidArgumentException');
}

// -----------------------------------------------------------------------
// 21. merge()
// -----------------------------------------------------------------------
$a = $users->take(2);
$b = $users->skip(3);
$merged = $a->merge($b);
if ($merged->count() !== 4) fail("merge() wrong count: {$merged->count()}");
pass('merge() two Collections');

$merged = $a->merge([['id' => 99, 'name' => 'Extra']]);
if ($merged->count() !== 3) fail("merge(array) wrong count: {$merged->count()}");
pass('merge() with array');

// -----------------------------------------------------------------------
// 22. reverse()
// -----------------------------------------------------------------------
$rev = $users->reverse();
if ($rev->first()->name !== 'Eve') fail("reverse() wrong first: {$rev->first()->name}");
if ($users->first()->name !== 'Alice') fail('reverse() mutated original');
pass('reverse() — immutable');

// -----------------------------------------------------------------------
// 23. only()
// -----------------------------------------------------------------------
$only = $users->only(0, 2, 4);
if ($only->count() !== 3) fail("only() wrong count: {$only->count()}");
if ($only->first()->name !== 'Alice') fail("only() first wrong");
if ($only->last()->name !== 'Eve')   fail("only() last wrong");
pass('only(indices)');

// -----------------------------------------------------------------------
// 24. toArray() / toJson() / __toString()
// -----------------------------------------------------------------------
$arr = $users->take(1)->toArray();
if (! is_array($arr)) fail('toArray() should return array');
if (! is_array($arr[0])) fail('toArray() rows should be arrays');
if (($arr[0]['name'] ?? null) !== 'Alice') fail("toArray() value wrong");
pass('toArray()');

$json = $users->take(1)->toJson();
$decoded = json_decode($json, true);
if (! is_array($decoded) || $decoded[0]['name'] !== 'Alice') fail('toJson() wrong');
pass('toJson()');

$str = (string) $users->take(1);
if (! str_contains($str, 'Alice')) fail('__toString() wrong');
pass('__toString()');

// -----------------------------------------------------------------------
// 25. JsonSerializable
// -----------------------------------------------------------------------
$json = json_encode($users->take(2));
$decoded = json_decode($json, true);
if (count($decoded) !== 2) fail("JsonSerializable wrong count");
if ($decoded[0]['name'] !== 'Alice') fail("JsonSerializable wrong value");
pass('JsonSerializable (json_encode($collection))');

// -----------------------------------------------------------------------
// 26. ArrayAccess
// -----------------------------------------------------------------------
$c = makeUsers();
if (! isset($c[0])) fail('offsetExists() wrong');
if ($c[1]->name !== 'Bob') fail("offsetGet() wrong: {$c[1]->name}");
pass('ArrayAccess: offsetExists + offsetGet');

try {
    $c[0] = (object)['id' => 99];
    fail('offsetSet() should throw LogicException');
} catch (\LogicException) {
    pass('ArrayAccess: offsetSet() throws LogicException (immutable)');
}

try {
    unset($c[0]);
    fail('offsetUnset() should throw LogicException');
} catch (\LogicException) {
    pass('ArrayAccess: offsetUnset() throws LogicException (immutable)');
}

// -----------------------------------------------------------------------
// 27. IteratorAggregate — foreach
// -----------------------------------------------------------------------
$names = [];
foreach (makeUsers() as $user) {
    $names[] = $user->name;
}
if ($names !== ['Alice', 'Bob', 'Charlie', 'Dave', 'Eve']) {
    fail("foreach iteration wrong: " . json_encode($names));
}
pass('IteratorAggregate — foreach works');

// -----------------------------------------------------------------------
// 28. Countable — count()
// -----------------------------------------------------------------------
if (count(makeUsers()) !== 5) fail('Countable: count() wrong');
pass('Countable — count($collection)');

// -----------------------------------------------------------------------
// 29. Method chaining (pipeline)
// -----------------------------------------------------------------------
$result = makeUsers()
    ->filter(fn($u) => $u->active === 1)
    ->sortBy('score', 'desc')
    ->take(3)
    ->pluck('name');

if ($result[0] !== 'Alice') fail("Pipeline first wrong: {$result[0]}");
if (count($result) !== 3) fail("Pipeline count wrong: " . count($result));
pass('Method chaining pipeline (filter → sortBy → take → pluck)');

// -----------------------------------------------------------------------
// 30. Integration: DB::table()->get() returns Collection (MySQL Version)
// -----------------------------------------------------------------------
DB::reset();
DB::addConnection([
    'driver'    => Config::MYSQL,
    'host'      => '127.0.0.1',
    'port'      => '3306',
    'database'  => 'test',
    'username'  => 'root',
    'password'  => '123456',
    'charset'   => 'utf8mb4',
]);

// Prepare clean MySQL state for integration test
DB::statement('DROP TABLE IF EXISTS `products`');
DB::statement('
    CREATE TABLE `products` (
        `id`        INT AUTO_INCREMENT PRIMARY KEY,
        `name`      VARCHAR(255) NOT NULL,
        `price`     DOUBLE DEFAULT 0,
        `category`  VARCHAR(100) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
');

DB::table('products')->insertBatch([
    ['name' => 'Apple',  'price' => 1.5,  'category' => 'fruit'],
    ['name' => 'Banana', 'price' => 0.5,  'category' => 'fruit'],
    ['name' => 'Carrot', 'price' => 0.8,  'category' => 'veggie'],
    ['name' => 'Date',   'price' => 3.0,  'category' => 'fruit'],
    ['name' => 'Eggplant','price' => 1.2, 'category' => 'veggie'],
]);

$products = DB::table('products')->get();
if (! ($products instanceof Collection)) fail('DB::table()->get() should return Collection');
pass('DB::table()->get() returns Collection');

// Chain on DB result
$expensiveFruits = $products
    ->filter(fn($p) => $p->category === 'fruit')
    ->sortBy('price', 'desc')
    ->pluck('name');

if ($expensiveFruits[0] !== 'Date') fail("Chained filter wrong: {$expensiveFruits[0]}");
if (count($expensiveFruits) !== 3) fail("Chained filter count wrong");
pass('Collection chaining on DB result (filter → sortBy → pluck)');

// Aggregate on DB result
$avgPrice = $products->avg('price');
$expected = round((1.5 + 0.5 + 0.8 + 3.0 + 1.2) / 5, 4);
if (abs((float)$avgPrice - $expected) > 0.001) fail("avg() on DB result: expected {$expected}, got {$avgPrice}");
pass('avg() on Collection from DB result');

$grouped = $products->groupBy('category');
if (count($grouped['fruit']) !== 3) fail("groupBy() on DB result wrong");
pass('groupBy() on Collection from DB result');

// chunk() returns array of Collections
$chunks = $products->chunk(2);
if (! ($chunks[0] instanceof Collection)) fail('chunk() should return Collection instances');
pass('chunk() returns array<Collection>');

// -----------------------------------------------------------------------
// 31. Empty collection edge cases
// -----------------------------------------------------------------------
$empty = new Collection();
if ($empty->first() !== null)   fail('first() on empty should be null');
if ($empty->last() !== null)    fail('last() on empty should be null');
if ($empty->sum('x') !== 0)    fail('sum() on empty should be 0');
if ($empty->avg('x') !== 0.0)  fail('avg() on empty should be 0.0');
if ($empty->min('x') !== null) fail('min() on empty should be null');
if ($empty->max('x') !== null) fail('max() on empty should be null');
if ($empty->toArray() !== [])  fail('toArray() on empty should be []');
pass('Empty Collection edge cases (first/last/sum/avg/min/max/toArray)');

$emptyChunks = $empty->chunk(3);
if ($emptyChunks !== []) fail('chunk() on empty should return []');
pass('chunk() on empty Collection → []');

// -----------------------------------------------------------------------
// Summary
// -----------------------------------------------------------------------
echo "\n\033[32m✔ All Collection tests passed on MySQL!\033[0m\n\n";