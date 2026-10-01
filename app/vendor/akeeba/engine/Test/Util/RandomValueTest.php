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

use Akeeba\Engine\Util\RandomValue;
use PHPUnit\Framework\TestCase;

final class RandomValueTest extends TestCase
{
	/** @var RandomValue */
	private $rng;

	protected function setUp(): void
	{
		$this->rng = new RandomValue();
	}

	public function testGenerateReturnsCorrectByteCount(): void
	{
		foreach ([1, 8, 16, 32, 64, 128, 256] as $bytes)
		{
			$result = $this->rng->generate($bytes);
			$this->assertSame($bytes, strlen($result), "generate($bytes) must return exactly $bytes bytes");
		}
	}

	public function testGenerateDefaultIs32Bytes(): void
	{
		$result = $this->rng->generate();
		$this->assertSame(32, strlen($result));
	}

	public function testGenerateTwoCallsDiffer(): void
	{
		$a = $this->rng->generate(32);
		$b = $this->rng->generate(32);
		// Overwhelmingly likely to differ with 32 bytes of CSPRNG output
		$this->assertNotSame($a, $b, 'Two successive generate() calls should produce different output');
	}

	public function testGenerateStringLengthMatchesRequest(): void
	{
		foreach ([1, 8, 16, 32, 64, 100] as $length)
		{
			$result = $this->rng->generateString($length);
			$this->assertSame(
				$length,
				strlen($result),
				"generateString($length) must return a string of exactly $length characters"
			);
		}
	}

	public function testGenerateStringDefaultLength(): void
	{
		$result = $this->rng->generateString();
		$this->assertSame(32, strlen($result));
	}

	public function testGenerateStringUsesDefaultCharacterSet(): void
	{
		$defaultCharSet = 'abcdefghijklmnopqrstuvwxyz-ABCDEFGHIJKLMNOPQRSTUVWXYZ_0123456789';

		for ($i = 0; $i < 10; $i++)
		{
			$result = $this->rng->generateString(64);

			foreach (str_split($result) as $char)
			{
				$this->assertNotFalse(
					strpos($defaultCharSet, $char),
					"Character '$char' is not in the default character set"
				);
			}
		}
	}

	public function testGenerateStringUsesCustomCharacterSet(): void
	{
		$customCharSet = 'ABCDEF0123456789';

		for ($i = 0; $i < 10; $i++)
		{
			$result = $this->rng->generateString(32, $customCharSet);

			foreach (str_split($result) as $char)
			{
				$this->assertNotFalse(
					strpos($customCharSet, $char),
					"Character '$char' is not in the custom character set '$customCharSet'"
				);
			}
		}
	}

	public function testGenerateStringTwoCallsDiffer(): void
	{
		$a = $this->rng->generateString(64);
		$b = $this->rng->generateString(64);
		// Overwhelmingly likely to differ with 64 chars from a 64-char set
		$this->assertNotSame($a, $b, 'Two successive generateString() calls should produce different output');
	}

	public function testGenerateStringContainsOnlyAlphanumericAndSpecialChars(): void
	{
		// The default source string uses exactly 64 characters: a-z, -, A-Z, _, 0-9
		$pattern = '/^[a-zA-Z0-9\-_]+$/';

		for ($i = 0; $i < 5; $i++)
		{
			$result = $this->rng->generateString(32);
			$this->assertMatchesRegularExpression(
				$pattern,
				$result,
				'generateString() output must match the default character set pattern'
			);
		}
	}
}
