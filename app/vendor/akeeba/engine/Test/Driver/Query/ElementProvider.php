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

final class ElementProvider
{
	/**
	 * Provider for __toString() tests with a regular (non-function) element name.
	 *
	 * Returns: [name, elements, glue, expected_string]
	 */
	public static function toStringRegularProvider(): array
	{
		return [
			'single string element, default glue' => [
				'SELECT',
				'a',
				',',
				PHP_EOL . 'SELECT a',
			],
			'multiple elements via array, default glue' => [
				'SELECT',
				['a', 'b', 'c'],
				',',
				PHP_EOL . 'SELECT a,b,c',
			],
			'multiple elements, custom glue space' => [
				'ORDER BY',
				['col1 ASC', 'col2 DESC'],
				', ',
				PHP_EOL . 'ORDER BY col1 ASC, col2 DESC',
			],
			'single element with AND glue' => [
				'WHERE',
				'x = 1',
				' AND ',
				PHP_EOL . 'WHERE x = 1',
			],
			'multiple elements with AND glue' => [
				'WHERE',
				['x = 1', 'y = 2'],
				' AND ',
				PHP_EOL . 'WHERE x = 1 AND y = 2',
			],
		];
	}

	/**
	 * Provider for __toString() tests with a function-style element name (ending in '()').
	 *
	 * Returns: [name, elements, glue, expected_string]
	 */
	public static function toStringFunctionProvider(): array
	 {
		return [
			'function style no args' => [
				'COUNT()',
				'*',
				',',
				PHP_EOL . 'COUNT(*)',
			],
			'function style multiple args' => [
				'COALESCE()',
				['a', 'b', 'c'],
				', ',
				PHP_EOL . 'COALESCE(a, b, c)',
			],
		];
	}

	/**
	 * Provider for append() tests.
	 *
	 * Returns: [initial_elements, appended, expected_elements]
	 */
	public static function appendProvider(): array
	{
		return [
			'append string to empty' => [
				'first',
				'second',
				['first', 'second'],
			],
			'append array to string' => [
				'first',
				['second', 'third'],
				['first', 'second', 'third'],
			],
			'append string to array init' => [
				['a', 'b'],
				'c',
				['a', 'b', 'c'],
			],
			'append array to array init' => [
				['a', 'b'],
				['c', 'd'],
				['a', 'b', 'c', 'd'],
			],
		];
	}
}
