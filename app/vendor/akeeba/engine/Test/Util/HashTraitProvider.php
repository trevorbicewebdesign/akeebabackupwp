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

final class HashTraitProvider
{
	public static function md5HexProvider(): array
	{
		return [
			'empty string'     => ['', 'd41d8cd98f00b204e9800998ecf8427e'],
			'abc'              => ['abc', '900150983cd24fb0d6963f7d28e17f72'],
			'hello world'      => ['hello world', '5eb63bbbe01eeed093cb22bb8f5acdc3'],
			'longer string'    => [
				'The quick brown fox jumps over the lazy dog',
				'9e107d9d372bb6826bd81d3542a419d6',
			],
		];
	}

	public static function sha1HexProvider(): array
	{
		return [
			'empty string'     => ['', 'da39a3ee5e6b4b0d3255bfef95601890afd80709'],
			'abc'              => ['abc', 'a9993e364706816aba3e25717850c26c9cd0d89d'],
			'hello world'      => ['hello world', '2aae6c35c94fcfb415dbe95f408b9ce91ee846ed'],
			'longer string'    => [
				'The quick brown fox jumps over the lazy dog',
				'2fd4e1c67a2d28fced849ee1bb76e7391b93eb12',
			],
		];
	}

	public static function md5FileProvider(): array
	{
		return [
			'empty content'    => ['', 'd41d8cd98f00b204e9800998ecf8427e'],
			'simple content'   => ['hello world', '5eb63bbbe01eeed093cb22bb8f5acdc3'],
			'multiline'        => ["line1\nline2\nline3", '81facad50c8e6244de64a98cf4f56f77'],
		];
	}

	public static function sha1FileProvider(): array
	{
		return [
			'empty content'    => ['', 'da39a3ee5e6b4b0d3255bfef95601890afd80709'],
			'simple content'   => ['hello world', '2aae6c35c94fcfb415dbe95f408b9ce91ee846ed'],
			'multiline'        => ["line1\nline2\nline3", '0ab7283988e8f49022d126054947f222cbdf0a52'],
		];
	}
}
