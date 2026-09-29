<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Alice\Check\Runtimeerrors;

use Awf\Container\Container;
use Awf\Database\Driver;
use Solo\Alice\Check\Base;
use Solo\Alice\Exception\StopScanningEarly;
use Akeeba\Engine\Factory;
use Exception;
use Awf\Text\Text;

/**
 * Check if the user add one or more additional database, but the connection details are wrong
 * In such cases Akeeba Backup will receive an error, halting the whole backup process
 */
class ExtraDatabaseCannotConnect extends Base
{
	public function __construct(Container $container, $logFile = null)
	{
		$this->priority         = 90;
		$this->checkLanguageKey = 'COM_AKEEBA_ALICE_ANALYZE_RUNTIME_ERRORS_DBADD_WRONG';

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
		$filters = Factory::getFilters();
		$multidb = $filters->getFilterData('multidb');

		foreach ($multidb as $addDb)
		{
			$options = [
				'driver'   => $addDb['driver'],
				'host'     => $addDb['host'],
				'port'     => $addDb['port'],
				'user'     => $addDb['username'],
				'password' => $addDb['password'],
				'database' => $addDb['database'],
				'prefix'   => $addDb['prefix'],
			];

			try
			{
				$db = Driver::fromOptions($options);
				$db->connect();
				$db->disconnect();
			}
			catch (Exception $e)
			{
				$error = true;
			}
		}

		// If necessary, set the old profile again
		if ($cur_profile != $profile)
		{
			$container->segment->set('profile', $cur_profile);
		}

		if ($error)
		{
			$this->setResult(-1);
			$this->setErrorLanguageKey([
				'COM_AKEEBA_ALICE_ANALYZE_RUNTIME_ERRORS_DBADD_WRONG_ERROR',
			]);
		}
	}

	public function getSolution()
	{
		// Test skipped? No need to provide a solution
		if ($this->getResult() === 0)
		{
			return '';
		}

		return Text::_('COM_AKEEBA_ALICE_ANALYZE_RUNTIME_ERRORS_DBADD_WRONG_SOLUTION');
	}
}
