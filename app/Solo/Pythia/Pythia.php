<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Pythia;


use Awf\Filesystem\File;
use Solo\Container;

class Pythia
{
	/**
	 * Get information about the script installed under $path. Guessing classes ("oracles") try to figure out what
	 * kind of CMS/script is installed and its database settings.
	 *
	 * @param   string  $path  The path to scan
	 *
	 * @return  array
	 */
	public function getCmsInfo(string $path): array
	{
		// Initialise
		$ret = array(
			'cms'		=> 'generic',
			'installer'	=> 'angie-generic',
			'database'	=> array(
				'driver'	=> $this->getBestMySQLDriver(),
				'host'		=> '',
				'port'		=> '',
				'username'	=> '',
				'password'	=> '',
				'name'		=> '',
				'prefix'	=> '',
			),
			'extradirs' => array(),
            'extradb'   => array()
		);

		// Get a list of all the CMS guessing classes
		$dummy = array();
		$fs = new File($dummy, new Container());
		$files = $fs->directoryFiles(__DIR__ . '/Oracle', '.php');

		if (empty($files))
		{
			return $ret;
		}

		foreach ($files as $file)
		{
			$className = '\\Solo\\Pythia\\Oracle\\' . ucfirst(basename($file, '.php'));

			if (!class_exists($className))
			{
				continue;
			}

			/** @var OracleInterface $o */
			$o = new $className($path);

			if ($o->isRecognised())
			{
				$ret['cms']         = $o->getName();
				$ret['installer']   = $o->getInstaller();
				$ret['database']    = $o->getDbInformation();
				$ret['extradirs']   = $o->getExtradirs();

				$ret['database']['driver'] = $this->cleanUpDBDriverName($ret['database']['driver']);

                // Please note that if you have any extra db, the Oracle class should automatically add it to the filters
                $ret['extradb']     = $o->getExtraDb();

				return $ret;
			}
		}

		return $ret;
	}


	/**
	 * Get the best matching driver.
	 *
	 * There are three MySQL drivers available: mysql, mysqli and PDO. Not all PHP versions support all drivers. It is
	 * possible that the Oracle class tells us to use a certain driver which is not available. For example, the
	 * PrestaShop oracle will always return 'mysqli'. In here we check that value against what's available and in case
	 * it's not we return the best alternative option.
	 *
	 * @param   string  $driver
	 *
	 * @return  string  Cleaned-up driver name or 'unsupported' if the requested db technology is not supported
	 *
	 * @since  1.9.4
	 */
	protected function cleanUpDBDriverName(?string $driver): string
	{
		// Get the best possible default driver
		$defaultDriver = $this->getBestMySQLDriver();

		// Get compatibility
		$hasPdo    = class_exists('\PDO');
		$hasMySQL  = function_exists('mysql_connect');
		$hasMySQLi = function_exists('mysqli_connect');

		$driver = strtolower($driver ?? '');

		// Dummy driver? Well, there's nothing to do
		if ($driver == 'none')
		{
			return 'none';
		}

		// Is this a subcase of mysqli or mysql drivers?
		if (substr($driver, 0, 8) == 'pdomysql')
		{
			return $hasPdo ? 'pdomysql' : $defaultDriver;
		}

		if (substr($driver, 0, 6) == 'mysqli')
		{
			return $hasMySQLi ? 'mysqli' : $defaultDriver;
		}

		if (substr($driver, 0, 5) == 'mysql')
		{
			return $hasMySQL ? 'mysql' : $defaultDriver;
		}

		// Sometimes we get driver names in the form of foomysql instead of mysqlfoo. Let's look for that too.
		if (substr($driver, -8) == 'pdomysql')
		{
			return $hasPdo ? 'pdomysql' : $defaultDriver;
		}
		elseif (substr($driver, -6) == 'mysqli')
		{
			return $hasMySQLi ? 'mysqli' : $defaultDriver;
		}
		elseif (substr($driver, -5) == 'mysql')
		{
			return $hasMySQL ? 'mysql' : $defaultDriver;
		}

		// I give up! You'd better be using a MySQL db server.
		return $defaultDriver;
	}

	/**
	 * Gets the best available MySQL driver for this server
	 *
	 * @return  string  Best MySQL driver or 'unsupported' if the MySQL db technology is not supported
	 *
	 * @since   1.9.4
	 */
	protected function getBestMySQLDriver(): string
	{
		$hasPdo    = class_exists('\PDO');
		$hasMySQL  = function_exists('mysql_connect');
		$hasMySQLi = function_exists('mysqli_connect');

		if ($hasPdo)
		{
			return 'pdomysql';
		}

		if ($hasMySQLi)
		{
			return 'mysqli';
		}

		if ($hasMySQL)
		{
			return 'mysql';
		}

		return 'unsupported';
	}
}
