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

class BackupLogView
{
	public static function register(Application $app)
	{
		$app
			->command('backup:log id [--path]', new self())
			->descriptions(
				'View or locate the log file for a backup record',
				[
					'id'     => 'Backup record ID',
					'--path' => 'Print the absolute path to the log file instead of its contents',
				]
			);
	}

	public function __invoke(
		int $id, bool $path,
		InputInterface $input, OutputInterface $output, SymfonyStyle $io
	)
	{
		$stat = Platform::getInstance()->get_statistics($id);

		if (empty($stat) || ($stat['id'] ?? null) != $id)
		{
			$io->error("Backup record $id does not exist.");

			return 1;
		}

		define('AKEEBA_PROFILE', (int) $stat['profile_id']);
		Platform::getInstance()->load_configuration((int) $stat['profile_id']);

		// Build candidate log file paths
		$logFile = null;

		if (!empty($stat['absolute_path']))
		{
			$dir        = dirname($stat['absolute_path']);
			$base       = $dir . '/akeeba.' . $stat['tag'] . '.' . $stat['backupid'];
			$candidates = [$base . '.log.php', $base . '.php', $base . '.log'];

			foreach ($candidates as $c)
			{
				if (@is_file($c))
				{
					$logFile = $c;
					break;
				}
			}
		}

		// Fallback: use Logger::getLogFilename when backupid is empty or no file found yet
		if ($logFile === null)
		{
			$fallbackCandidates = Factory::getLog()->getAllLogFilenames($stat['tag'] ?? null);

			foreach ($fallbackCandidates as $c)
			{
				if (@is_file($c))
				{
					$logFile = $c;
					break;
				}
			}
		}

		if ($logFile === null)
		{
			$io->error("No log file found for backup record $id.");

			return 1;
		}

		if ($path)
		{
			$io->writeln($logFile);

			return 0;
		}

		// Stream log file contents, stripping the PHP die() guard on the first line of .log.php files
		$handle = @fopen($logFile, 'r');

		if ($handle === false)
		{
			$io->error("Cannot open log file: $logFile");

			return 1;
		}

		$firstLine = true;

		while (!feof($handle))
		{
			$line = fgets($handle);

			if ($line === false)
			{
				break;
			}

			if ($firstLine)
			{
				$firstLine = false;

				// Strip the PHP die() guard line present at the start of .log.php files
				if (strncmp($line, '<?php', 5) === 0)
				{
					continue;
				}
			}

			$io->write($line);
		}

		fclose($handle);

		return 0;
	}
}
