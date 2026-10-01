<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License version 3, or later
 *
 * This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, version 3.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program. If not, see
 * <https://www.gnu.org/licenses/>.
 */

namespace Akeeba\Engine\Test\Util;

use Akeeba\Engine\Util\Collection;
use PHPUnit\Framework\TestCase;

final class CollectionTest extends TestCase
{
	// -----------------------------------------------------------------------
	// Construction / all() / toArray() / toJson()
	// -----------------------------------------------------------------------

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\CollectionProvider::constructAndAllProvider()
	 */
	public function testConstructAndAll(array $items, array $expected): void
	{
		$c = new Collection($items);
		$this->assertSame($expected, $c->all());
	}

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\CollectionProvider::constructAndAllProvider()
	 */
	public function testToArray(array $items, array $expected): void
	{
		$c = new Collection($items);
		$this->assertSame($expected, $c->toArray());
	}

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\CollectionProvider::toJsonProvider()
	 */
	public function testToJson(array $items, string $expected): void
	{
		$c = new Collection($items);
		$this->assertSame($expected, $c->toJson());
	}

	public function testToStringEqualsToJson(): void
	{
		$c = new Collection(['a' => 1, 'b' => 2]);
		$this->assertSame($c->toJson(), (string) $c);
	}

	// -----------------------------------------------------------------------
	// make()
	// -----------------------------------------------------------------------

	public function testMakeNull(): void
	{
		$c = Collection::make(null);
		$this->assertSame([], $c->all());
	}

	public function testMakeArray(): void
	{
		$c = Collection::make([1, 2, 3]);
		$this->assertSame([1, 2, 3], $c->all());
	}

	public function testMakeCollection(): void
	{
		$original = new Collection([1, 2, 3]);
		$made     = Collection::make($original);
		$this->assertSame($original, $made);
	}

	public function testMakeScalar(): void
	{
		$c = Collection::make('hello');
		$this->assertSame(['hello'], $c->all());
	}

	// -----------------------------------------------------------------------
	// has() / get() / put() / forget()
	// -----------------------------------------------------------------------

	public function testHasExistingKey(): void
	{
		$c = new Collection(['foo' => 'bar']);
		$this->assertTrue($c->has('foo'));
	}

	public function testHasMissingKey(): void
	{
		$c = new Collection([]);
		$this->assertFalse($c->has('foo'));
	}

	public function testGetExistingKey(): void
	{
		$c = new Collection(['x' => 42]);
		$this->assertSame(42, $c->get('x'));
	}

	public function testGetMissingKeyReturnsDefault(): void
	{
		$c = new Collection([]);
		$this->assertSame('default', $c->get('missing', 'default'));
	}

	public function testGetMissingKeyCallableDefault(): void
	{
		$c = new Collection([]);
		$this->assertSame('computed', $c->get('missing', function () { return 'computed'; }));
	}

	public function testPut(): void
	{
		$c = new Collection([]);
		$c->put('key', 'value');
		$this->assertSame('value', $c->get('key'));
	}

	public function testForget(): void
	{
		$c = new Collection(['a' => 1, 'b' => 2]);
		$c->forget('a');
		$this->assertFalse($c->has('a'));
		$this->assertTrue($c->has('b'));
	}

	// -----------------------------------------------------------------------
	// push() / prepend() / pop() / shift()
	// -----------------------------------------------------------------------

	public function testPush(): void
	{
		$c = new Collection([1, 2]);
		$c->push(3);
		$this->assertSame([1, 2, 3], $c->all());
	}

	public function testPrepend(): void
	{
		$c = new Collection([2, 3]);
		$c->prepend(1);
		$this->assertSame([1, 2, 3], $c->all());
	}

	public function testPop(): void
	{
		$c   = new Collection([1, 2, 3]);
		$val = $c->pop();
		$this->assertSame(3, $val);
		$this->assertSame([1, 2], $c->all());
	}

	public function testShift(): void
	{
		$c   = new Collection([1, 2, 3]);
		$val = $c->shift();
		$this->assertSame(1, $val);
		$this->assertSame([2, 3], $c->all());
	}

	// -----------------------------------------------------------------------
	// isEmpty() / count()
	// -----------------------------------------------------------------------

