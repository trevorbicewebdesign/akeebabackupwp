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

namespace Akeeba\Engine\Test\Filter;

class BaseProvider
{
	/**
	 * Cases for direct-method exclusion filters.
	 *
	 * Each row: [object, subtype, method, filterData, root, test, expectedFiltered]
	 */
	public static function isFilteredDirectProvider()
	{
		return [
			'exact match is filtered' => [
				'dir', 'all', 'direct',
				['[ROOT]' => ['/var/www/cache', '/var/www/tmp']],
				'[ROOT]', '/var/www/cache',
				true,
			],
			'non-match is not filtered' => [
				'dir', 'all', 'direct',
				['[ROOT]' => ['/var/www/cache', '/var/www/tmp']],
				'[ROOT]', '/var/www/public',
				false,
			],
			'empty filter data never filters' => [
				'dir', 'all', 'direct',
				[],
				'[ROOT]', '/var/www/cache',
				false,
			],
			'unknown root is not filtered' => [
				'dir', 'all', 'direct',
				['[ROOT]' => ['/var/www/cache']],
				'[OTHERROOT]', '/var/www/cache',
				false,
			],
			'file object exact match' => [
				'file', 'all', 'direct',
				['[ROOT]' => ['readme.txt', '.htaccess']],
				'[ROOT]', '.htaccess',
				true,
			],
			'file object non-match' => [
				'file', 'all', 'direct',
				['[ROOT]' => ['readme.txt', '.htaccess']],
				'[ROOT]', 'index.php',
				false,
			],
			'subtype children direct match' => [
				'dir', 'children', 'direct',
				['[ROOT]' => ['/var/www/logs']],
				'[ROOT]', '/var/www/logs',
				true,
			],
			'subtype content direct match' => [
				'dir', 'content', 'direct',
				['[ROOT]' => ['/var/www/uploads']],
				'[ROOT]', '/var/www/uploads',
				true,
			],
			'dbobject object direct match' => [
				'dbobject', 'all', 'direct',
				['[DB]' => ['#__session', '#__log']],
				'[DB]', '#__session',
				true,
			],
			'dbobject object non-match' => [
				'dbobject', 'all', 'direct',
				['[DB]' => ['#__session', '#__log']],
				'[DB]', '#__users',
				false,
			],
		];
	}

	/**
	 * Cases for regex-method exclusion filters.
	 *
	 * Each row: [filterData, root, test, expectedFiltered]
	 */
	public static function isFilteredRegexProvider()
	{
		return [
			'regex match returns filtered' => [
				['[ROOT]' => ['#\\.log$#']],
				'[ROOT]', 'access.log',
				true,
			],
			'regex no match returns not filtered' => [
				['[ROOT]' => ['#\\.log$#']],
				'[ROOT]', 'access.php',
				false,
			],
			'negated regex: string NOT matching is filtered' => [
				// ! prefix: negates PCRE — if NOT matching, return true (filtered)
				['[ROOT]' => ['!#\\.php$#']],
				'[ROOT]', 'access.log',
				true,
			],
			'negated regex: string matching is not filtered' => [
				['[ROOT]' => ['!#\\.php$#']],
				'[ROOT]', 'index.php',
				false,
			],
			'multiple patterns: first matches' => [
				['[ROOT]' => ['#\\.log$#', '#\\.tmp$#']],
				'[ROOT]', 'error.log',
				true,
			],
			'multiple patterns: second matches' => [
				['[ROOT]' => ['#\\.log$#', '#\\.tmp$#']],
				'[ROOT]', 'session.tmp',
				true,
			],
			'multiple patterns: none matches' => [
				['[ROOT]' => ['#\\.log$#', '#\\.tmp$#']],
				'[ROOT]', 'index.php',
				false,
			],
		];
	}

	/**
	 * Cases where the filter should NOT apply due to object/subtype mismatch or disabled state.
	 *
	 * Each row: [object, subtype, filterObject, filterSubtype, enabled, test, expectedFiltered]
	 */
	public static function isFilteredMismatchProvider()
	{
		return [
			'wrong object type' => [
				'file', 'all',    // filter configured for file/all
				'dir',  'all',    // query for dir/all
				true,
				false,
			],
			'wrong subtype' => [
				'dir', 'children',  // filter configured for dir/children
				'dir', 'all',       // query for dir/all
				true,
				false,
			],
			'inclusion subtype never excluded' => [
				'dir', 'inclusion',
				'dir', 'inclusion',
				true,
				false,
			],
			'disabled filter never filters' => [
				'dir', 'all',
				'dir', 'all',
				false,  // disabled
				false,
			],
		];
	}

	/**
	 * Cases for root independence: same path under different roots filters independently.
	 *
	 * Each row: [filterData, queryRoot, test, expectedFiltered]
	 */
	public static function rootIndependenceProvider()
	{
		return [
			'path in root A is filtered for root A' => [
				['[ROOT_A]' => ['/some/path'], '[ROOT_B]' => []],
				'[ROOT_A]', '/some/path',
				true,
			],
			'path in root A is not filtered for root B' => [
				['[ROOT_A]' => ['/some/path'], '[ROOT_B]' => []],
				'[ROOT_B]', '/some/path',
				false,
			],
			'path in root B is filtered for root B' => [
				['[ROOT_A]' => [], '[ROOT_B]' => ['/some/path']],
				'[ROOT_B]', '/some/path',
				true,
			],
		];
	}
}
