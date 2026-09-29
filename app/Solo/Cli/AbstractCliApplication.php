<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Cli;

use Akeeba\Engine\Factory;
use Akeeba\Engine\Platform;
use Awf\Application\Cli;
use Awf\Text\Language;
use Awf\Text\Text;

abstract class AbstractCliApplication extends Cli
{
	public function __construct(?\Awf\Container\Container $container = null, ?Language $languageObject = null)
	{
		parent::__construct($container, $languageObject);

		if (empty($this->container->basePath))
		{
			$this->container->basePath = APATH_BASE . '/Solo';
		}
	}

	public function initialise()
	{
		// Halt if the configuration does not exist yet
		$configPath = $this->getContainer()->appConfig->getDefaultPath();
		$isConfigured = @file_exists($configPath) || (defined('DB_NAME') && defined('DB_USER') && defined('DB_PASSWORD') && defined('DB_HOST'));

		if (!$isConfigured)
		{
			$this->out('Configuration not found; aborting');
			$this->close(254);
		}

		// Load the application's configuration
		$this->container->appConfig->loadConfiguration($configPath);

		// Load the extra language files
		$this->container->language->loadLanguage(null, $this->container->languagePath . '/akeebabackup');

		// Load Akeeba Engine's configuration
		Platform::getInstance()->load_configuration();

		// WordPress key file detection
		$this->loadWordPressKeyFile();

		return $this;
	}

	/**
	 * Language file processing callback. It converts _QQ_ to " and replaces the product name in the legacy INI files
	 * imported from Akeeba Backup for Joomla!.
	 *
	 * @param   string $filename The full path to the file being loaded
	 * @param   array  $strings  The key/value array of the translations
	 *
	 * @return  boolean|array  False to prevent loading the file, or array of processed language string, or true to
	 *                         ignore this processing callback.
	 */
	public function processLanguageIniFile($filename, $strings)
	{
		foreach ($strings as $k => $v)
		{
			$v = str_replace('_QQ_', '"', $v);

			if (!defined('ABSPATH'))
			{
				$v = str_replace('Akeeba Backup', 'Akeeba Solo', $v);
			}
			else
			{
				$v = str_replace('Akeeba Solo', 'Akeeba Backup', $v);
			}

			$strings[$k] = $v;
		}

		return $strings;
	}

	/**
	 * Returns a fancy formatted time lapse code
	 *
	 * @param   integer         $referenceDateTime  Timestamp of the reference date/time
	 * @param   string|integer  $currentDateTime    Timestamp of the current date/time
	 * @param   string          $measureBy          One of s, m, h, d, or y (time unit)
	 * @param   boolean         $autoText           Append text automatically?
	 *
	 * @return  string
	 */
	protected function timeago($referenceDateTime = 0, $currentDateTime = '', $measureBy = '', $autoText = true)
	{
		if ($currentDateTime == '')
		{
			$currentDateTime = time();
		}

		// Raw time difference
		$Raw   = $currentDateTime - $referenceDateTime;
		$Clean = abs($Raw);

		$calcNum = array(
			array('s', 60),
			array('m', 60 * 60),
			array('h', 60 * 60 * 60),
			array('d', 60 * 60 * 60 * 24),
			array('y', 60 * 60 * 60 * 24 * 365)
		);

		$calc = array(
			's' => array(1, 'second'),
			'm' => array(60, 'minute'),
			'h' => array(60 * 60, 'hour'),
			'd' => array(60 * 60 * 24, 'day'),
			'y' => array(60 * 60 * 24 * 365, 'year')
		);

		if ($measureBy == '')
		{
			$usemeasure = 's';

			for ($i = 0; $i < count($calcNum); $i++)
			{
				if ($Clean <= $calcNum[$i][1])
				{
					$usemeasure = $calcNum[$i][0];
					$i          = count($calcNum);
				}
			}
		}
		else
		{
			$usemeasure = $measureBy;
		}

		$datedifference = floor($Clean / $calc[$usemeasure][0]);

		if ($autoText == true && ($currentDateTime == time()))
		{
			if ($Raw < 0)
			{
				$prospect = ' from now';
			}
			else
			{
				$prospect = ' ago';
			}
		}
		else
		{
			$prospect = '';
		}

		if ($referenceDateTime != 0)
		{
			if ($datedifference == 1)
			{
				return $datedifference . ' ' . $calc[$usemeasure][1] . ' ' . $prospect;
			}
			else
			{
				return $datedifference . ' ' . $calc[$usemeasure][1] . 's ' . $prospect;
			}
		}
		else
		{
			return 'No input time referenced.';
		}
	}