	public function testIsEmptyTrue(): void
	{
		$c = new Collection([]);
		$this->assertTrue($c->isEmpty());
	}

	public function testIsEmptyFalse(): void
	{
		$c = new Collection([1]);
		$this->assertFalse($c->isEmpty());
	}

	public function testCount(): void
	{
		$c = new Collection([1, 2, 3]);
		$this->assertSame(3, $c->count());
		$this->assertSame(3, count($c));
	}

	// -----------------------------------------------------------------------
	// filter()
	// -----------------------------------------------------------------------

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\CollectionProvider::filterProvider()
	 */
	public function testFilter(array $items, callable $callback, array $expected): void
	{
		$c      = new Collection($items);
		$result = $c->filter($callback);
		$this->assertSame($expected, $result->all());
	}

	// -----------------------------------------------------------------------
	// map() / mapPreserve()
	// -----------------------------------------------------------------------

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\CollectionProvider::mapProvider()
	 */
	public function testMap(array $items, callable $callback, array $expected): void
	{
		$c      = new Collection($items);
		$result = $c->map($callback);
		$this->assertSame($expected, $result->all());
	}

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\CollectionProvider::mapPreserveProvider()
	 */
	public function testMapPreserve(array $items, callable $callback, array $expected): void
	{
		$c      = new Collection($items);
		$result = $c->mapPreserve($callback);
		$this->assertSame($expected, $result->all());
	}

	// -----------------------------------------------------------------------
	// flatten() / collapse()
	// -----------------------------------------------------------------------

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\CollectionProvider::flattenProvider()
	 */
	public function testFlatten(array $items, array $expected): void
	{
		$c      = new Collection($items);
		$result = $c->flatten();
		$this->assertSame($expected, $result->all());
	}

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\CollectionProvider::collapseProvider()
	 */
	public function testCollapse(array $items, array $expected): void
	{
		$c      = new Collection($items);
		$result = $c->collapse();
		$this->assertSame($expected, $result->all());
	}

	// -----------------------------------------------------------------------
	// diff() / intersect()
	// -----------------------------------------------------------------------

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\CollectionProvider::diffProvider()
	 */
	public function testDiff(array $items, $other, array $expected): void
	{
		$c      = new Collection($items);
		$result = $c->diff($other);
		$this->assertSame($expected, $result->all());
	}

	public function testDiffWithCollection(): void
	{
		$c      = new Collection([1, 2, 3, 4, 5]);
		$other  = new Collection([2, 4]);
		$result = $c->diff($other);
		$this->assertSame([0 => 1, 2 => 3, 4 => 5], $result->all());
	}

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\CollectionProvider::intersectProvider()
	 */
	public function testIntersect(array $items, $other, array $expected): void
	{
		$c      = new Collection($items);
		$result = $c->intersect($other);
		$this->assertSame($expected, $result->all());
	}

	// -----------------------------------------------------------------------
	// first() / last()
	// -----------------------------------------------------------------------

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\CollectionProvider::firstProvider()
	 */
	public function testFirst(array $items, $callback, $default, $expected): void
	{
		$c      = new Collection($items);
		$result = $c->first($callback, $default);
		$this->assertSame($expected, $result);
	}

	public function testFirstOnEmptyCollection(): void
	{
		$c = new Collection([]);
		$this->assertNull($c->first());
	}

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\CollectionProvider::lastProvider()
	 */
	public function testLast(array $items, $expected): void
	{
		$c = new Collection($items);
		$this->assertSame($expected, $c->last());
	}

	// -----------------------------------------------------------------------
	// slice() / splice()
	// -----------------------------------------------------------------------

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\CollectionProvider::sliceProvider()
	 */
	public function testSlice(array $items, int $offset, $length, bool $preserveKeys, array $expected): void
	{
		$c      = new Collection($items);
		$result = $c->slice($offset, $length, $preserveKeys);
		$this->assertSame($expected, $result->all());
	}

	public function testSpliceReturnsRemovedItems(): void
	{
		$c      = new Collection([1, 2, 3, 4, 5]);
		$result = $c->splice(1, 2);
		$this->assertSame([2, 3], $result->all());
		$this->assertSame([1, 4, 5], $c->all());
	}

