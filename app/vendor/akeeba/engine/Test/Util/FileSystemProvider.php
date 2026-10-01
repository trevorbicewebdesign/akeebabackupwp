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

final class FileSystemProvider
{
	/**
	 * Data provider for TranslateWinPath on a non-Windows (UNIX-like) host.
	 *
	 * On non-Windows, the method only removes duplicate forward slashes — backslash
	 * conversion is intentionally skipped because DIRECTORY_SEPARATOR is '/'.
	 */
	public static function translateWinPathUnixProvider(): array
	{
		return [
			'unchanged unix path'                  => ['/var/www/html', '/var/www/html'],
			'double slash is collapsed'            => ['/var//www/html', '/var/www/html'],
			'triple slash is collapsed'            => ['/var///www/html', '/var/www/html'],
			'many slashes collapsed to one'        => ['/a////b', '/a/b'],
			'leading double slash unchanged on unix' => ['//server/share', '/server/share'],
			'empty string'                         => ['', ''],
			'just a slash'                         => ['/', '/'],
			'backslash is NOT converted on unix'   => ['C:\\Windows\\System32', 'C:\\Windows\\System32'],
		];
	}

	/**
	 * Data provider for TrimTrailingSlash.
	 */
	public static function trimTrailingSlashProvider(): array
	{
		return [
			'no trailing slash'               => ['/var/www/html', '/var/www/html'],
			'trailing forward slash removed'  => ['/var/www/html/', '/var/www/html'],
			'trailing backslash removed'      => ['C:\\Windows\\', 'C:\\Windows'],
			'empty string unchanged'          => ['', ''],
			'root slash unchanged'            => ['/', ''],
			'double trailing slash: only one' => ['/var/www/html//', '/var/www/html/'],
			'just forward slash'              => ['/', ''],
			'just backslash'                  => ['\\', ''],
			'path without trailing slash'     => ['/var/www', '/var/www'],
		];
	}

	/**
	 * Data provider for stringUrlUnicodeSlug.
	 */
	public static function stringUrlUnicodeSlugProvider(): array
	{
		return [
			'empty string'                        => ['', ''],
			'simple lowercase word'               => ['hello', 'hello'],
			'mixed case lowercased'               => ['Hello World', 'hello-world'],
			'spaces become hyphens'               => ['foo bar baz', 'foo-bar-baz'],
			'multiple spaces collapse to one'     => ['foo  bar', 'foo-bar'],
			'hyphens become spaces then hyphens'  => ['foo-bar', 'foo-bar'],
			'forbidden colon removed'             => ['foo:bar', 'foo-bar'],
			'forbidden question mark removed'     => ['foo?bar', 'foo-bar'],
			'forbidden dot removed'               => ['foo.bar', 'foo-bar'],
			'forbidden slash removed'             => ['foo/bar', 'foo-bar'],
			'forbidden backslash removed'         => ['foo\\bar', 'foo-bar'],
			'forbidden at removed'                => ['foo@bar', 'foo-bar'],
			'leading trailing spaces trimmed'     => ['  hello  ', 'hello'],
			'unicode: accented char preserved'    => ["caf\xc3\xa9", "caf\xc3\xa9"],
			'double-byte space (east asian) removed' => ["\xE3\x80\x80foo", 'foo'],
			'numbers preserved'                   => ['backup 2024', 'backup-2024'],
			'underscore preserved'                => ['my_site', 'my_site'],
		];
	}

	/**
	 * Data provider for translateStockDirs.
	 *
	 * The stock directories used are:
	 *   '[SITEROOT]'   => '/var/www/html'
	 *   '[TEMP]'       => '/tmp/mysite'
	 *   '[BACKUP]'     => '/var/www/html/backup'
	 */
	public static function translateStockDirsProvider(): array
	{
		return [
			'variable at start'                 => [
				'[SITEROOT]/images',
				false, false,
				'/var/www/html/images',
			],
			'variable in middle replaced'       => [
				'/some/[SITEROOT]/path',
				false, false,
				'/some//var/www/html/path',
			],
			'no variable unchanged'             => [
				'/var/www/html/images',
				false, false,
				'/var/www/html/images',
			],
			'temp variable'                     => [
				'[TEMP]/cache',
				false, false,
				'/tmp/mysite/cache',
			],
			'trim trailing slash'               => [
				'[SITEROOT]/',
				false, true,
				'/var/www/html',
			],
			'remove multiple slashes when translate_win_dirs=true' => [
				'[SITEROOT]//images',
				true, false,
				'/var/www/html/images',
			],
			'empty string unchanged'            => ['', false, false, ''],
		];
	}

	/**
	 * Data provider for rebaseFolderToStockDirs.
	 *
	 * Stock directories (same as translateStockDirs provider):
	 *   '[SITEROOT]' => '/var/www/html'
	 *   '[TEMP]'     => '/tmp/mysite'
	 *   '[BACKUP]'   => '/var/www/html/backup'
	 */
	public static function rebaseFolderToStockDirsProvider(): array
	{
		return [
			'exact siteroot match'              => ['/var/www/html', '[SITEROOT]'],
			'siteroot subdirectory'             => ['/var/www/html/images', '[SITEROOT]/images'],
			'temp directory'                    => ['/tmp/mysite', '[TEMP]'],
			'temp subdirectory'                 => ['/tmp/mysite/cache', '[TEMP]/cache'],
			// /var/www/html/backup is longer and should win over /var/www/html
			'backup subdir over siteroot'       => ['/var/www/html/backup/file.jpa', '[BACKUP]/file.jpa'],
			'unrelated path unchanged'          => ['/etc/passwd', '/etc/passwd'],
			'trailing slash stripped first'     => ['/var/www/html/', '[SITEROOT]'],
		];
	}
}
