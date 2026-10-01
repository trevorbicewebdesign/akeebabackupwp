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

namespace Akeeba\Engine\Test\Driver\Query;

use Akeeba\Engine\Driver\Query\Element;
use PHPUnit\Framework\TestCase;

final class ElementTest extends TestCase
{
	// -------------------------------------------------------------------------
	// Constructor
	// -------------------------------------------------------------------------

	public function testConstructorSetsNameWithStringElement(): void
	{
		$element = new Element('SELECT', 'a');

		$this->assertSame(['a'], $element->getElements());
	}

	public function testConstructorSetsNameWithArrayElements(): void
	{
		$element = new Element('SELECT', ['a', 'b']);

		$this->assertSame(['a', 'b'], $element->getElements());
	}

	public function testConstructorDefaultGlueIsComma(): void
	{
		$element = new Element('SELECT', ['a', 'b']);

		$this->assertSame(PHP_EOL . 'SELECT a,b', (string) $element);
	}

	public function testConstructorCustomGlue(): void
	{
		$element = new Element('WHERE', ['x = 1', 'y = 2'], ' AND ');

		$this->assertSame(PHP_EOL . 'WHERE x = 1 AND y = 2', (string) $element);
	}

	// -------------------------------------------------------------------------
	// __toString — regular name
	// -------------------------------------------------------------------------

	/**
	 * @dataProvider \Akeeba\Engine\Test\Driver\Query\ElementProvider::toStringRegularProvider()
	 */
	public function testToStringRegular(string $name, $elements, string $glue, string $expected): void
	{
		$element = new Element($name, $elements, $glue);

		$this->assertSame($expected, (string) $element);
	}

	// -------------------------------------------------------------------------
	// __toString — function-style name ending in '()'
	// -------------------------------------------------------------------------

	/**
	 * @dataProvider \Akeeba\Engine\Test\Driver\Query\ElementProvider::toStringFunctionProvider()
	 */
	public function testToStringFunction(string $name, $elements, string $glue, string $expected): void
	{
		$element = new Element($name, $elements, $glue);

		$this->assertSame($expected, (string) $element);
	}

	public function testToStringFunctionStyleOmitsTrailingParenthesesFromName(): void
	{
		$element = new Element('MAX()', 'price', ',');
		$result  = (string) $element;

		// Must not contain 'MAX()(' — the trailing '()' is stripped and replaced
		$this->assertStringNotContainsString('MAX()(', $result);
		$this->assertSame(PHP_EOL . 'MAX(price)', $result);
	}

	// -------------------------------------------------------------------------
	// append()
	// -------------------------------------------------------------------------

	/**
	 * @dataProvider \Akeeba\Engine\Test\Driver\Query\ElementProvider::appendProvider()
	 */
	public function testAppend($initial, $appended, array $expected): void
	{
		$element = new Element('SELECT', $initial);
		$element->append($appended);

		$this->assertSame($expected, $element->getElements());
	}

	public function testAppendStringAddsOneElement(): void
	{
		$element = new Element('SELECT', 'a');
		$element->append('b');

		$this->assertSame(['a', 'b'], $element->getElements());
	}

	public function testAppendArrayMergesElements(): void
	{
		$element = new Element('SELECT', 'a');
		$element->append(['b', 'c']);

		$this->assertSame(['a', 'b', 'c'], $element->getElements());
	}

	public function testAppendDoesNotDeduplicate(): void
	{
		$element = new Element('SELECT', 'a');
		$element->append('a');

		$this->assertSame(['a', 'a'], $element->getElements());
	}

	// -------------------------------------------------------------------------
	// getElements()
	// -------------------------------------------------------------------------

	public function testGetElementsReturnsArray(): void
	{
		$element = new Element('SELECT', 'x');

		$this->assertIsArray($element->getElements());
	}

	public function testGetElementsReflectsCurrentState(): void
	{
		$element = new Element('SELECT', ['a', 'b']);
		$element->append('c');

		$this->assertSame(['a', 'b', 'c'], $element->getElements());
	}

	// -------------------------------------------------------------------------
	// __clone() deep copy
	// -------------------------------------------------------------------------

	public function testCloneProducesIndependentElementsList(): void
	{
		$original = new Element('SELECT', ['a', 'b']);
		$clone    = clone $original;

		$clone->append('c');

		// Original must not be affected by appending to the clone
		$this->assertSame(['a', 'b'], $original->getElements());
		$this->assertSame(['a', 'b', 'c'], $clone->getElements());
	}

	public function testCloneProducesSameStringAsOriginal(): void
	{
		$original = new Element('WHERE', ['x = 1', 'y = 2'], ' AND ');
		$clone    = clone $original;

		$this->assertSame((string) $original, (string) $clone);
	}

	public function testCloneIsNotSameObject(): void
	{
		$original = new Element('SELECT', 'a');
		$clone    = clone $original;

		$this->assertNotSame($original, $clone);
	}
}
