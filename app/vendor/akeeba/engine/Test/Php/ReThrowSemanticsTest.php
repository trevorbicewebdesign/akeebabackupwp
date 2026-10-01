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

namespace Akeeba\Engine\Test\Php;

use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Documents a subtle PHP re-throwing anti-pattern, so it stays discoverable and greppable if anyone reintroduces it.
 *
 * When re-throwing a caught exception you must write `throw $e;`. Writing `throw new $e;` is NOT a harmless typo: where
 * $e is an object, `new $e` instantiates a FRESH instance of $e's class, calling its constructor with NO arguments.
 * That silently discards the original exception's code and message, so the mangled, blank exception bubbles up with
 * code 0 and an empty message — erasing exactly the information you were trying to propagate.
 *
 * These tests pin down that difference. If a `throw new $e;` (or any `new $someExceptionObject`) re-throw pattern is
 * reintroduced anywhere in the codebase, grep for "throw new $" / "new $e" and this test to understand why it is wrong.
 *
 * @group php
 */
final class ReThrowSemanticsTest extends TestCase
{
	/**
	 * `new $e` where $e is an exception object creates a blank instance of the same class: code 0, empty message, and a
	 * DIFFERENT object. This is the anti-pattern behind the useless "WebDAV upload failed, 0:" log lines.
	 */
	public function testNewFromExceptionObjectDiscardsCodeAndMessage(): void
	{
		$original = new RuntimeException('the real, useful failure detail', 507);

		// The anti-pattern: `throw new $e;` boils down to this re-instantiation.
		$reInstantiated = new $original;

		$this->assertNotSame(
			$original, $reInstantiated,
			'`new $e` creates a fresh object, not the original one.'
		);
		$this->assertSame(
			'', $reInstantiated->getMessage(),
			'`new $e` discards the original message, leaving it empty.'
		);
		$this->assertSame(
			0, $reInstantiated->getCode(),
			'`new $e` discards the original code, leaving it 0.'
		);
	}

	/**
	 * The correct form, `throw $e;`, propagates the very same object with its code and message intact.
	 */
	public function testReThrowingTheSameObjectPreservesCodeAndMessage(): void
	{
		$original = new RuntimeException('the real, useful failure detail', 507);

		try
		{
			throw $original;
		}
		catch (RuntimeException $e)
		{
			$this->assertSame(
				$original, $e,
				'`throw $e;` re-throws the original object unchanged.'
			);
			$this->assertSame('the real, useful failure detail', $e->getMessage());
			$this->assertSame(507, $e->getCode());
		}
	}
}
