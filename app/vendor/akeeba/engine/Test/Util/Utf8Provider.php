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

final class Utf8Provider
{
	/**
	 * Provider for utf8_encode tests.
	 *
	 * Input is a Latin-1 (ISO-8859-1) string; expected is the UTF-8 encoded version.
	 */
	public static function encodeProvider(): array
	{
		return [
			'empty string'          => ['', ''],
			// Pure ASCII is identical in UTF-8
			'pure ASCII'            => ['Hello World', 'Hello World'],
			'digits and symbols'    => ['123!@#', '123!@#'],
			// Latin-1 high bytes become two-byte UTF-8 sequences
			// é = 0xE9 in Latin-1 → UTF-8: 0xC3 0xA9
			'e acute (0xE9)'        => ["\xE9", "\xC3\xA9"],
			// ñ = 0xF1 in Latin-1 → UTF-8: 0xC3 0xB1
			'n tilde (0xF1)'        => ["\xF1", "\xC3\xB1"],
			// ÿ = 0xFF in Latin-1 → UTF-8: 0xC3 0xBF
			'y diaeresis (0xFF)'    => ["\xFF", "\xC3\xBF"],
			// À = 0xC0 in Latin-1 → UTF-8: 0xC3 0x80
			'A grave (0xC0)'        => ["\xC0", "\xC3\x80"],
			// Mixed: "café" in Latin-1
			'cafe with accent'      => ["caf\xE9", "caf\xC3\xA9"],
		];
	}

	/**
	 * Provider for utf8_decode tests.
	 *
	 * Input is a UTF-8 string; expected is the Latin-1 (ISO-8859-1) decoded version.
	 */
	public static function decodeProvider(): array
	{
		return [
			'empty string'          => ['', ''],
			// Pure ASCII is identical in both encodings
			'pure ASCII'            => ['Hello World', 'Hello World'],
			'digits and symbols'    => ['123!@#', '123!@#'],
			// UTF-8 two-byte sequences for Latin-1 high bytes
			// UTF-8: 0xC3 0xA9 → Latin-1: 0xE9 (é)
			'e acute (UTF-8)'       => ["\xC3\xA9", "\xE9"],
			// UTF-8: 0xC3 0xB1 → Latin-1: 0xF1 (ñ)
			'n tilde (UTF-8)'       => ["\xC3\xB1", "\xF1"],
			// UTF-8: 0xC3 0xBF → Latin-1: 0xFF (ÿ)
			'y diaeresis (UTF-8)'   => ["\xC3\xBF", "\xFF"],
			// UTF-8: 0xC3 0x80 → Latin-1: 0xC0 (À)
			'A grave (UTF-8)'       => ["\xC3\x80", "\xC0"],
			// Mixed: "café" in UTF-8
			'cafe with accent'      => ["caf\xC3\xA9", "caf\xE9"],
		];
	}

	/**
	 * Provider for round-trip tests: decode(encode(x)) === x for Latin-1 inputs.
	 */
	public static function roundTripProvider(): array
	{
		return [
			'empty string'       => [''],
			'pure ASCII'         => ['Hello World'],
			'e acute'            => ["\xE9"],
			'n tilde'            => ["\xF1"],
			'y diaeresis'        => ["\xFF"],
			'A grave'            => ["\xC0"],
			'all printable'      => ["caf\xE9 \xF1ice"],
			'full Latin-1 range' => [implode('', array_map('chr', range(0x20, 0xFF)))],
		];
	}
}
