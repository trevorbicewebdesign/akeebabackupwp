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

namespace Akeeba\Engine\DevPlatform\Command;

use Akeeba\Engine\Factory;
use Akeeba\Engine\Platform;
use Silly\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class BackupDeleteFiles
{
	public static function register(Application $app)
	{
		$app
			->command('backup:delete:files id [--force]', new self())
			->descriptions(
				'Delete the local archive files of a backup record',
				[
					'id'      => 'Backup record ID',
					'--force' => 'Skip confirmation prompt',
				]
			);
	}

	public function __invoke(
		int $id, bool $force, InputInterface $input, OutputInterface $output, SymfonyStyle $io
	)
	{
		$stat = Platform::getInstance()->get_statistics($id);

		if (empty($stat))
		{
			$io->error(sprintf('Backup record #%d does not exist.', $id));

			return 1;
		}

		if (!empty($stat['frozen']))
		{
			$io->error(sprintf('Backup record #%d is frozen and cannot be modified.', $id));

			return 1;
		}

		if (!$force && !$io->confirm(sprintf('Delete local archive files of backup #%d?', $id), false))
		{
			return 0;
		}

		return $this->deleteLocalFiles($id, $io) ? 0 : 1;
	}

	/**
	 * Delete the local archive files and log files for a backup record, then update the record.
	 *
	 * This method performs the work without prompting. The caller is responsible for any
	 * confirmation prompts and frozen-guard checks.
	 *
	 * @param   int          $id  Backup record ID
	 * @param   SymfonyStyle $io  Console I/O helper
	 *
	 * @return  bool  True on success (including partial success with warnings), false on fatal error.
	 */
	public function deleteLocalFiles(int $id, SymfonyStyle $io): bool
	{
		$stat = Platform::getInstance()->get_statistics($id);

		if (empty($stat))
		{
			$io->error(sprintf('Backup record #%d does not exist.', $id));

			return false;
		}

		$profileId = (int) $stat['profile_id'];

		if (!defined('AKEEBA_PROFILE'))
		{
			define('AKEEBA_PROFILE', $profileId);
		}

		Platform::getInstance()->load_configuration($profileId);

		// Delete log files for this backup record
		if (!empty($stat['backupid']))
		{
			$logDir      = @dirname($stat['absolute_path']);
			$logBaseName = 'akeeba.' . $stat['tag'] . '.' . $stat['backupid'] . '.log';

			$logFile    = $logDir . DIRECTORY_SEPARATOR . $logBaseName;
			$logFilePhp = $logFile . '.php';

			if (@file_exists($logFile))
			{
				@unlink($logFile);
			}

			if (@file_exists($logFilePhp))
			{
				@unlink($logFilePhp);
			}
		}

		// Delete archive parts
		$allFiles    = Factory::getStatistics()->get_all_filenames($stat, false) ?: [];
		$failedFiles = [];

		foreach ($allFiles as $file)
		{
			if (!@file_exists($file))
			{
				continue;
			}

			if (!@unlink($file))
			{
				$failedFiles[] = $file;
			}
		}

		// Update the record: mark files as gone, reset total size
		Platform::getInstance()->set_or_update_statistics(
			$id,
			array_merge($stat, ['filesexist' => 0, 'total_size' => 0])
		);

		$io->success(sprintf('Deleted local archive files of backup record #%d.', $id));

		if (!empty($failedFiles))
		{
			$io->warning(
				array_merge(
					['The following files could not be deleted:'],
					$failedFiles
				)
			);
		}

		return true;
	}
}
