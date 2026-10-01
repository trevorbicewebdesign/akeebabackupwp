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
use Throwable;

class BackupRemoteUpload
{
	public static function register(Application $app)
	{
		$app
			->command('backup:remote:upload id [--profile=]', new self())
			->descriptions(
				'Upload a backup record\'s local archive parts to remote storage',
				[
					'id'        => 'Backup record ID',
					'--profile' => 'Override the profile ID to use (default: use the record\'s profile)',
				]
			);
	}

	public function __invoke(
		int $id, ?int $profile, InputInterface $input, OutputInterface $output, SymfonyStyle $io
	)
	{
		$stat = Platform::getInstance()->get_statistics($id);

		if (empty($stat) || ($stat['id'] ?? null) != $id)
		{
			$io->error("Backup record $id does not exist.");

			return 1;
		}

		$profileId = $profile ?? (int) $stat['profile_id'];

		define('AKEEBA_PROFILE', $profileId);
		Platform::getInstance()->load_configuration($profileId);

		$config = Factory::getConfiguration();

		// Make sure we have at least one part.
		$stat['multipart'] = max((int) ($stat['multipart'] ?? 1), 1);

		// Check that local files exist before attempting upload.
		$localFiles = Factory::getStatistics()->get_all_filenames($stat, false);

		if (empty($localFiles))
		{
			$io->error("Backup record #$id has no local archive files to upload.");

			return 1;
		}

		$engineName = $config->get('akeeba.advanced.postproc_engine');
		$engine     = Factory::getPostprocEngine($engineName);

		if ($engine === null)
		{
			$io->error("No post-processing engine configured (profile #$profileId).");

			return 1;
		}

		$io->text("Uploading backup record #$id using engine '$engineName'…");

		$part           = 0;
		$remoteFilename = null;

		try
		{
			while ($part < $stat['multipart'])
			{
				$localBase = $stat['absolute_path'];
				$ext       = strtolower(str_replace('.', '', strrchr(basename($localBase), '.')));
				$localFile = substr($localBase, 0, -strlen($ext)) . $this->partExtension($ext, $part);

				$io->text(sprintf('  Uploading part %d/%d: %s', $part + 1, $stat['multipart'], basename($localFile)));

				$done = $engine->processPart($localFile);

				if (!is_bool($done))
				{
					throw new \LogicException('Unexpected processPart() result (expected bool).');
				}

				if ($done)
				{
					$part++;
				}

				$remoteFilename = $engineName . '://' . $engine->getRemotePath();
			}
		}
		catch (Throwable $e)
		{
			$io->error(sprintf('Upload failed: %s', $e->getMessage()));

			return 1;
		}

		Platform::getInstance()->set_or_update_statistics($id, array_merge($stat, ['remote_filename' => $remoteFilename]));

		$io->success("Uploaded backup record #$id to remote storage ($remoteFilename).");

		return 0;
	}

	/**
	 * Returns the file extension for a given 0-based part number.
	 *
	 * Part 0 keeps the original extension (e.g. "jpa").
	 * Part N gets the first character of the extension followed by a zero-padded two-digit number
	 * (e.g. part 1 of "jpa" → "j01", part 2 → "j02").
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
