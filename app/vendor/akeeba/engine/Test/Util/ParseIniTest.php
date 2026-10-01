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

use Akeeba\Engine\Util\ParseIni;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/ParseIniProvider.php';

final class ParseIniTest extends TestCase
{
	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\ParseIniProvider::recursiveUnescapeProvider()
	 */
	public function testRecursiveUnescapeString(string $input, string $expected): void
	{
		$this->assertSame($expected, ParseIni::recursiveUnescape($input));
	}

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\ParseIniProvider::recursiveUnescapeArrayProvider()
	 */
	public function testRecursiveUnescapeArray(array $input, array $expected): void
	{
		$this->assertSame($expected, ParseIni::recursiveUnescape($input));
	}

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\ParseIniProvider::parseIniRawNoSectionsProvider()
	 */
	public function testParseIniRawNoSections(string $rawIni, array $expected): void
	{
		$result = ParseIni::parse_ini_file($rawIni, false, true);
		$this->assertSame($expected, $result);
	}

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\ParseIniProvider::parseIniRawWithSectionsProvider()
	 */
	public function testParseIniRawWithSections(string $rawIni, array $expected): void
	{
		$result = ParseIni::parse_ini_file($rawIni, true, true);
		$this->assertSame($expected, $result);
	}

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\ParseIniProvider::parseIniPhpRawProvider()
	 */
	public function testParseIniFilePHP(string $rawIni, bool $processSections, bool $rawdata, array $expected): void
	{
		$result = ParseIni::parse_ini_file_php($rawIni, $processSections, $rawdata);
		$this->assertSame($expected, $result);
	}

	/**
	 * Test that the pure-PHP parser is forced when $forcePHP is true (raw data path).
	 */
	public function testForcePHPParserRaw(): void
	{
		$ini    = "key=value\nanother=thing\n";
		$result = ParseIni::parse_ini_file($ini, false, true, true);
		$this->assertSame(['key' => 'value', 'another' => 'thing'], $result);
	}

	/**
	 * Test parse_ini_file() with a real file on disk (non-raw mode).
	 */
	public function testParseIniFromFile(): void
	{
		$tmpFile = tempnam(sys_get_temp_dir(), 'akeeba_ini_test_');
		file_put_contents($tmpFile, "alpha=one\nbeta=two\n");

		try
		{
			$result = ParseIni::parse_ini_file($tmpFile, false, false);
			$this->assertSame(['alpha' => 'one', 'beta' => 'two'], $result);
		}
		finally
		{
			@unlink($tmpFile);
		}
	}

	/**
	 * Test parse_ini_file() file mode with sections.
	 */
	public function testParseIniFromFileWithSections(): void
	{
		$tmpFile = tempnam(sys_get_temp_dir(), 'akeeba_ini_test_');
		file_put_contents($tmpFile, "[main]\nalpha=one\n[other]\nbeta=two\n");

		try
		{
			$result = ParseIni::parse_ini_file($tmpFile, true, false);
			$this->assertSame(
				['main' => ['alpha' => 'one'], 'other' => ['beta' => 'two']],
				$result
			);
		}
		finally
		{
			@unlink($tmpFile);
		}
	}

	/**
	 * Verify that dollar signs in values are preserved (not interpolated as variables).
	 * This is an important regression guard because parse_ini_string without INI_SCANNER_RAW
	 * would swallow $foo references.
	 */
	public function testDollarSignsArePreserved(): void
	{
		$ini    = "key=The value is \$foo and more\n";
		$result = ParseIni::parse_ini_file($ini, false, true);
		$this->assertSame('The value is $foo and more', $result['key']);
	}

	/**
	 * Verify that escaped backslash-dollar (\$) is preserved as-is in INI_SCANNER_RAW mode.
	 * The engine uses INI_SCANNER_RAW which does NOT unescape \$ to $; the sequence is returned
	 * verbatim. This is intentional — only \r, \n, \t, and \" are post-processed.
	 */
	public function testEscapedDollarSignPreserved(): void
	{
		$ini    = "key=price is \\$100\n";
		$result = ParseIni::parse_ini_file($ini, false, true);
		// INI_SCANNER_RAW keeps \$ as \$; recursiveUnescape does not touch it
		$this->assertSame('price is \$100', $result['key']);
	}

	/**
	 * Verify recursiveUnescape unescapes values inside deeply nested arrays.
	 */
	public function testRecursiveUnescapeDeepNesting(): void
	{
		$input    = ['level1' => ['level2' => 'val\\nwith\\tnewline']];
		$expected = ['level1' => ['level2' => "val\nwith\tnewline"]];
		$this->assertSame($expected, ParseIni::recursiveUnescape($input));
	}
}
