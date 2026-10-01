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

class StatisticsProvider
{
	/**
	 * Provider for filesexist = 0 cases (always returns empty array regardless of other params).
	 */
	public static function filesExistZeroProvider()
	{
		return [
			'filesexist=0 skipNonComplete=true' => [
				[
					'filesexist'    => 0,
					'absolute_path' => '/tmp/backup.jpa',
					'archivename'   => 'backup.jpa',
					'multipart'     => 0,
					'status'        => 'complete',
				],
				true,
				[],
			],
			'filesexist=0 skipNonComplete=false' => [
				[
					'filesexist'    => 0,
					'absolute_path' => '/tmp/backup.jpa',
					'archivename'   => 'backup.jpa',
					'multipart'     => 0,
					'status'        => 'complete',
				],
				false,
				[],
			],
		];
	}

	/**
	 * Provider for empty archivename (returns null — writer that doesn't store on server).
	 */
	public static function emptyArchiveNameProvider()
	{
		return [
			'empty archivename skipNonComplete=true' => [
				[
					'filesexist'    => 1,
					'absolute_path' => '/tmp/backup.jpa',
					'archivename'   => '',
					'multipart'     => 0,
					'status'        => 'complete',
				],
				true,
				null,
			],
			'empty archivename skipNonComplete=false' => [
				[
					'filesexist'    => 1,
					'absolute_path' => '/tmp/backup.jpa',
					'archivename'   => '',
					'multipart'     => 0,
					'status'        => 'complete',
				],
				false,
				null,
			],
		];
	}

	/**
	 * Provides multipart extension-naming scheme test cases.
	 * Returns the base filename and expected part names for a given multipart count.
	 *
	 * Format: [archivename, multipart, expectedPartNames]
	 * expectedPartNames includes the main file and the numbered parts (but NOT the main file
	 * in the list — the main file goes last, part files go before).
	 */
	public static function multipartNamingProvider()
	{
		return [
			'jpa multipart=2' => [
				'backup.jpa',
				2,
				['backup.j01', 'backup.jpa'],
			],
			'jpa multipart=3' => [
				'backup.jpa',
				3,
				['backup.j01', 'backup.j02', 'backup.jpa'],
			],
			'jpa multipart=10' => [
				'backup.jpa',
				10,
				['backup.j01', 'backup.j02', 'backup.j03', 'backup.j04', 'backup.j05',
				 'backup.j06', 'backup.j07', 'backup.j08', 'backup.j09', 'backup.jpa'],
			],
			'zip multipart=2' => [
				'backup.zip',
				2,
				['backup.z01', 'backup.zip'],
			],
			'zip multipart=3' => [
				'backup.zip',
				3,
				['backup.z01', 'backup.z02', 'backup.zip'],
			],
		];
	}
}
