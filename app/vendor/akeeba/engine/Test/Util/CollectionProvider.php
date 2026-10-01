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

final class CollectionProvider
{
	public static function constructAndAllProvider(): array
	{
		return [
			'empty array'          => [[], []],
			'flat numeric array'   => [[1, 2, 3], [1, 2, 3]],
			'associative array'    => [['a' => 1, 'b' => 2], ['a' => 1, 'b' => 2]],
			'mixed keys'           => [[0 => 'x', 'foo' => 'bar'], [0 => 'x', 'foo' => 'bar']],
		];
	}

	public static function toJsonProvider(): array
	{
		return [
			'empty'       => [[], '[]'],
			'flat'        => [[1, 2, 3], '[1,2,3]'],
			'associative' => [['a' => 1, 'b' => 2], '{"a":1,"b":2}'],
		];
	}

	public static function filterProvider(): array
	{
		return [
			'keep even numbers'    => [
				[1, 2, 3, 4, 5, 6],
				function ($v) { return $v % 2 === 0; },
				[1 => 2, 3 => 4, 5 => 6],
			],
			'keep non-empty'       => [
				['a' => '', 'b' => 'hello', 'c' => ''],
				function ($v) { return $v !== ''; },
				['b' => 'hello'],
			],
			'filter on empty'      => [
				[],
				function ($v) { return true; },
				[],
			],
		];
	}

	public static function mapProvider(): array
	{
		return [
			'double values'       => [
				[1, 2, 3],
				function ($v, $k) { return $v * 2; },
				[2, 4, 6],
			],
			'string lengths'      => [
				['foo' => 'hello', 'bar' => 'hi'],
				function ($v, $k) { return strlen($v); },
				[0 => 5, 1 => 2],
			],
			'map on empty'        => [
				[],
				function ($v, $k) { return $v; },
				[],
			],
		];
	}

	public static function mapPreserveProvider(): array
	{
		return [
			'double values'  => [
				[1, 2, 3],
				function ($v) { return $v * 2; },
				[2, 4, 6],
			],
			'uppercase'      => [
				['foo' => 'hello', 'bar' => 'world'],
				function ($v) { return strtoupper($v); },
				['foo' => 'HELLO', 'bar' => 'WORLD'],
			],
		];
	}

	public static function flattenProvider(): array
	{
		return [
			'nested arrays'      => [
				[[1, 2], [3, [4, 5]]],
				[1, 2, 3, 4, 5],
			],
			'already flat'       => [
				[1, 2, 3],
				[1, 2, 3],
			],
			'three levels deep'  => [
				[[[1, 2], [3]], [4]],
				[1, 2, 3, 4],
			],
			'empty'              => [[], []],
		];
	}

	public static function collapseProvider(): array
	{
		return [
			'two sub-arrays'     => [
				[[1, 2, 3], [4, 5, 6]],
				[1, 2, 3, 4, 5, 6],
			],
			'three sub-arrays'   => [
				[['a', 'b'], ['c'], ['d', 'e']],
				['a', 'b', 'c', 'd', 'e'],
			],
			'empty collection'   => [[], []],
		];
	}

	public static function diffProvider(): array
	{
		return [
			'array diff'          => [
				[1, 2, 3, 4, 5],
				[2, 4],
				[0 => 1, 2 => 3, 4 => 5],
			],
			'collection diff'     => [
				['a', 'b', 'c'],
				['b'],
				[0 => 'a', 2 => 'c'],
			],
			'no overlap'          => [
				[1, 2, 3],
				[4, 5, 6],
				[0 => 1, 1 => 2, 2 => 3],
			],
			'full overlap'        => [
				[1, 2, 3],
				[1, 2, 3],
				[],
			],
		];
	}

	public static function intersectProvider(): array
	{
		return [
			'partial overlap'     => [
				[1, 2, 3, 4],
				[2, 4, 6],
				[1 => 2, 3 => 4],
			],
			'no overlap'          => [
				[1, 2, 3],
				[4, 5, 6],
				[],
			],
			'full overlap'        => [
				[1, 2, 3],
				[1, 2, 3],
				[0 => 1, 1 => 2, 2 => 3],
			],
		];
	}

