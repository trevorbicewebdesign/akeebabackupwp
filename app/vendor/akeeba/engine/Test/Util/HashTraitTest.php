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

use Akeeba\Engine\Test\Stub\Util\HashTraitStub;
use PHPUnit\Framework\TestCase;

final class HashTraitTest extends TestCase
{
	/** @var string[] */
	private $tempFiles = [];

	protected function tearDown(): void
	{
		foreach ($this->tempFiles as $file)
		{
			if (file_exists($file))
			{
				@unlink($file);
			}
		}

		$this->tempFiles = [];
	}

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\HashTraitProvider::md5HexProvider()
	 */
	public function testMd5Hex(string $input, string $expected): void
	{
		$this->assertSame($expected, HashTraitStub::publicMd5($input, false));
	}

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\HashTraitProvider::sha1HexProvider()
	 */
	public function testSha1Hex(string $input, string $expected): void
	{
		$this->assertSame($expected, HashTraitStub::publicSha1($input, false));
	}

	public function testMd5BinaryLength(): void
	{
		$result = HashTraitStub::publicMd5('hello', true);
		$this->assertSame(16, strlen($result), 'MD5 binary output must be 16 bytes');
	}

	public function testSha1BinaryLength(): void
	{
		$result = HashTraitStub::publicSha1('hello', true);
		$this->assertSame(20, strlen($result), 'SHA-1 binary output must be 20 bytes');
	}

	public function testMd5BinaryMatchesHex(): void
	{
		$hex    = HashTraitStub::publicMd5('hello', false);
		$binary = HashTraitStub::publicMd5('hello', true);
		$this->assertSame($hex, bin2hex($binary));
	}

	public function testSha1BinaryMatchesHex(): void
	{
		$hex    = HashTraitStub::publicSha1('hello', false);
		$binary = HashTraitStub::publicSha1('hello', true);
		$this->assertSame($hex, bin2hex($binary));
	}

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\HashTraitProvider::md5FileProvider()
	 */
	public function testMd5File(string $content, string $expected): void
	{
		$tmpFile           = tempnam(sys_get_temp_dir(), 'akeeba_test_');
		$this->tempFiles[] = $tmpFile;
		file_put_contents($tmpFile, $content);

		$this->assertSame($expected, HashTraitStub::publicMd5File($tmpFile, false));
	}

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\HashTraitProvider::sha1FileProvider()
	 */
	public function testSha1File(string $content, string $expected): void
	{
		$tmpFile           = tempnam(sys_get_temp_dir(), 'akeeba_test_');
		$this->tempFiles[] = $tmpFile;
		file_put_contents($tmpFile, $content);

		$this->assertSame($expected, HashTraitStub::publicSha1File($tmpFile, false));
	}

	public function testMd5FileBinaryLength(): void
	{
		$tmpFile           = tempnam(sys_get_temp_dir(), 'akeeba_test_');
		$this->tempFiles[] = $tmpFile;
		file_put_contents($tmpFile, 'test content');

		$result = HashTraitStub::publicMd5File($tmpFile, true);
		$this->assertSame(16, strlen($result), 'MD5 file binary output must be 16 bytes');
	}

	public function testSha1FileBinaryLength(): void
	{
		$tmpFile           = tempnam(sys_get_temp_dir(), 'akeeba_test_');
		$this->tempFiles[] = $tmpFile;
		file_put_contents($tmpFile, 'test content');

		$result = HashTraitStub::publicSha1File($tmpFile, true);
		$this->assertSame(20, strlen($result), 'SHA-1 file binary output must be 20 bytes');
	}
}
