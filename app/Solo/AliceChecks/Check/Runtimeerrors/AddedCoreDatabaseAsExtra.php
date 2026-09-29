<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Alice\Check\Runtimeerrors;

use Awf\Container\Container;
use Solo\Alice\Check\Base;
use Solo\Alice\Exception\StopScanningEarly;
use Akeeba\Engine\Factory;
use Awf\Text\Text;

/**
 * Check if the user added the site database as additional database. Some servers won't allow more than one connection
 * to the same database, causing the backup process to fail
 */
class AddedCoreDatabaseAsExtra extends Base
{
	public function __construct(Container $container, $logFile = null)
	{
		$this->priority         = 100;
		$this->checkLanguageKey = 'COM_AKEEBA_ALICE_ANALYZE_RUNTIME_ERRORS_DBADD_JSAME';

		parent::__construct($container, $logFile);
	}

	public function check()
	{
		$profile = 0;

		$this->scanLines(function ($line) use (&$profile) {
			$pos = strpos($line, '|Loaded profile');

			if ($pos === false)
			{
				return;
			}

			preg_match('/profile\s+#(\d+)/', $line, $matches);

			if (isset($matches[1]))
			{
				$profile = (int) $matches[1];
			}

			throw new StopScanningEarly();

		});

		// Mhm... no profile ID? Something weird happened better stop here and mark the test as skipped
		if ($profile <= 0)
		{
			return;
		}

		// Do I have to switch profile?
		$container   = $this->container;
		$cur_profile = $container->segment->get('profile', null);

		if ($cur_profile != $profile)
		{
			$container->segment->set('profile', $profile);
		}

		$error   = false;
		$config  = $container->appConfig;
		$filters = Factory::getFilters();
		$multidb = $filters->getFilterData('multidb');

		$jdb = [
			'driver'   => $config->get('dbtype'),
			'host'     => $config->get('host'),
			'username' => $config->get('user'),
			'password' => $config->get('password'),
			'database' => $config->get('db'),
		];

		foreach ($multidb as $addDb)
		{
			$options = [
				'driver'   => $addDb['driver'],
				'host'     => $addDb['host'],
				'username' => $addDb['username'],
				'password' => $addDb['password'],
				'database' => $addDb['database'],
			];

			// It's the same database used by Joomla, this could led to errors
			if ($jdb == $options)
			{
				$error = true;
			}
		}

		// If needed set the old profile again
		if ($cur_profile != $profile)
		{
			$container->segment->set('profile', $cur_profile);
		}

		if (!$error)
		{
			return;
		}

		$this->setResult(-1);
		$this->setErrorLanguageKey([
			'COM_AKEEBA_ALICE_ANALYZE_RUNTIME_ERRORS_DBADD_JSAME_ERROR',
		]);
	}

	public function getSolution()
	{
		// Test skipped? No need to provide a solution
		if ($this->getResult() === 0)
		{
			return '';
		}

		return Text::_('COM_AKEEBA_ALICE_ANALYZE_RUNTIME_ERRORS_DBADD_JSAME_SOLUTION');
	}
}
