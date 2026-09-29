<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Application;


use Awf\Application\Configuration;

class AppConfig extends Configuration
{
	const WP_OPTION_NAME = 'akeebabackupwp_config';

	/** @inheritDoc */
	public function __construct(\Awf\Container\Container $container, $data = null)
	{
		parent::__construct($container, $data);

		$this->defaultPath = __DIR__ . '/../../private/config.php';
	}

	/**
	 * Saves the system configuration
	 *
	 * @param   string  $filePath  The path to the JSON file (optional)
	 *
	 * @return  void
	 *
	 * @throws  \RuntimeException  When saving fails
	 */
	public function saveConfiguration($filePath = null)
	{
		// Remove the database and site URL information from the configuration being saved.
		$clone = clone $this;
		$clone->set('dbdriver', null);
		$clone->set('dbhost', null);
		$clone->set('dbuser', null);
		$clone->set('dbpass', null);
		$clone->set('dbname', null);
		$clone->set('dbselect', null);
		$clone->set('connection', null);
		$clone->set('prefix', null);
		$clone->set('live_site', null);
		$clone->set('base_url', null);

		// Serialise the configuration in JSON format.
		$fileData = $clone->toString('JSON', ['pretty_print' => true]);

		// Try to save the configuration.
		try
		{
			$res = $this->saveConfigData($fileData);
		}
		catch (\Exception $e)
		{
			throw new \RuntimeException('Can not save Akeeba Backup\'s system configuration to the database', 500, $e);
		}

		if (!$res)
		{
			throw new \RuntimeException('Can not save Akeeba Backup\'s system configuration to the database (no further information was provided).', 500);
		}
	}

	/**
	 * Loads the system configuration
	 *
	 * @param   string  $filePath  The path to the JSON file (optional)
	 *
	 * @return  void
	 */
	public function loadConfiguration($filePath = null)
	{
		global $table_prefix, $wpdb;

		// Reset the class
		$this->data = new \stdClass();

		// Set up the database connection before doing anything else, since the config is saved there.
		$driver = 'Mysqli';

		if (!isset($wpdb) || !is_object($wpdb->dbh) || !($wpdb->dbh instanceof \mysqli))
		{
			$driver = function_exists('mysql_connect') ? 'Mysql' : 'Mysqli';
		}

		$this->set('dbdriver', $driver);

		if (isset($wpdb))
		{
			$this->set('connection', $wpdb->dbh);
		}

		$this->set('dbselect', false);

		if (!isset($wpdb) || empty($wpdb->dbh))
		{
			$this->set('dbhost', DB_HOST);
			$this->set('dbuser', DB_USER);
			$this->set('dbpass', DB_PASSWORD);
			$this->set('dbname', DB_NAME);
			$this->set('dbselect', true);
		}

		if (class_exists('wpdb'))
		{
			$table_prefix = $wpdb->prefix;
		}

		$this->set('prefix', $table_prefix);

		// Try to load the configuration from the DB or the legacy file (also auto-migrates from file to DB)
		$this->loadConfigData();

		// Set up additional data from defines.
		if (defined('AKEEBABACKUPWP_SITEURL'))
		{
			$this->set('live_site', AKEEBABACKUPWP_SITEURL);
		}

		if (defined('AKEEBABACKUPWP_URL'))
		{
			$this->set('base_url', AKEEBABACKUPWP_URL);
		}

		if (defined('AKEEBABACKUPWP_ROOTURL'))
		{
			$this->set('cms_url', AKEEBABACKUPWP_ROOTURL);
		}

		// First of all, let's try to fetch it from WordPress
		$timezone = function_exists('get_option') ? get_option('timezone_string') : '';

		// No joy? Maybe we're in CLI? Fallback to our configuration file
		if (!$timezone)
		{
			$timezone = $this->get('timezone', '');
		}

		// Still nothing? Well, let's fall back to UTC
		if (!$timezone)
		{
			$timezone = 'UTC';
		}

		$this->set('timezone', $timezone);
	}