	/**
	 * Returns the current memory usage
	 *
	 * @return string
	 */
	protected function memUsage()
	{
		if (function_exists('memory_get_usage'))
		{
			$size = memory_get_usage();
			$unit = array('b', 'KB', 'MB', 'GB', 'TB', 'PB');

			return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . ' ' . $unit[$i];
		}
		else
		{
			return "(unknown)";
		}
	}

	/**
	 * Returns the peak memory usage
	 *
	 * @return string
	 */
	protected function peakMemUsage()
	{
		if (function_exists('memory_get_peak_usage'))
		{
			$size = memory_get_peak_usage();
			$unit = array('b', 'KB', 'MB', 'GB', 'TB', 'PB');

			return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . ' ' . $unit[$i];
		}
		else
		{
			return "(unknown)";
		}
	}

	/**
	 * Parses POSIX command line options and returns them as an associative array. Each array item contains
	 * a single dimensional array of values. Arguments without a dash are silently ignored.
	 *
	 * @return array
	 */
	protected function parseOptions()
	{
		global $argc, $argv;

		// Workaround for PHP-CGI
		if (!isset($argc) && !isset($argv))
		{
			$query = "";

			if (!empty($_GET))
			{
				foreach ($_GET as $k => $v)
				{
					$query .= " $k";

					if ($v != "")
					{
						$query .= "=$v";
					}
				}
			}
			$query = ltrim($query);
			$argv  = explode(' ', $query);
			$argc  = count($argv);
		}

		$currentName = "";
		$options     = array();

		for ($i = 1; $i < $argc; $i++)
		{
			$argument = $argv[$i];

			if (strpos($argument, "-") === 0)
			{
				$argument = ltrim($argument, '-');

				if (strstr($argument, '='))
				{
					[$name, $value] = explode('=', $argument, 2);
				}
				else
				{
					$name  = $argument;
					$value = null;
				}

				$currentName = $name;

				if (!isset($options[$currentName]) || ($options[$currentName] == null))
				{
					$options[$currentName] = array();
				}
			}
			else
			{
				$value = $argument;
			}
			if ((!is_null($value)) && (!is_null($currentName)))
			{
				if (strstr($value, '='))
				{
					$parts = explode('=', $value, 2);
					$key   = $parts[0];
					$value = $parts[1];
				}
				else
				{
					$key = null;
				}

				$values = $options[$currentName];

				if (is_null($values))
				{
					$values = array();
				}

				if (is_null($key))
				{
					array_push($values, $value);
				}
				else
				{
					$values[$key] = $value;
				}

				$options[$currentName] = $values;
			}
		}

		return $options;
	}

	/**
	 * Returns the value of a command line option
	 *
	 * @param   string   $key              The full name of the option, e.g. "foobar"
	 * @param   mixed    $default          The default value to return
	 * @param   boolean  $first_item_only  Return only the first value specified (default = true)
	 *
	 * @return  mixed
	 */
	protected function getOption($key, $default = null, $first_item_only = true)
	{
		static $options = null;

		if (is_null($options))
		{
			$options = $this->parseOptions();
		}

		if (!array_key_exists($key, $options))
		{
			return $default;
		}
		else
		{
			if ($first_item_only)
			{
				return $options[$key][0];
			}
			else
			{
				return $options[$key];
			}
		}
	}

	/**
	 * Load WordPress' settings encryption key file
	 *
	 * @return  void
	 * @since   8.1.0
	 */
	private function loadWordPressKeyFile()
	{
		$wpKeyPath = realpath(__DIR__ . '/../../../../../akeebabackup_secretkey.php');

		if ($wpKeyPath === false)
		{
			return;
		}

		try
		{
			require_once $wpKeyPath;
		}
		catch (\Throwable $e)
		{
			return;
		}

		if (!defined('AKEEBA_SERVERKEY'))
		{
			return;
		}

		$key = base64_decode(AKEEBA_SERVERKEY);

		if ($key === false)
		{
			return;
		}

		Factory::getSecureSettings()->setKey($key);
	}
}