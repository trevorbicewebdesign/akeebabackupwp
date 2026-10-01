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

use Akeeba\Engine\Platform;
use Silly\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class BackupDelete
{
	public static function register(Application $app)
	{
		$app
			->command('backup:delete id [--force]', new self())
			->descriptions(
				'Delete a backup record and its local archive files',
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

		if (!$force && !$io->confirm(sprintf('Delete backup record #%d and its local files?', $id), false))
		{
			return 0;
		}

		(new BackupDeleteFiles())->deleteLocalFiles($id, $io);

		if (!Platform::getInstance()->delete_statistics($id))
		{
			$io->error(sprintf('Could not delete backup record #%d from the database.', $id));

			return 1;
		}

		$io->success(sprintf('Deleted backup record #%d.', $id));

		return 0;
	}
}
