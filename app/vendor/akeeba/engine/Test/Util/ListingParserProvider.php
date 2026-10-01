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

/**
 * Data providers for ListingParserTest.
 */
class ListingParserProvider
{
	/**
	 * Provides UNIX-style ls -la listing strings and their expected parsed results (full mode).
	 */
	public static function parseUnixListingFullProvider(): array
	{
		// Date for 'Jan  2 2023' in the listing: dateString = '2 Jan 2023' = 1672610400 (UTC+2 = 1672617600)
		// We calculate the date via date_create('2 Jan 2023')->getTimestamp()
		$dateJan2023 = date_create('2 Jan 2023')->getTimestamp();

		// Permissions for -rw-r--r--:
		// userPerms='rw-': r=4, w=2 => permBit=6
		// groupPerms='r--': r=4 => permBit=4
		// otherPerms='r--': r=4 => permBit=4
		// bitPart=0, permsPart='644', perms = octdec('0644') = 420
		$permsRwRR = octdec('0644'); // 420

		// Permissions for drwxr-xr-x:
		// userPerms='rwx': r=4, w=2, x=1 => permBit=7
		// groupPerms='r-x': r=4, x=1 => permBit=5
		// otherPerms='r-x': r=4, x=1 => permBit=5
		// bitPart=0, permsPart='755', perms = octdec('0755') = 493
		$permsDirRxRx = octdec('0755'); // 493

		// Permissions for lrwxrwxrwx:
		// All three are 'rwx': r=4, w=2, x=1 => permBit=7 each
		// bitPart=0, permsPart='777', perms = octdec('0777') = 511
		$permsSymlink = octdec('0777'); // 511

		return [
			'regular file line' => [
				"-rw-r--r-- 1 user group 12345 Jan  2 2023 file.txt",
				false,
				[
					[
						'name'   => 'file.txt',
						'type'   => 'file',
						'target' => '',
						'user'   => 'user',
						'group'  => 'group',
						'size'   => '12345',
						'date'   => $dateJan2023,
						'perms'  => $permsRwRR,
					],
				],
			],

			'directory line' => [
				"drwxr-xr-x 2 user group 4096 Jan  2 2023 dirname",
				false,
				[
					[
						'name'   => 'dirname',
						'type'   => 'dir',
						'target' => '',
						'user'   => 'user',
						'group'  => 'group',
						'size'   => '4096',
						'date'   => $dateJan2023,
						'perms'  => $permsDirRxRx,
					],
				],
			],

			'symlink line' => [
				"lrwxrwxrwx 1 user group 7 Jan  2 2023 link->target",
				false,
				[
					[
						'name'   => 'link',
						'type'   => 'link',
						'target' => 'target',
						'user'   => 'user',
						'group'  => 'group',
						'size'   => '7',
						'date'   => $dateJan2023,
						'perms'  => $permsSymlink,
					],
				],
			],

			'multiple lines preserve order' => [
				"-rw-r--r-- 1 user group 12345 Jan  2 2023 file.txt\ndrwxr-xr-x 2 user group 4096 Jan  2 2023 dirname",
				false,
				[
					[
						'name'   => 'file.txt',
						'type'   => 'file',
						'target' => '',
						'user'   => 'user',
						'group'  => 'group',
						'size'   => '12345',
						'date'   => $dateJan2023,
						'perms'  => $permsRwRR,
					],
					[
						'name'   => 'dirname',
						'type'   => 'dir',
						'target' => '',
						'user'   => 'user',
						'group'  => 'group',
						'size'   => '4096',
						'date'   => $dateJan2023,
						'perms'  => $permsDirRxRx,
					],
				],
			],

			'lines with too few fields are ignored' => [
				"total 8\n-rw-r--r-- 1 user group 12345 Jan  2 2023 file.txt",
				false,
				[
					[
						'name'   => 'file.txt',
						'type'   => 'file',
						'target' => '',
						'user'   => 'user',
						'group'  => 'group',
						'size'   => '12345',
						'date'   => $dateJan2023,
						'perms'  => $permsRwRR,
					],
				],
			],
		];
	}

