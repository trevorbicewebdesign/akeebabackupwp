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

use Akeeba\Engine\Util\Utf8;
use PHPUnit\Framework\TestCase;

final class Utf8Test extends TestCase
{
	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\Utf8Provider::encodeProvider()
	 */
	public function testUtf8Encode(string $input, string $expected): void
	{
		$this->assertSame($expected, Utf8::utf8_encode($input));
	}

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\Utf8Provider::decodeProvider()
	 */
	public function testUtf8Decode(string $input, string $expected): void
	{
		$this->assertSame($expected, Utf8::utf8_decode($input));
	}

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\Utf8Provider::roundTripProvider()
	 */
	public function testRoundTrip(string $latin1Input): void
	{
		$this->assertSame($latin1Input, Utf8::utf8_decode(Utf8::utf8_encode($latin1Input)));
	}

	public function testEncodeProducesValidUtf8(): void
	{
		// High bytes in Latin-1 range
		$input    = implode('', array_map('chr', range(0x80, 0xFF)));
		$encoded  = Utf8::utf8_encode($input);

		// mb_check_encoding will verify it's valid UTF-8
		$this->assertTrue(mb_check_encoding($encoded, 'UTF-8'), 'utf8_encode() must produce valid UTF-8');
	}

	public function testEncodeDoesNotChangeAscii(): void
	{
		$ascii = implode('', array_map('chr', range(0x00, 0x7F)));
		$this->assertSame($ascii, Utf8::utf8_encode($ascii));
	}

	public function testDecodeDoesNotChangeAscii(): void
	{
		$ascii = implode('', array_map('chr', range(0x00, 0x7F)));
		$this->assertSame($ascii, Utf8::utf8_decode($ascii));
	}
}