	// -----------------------------------------------------------------------
	// take()
	// -----------------------------------------------------------------------

	public function testTakePositive(): void
	{
		$c      = new Collection([1, 2, 3, 4, 5]);
		$result = $c->take(3);
		$this->assertSame([1, 2, 3], $result->all());
	}

	public function testTakeNegative(): void
	{
		$c      = new Collection([1, 2, 3, 4, 5]);
		$result = $c->take(-2);
		$this->assertSame([4, 5], $result->all());
	}

	// -----------------------------------------------------------------------
	// reverse()
	// -----------------------------------------------------------------------

	public function testReverse(): void
	{
		$c      = new Collection([1, 2, 3]);
		$result = $c->reverse();
		$this->assertSame([3, 2, 1], $result->all());
	}

	// -----------------------------------------------------------------------
	// unique() / values()
	// -----------------------------------------------------------------------

	public function testUnique(): void
	{
		$c      = new Collection([1, 2, 2, 3, 3, 3]);
		$result = $c->unique();
		$this->assertSame([0 => 1, 1 => 2, 3 => 3], $result->all());
	}

	public function testValues(): void
	{
		$c = new Collection([2 => 'a', 5 => 'b', 9 => 'c']);
		$c->values();
		$this->assertSame([0 => 'a', 1 => 'b', 2 => 'c'], $c->all());
	}

	// -----------------------------------------------------------------------
	// reduce() / sum()
	// -----------------------------------------------------------------------

	public function testReduce(): void
	{
		$c      = new Collection([1, 2, 3, 4, 5]);
		$result = $c->reduce(function ($carry, $item) { return $carry + $item; }, 0);
		$this->assertSame(15, $result);
	}

	public function testSumByCallable(): void
	{
		$c      = new Collection([['v' => 1], ['v' => 2], ['v' => 3]]);
		$result = $c->sum(function ($item) { return $item['v']; });
		$this->assertSame(6, $result);
	}

	public function testSumByKey(): void
	{
		$c      = new Collection([['price' => 10], ['price' => 20], ['price' => 30]]);
		$result = $c->sum('price');
		$this->assertSame(60, $result);
	}

	// -----------------------------------------------------------------------
	// implode()
	// -----------------------------------------------------------------------

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\CollectionProvider::implodeProvider()
	 */
	public function testImplode(array $items, string $value, $glue, string $expected): void
	{
		$c      = new Collection($items);
		$result = $glue === null ? $c->implode($value) : $c->implode($value, $glue);
		$this->assertSame($expected, $result);
	}

	// -----------------------------------------------------------------------
	// groupBy()
	// -----------------------------------------------------------------------

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\CollectionProvider::groupByProvider()
	 */
	public function testGroupBy(array $items, $groupBy, array $expected): void
	{
		$c      = new Collection($items);
		$result = $c->groupBy($groupBy);
		$this->assertSame($expected, $result->all());
	}

	// -----------------------------------------------------------------------
	// sort() / sortBy() / sortByDesc()
	// -----------------------------------------------------------------------

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\CollectionProvider::sortProvider()
	 */
	public function testSort(array $items, callable $callback, array $expected): void
	{
		$c      = new Collection($items);
		$result = $c->sort($callback);
		$this->assertSame($expected, $result->all());
	}

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\CollectionProvider::sortByProvider()
	 */
	public function testSortBy(array $items, $callback, bool $descending, array $expected): void
	{
		$c      = new Collection($items);
		$result = $c->sortBy($callback, SORT_REGULAR, $descending);
		$this->assertSame($expected, $result->all());
	}

	public function testSortByDesc(): void
	{
		$items    = [['n' => 1], ['n' => 3], ['n' => 2]];
		$c        = new Collection($items);
		$result   = $c->sortByDesc('n');
		$this->assertSame([1 => ['n' => 3], 2 => ['n' => 2], 0 => ['n' => 1]], $result->all());
	}

	// -----------------------------------------------------------------------
	// transform()
	// -----------------------------------------------------------------------

	public function testTransform(): void
	{
		$c = new Collection([1, 2, 3]);
		$c->transform(function ($v) { return $v * 10; });
		$this->assertSame([10, 20, 30], $c->all());
	}

