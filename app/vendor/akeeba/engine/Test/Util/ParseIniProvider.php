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

final class ParseIniProvider
{
	/**
	 * Data provider for recursiveUnescape tests.
	 *
	 * @return array
	 */
	public static function recursiveUnescapeProvider(): array
	{
		return [
			'empty string'         => ['', ''],
			'no escapes'           => ['hello world', 'hello world'],
			'escaped newline'      => ['line1\nline2', "line1\nline2"],
			'escaped carriage ret' => ['line1\rline2', "line1\rline2"],
			'escaped tab'          => ['col1\tcol2', "col1\tcol2"],
			'escaped double quote' => ['say \"hello\"', 'say "hello"'],
			'all escapes combined' => ['\r\n\t\"', "\r\n\t\""],
			'backslash not escape' => ['C:\\Users\\foo', 'C:\\Users\\foo'],
		];
	}

	/**
	 * Data provider for recursiveUnescape with arrays (recursive behavior).
	 * Note: only VALUES are unescaped; array KEYS are not touched.
	 *
	 * @return array
	 */
	public static function recursiveUnescapeArrayProvider(): array
	{
		return [
			'flat array'   => [
				['a\nb', 'c\td'],
				["a\nb", "c\td"],
			],
			'nested array' => [
				// Keys are not unescaped — only values
				['key1' => 'val\n1', 'key2' => ['nested\\t' => 'v\"alue']],
				['key1' => "val\n1", 'key2' => ['nested\\t' => 'v"alue']],
			],
			'empty array'  => [[], []],
		];
	}

	/**
	 * Data provider for parse_ini_file() with raw data (no sections).
	 *
	 * @return array
	 */
	public static function parseIniRawNoSectionsProvider(): array
	{
		return [
			'empty input'                   => [
				'',
				[],
			],
			'simple key=value'              => [
				"foo=bar\nbaz=qux\n",
				['foo' => 'bar', 'baz' => 'qux'],
			],
			'quoted value'                  => [
				"key=\"hello world\"\n",
				['key' => 'hello world'],
			],
			'value with comment ignored'    => [
				"key=value ; inline comment\n",
				['key' => 'value'],
			],
			'semicolon-only comment line'   => [
				"; this is a comment\nkey=value\n",
				['key' => 'value'],
			],
			'hash comment line skipped'     => [
				"# comment\nkey=value\n",
				['key' => 'value'],
			],
			'escaped newline in value'      => [
				"key=line1\\nline2\n",
				['key' => "line1\nline2"],
			],
			'escaped tab in value'          => [
				"key=col1\\tcol2\n",
				['key' => "col1\tcol2"],
			],
			'escaped double quote in value' => [
				"key=say \\\"hello\\\"\n",
				['key' => 'say "hello"'],
			],
			'integer-like value'            => [
				"count=42\n",
				['count' => '42'],
			],
			// INI_SCANNER_RAW keeps true/false as literal strings, not boolean-converted
			'boolean true string'           => [
				"flag=true\n",
				['flag' => 'true'],
			],
			'boolean false string'          => [
				"flag=false\n",
				['flag' => 'false'],
			],
		];
	}

	/**
	 * Data provider for parse_ini_file() with raw data and sections enabled.
	 *
	 * @return array
	 */
	public static function parseIniRawWithSectionsProvider(): array
	{
		return [
			'single section'         => [
				"[mysection]\nfoo=bar\nbaz=qux\n",
				['mysection' => ['foo' => 'bar', 'baz' => 'qux']],
			],
			'multiple sections'      => [
				"[section1]\nkey1=val1\n[section2]\nkey2=val2\n",
				[
					'section1' => ['key1' => 'val1'],
					'section2' => ['key2' => 'val2'],
				],
			],
			'global key and section' => [
				"global=yes\n[section1]\nlocal=no\n",
				['global' => 'yes', 'section1' => ['local' => 'no']],
			],
			'empty input'            => [
				'',
				[],
			],
		];
	}

	/**
	 * Data provider for the pure-PHP parser parse_ini_file_php() with raw data.
	 *
	 * @return array
	 */
	public static function parseIniPhpRawProvider(): array
	{
		return [
			'empty input'             => [
				'',
				false,
				true,
				[],
			],
			'simple key=value'        => [
				"foo=bar\nbaz=qux\n",
				false,
				true,
				['foo' => 'bar', 'baz' => 'qux'],
			],
			'quoted value'            => [
				"key=\"hello world\"\n",
				false,
				true,
				['key' => 'hello world'],
			],
			'section with key'        => [
				"[s]\nfoo=bar\n",
				true,
				true,
				['s' => ['foo' => 'bar']],
			],
			'global and section'      => [
				"global=1\n[s]\nfoo=bar\n",
				true,
				true,
				['s' => ['foo' => 'bar'], 'global' => '1'],
			],
			'semicolon comment'       => [
				"; comment\nkey=value\n",
				false,
				true,
				['key' => 'value'],
			],
			'value with crlf endings' => [
				"foo=bar\r\nbaz=qux\r\n",
				false,
				true,
				['foo' => 'bar', 'baz' => 'qux'],
			],
		];
	}
}
