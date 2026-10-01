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
use Akeeba\Engine\DevPlatform\Command\Mixin\OutputsData;
use Akeeba\Engine\Factory;
use Akeeba\Engine\Platform;
use Silly\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class BackupInfo
{
	use OutputsData;
	use ComputesBackupStatus;

	public static function register(Application $app)
	{
		$app
			->command('backup:info id [--json]', new self())
			->descriptions(
				'Show detailed information about a backup record',
				[
					'id'     => 'Backup record ID',
					'--json' => 'Output JSON instead of a table',
				]
			);
	}

	public function __invoke(
		int $id, bool $json,
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

		$status = $this->computeBackupStatus($stat);

		$record                = $stat;
		$record['status_meta'] = $status;
		$record['local_files'] = Factory::getStatistics()->get_all_filenames($stat, false) ?: [];

		$this->outputRecord($record, $json, $io);

		return 0;
	}
}
