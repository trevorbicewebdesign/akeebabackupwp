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

final class PhinProvider
{
	public static function ceeProvider(): array
	{
		return [
			'empty string'                    => ['', ''],
			'all lowercase letters'           => [
				'abcdefghijklmnopqrstuvwxyz',
				'kmzphqwyxnvutsrjoigfedcbal',
			],
			'all uppercase letters'           => [
				'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
				'NQKZVJXBMFWPSHCEYGTALORDUI',
			],
			'lowercase word'                  => ['hello', 'yhuur'],
			'uppercase word'                  => ['HELLO', 'BVPPC'],
			'mixed-case word'                 => ['Hello', 'Bhuur'],
			'mixed-case phrase'               => ['Hello World', 'Bhuur Rriup'],
			'digits are unchanged'            => ['abc123xyz', 'kmz123bal'],
			'punctuation is unchanged'        => ['foo.bar!', 'qrr.mki!'],
			'leading and trailing whitespace' => [' abc ', ' kmz '],
		];
	}

	public static function artooProvider(): array
	{
		return [
			'empty string'                    => ['', ''],
			'all substituted lowercase'       => [
				'kmzphqwyxnvutsrjoigfedcbal',
				'abcdefghijklmnopqrstuvwxyz',
			],
			'all substituted uppercase'       => [
				'NQKZVJXBMFWPSHCEYGTALORDUI',
				'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
			],
			'encoded lowercase word'          => ['yhuur', 'hello'],
			'encoded uppercase word'          => ['BVPPC', 'HELLO'],
			'encoded mixed-case word'         => ['Bhuur', 'Hello'],
			'encoded mixed-case phrase'       => ['Bhuur Rriup', 'Hello World'],
			'digits are unchanged'            => ['kmz123bal', 'abc123xyz'],
			'punctuation is unchanged'        => ['qrr.mki!', 'foo.bar!'],
			'leading and trailing whitespace' => [' kmz ', ' abc '],
		];
	}

	public static function roundTripProvider(): array
	{
		return [
			'empty string'        => [''],
			'lowercase'           => ['abcdefghijklmnopqrstuvwxyz'],
			'uppercase'           => ['ABCDEFGHIJKLMNOPQRSTUVWXYZ'],
			'mixed case'          => ['The Quick Brown Fox Jumps Over The Lazy Dog'],
			'alphanumeric'        => ['abc123XYZ'],
			'special characters'  => ['hello, world! 42'],
			'only non-letters'    => ['123 !@#$%'],
			'unicode non-letters' => ["caf\xc3\xa9 \xe2\x82\xac"],
		];
	}
}
