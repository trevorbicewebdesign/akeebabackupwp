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

final class EncryptProvider
{
	/**
	 * Plaintexts and key sizes for AES-CTR round-trip tests.
	 */
	public static function ctrRoundTripProvider(): array
	{
		return [
			'empty string, 128-bit'      => ['', 'testpassword', 128],
			'ASCII short, 128-bit'       => ['Hello, World!', 'testpassword', 128],
			'ASCII long, 128-bit'        => [str_repeat('The quick brown fox. ', 50), 'mypassword', 128],
			'multibyte UTF-8, 128-bit'   => ["caf\xc3\xa9 \xe2\x82\xac price", 'password', 128],
			'binary bytes, 128-bit'      => ["\x00\x01\x02\x03\xff\xfe\xfd", 'key', 128],
			'exactly 16 bytes, 128-bit'  => ['0123456789ABCDEF', 'pass', 128],
			'empty string, 192-bit'      => ['', 'testpassword', 192],
			'ASCII short, 192-bit'       => ['Hello, World!', 'testpassword', 192],
			'ASCII long, 192-bit'        => [str_repeat('The quick brown fox. ', 50), 'mypassword', 192],
			'empty string, 256-bit'      => ['', 'testpassword', 256],
			'ASCII short, 256-bit'       => ['Hello, World!', 'testpassword', 256],
			'ASCII long, 256-bit'        => [str_repeat('The quick brown fox. ', 50), 'mypassword', 256],
		];
	}

	/**
	 * Invalid key sizes for AES-CTR — should return empty string.
	 */
	public static function ctrInvalidKeyBitsProvider(): array
	{
		return [
			'64-bit'  => ['Hello', 'pass', 64],
			'512-bit' => ['Hello', 'pass', 512],
			'0-bit'   => ['Hello', 'pass', 0],
		];
	}

	/**
	 * Plaintexts for AES-CBC round-trip tests.
	 */
	public static function cbcRoundTripProvider(): array
	{
		return [
			'ASCII short'        => ['Hello, World!', 'testpassword'],
			'ASCII long'         => [str_repeat('The quick brown fox. ', 50), 'mypassword'],
			'multibyte UTF-8'    => ["caf\xc3\xa9 \xe2\x82\xac price", 'password'],
			'binary bytes'       => ["\x00\x01\x02\x03\xff\xfe\xfd", 'key'],
			'exactly 16 bytes'   => ['0123456789ABCDEF', 'pass'],
			'exactly 32 bytes'   => ['0123456789ABCDEF0123456789ABCDEF', 'pass'],
		];
	}

	/**
	 * PBKDF2-HMAC-SHA1 known-answer test vectors from RFC 6070.
	 *
	 * Format: [$password, $salt, $algorithm, $count, $key_length, $expectedHex]
	 */
	public static function pbkdf2KnownAnswerProvider(): array
	{
		return [
			// RFC 6070, Section 2, Test Vectors
			'rfc6070-v1' => [
				'password', 'salt', 'sha1', 1, 20,
				'0c60c80f961f0e71f3a9b524af6012062fe037a6',
			],
			'rfc6070-v2' => [
				'password', 'salt', 'sha1', 2, 20,
				'ea6c014dc72d6f8ccd1ed92ace1d41f0d8de8957',
			],
			'rfc6070-v3' => [
				'password', 'salt', 'sha1', 4096, 20,
				'4b007901b765489abead49d926f721d065a429c1',
			],
			'rfc6070-v5' => [
				'passwordPASSWORDpassword', 'saltSALTsaltSALTsaltSALTsaltSALTsalt', 'sha1', 4096, 25,
				'3d2eec4fe41c849b80c8d83662c0e44a8b291a964cf2f07038',
			],
			'rfc6070-v6' => [
				"pass\x00word", "sa\x00lt", 'sha1', 4096, 16,
				'56fa6aa75548099dcc37d7f03425e0c3',
			],
		];
	}

	/**
	 * Data for stringLength() tests.
	 *
	 * Format: [$string, $expectedByteLength]
	 */
	public static function stringLengthProvider(): array
	{
		return [
			'empty'            => ['', 0],
			'ASCII'            => ['Hello', 5],
			'UTF-8 multibyte'  => ["caf\xc3\xa9", 5],   // 'é' is 2 bytes
			'3-byte UTF-8'     => ["\xe2\x82\xac", 3],   // '€' is 3 bytes
			'binary zero'      => ["\x00", 1],
			'binary bytes'     => ["\x00\x01\x02\x03", 4],
		];
	}

	/**
	 * Data for subString() tests.
	 *
	 * Format: [$string, $start, $length, $expected]
	 */
	public static function subStringProvider(): array
	{
		return [
			'ASCII from 0'         => ['Hello', 0, 3, 'Hel'],
			'ASCII from middle'    => ['Hello', 1, 3, 'ell'],
			'UTF-8 raw bytes'      => ["caf\xc3\xa9", 0, 3, 'caf'],
			'UTF-8 last 2 bytes'   => ["caf\xc3\xa9", 3, 2, "\xc3\xa9"],
			'no length param'      => ['Hello', 2, null, 'llo'],
			'empty string'         => ['', 0, 5, ''],
		];
	}
}
