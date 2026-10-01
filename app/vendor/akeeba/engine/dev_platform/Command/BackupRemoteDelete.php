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

use Akeeba\Engine\DevPlatform\Command\Mixin\ComputesBackupStatus;
use Akeeba\Engine\Factory;
use Akeeba\Engine\Platform;
use Silly\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

class BackupRemoteDelete
{
	use ComputesBackupStatus;

	public static function register(Application $app)
	{
		$app
			->command('backup:remote:delete id [--profile=] [--force]', new self())
			->descriptions(
				'Delete the remotely stored archive files of a backup record',
				[
					'id'        => 'Backup record ID',
					'--profile' => 'Override the profile ID to use (default: use the record\'s profile)',
					'--force'   => 'Skip the confirmation prompt',
				]
			);
	}

	public function __invoke(
		int $id, ?int $profile, bool $force, InputInterface $input, OutputInterface $output, SymfonyStyle $io
	)
	{
		$stat = Platform::getInstance()->get_statistics($id);

		if (empty($stat) || ($stat['id'] ?? null) != $id)
		{
			$io->error("Backup record $id does not exist.");

			return 1;
		}

		// Frozen guard: never delete remote files of a frozen record.
		if (!empty($stat['frozen']))
		{
			$io->error("Backup record #$id is frozen. Unfreeze it before deleting its remote files.");

			return 1;
		}

		$profileId = $profile ?? (int) $stat['profile_id'];

		define('AKEEBA_PROFILE', $profileId);
		Platform::getInstance()->load_configuration($profileId);

		$config = Factory::getConfiguration();

		if (!$this->isRemoteFilename($stat['remote_filename'] ?? ''))
		{
			$io->error("Backup record #$id has no remotely stored files.");

			return 1;
		}

		// Confirmation prompt unless --force is given.
		if (!$force)
		{
			$confirmed = $io->confirm(
				sprintf(
					'Are you sure you want to delete the remote files for backup record #%d (%s)?',
					$id,
					$stat['remote_filename']
				),
				false
			);

			if (!$confirmed)
			{
				$io->text('Operation cancelled.');

				return 0;
			}
		}

		[$engineName, $remotePathBase] = explode('://', $stat['remote_filename'], 2);

		$engine = Factory::getPostprocEngine($engineName);

		if ($engine === null)
		{
			$io->error("Cannot load post-processing engine '$engineName'.");

			return 1;
		}

		if (!$engine->supportsDelete())
		{
			$io->error("Engine '$engineName' does not support remote file deletion.");

			return 1;
		}

		$totalParts = max((int) ($stat['multipart'] ?? 1), 1);

		$io->text(sprintf("Deleting %d remote file(s) for backup record #$id via '$engineName'…", $totalParts));

		for ($part = 0; $part < $totalParts; $part++)
		{
			$ext        = strtolower(str_replace('.', '', strrchr(basename($remotePathBase), '.')));
			$remotePart = substr($remotePathBase, 0, -strlen($ext)) . $this->partExtension($ext, $part);

			$io->text(sprintf('  Deleting part %d/%d: %s', $part + 1, $totalParts, basename($remotePart)));

			try
			{
				$engine->delete($remotePart);
			}
			catch (Throwable $e)
			{
				$io->error(sprintf('Failed to delete remote part %d: %s', $part + 1, $e->getMessage()));

				return 1;
			}
		}

		Platform::getInstance()->set_or_update_statistics($id, array_merge($stat, ['remote_filename' => '']));

		$io->success("Deleted remotely stored files of backup record #$id.");

		return 0;
	}

	/**
	 * Returns the file extension for a given 0-based part number.
	 *
	 * @param   string  $baseExtension  The extension of the first part (without leading dot).
	 * @param   int     $part           0-based part index.
	 *
	 * @return  string
	 */
	private function partExtension(string $baseExtension, int $part): string
	{
		return $part === 0 ? $baseExtension : substr($baseExtension, 0, 1) . sprintf('%02u', $part);
	}
}
