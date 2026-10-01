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

final class ComplexifyProvider
{
	/**
	 * Provides pairs of (weaker, stronger) passwords for monotonic ordering tests.
	 * The first item must score lower than the second item.
	 */
	public static function weakVsStrongProvider(): array
	{
		return [
			'short vs long lowercase'               => ['aaa', 'abcdefghij'],
			'short number vs long mixed'            => ['1234', 'AbC1!xYz9@Qw'],
			'single char vs mixed long'             => ['a', 'Passw0rd!SecureEnough'],
			'all same vs diverse chars'             => ['aaaaaaaaaa', 'aB3!xY7@zQ'],
		];
	}

	/**
	 * Provides sets of same-charset passwords of increasing length to verify that
	 * longer passwords score higher.
	 */
	public static function longerScoreshigherProvider(): array
	{
		return [
			'lowercase 8 vs 15 chars'  => ['abcdefgh', 'abcdefghijklmno'],
			'mixed 8 vs 15 chars'      => ['Abcdefgh', 'Abcdefghijklmno'],
		];
	}

	/**
	 * Provides a sequence of passwords where each adds a new character class.
	 * Returns [lowercase_only, +uppercase, +digits, +symbols] — each must score
	 * higher than the one before.
	 */
	public static function charsetExpansionProvider(): array
	{
		return [
			'adding uppercase increases score' => ['abcdefgh', 'abcdEFGH'],
			'adding digits increases score'    => ['abcdEFGH', 'abcEFG12'],
			'adding symbols increases score'   => ['abcEFG12', 'abEF12!@'],
		];
	}

	/**
	 * Provides banned passwords that should be flagged (score at the floor, valid=false,
	 * and 'banned' in errors).
	 */
	public static function bannedPasswordProvider(): array
	{
		return [
			'password'  => ['password'],
			'123456'    => ['123456'],
			'qwerty'    => ['qwerty'],
			'dragon'    => ['dragon'],
			'letmein'   => ['letmein'],
			'iloveyou'  => ['iloveyou'],
		];
	}
}
