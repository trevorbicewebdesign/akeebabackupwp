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

class NukeProfiles
{
	public static function register(Application $app)
	{
		$app
			->command('nuke:profiles [--force]', new self())
			->descriptions(
				'Delete all backup profiles and recreate the default profile (#1)',
				[
					'--force' => 'Skip the confirmation prompt',
				]
			);
	}

	public function __invoke(bool $force, InputInterface $input, OutputInterface $output, SymfonyStyle $io)
	{
		if (!$force && !$io->confirm('This deletes ALL backup profiles and recreates the default profile. Continue?', false))
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

		// SQLite does not support TRUNCATE TABLE; empty the table with an unconditional DELETE instead.
		$db->setQuery(
			$db->getQuery(true)
				->delete($db->quoteName('#__ak_profiles'))
		)->execute();

		// Recreate the default backup profile (ID #1) from scratch with empty settings and filters.
		$profile = (object) [
			'id'            => 1,
			'description'   => 'Default Backup Profile',
			'configuration' => '',
			'filters'       => '',
		];

		$db->insertObject('#__ak_profiles', $profile, 'id');

		// Switch to profile #1.
		if (!defined('AKEEBA_PROFILE'))
		{
			define('AKEEBA_PROFILE', 1);
		}

		/**
		 * Loading the profile's empty configuration causes the engine to revert to the default options and save them
		 * back to the database automatically.
		 */
		Platform::getInstance()->load_configuration(1);

		// Reset the filters to a blank state and save them to the database.
		$filters = Factory::getFilters();
		$filters->reset();
		$filters->save();

		$io->success('All backup profiles were deleted and the default profile (#1) was recreated.');
	}
}
