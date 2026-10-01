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
use Akeeba\Engine\Platform;
use Silly\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class BackupList
{
	use OutputsData;
	use ComputesBackupStatus;

	public static function register(Application $app)
	{
		$app
			->command(
				'backup:list [--profile=] [--after=] [--before=] [--description=] [--limit=] [--from=] [--json]',
				new self()
			)
			->defaults(
				[
					'profile'     => null,
					'after'       => null,
					'before'      => null,
					'description' => null,
					'limit'       => 0,
					'from'        => 0,
				]
			)
			->descriptions(
				'List backup records',
				[
					'--profile'     => 'Filter by profile ID',
					'--after'       => 'Only backups started on/after this date (e.g. 2026-01-01 or 2026-01-01 00:00:00)',
					'--before'      => 'Only backups started on/before this date',
					'--description' => 'Partial (LIKE) match of the description',
					'--limit'       => 'Max records (0 = all; default 0)',
					'--from'        => 'Offset (default 0)',
					'--json'        => 'Output JSON instead of a table',
				]
			);
	}

	public function __invoke(
		$profile, $after, $before, $description, $limit, $from, bool $json,
		InputInterface $input, OutputInterface $output, SymfonyStyle $io
	)
	{
		$filters = [];

		if (!empty($description))
		{
			$filters[] = ['field' => 'description', 'operand' => 'LIKE', 'value' => $description];
		}

		if (!empty($after) && !empty($before))
		{
			$filters[] = ['field' => 'backupstart', 'operand' => 'BETWEEN', 'value' => $after, 'value2' => $before];
		}
		elseif (!empty($after))
		{
			$filters[] = ['field' => 'backupstart', 'operand' => '>=', 'value' => $after];
		}
		elseif (!empty($before))
		{
			$filters[] = ['field' => 'backupstart', 'operand' => '<=', 'value' => $before];
		}

		$profileId = (int) $profile;

		if ($profileId > 0)
		{
			$filters[] = ['field' => 'profile_id', 'operand' => '=', 'value' => $profileId];
		}

		$list  = Platform::getInstance()->get_statistics_list(
			[
				'limitstart' => (int) $from,
				'limit'      => (int) $limit,
				'filters'    => $filters ?: null,
				'order'      => ['by' => 'id', 'order' => 'desc'],
			]
		) ?: [];
		$valid = Platform::getInstance()->get_valid_backup_records() ?: [];

		$rows = [];

		foreach ($list as $stat)
		{
			$status = $this->computeBackupStatus($stat, $valid);
			$rows[] = [
				'ID'          => $stat['id'],
				'Profile'     => $stat['profile_id'],
				'Description' => $stat['description'],
				'Start'       => $stat['backupstart'],
				'Status'      => $status,
				'Size'        => $stat['total_size'],
			];
		}

		$this->outputRows($rows, ['ID', 'Profile', 'Description', 'Start', 'Status', 'Size'], $json, $io);

		return 0;
	}
}