	/**
	 * Saves the Akeeba Backup configuration information to the WordPress #__options table.
	 *
	 * If a legacy configuration file is detected and saving to the database succeeded the legacy file will be removed.
	 *
	 * IMPORTANT! $configData must be already encoded to a JSON string when this method is called.
	 *
	 * @param   string  $configData
	 *
	 * @return  bool  True if saving succeeded (or the saved information is the same as the $configData passed).
	 * @since   7.3.0
	 */
	private function saveConfigData($configData)
	{
		// If I am inside the CMS I can do this the easy way.
		if (defined('WPINC'))
		{
			/**
			 * This check is necessary because WordPress idiotically returns false in two very different cases:
			 * 1. When saving the option fails – in which case we DO NEED to get false.
			 * 2. When the option value being saved is the same as the existing value – in this case I want TRUE.
			 */
			$oldOption = get_option(self::WP_OPTION_NAME, null);

			if ($oldOption === $configData)
			{
				return true;
			}

			$ret = update_option(self::WP_OPTION_NAME, $configData, false);
		}
		// If I am outside the CMS I need to do it the really hard way. First, do I already have options?
		else
		{
			$db         = $this->container->db;
			$query      = $db->getQuery(true)
				->select('COUNT(*)')
				->from('#__options')
				->where($db->qn('option_name') . ' = ' . $db->q(self::WP_OPTION_NAME));
			$hasOptions = $db->setQuery($query)->loadResult() > 0;

			// Get the new options record
			$o = (object) [
				'option_name'  => self::WP_OPTION_NAME,
				'option_value' => $configData,
				// This atrocious crime against humanity is how WordPress encodes boolean values stored in MySQL.
				'autoload'     => 'no',
			];

			// Insert or update the options record
			if (!$hasOptions)
			{
				$ret = $db->insertObject('#__options', $o);
			}
			else
			{
				$ret = $db->updateObject('#__options', $o, ['option_name']);
			}
		}

		// If I saved to the DB and I have a legacy file try to delete it.
		if ($ret)
		{
			$filePath      = $this->getDefaultPath();
			$filePath      = realpath(dirname($filePath)) . '/' . basename($filePath);
			$hasLegacyFile = @file_exists($filePath) && is_file($filePath);

			if ($hasLegacyFile)
			{
				$this->container->fileSystem->delete($filePath);
			}
		}

		return $ret;
	}

	/**
	 * Loads the configuration information from the WordPress database.
	 *
	 * If a legacy configuration file is detected it will be loaded first, then the database information will be loaded
	 * and merged with it. In case we did find a legacy configuration file we will also try to save the configuration to
	 * the WordPress database. This automatically migrates the configuration information from the file to the database
	 * and removes the legacy configuration file.
	 *
	 * @return  void
	 * @since   7.3.0
	 */
	private function loadConfigData()
	{
		$loadedFromLegacyFile = false;

		// First, try to load data from the legacy file
		$filePath = $this->getDefaultPath();

		if (file_exists($filePath))
		{
			$fileData = @file_get_contents($filePath);

			if ($fileData !== false)
			{
				$loadedFromLegacyFile = true;
				$fileData             = explode("\n", $fileData, 2);
				$fileData             = $fileData[1];

				$this->loadString($fileData);
			}
		}

		// Try to load from the database
		if (defined('WPINC'))
		{
			$jsonData = get_option(self::WP_OPTION_NAME, null);
		}
		else
		{
			$db       = $this->container->db;
			$query    = $db->getQuery(true)
				->select($db->qn('option_value'))
				->from($db->qn('#__options'))
				->where($db->qn('option_name') . ' = ' . $db->q(self::WP_OPTION_NAME));
			$jsonData = $db->setQuery($query)->loadResult();
		}

		if (!empty($jsonData))
		{
			try
			{
				$this->loadString($jsonData);
			}
			catch (\Exception $e)
			{
			}
		}


		// If we loaded from the legacy file migrate the data to the database. This removes the legacy file.
		if ($loadedFromLegacyFile)
		{
			$this->saveConfiguration();
		}
	}
}