	public static function firstProvider(): array
	{
		return [
			'no callback returns first element'   => [
				[10, 20, 30],
				null,
				null,
				10,
			],
			'callback finds first even'           => [
				[1, 3, 4, 6],
				function ($k, $v) { return $v % 2 === 0; },
				null,
				4,
			],
			'callback finds nothing, default null' => [
				[1, 3, 5],
				function ($k, $v) { return $v % 2 === 0; },
				null,
				null,
			],
			'callback finds nothing, static default' => [
				[1, 3, 5],
				function ($k, $v) { return $v % 2 === 0; },
				'default',
				'default',
			],
			'callback finds nothing, callable default' => [
				[1, 3, 5],
				function ($k, $v) { return $v % 2 === 0; },
				function () { return 'computed'; },
				'computed',
			],
		];
	}

	public static function lastProvider(): array
	{
		return [
			'non-empty returns last'  => [[10, 20, 30], 30],
			'single element'          => [[42], 42],
			'empty returns null'      => [[], null],
		];
	}

	public static function sliceProvider(): array
	{
		return [
			'offset 1 no length'           => [
				[1, 2, 3, 4, 5], 1, null, false,
				[2, 3, 4, 5],
			],
			'offset 1 length 2'            => [
				[1, 2, 3, 4, 5], 1, 2, false,
				[2, 3],
			],
			'preserve keys'                => [
				[1, 2, 3, 4, 5], 1, 2, true,
				[1 => 2, 2 => 3],
			],
			'offset 0 length 3'            => [
				['a', 'b', 'c', 'd'], 0, 3, false,
				['a', 'b', 'c'],
			],
		];
	}

	public static function implodeProvider(): array
	{
		return [
			'glue comma'   => [
				[['name' => 'Alice'], ['name' => 'Bob'], ['name' => 'Charlie']],
				'name',
				', ',
				'Alice, Bob, Charlie',
			],
			'no glue'      => [
				[['name' => 'A'], ['name' => 'B']],
				'name',
				null,
				'AB',
			],
		];
	}

	public static function groupByProvider(): array
	{
		return [
			'group by string key' => [
				[
					['type' => 'fruit', 'name' => 'apple'],
					['type' => 'veg',   'name' => 'carrot'],
					['type' => 'fruit', 'name' => 'banana'],
				],
				'type',
				[
					'fruit' => [
						['type' => 'fruit', 'name' => 'apple'],
						['type' => 'fruit', 'name' => 'banana'],
					],
					'veg' => [
						['type' => 'veg', 'name' => 'carrot'],
					],
				],
			],
			'group by callable'   => [
				[1, 2, 3, 4, 5, 6],
				function ($v) { return $v % 2 === 0 ? 'even' : 'odd'; },
				[
					'odd'  => [1, 3, 5],
					'even' => [2, 4, 6],
				],
			],
		];
	}

	public static function sortProvider(): array
	{
		return [
			'ascending numeric' => [
				[3, 1, 4, 1, 5, 9, 2, 6],
				function ($a, $b) { return $a <=> $b; },
				[1 => 1, 3 => 1, 6 => 2, 0 => 3, 2 => 4, 4 => 5, 7 => 6, 5 => 9],
			],
		];
	}

	public static function sortByProvider(): array
	{
		return [
			'sort by string key ascending' => [
				[
					['name' => 'Charlie', 'age' => 30],
					['name' => 'Alice',   'age' => 25],
					['name' => 'Bob',     'age' => 28],
				],
				'name',
				false,
				[
					1 => ['name' => 'Alice',   'age' => 25],
					2 => ['name' => 'Bob',     'age' => 28],
					0 => ['name' => 'Charlie', 'age' => 30],
				],
			],
			'sort by string key descending' => [
				[
					['name' => 'Charlie', 'age' => 30],
					['name' => 'Alice',   'age' => 25],
					['name' => 'Bob',     'age' => 28],
				],
				'name',
				true,
				[
					0 => ['name' => 'Charlie', 'age' => 30],
					2 => ['name' => 'Bob',     'age' => 28],
					1 => ['name' => 'Alice',   'age' => 25],
				],
			],
		];
	}
}
