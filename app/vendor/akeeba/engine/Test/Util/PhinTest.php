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

use Akeeba\Engine\Util\Phin;
use PHPUnit\Framework\TestCase;

final class PhinTest extends TestCase
{
	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\PhinProvider::ceeProvider()
	 */
	public function testCee(string $input, string $expected): void
	{
		$this->assertSame($expected, Phin::cee($input));
	}

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\PhinProvider::artooProvider()
	 */
	public function testArtoo(string $input, string $expected): void
	{
		$this->assertSame($expected, Phin::artoo($input));
	}

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\PhinProvider::roundTripProvider()
	 */
	public function testRoundTrip(string $input): void
	{
		$this->assertSame($input, Phin::artoo(Phin::cee($input)));
	}

	public function testCeeChangesAllLowercaseLetters(): void
	{
		$lowercase = 'abcdefghijklmnopqrstuvwxyz';

		$encoded = Phin::cee($lowercase);

		$this->assertNotSame($lowercase, $encoded, 'cee() must change at least one letter');

		foreach (str_split($lowercase) as $i => $letter)
		{
			$this->assertNotSame($letter, $encoded[$i], "cee() must not map '$letter' to itself");
		}
	}

	public function testCeeChangesAllUppercaseLetters(): void
	{
		$uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

		$encoded = Phin::cee($uppercase);

		$this->assertNotSame($uppercase, $encoded, 'cee() must change at least one letter');

		foreach (str_split($uppercase) as $i => $letter)
		{
			$this->assertNotSame($letter, $encoded[$i], "cee() must not map '$letter' to itself");
		}
	}

	public function testCeeProducesUniqueSubstitutionsForLowercase(): void
	{
		$encoded = str_split(Phin::cee('abcdefghijklmnopqrstuvwxyz'));

		$this->assertCount(26, array_unique($encoded), 'Each lowercase letter must map to a distinct target');
	}

	public function testCeeProducesUniqueSubstitutionsForUppercase(): void
	{
		$encoded = str_split(Phin::cee('ABCDEFGHIJKLMNOPQRSTUVWXYZ'));

		$this->assertCount(26, array_unique($encoded), 'Each uppercase letter must map to a distinct target');
	}

	public function testCeePreservesLength(): void
	{
		$input = 'Hello, World! 42';

		$this->assertSame(strlen($input), strlen(Phin::cee($input)));
	}

	public function testArtooPreservesLength(): void
	{
		$input = 'Hello, World! 42';

		$this->assertSame(strlen($input), strlen(Phin::artoo($input)));
	}
}
