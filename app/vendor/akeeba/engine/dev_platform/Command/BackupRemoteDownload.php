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
use Akeeba\Engine\Postproc\Exception\DownloadToServerNotSupported;
use Akeeba\Engine\Postproc\Exception\RangeDownloadNotSupported;
use Silly\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

class BackupRemoteDownload
{
	use ComputesBackupStatus;

	public static function register(Application $app)
	{
		$app
			->command('backup:remote:download id [--profile=]', new self())
			->descriptions(
				'Download a remotely stored backup archive back to local storage',
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

		if (!$this->isRemoteFilename($stat['remote_filename'] ?? ''))
		{
			$io->error("Backup record #$id has no remotely stored files.");

			return 1;
		}

		[$engineName, $remotePathBase] = explode('://', $stat['remote_filename'], 2);

		$engine = Factory::getPostprocEngine($engineName);

		if ($engine === null)
		{
			$io->error("Cannot load post-processing engine '$engineName'.");

			return 1;
		}

		if (!$engine->supportsDownloadToFile())
		{
			$io->error("Engine '$engineName' does not support downloading files to the server.");

			return 1;
		}

		$totalParts = max((int) ($stat['multipart'] ?? 1), 1);
		$outputDir  = $config->get('akeeba.basic.output_directory');

		$io->text(sprintf("Downloading backup record #$id (%d part(s)) from '$engineName'…", $totalParts));

		for ($part = 0; $part < $totalParts; $part++)
		{
			$ext        = strtolower(str_replace('.', '', strrchr(basename($remotePathBase), '.')));
			$remotePart = substr($remotePathBase, 0, -strlen($ext)) . $this->partExtension($ext, $part);
			$localPart  = $outputDir . '/' . basename($remotePart);

			$io->text(sprintf('  Downloading part %d/%d: %s', $part + 1, $totalParts, basename($remotePart)));

			@unlink($localPart);

			try
			{
				// Attempt a single full download (no offset / length).
				$engine->downloadToFile($remotePart, $localPart);
			}
			catch (DownloadToServerNotSupported $e)
			{
				$io->error(sprintf("Engine '$engineName' does not support server-side download: %s", $e->getMessage()));

				return 1;
			}
			catch (RangeDownloadNotSupported $e)
			{
				// The engine cannot do a full single-shot download; fall back to range-based fragment loop.
				$io->text('    Engine requires range-based download; switching to fragment loop.');

				try
				{
					$this->downloadByFragments($engine, $remotePart, $localPart, $io);
				}
				catch (Throwable $fragEx)
				{
					$io->error(sprintf('Fragment download failed for part %d: %s', $part + 1, $fragEx->getMessage()));

					return 1;
				}
			}
			catch (Throwable $e)
			{
				$io->error(sprintf('Download failed for part %d: %s', $part + 1, $e->getMessage()));

				return 1;
			}
		}

		Platform::getInstance()->set_or_update_statistics($id, array_merge($stat, ['filesexist' => 1]));

		$io->success("Downloaded backup record #$id back to local storage.");

		return 0;
	}

	/**
	 * Downloads a remote file in fragments using range requests, appending each fragment to the
	 * local file until the engine signals completion (returns less than the requested chunk size).
	 *
	 * @param   \Akeeba\Engine\Postproc\PostProcInterface  $engine      The post-processing engine.
	 * @param   string                                     $remotePath  Remote path to the file.
	 * @param   string                                     $localFile   Absolute local path to write to.
	 * @param   SymfonyStyle                               $io          Console output helper.
	 *
	 * @return  void
	 */
	private function downloadByFragments($engine, string $remotePath, string $localFile, SymfonyStyle $io): void
	{
		// Use 1 MB fragments — same as the Joomla reference model.
		$fragmentSize = 1048576;
		$offset       = 0;
		$fragment     = 0;

		do
		{
			$io->text(sprintf('    Fragment %d (offset %d)…', ++$fragment, $offset));

			// Write a temporary fragment file, then append to the local output.
			$tempFile = $localFile . '.frag';

			@unlink($tempFile);

			$engine->downloadToFile($remotePath, $tempFile, $offset, $fragmentSize);

			$fragmentData = file_get_contents($tempFile);
			@unlink($tempFile);

			$bytesRead = strlen($fragmentData);

			file_put_contents($localFile, $fragmentData, FILE_APPEND);

			$offset += $bytesRead;

			// When the returned data is smaller than the requested chunk, we have reached the end.
		} while ($bytesRead >= $fragmentSize);
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
