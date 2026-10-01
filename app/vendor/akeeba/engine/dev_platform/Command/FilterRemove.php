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

class FilterRemove
{
	/** DB-side filter types */
	private const DB_TYPES = ['tables', 'tabledata', 'regextables', 'regextabledata', 'multidb'];

	/** Inclusion filter types (use single-argument remove($uuid)) */
	private const INCLUSION_TYPES = ['extradirs', 'multidb'];

	public static function register(Application $app)
	{
		$app
			->command('filter:remove filter [--profile=] [--type=] [--root=]', new self())
			->defaults(
				[
					'profile' => 1,
					'type'    => 'files',
					'root'    => '',
				]
			)
			->descriptions(
				'Remove an inclusion or exclusion filter from a profile',
				[
					'filter'    => 'For exclusion types: the filter string. For extradirs/multidb: the UUID.',
					'--profile' => 'Profile ID (default 1)',
					'--type'    => 'Filter type (files, directories, skipfiles, skipdirs, regexfiles, regexdirectories, extradirs, tables, tabledata, regextables, regextabledata, multidb)',
					'--root'    => 'Filter root (default: [SITEROOT] for filesystem types, [SITEDB] for database types)',
				]
			);
	}

	public function __invoke(
		string $filter,
		int $profile,
		string $type,
		string $root,
		InputInterface $input,
		OutputInterface $output,
		SymfonyStyle $io
	)
	{
		define('AKEEBA_PROFILE', $profile);
		Platform::getInstance()->load_configuration($profile);

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

		if (in_array($type, self::INCLUSION_TYPES))
		{
			$success = $filterObject->remove($filter);
		}
		else
		{
			$success = $filterObject->remove($root, $filter);
		}

		if (!$success)
		{
			$io->error('Failed to remove filter (not found?).');

			return 3;
		}

		Factory::getFilters()->save();

		$io->success(sprintf("Removed %s filter '%s' from profile #%d.", $type, $filter, $profile));

		return 0;
	}
}
