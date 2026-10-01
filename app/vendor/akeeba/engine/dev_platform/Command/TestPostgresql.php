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

use Akeeba\Engine\Driver\Postgresql;
use Akeeba\Engine\Factory;
use Akeeba\Engine\Platform;
use Silly\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class TestPostgresql
{
	public static function register(Application $app)
	{
		$app
			->command('test:postgresql [--profile=]', new self())
			->defaults([
				'profile' => 1,
			])
			->descriptions('Test the Postgresql driver', [
				'--profile' => 'Profile ID to use for database credentials',
			]);
	}

	public function __invoke(int $profile, InputInterface $input, OutputInterface $output, SymfonyStyle $io)
	{
		$io->title('Testing Postgresql Driver');

		if (!defined('AKEEBA_PROFILE'))
		{
			define('AKEEBA_PROFILE', $profile);
		}

		$platform = Platform::getInstance();
		$platform->load_configuration($profile);

		$config = Factory::getConfiguration();

		$options = [
			'driver'   => $config->get('akeeba.platform.dbdriver', 'postgresql'),
			'host'     => $config->get('akeeba.platform.dbhost', 'localhost'),
			'user'     => $config->get('akeeba.platform.dbusername', 'postgres'),
			'password' => $config->get('akeeba.platform.dbpassword', 'postgres'),
			'database' => $config->get('akeeba.platform.dbname', 'pgboot6'),
			'port'     => $config->get('akeeba.platform.dbport', 5432),
			'schema'   => $config->get('akeeba.platform.dbschema', 'testing'),
		];

		$schema = $options['schema'];

		try
		{
			$io->section('Connecting to PostgreSQL...');
			$db = new Postgresql($options);
			$io->success('Connected to PostgreSQL');

			$version = $db->getVersion();
			$io->info('PostgreSQL Version: ' . $version);

			// Test query
			$io->section('Testing basic query...');
			$db->setQuery('SELECT current_database(), current_schema(), current_user');
			$result = $db->loadAssoc();
			$io->info('Database: ' . $result['current_database']);
			$io->info('Schema: ' . $result['current_schema']);
			$io->info('User: ' . $result['current_user']);

			// List schemas
			$db->setQuery('SELECT schema_name FROM information_schema.schemata');
			$schemas = $db->loadColumn();
			$io->info('Available schemas: ' . implode(', ', $schemas));

			// Test create table
			$io->section('Testing CREATE TABLE...');
			$db->setQuery('CREATE TABLE IF NOT EXISTS ' . $db->qn($schema . '.test_table') . ' (id SERIAL PRIMARY KEY, val TEXT)');
			$db->execute();
			$io->success('Created ' . $schema . '.test_table');

			// Test insert
			$io->section('Testing INSERT...');
			$db->setQuery('INSERT INTO ' . $db->qn($schema . '.test_table') . ' (val) VALUES (' . $db->quote('Hello World') . ')');
			$db->execute();
			$id = $db->insertid();
			$io->success('Inserted row with ID: ' . $id);

			// Test select
			$io->section('Testing SELECT...');
			$db->setQuery('SELECT * FROM ' . $db->qn($schema . '.test_table') . ' WHERE id = ' . (int)$id);
			$row = $db->loadAssoc();
			$io->info('Fetched row val: ' . $row['val']);

			// Test getTableList
			$io->section('Testing getTableList...');
			$tables = $db->getTableList();
			$io->info('Tables in schema ' . $db->q($schema) . ': ' . implode(', ', $tables));

			// Test drop table
			$io->section('Testing dropTable...');
			$db->dropTable('test_table');
			$io->success('Dropped test_table');

			$io->success('All fundamental tests passed!');

		}
		catch (\Exception $e)
		{
			$io->error('Test failed: ' . $e->getMessage());
			$io->note('If connection failed, try adjusting credentials in ' . __FILE__);
		}
	}
}
