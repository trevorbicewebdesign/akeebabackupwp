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

namespace Akeeba\Engine\DevPlatform\Command\Mixin;

use Akeeba\Engine\Factory;
use Akeeba\Engine\Platform;

trait ComputesBackupStatus
{
	/**
	 * Compute the human-facing status of a backup record.
	 *
	 * Mirrors the "meta" status logic of Akeeba Backup for Joomla
	 * (StatisticsModel::getStatisticsListWithMeta).
	 *
	 * @param   array       $stat          A backup record (assoc array from get_statistics()).
	 * @param   array|null  $validRecords  Result of Platform::get_valid_backup_records(). Pass it in
	 *                                     when computing for many records to avoid repeated queries;
	 *                                     null means it will be fetched.
	 *
	 * @return  string  One of: OK, Obsolete, Remote, Running, Failed.
	 */
	protected function computeBackupStatus(array $stat, ?array $validRecords = null): string
	{
		$validRecords ??= Platform::getInstance()->get_valid_backup_records() ?: [];

		switch ($stat['status'] ?? '')
		{
			case 'run':
				return 'Running';

			case 'fail':
				return 'Failed';
		}

		$hasRemoteFiles = $this->isRemoteFilename($stat['remote_filename'] ?? '');
		$meta           = $hasRemoteFiles ? 'Remote' : 'Obsolete';

		if (in_array($stat['id'] ?? null, $validRecords))
		{
			$archives      = Factory::getStatistics()->get_all_filenames($stat);
			$hasLocalFiles = is_array($archives) && count($archives) > 0;
			$meta          = $hasLocalFiles ? 'OK' : ($hasRemoteFiles ? 'Remote' : 'Obsolete');
		}

		return $meta;
	}

	/**
	 * Does the given remote filename look like a valid "engine://path" reference?
	 *
	 * @param   string|null  $remoteFilename
	 *
	 * @return  bool
	 */
	protected function isRemoteFilename(?string $remoteFilename): bool
	{
		$remoteFilename = trim((string) $remoteFilename);

		return $remoteFilename !== '' && (bool) preg_match('#^[a-z0-9]+://.+#i', $remoteFilename);
	}
}
