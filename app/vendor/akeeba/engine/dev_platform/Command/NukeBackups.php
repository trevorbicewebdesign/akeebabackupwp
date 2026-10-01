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
use Silly\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class NukeBackups
{
	public static function register(Application $app)
	{
		$app
			->command('nuke:backups [--force]', new self())
			->descriptions(
				'Delete all backup records and local backup archives',
				[
					'--force' => 'Skip the confirmation prompt',
				]
			);
	}

	public function __invoke(bool $force, InputInterface $input, OutputInterface $output, SymfonyStyle $io)
	{
		if (!$force && !$io->confirm('This deletes ALL backup records and local archives. Continue?', false))
		{
			$io->text('Aborted.');

			return 0;
		}

		$this->doNuke($io);

		return 0;
	}

	public function doNuke(SymfonyStyle $io): void
	{
		$db = Factory::getDatabase();

		$db->setQuery($db->getQuery(true)->delete($db->quoteName('#__ak_stats')))->execute();

		$backupDir = realpath(__DIR__ . '/../backup');

		if ($backupDir !== false && is_dir($backupDir))
		{
			$this->emptyDirectory($backupDir, ['index.html', 'index.htm', 'index.php', '.htaccess']);
		}

		$io->success('All backup records and local backup archives were deleted.');
	}

	private function emptyDirectory(string $dir, array $keep = []): void
	{
		foreach (@scandir($dir) ?: [] as $entry)
		{
			if ($entry === '.' || $entry === '..') continue;

			$path = $dir . '/' . $entry;

			if (is_dir($path) && !is_link($path))
			{
				$this->emptyDirectory($path, []);
				@rmdir($path);
			}
			elseif (!in_array($entry, $keep))
			{
				@unlink($path);
			}
		}
	}
}