	/**
	 * Provides UNIX-style ls -la listing strings and their expected parsed results (quick mode).
	 */
	public static function parseUnixListingQuickProvider(): array
	{
		return [
			'quick mode regular file' => [
				"-rw-r--r-- 1 user group 12345 Jan  2 2023 file.txt",
				true,
				[
					[
						'name'   => 'file.txt',
						'type'   => 'file',
						'size'   => '12345',
						'target' => '',
					],
				],
			],

			'quick mode directory' => [
				"drwxr-xr-x 2 user group 4096 Jan  2 2023 dirname",
				true,
				[
					[
						'name'   => 'dirname',
						'type'   => 'dir',
						'size'   => '4096',
						'target' => '',
					],
				],
			],

			'quick mode symlink' => [
				"lrwxrwxrwx 1 user group 7 Jan  2 2023 link->target",
				true,
				[
					[
						'name'   => 'link',
						'type'   => 'link',
						'size'   => '7',
						'target' => 'target',
					],
				],
			],
		];
	}

	/**
	 * Provides MS-DOS style directory listing strings and their expected parsed results (full mode).
	 */
	public static function parseMSDOSListingFullProvider(): array
	{
		// 24-hour format without AM/PM
		// dateString = '01-01-2024 15:30'
		$date24h = date_create('01-01-2024 15:30')->getTimestamp();

		// AM/PM format
		// dateString = '01-01-2024 12:00 AM'
		$dateAM = date_create('01-01-2024 12:00 AM')->getTimestamp();

		return [
			'directory line 24h' => [
				"01-01-2024 15:30 <DIR> dirname",
				false,
				[
					[
						'name'   => 'dirname',
						'type'   => 'dir',
						'target' => '',
						'user'   => '0',
						'group'  => '0',
						'size'   => '0',
						'date'   => $date24h,
						'perms'  => '0',
					],
				],
			],

			'file line 24h' => [
				"01-01-2024 15:30 12345 file.txt",
				false,
				[
					[
						'name'   => 'file.txt',
						'type'   => 'file',
						'target' => '',
						'user'   => '0',
						'group'  => '0',
						'size'   => 12345,
						'date'   => $date24h,
						'perms'  => '0',
					],
				],
			],

			'mixed file and directory 24h' => [
				"01-01-2024 15:30 <DIR> dirname\n01-01-2024 15:30 12345 file.txt",
				false,
				[
					[
						'name'   => 'dirname',
						'type'   => 'dir',
						'target' => '',
						'user'   => '0',
						'group'  => '0',
						'size'   => '0',
						'date'   => $date24h,
						'perms'  => '0',
					],
					[
						'name'   => 'file.txt',
						'type'   => 'file',
						'target' => '',
						'user'   => '0',
						'group'  => '0',
						'size'   => 12345,
						'date'   => $date24h,
						'perms'  => '0',
					],
				],
			],

			'directory line with AM/PM' => [
				"01-01-2024 12:00 AM <DIR> dirname",
				false,
				[
					[
						'name'   => 'dirname',
						'type'   => 'dir',
						'target' => '',
						'user'   => '0',
						'group'  => '0',
						'size'   => '0',
						'date'   => $dateAM,
						'perms'  => '0',
					],
				],
			],
		];
	}

	/**
	 * Provides MS-DOS style directory listing strings and their expected parsed results (quick mode).
	 */
	public static function parseMSDOSListingQuickProvider(): array
	{
		return [
			'quick mode directory' => [
				"01-01-2024 15:30 <DIR> dirname",
				true,
				[
					[
						'name'   => 'dirname',
						'type'   => 'dir',
						'size'   => '0',
						'target' => '',
					],
				],
			],

			'quick mode file' => [
				"01-01-2024 15:30 12345 file.txt",
				true,
				[
					[
						'name'   => 'file.txt',
						'type'   => 'file',
						'size'   => 12345,
						'target' => '',
					],
				],
			],
		];
	}

	/**
	 * Tests that parseListing() falls back to MSDOS when UNIX parsing returns empty.
	 */
	public static function parseListingFallbackProvider(): array
	{
		$date24h = date_create('01-01-2024 15:30')->getTimestamp();

		return [
			'unix listing returns unix results' => [
				"-rw-r--r-- 1 user group 12345 Jan  2 2023 file.txt",
				false,
				'file',
				'file.txt',
				'12345',
			],

			'msdos listing falls back when unix fails' => [
				"01-01-2024 15:30 12345 file.txt",
				false,
				'file',
				'file.txt',
				12345,
			],
		];
	}

	/**
	 * Tests for empty and malformed listings.
	 */
	public static function emptyOrMalformedProvider(): array
	{
		return [
			'empty string returns empty array' => [
				'',
			],

			'only whitespace returns empty array' => [
				"   \n   \n",
			],

			'total line only unix' => [
				"total 8",
			],

			'fewer than 4 fields msdos' => [
				"just three fields",
			],
		];
	}
}
