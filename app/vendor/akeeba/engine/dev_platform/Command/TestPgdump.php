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

use Akeeba\Engine\Dump\Native;
use Akeeba\Engine\Factory;
use Akeeba\Engine\Platform;
use Akeeba\Engine\Base\Part;
use Silly\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class TestPgdump
{
	public static function register(Application $app)
	{
		$app
			->command('test:pgdump [--profile=]', new self())
			->defaults(['profile' => 6])
			->descriptions('Test the PostgreSQL Native dump engine', [
				'--profile' => 'Profile ID to use for database credentials',
			]);
	}

	public function __invoke(int $profile, InputInterface $input, OutputInterface $output, SymfonyStyle $io)
	{
		$io->title('Testing PostgreSQL Native Dump Engine');

		if (!defined('AKEEBA_PROFILE'))
		{
			define('AKEEBA_PROFILE', $profile);
		}

		$platform = Platform::getInstance();
		$platform->load_configuration($profile);

		try {
			$engine = new Native();
			$io->section('Preparing engine...');
			
			$config = Factory::getConfiguration();
			
			// Build params array for Native engine
			$params = [
				'driver'   => $config->get('akeeba.platform.dbdriver'),
				'host'     => $config->get('akeeba.platform.dbhost'),
				'user'     => $config->get('akeeba.platform.dbusername'),
				'password' => $config->get('akeeba.platform.dbpassword'),
				'database' => $config->get('akeeba.platform.dbname'),
				'port'     => $config->get('akeeba.platform.dbport'),
				'schema'   => $config->get('akeeba.platform.schema', ($config->get('akeeba.platform.dbschema', 'public'))),
				'prefix'   => $config->get('akeeba.platform.dbprefix', ''),
			];
			
			$engine->setup($params);
			
			$reflection = new \ReflectionClass($engine);
			$method = $reflection->getMethod('_prepare');
			$method->setAccessible(true);
			$method->invoke($engine);
			
			$io->success('Engine prepared successfully');
			
			$engineProp = $reflection->getProperty('_engine');
			$engineProp->setAccessible(true);
			$internalEngine = $engineProp->getValue($engine);
			
			$io->info('Internal engine class: ' . get_class($internalEngine));
			
			if (!($internalEngine instanceof \Akeeba\Engine\Dump\Native\Postgresql)) {
				throw new \Exception('Internal engine is not an instance of Postgres');
			}
			
			$io->section('Listing tables...');
			$internalEngine->getTablesToBackup();
			
			$reflInternal = new \ReflectionClass($internalEngine);
			$entitiesProp = $reflInternal->getProperty('entities');
			$entitiesProp->setAccessible(true);
			$entities = $entitiesProp->getValue($internalEngine);
			
			foreach ($entities as $entity) {
				$io->writeln("- " . $entity->type . ": " . $entity->name);
			}
			
			$io->success('Found ' . count($entities) . ' entities');

			if (count($entities) > 0) {
				$io->section('Testing DDL retrieval for first entity...');
				$firstEntity = $entities->first();
				
				$reflInternal = new \ReflectionClass($internalEngine);
				$methodCreate = $reflInternal->getMethod('getCreateStatement');
				$methodCreate->setAccessible(true);
				
				$ddl = $methodCreate->invoke($internalEngine, $firstEntity->abstractName, $firstEntity->name, $firstEntity->type);
				
				$io->text('DDL for ' . $firstEntity->name . ':');
				$io->writeln($ddl);
			}

			$this->testNonEmptyTableDump($internalEngine, $io);

			$io->success('PostgreSQL Native Dump Engine tests passed!');

		} catch (\Throwable $e) {
			$io->error('Test failed: ' . $e->getMessage());
			$io->writeln($e->getTraceAsString());
		}
	}

	private function testNonEmptyTableDump(\Akeeba\Engine\Dump\Native\Postgresql $internalEngine, SymfonyStyle $io): void
	{
		$io->section('Testing data dump for a non-empty table...');

		$reflInternal = new \ReflectionClass($internalEngine);
		$entitiesProp = $reflInternal->getProperty('entities');
		$entitiesProp->setAccessible(true);
		$entities = $entitiesProp->getValue($internalEngine);

		$rowCountMethod = $reflInternal->getMethod('getRowCount');
		$rowCountMethod->setAccessible(true);

		$maxRangeProp = $reflInternal->getProperty('maxRange');
		$maxRangeProp->setAccessible(true);

		$targetEntity = null;

		foreach ($entities as $entity) {
			if ($entity->type !== 'table' || !$entity->dumpContents) {
				continue;
			}

			$rowCountMethod->invoke($internalEngine, $entity->abstractName);
			$rowCount = $maxRangeProp->getValue($internalEngine);

			if (is_numeric($rowCount) && (int) $rowCount > 0) {
				$targetEntity = $entity;
				break;
			}
		}

		if ($targetEntity === null) {
			throw new \RuntimeException('No table with a non-zero row count was found.');
		}

		$io->text('Dumping data for table ' . $targetEntity->name . '...');

		$entitiesProp->setValue($internalEngine, new \Akeeba\Engine\Util\Collection([$targetEntity]));
		$goToNextTable = $reflInternal->getMethod('goToNextTable');
		$goToNextTable->setAccessible(true);
		$goToNextTable->invoke($internalEngine);

		while (!in_array($internalEngine->getState(), [Part::STATE_POSTRUN, Part::STATE_ERROR], true)) {
			$internalEngine->tick();
		}

		if ($internalEngine->getState() === Part::STATE_ERROR) {
			throw new \RuntimeException('Data dump engine ended in an error state.');
		}

		$tempFileProp = $reflInternal->getProperty('tempFile');
		$tempFileProp->setAccessible(true);
		$dumpFile = $tempFileProp->getValue($internalEngine);
		$dumpContents = is_string($dumpFile) && is_readable($dumpFile) ? file_get_contents($dumpFile) : '';

		if ($dumpContents === '') {
			throw new \RuntimeException('The SQL dump output was empty.');
		}

		$useAbstractProp = $reflInternal->getProperty('useAbstractNames');
		$useAbstractProp->setAccessible(true);
		$useAbstractNames = (bool) $useAbstractProp->getValue($internalEngine);
		$tableName = $useAbstractNames ? $targetEntity->abstractName : $targetEntity->name;
		$pattern = '/INSERT INTO\\s+[^;]*' . preg_quote($tableName, '/') . '/i';

		if (!preg_match($pattern, $dumpContents)) {
			throw new \RuntimeException('Expected INSERT INTO statements for table ' . $tableName . ' were not found.');
		}

		$io->success('Found INSERT INTO statements for table ' . $tableName . '.');
	}
}
