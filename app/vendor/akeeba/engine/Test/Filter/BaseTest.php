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

use Akeeba\Engine\Test\AbstractEngineTestCase;
use Akeeba\Engine\Test\Stub\Filter\ConcreteFilter;

final class BaseTest extends AbstractEngineTestCase
{
	// -------------------------------------------------------------------------
	// Direct-method filter tests
	// -------------------------------------------------------------------------

	/**
	 * @dataProvider \Akeeba\Engine\Test\Filter\BaseProvider::isFilteredDirectProvider()
	 */
	public function testIsFilteredDirect(
		string $object,
		string $subtype,
		string $method,
		array  $filterData,
		string $root,
		string $test,
		bool   $expected
	): void
	{
		$filter         = new ConcreteFilter();
		$filter->object  = $object;
		$filter->subtype = $subtype;
		$filter->method  = $method;
		$filter->seedFilterData($filterData);

		$result = $filter->isFiltered($test, $root, $object, $subtype);

		$this->assertSame($expected, $result);
	}

	// -------------------------------------------------------------------------
	// Regex-method filter tests
	// -------------------------------------------------------------------------

	/**
	 * @dataProvider \Akeeba\Engine\Test\Filter\BaseProvider::isFilteredRegexProvider()
	 */
	public function testIsFilteredRegex(
		array  $filterData,
		string $root,
		string $test,
		bool   $expected
	): void
	{
		$filter         = new ConcreteFilter();
		$filter->object  = 'file';
		$filter->subtype = 'all';
		$filter->method  = 'regex';
		$filter->seedFilterData($filterData);

		$result = $filter->isFiltered($test, $root, 'file', 'all');

		$this->assertSame($expected, $result);
	}

	// -------------------------------------------------------------------------
	// Object/subtype mismatch + disabled state
	// -------------------------------------------------------------------------

	/**
	 * @dataProvider \Akeeba\Engine\Test\Filter\BaseProvider::isFilteredMismatchProvider()
	 */
	public function testIsFilteredMismatch(
		string $filterObject,
		string $filterSubtype,
		string $queryObject,
		string $querySubtype,
		bool   $enabled,
		bool   $expected
	): void
	{
		$filter          = new ConcreteFilter();
		$filter->object  = $filterObject;
		$filter->subtype = $filterSubtype;
		$filter->method  = 'direct';
		$filter->enabled = $enabled;
		$filter->seedFilterData(['[ROOT]' => ['/some/path']]);

		$result = $filter->isFiltered('/some/path', '[ROOT]', $queryObject, $querySubtype);

		$this->assertSame($expected, $result);
	}

	// -------------------------------------------------------------------------
	// Root independence
	// -------------------------------------------------------------------------

	/**
	 * @dataProvider \Akeeba\Engine\Test\Filter\BaseProvider::rootIndependenceProvider()
	 */
	public function testRootIndependence(
		array  $filterData,
		string $queryRoot,
		string $test,
		bool   $expected
	): void
	{
		$filter          = new ConcreteFilter();
		$filter->object  = 'dir';
		$filter->subtype = 'all';
		$filter->method  = 'direct';
		$filter->seedFilterData($filterData);

		$result = $filter->isFiltered($test, $queryRoot, 'dir', 'all');

		$this->assertSame($expected, $result);
	}

	// -------------------------------------------------------------------------
	// Edge-case: empty filter data
	// -------------------------------------------------------------------------

	public function testEmptyFilterDataNeverFilters(): void
	{
		$filter          = new ConcreteFilter();
		$filter->object  = 'dir';
		$filter->subtype = 'all';
		$filter->method  = 'direct';
		$filter->seedFilterData([]);

		$this->assertSame(false, $filter->isFiltered('/any/path', '[ROOT]', 'dir', 'all'));
	}

	// -------------------------------------------------------------------------
	// hasFilters()
	// -------------------------------------------------------------------------

	public function testHasFiltersReturnsTrueWhenDataPresent(): void
	{
		$filter          = new ConcreteFilter();
		$filter->object  = 'dir';
		$filter->subtype = 'all';
		$filter->method  = 'direct';
		$filter->seedFilterData(['[ROOT]' => ['/cache']]);

		$this->assertSame(true, $filter->hasFilters());
	}

	public function testHasFiltersReturnsFalseWhenDataEmpty(): void
	{
		$filter          = new ConcreteFilter();
		$filter->object  = 'dir';
		$filter->subtype = 'all';
		$filter->method  = 'direct';
		$filter->seedFilterData([]);

		$this->assertSame(false, $filter->hasFilters());
	}

	public function testHasFiltersReturnsFalseWhenDisabled(): void
	{
		$filter          = new ConcreteFilter();
		$filter->object  = 'dir';
		$filter->subtype = 'all';
		$filter->method  = 'direct';
		$filter->enabled = false;
		$filter->seedFilterData(['[ROOT]' => ['/cache']]);

		$this->assertSame(false, $filter->hasFilters());
	}

	// -------------------------------------------------------------------------
	// getFilters()
	// -------------------------------------------------------------------------

	public function testGetFiltersReturnsArrayForKnownRoot(): void
	{
		$filter          = new ConcreteFilter();
		$filter->object  = 'dir';
		$filter->subtype = 'all';
		$filter->method  = 'direct';
		$filter->seedFilterData(['[ROOT]' => ['/cache', '/tmp']]);

		$result = $filter->getFilters('[ROOT]');

		$this->assertSame(['/cache', '/tmp'], $result);
	}

	public function testGetFiltersReturnsEmptyArrayForUnknownRoot(): void
	{
		$filter          = new ConcreteFilter();
		$filter->object  = 'dir';
		$filter->subtype = 'all';
		$filter->method  = 'direct';
		$filter->seedFilterData(['[ROOT]' => ['/cache']]);

		$result = $filter->getFilters('[OTHERROOT]');

		$this->assertSame([], $result);
	}

	public function testGetFiltersReturnsAllRootsWhenNullPassed(): void
	{
		$data            = ['[ROOT_A]' => ['/a'], '[ROOT_B]' => ['/b']];
		$filter          = new ConcreteFilter();
		$filter->object  = 'dir';
		$filter->subtype = 'all';
		$filter->method  = 'direct';
		$filter->seedFilterData($data);

		$result = $filter->getFilters(null);

		$this->assertSame($data, $result);
	}
}
