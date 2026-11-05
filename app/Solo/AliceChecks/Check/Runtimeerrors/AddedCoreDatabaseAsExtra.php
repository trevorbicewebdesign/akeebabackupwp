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
use Akeeba\Engine\Platform;
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

		$error   = false;
		$jdb     = $this->normalise($this->getApplicationDatabase());
		$multidb = $this->getMultidbDefinitions($profile);

		foreach ($multidb as $addDb)
		{
			$options = $this->normalise([
				'driver'   => $addDb['driver'] ?? '',
				'host'     => $addDb['host'] ?? '',
				'port'     => $addDb['port'] ?? '',
				'username' => $addDb['username'] ?? '',
				'password' => $addDb['password'] ?? '',
				'database' => $addDb['database'] ?? '',
			], $jdb['driver']);

			// It's the same database used by the site, this could led to errors
			if ($jdb == $options)
			{
				$error = true;
			}
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

	/**
	 * Returns the connection to the database of the application, as the platform reports it: Solo's own database settings, or
	 * the WordPress database in Akeeba Backup for WordPress.
	 *
	 * @return  array
	 */
	protected function getApplicationDatabase(): array
	{
		$platform = Platform::getInstance();
		$options  = $platform->get_platform_database_options();

		return [
			'driver'   => $platform->get_default_database_driver(),
			'host'     => $options['host'] ?? '',
			'port'     => $options['port'] ?? '',
			'username' => $options['user'] ?? '',
			'password' => $options['password'] ?? '',
			'database' => $options['database'] ?? '',
		];
	}

	/**
	 * Brings a database connection to a form in which two spellings of the same connection are equal.
	 *
	 * @param   array   $db             The connection: driver, host, port, username, password, database
	 * @param   string  $defaultDriver  The driver to assume when none is given
	 *
	 * @return  array
	 */
	private function normalise(array $db, string $defaultDriver = 'mysqli'): array
	{
		$driver = strtolower(trim((string) $db['driver']));
		$driver = strtolower(ltrim(strrchr('\\' . $driver, '\\'), '\\'));
		$driver = ($driver === '') ? $defaultDriver : $driver;
		$host   = trim((string) $db['host']);
		$port   = trim((string) $db['port']);

		// The port may be part of the host name; a socket or a pipe is not a port
		if ($port === '' && preg_match('/^(.+):(\d+)$/', $host, $matches))
		{
			[, $host, $port] = $matches;
		}

		return [
			'driver'   => ($driver === 'mysql') ? 'mysqli' : $driver,
			'host'     => strtolower($host),
			'port'     => ($port === '') ? '3306' : (string) (int) $port,
			'username' => (string) $db['username'],
			'password' => (string) $db['password'],
			'database' => (string) $db['database'],
		];
	}

	/**
	 * Returns the additional database definitions of the given backup profile.
	 *
	 * @param   int  $profile  The backup profile ID
	 *
	 * @return  array
	 */
	protected function getMultidbDefinitions(int $profile): array
	{
		// Do I have to switch profile?
		$container   = $this->container;
		$cur_profile = $container->segment->get('profile', null);

		if ($cur_profile != $profile)
		{
			$container->segment->set('profile', $profile);
		}

		$multidb = Factory::getFilters()->getFilterData('multidb');

		// If needed set the old profile again
		if ($cur_profile != $profile)
		{
			$container->segment->set('profile', $cur_profile);
		}

		return $multidb;
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