	// -----------------------------------------------------------------------
	// merge()
	// -----------------------------------------------------------------------

	public function testMerge(): void
	{
		$c      = new Collection(['a' => 1, 'b' => 2]);
		$result = $c->merge(['b' => 99, 'c' => 3]);
		$this->assertSame(['a' => 1, 'b' => 99, 'c' => 3], $result->all());
	}

	public function testMergeWithCollection(): void
	{
		$c      = new Collection([1, 2]);
		$other  = new Collection([3, 4]);
		$result = $c->merge($other);
		$this->assertSame([1, 2, 3, 4], $result->all());
	}

	// -----------------------------------------------------------------------
	// each()
	// -----------------------------------------------------------------------

	public function testEach(): void
	{
		$c       = new Collection([1, 2, 3]);
		$visited = [];
		$c->each(function ($v) use (&$visited) { $visited[] = $v; });
		$this->assertSame([1, 2, 3], $visited);
	}

	// -----------------------------------------------------------------------
	// lists() / fetch()
	// -----------------------------------------------------------------------

	public function testListsNoKey(): void
	{
		$c      = new Collection([['name' => 'Alice', 'age' => 25], ['name' => 'Bob', 'age' => 30]]);
		$result = $c->lists('name');
		$this->assertSame(['Alice', 'Bob'], $result);
	}

	public function testListsWithKey(): void
	{
		$c      = new Collection([['name' => 'Alice', 'id' => 1], ['name' => 'Bob', 'id' => 2]]);
		$result = $c->lists('name', 'id');
		$this->assertSame([1 => 'Alice', 2 => 'Bob'], $result);
	}

	public function testFetch(): void
	{
		$c      = new Collection([['profile' => ['name' => 'Alice']], ['profile' => ['name' => 'Bob']]]);
		$result = $c->fetch('profile.name');
		$this->assertSame(['Alice', 'Bob'], $result->all());
	}

	// -----------------------------------------------------------------------
	// ArrayAccess interface
	// -----------------------------------------------------------------------

	public function testOffsetExists(): void
	{
		$c = new Collection(['a' => 1]);
		$this->assertTrue(isset($c['a']));
		$this->assertFalse(isset($c['b']));
	}

	public function testOffsetGet(): void
	{
		$c = new Collection(['x' => 99]);
		$this->assertSame(99, $c['x']);
	}

	public function testOffsetSetWithKey(): void
	{
		$c       = new Collection([]);
		$c['k']  = 'v';
		$this->assertSame('v', $c['k']);
	}

	public function testOffsetSetNullKeyAppends(): void
	{
		$c    = new Collection([1, 2]);
		$c[]  = 3;
		$this->assertSame([1, 2, 3], $c->all());
	}

	public function testOffsetUnset(): void
	{
		$c = new Collection(['a' => 1, 'b' => 2]);
		unset($c['a']);
		$this->assertFalse(isset($c['a']));
	}

	// -----------------------------------------------------------------------
	// IteratorAggregate interface
	// -----------------------------------------------------------------------

	public function testGetIterator(): void
	{
		$items = [1, 2, 3];
		$c     = new Collection($items);
		$got   = [];
		foreach ($c as $v) {
			$got[] = $v;
		}
		$this->assertSame($items, $got);
	}

	// -----------------------------------------------------------------------
	// getCachingIterator()
	// -----------------------------------------------------------------------

	public function testGetCachingIterator(): void
	{
		$c    = new Collection([1, 2, 3]);
		$iter = $c->getCachingIterator();
		$this->assertInstanceOf(\CachingIterator::class, $iter);
	}

	// -----------------------------------------------------------------------
	// jsonSerialize()
	// -----------------------------------------------------------------------

	public function testJsonSerialize(): void
	{
		$c = new Collection(['a' => 1]);
		$this->assertSame(['a' => 1], $c->jsonSerialize());
	}

	// -----------------------------------------------------------------------
	// toArray() with nested toArray-able objects
	// -----------------------------------------------------------------------

	public function testToArrayCallsNestedToArray(): void
	{
		$inner = new Collection([1, 2]);
		$outer = new Collection(['nested' => $inner]);
		$this->assertSame(['nested' => [1, 2]], $outer->toArray());
	}
}
