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

class FilterAdd
{
	/** DB-side filter types */
	private const DB_TYPES = ['tables', 'tabledata', 'regextables', 'regextabledata', 'multidb'];

	public static function register(Application $app)
	{
		$app
			->command('filter:add filter [--profile=] [--type=] [--root=] [--virtual=]', new self())
			->defaults(
				[
					'profile' => 1,
					'type'    => 'files',
					'root'    => '',
					'virtual' => '',
				]
			)
			->descriptions(
				'Add an inclusion or exclusion filter to a profile',
				[
					'filter'    => 'For exclusion types: the filter string (path/table/regex). For extradirs: the filesystem directory to include.',
					'--profile' => 'Profile ID (default 1)',
					'--type'    => 'Filter type (files, directories, skipfiles, skipdirs, regexfiles, regexdirectories, extradirs, tables, tabledata, regextables, regextabledata, multidb)',
					'--root'    => 'Filter root (default: [SITEROOT] for filesystem types, [SITEDB] for database types)',
					'--virtual' => 'For extradirs only: the virtual directory name inside the archive (defaults to basename of the included directory)',
				]
			);
	}

	public function __invoke(
		string $filter,
		int $profile,
		string $type,
		string $root,
		string $virtual,
		InputInterface $input,
		OutputInterface $output,
		SymfonyStyle $io
	)
	{
		define('AKEEBA_PROFILE', $profile);
		Platform::getInstance()->load_configuration($profile);

		if ($type === 'multidb')
		{
			$io->error('Adding multidb inclusion filters is not supported via CLI.');

			return 1;
		}

		$filterObject = Factory::getFilterObject($type);

		if ($filterObject === null)
		{
			$io->error(sprintf("Unknown filter type '%s'.", $type));

			return 1;
		}

		// Determine default root when not provided
		if (empty($root))
		{
			$root = in_array($type, self::DB_TYPES) ? '[SITEDB]' : '[SITEROOT]';
		}

		if ($type === 'extradirs')
		{
			$uuid    = bin2hex(random_bytes(16));
			$virtual = $virtual ?: basename($filter);
			$success = $filterObject->set($uuid, [$filter, $virtual]);
		}
		else
		{
			$success = $filterObject->set($root, $filter);
		}

		if (!$success)
		{
			$io->error('Failed to add filter (it may already exist).');

			return 2;
		}

		Factory::getFilters()->save();

		$io->success(sprintf("Added %s filter '%s' to profile #%d.", $type, $filter, $profile));

		return 0;
	}
}
