<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engine\Platform;

use Akeeba\Engine\Factory;
use Akeeba\Engine\Platform;
use Akeeba\Engine\Psr\Log\LogLevel;
use Awf\Container\ContainerAwareInterface;
use Awf\Container\ContainerAwareTrait;

// Protection against direct access
defined('AKEEBAENGINE') or die();

if (!defined('DS'))
{
	define('DS', DIRECTORY_SEPARATOR); // Sometimes required (legacy code)
}

/**
 * Akeeba Solo for WordPress platform class
 */
class Wordpress extends Base implements ContainerAwareInterface
{
	use ContainerAwareTrait;

	const SHOW_120_DAYS_WARNING = false;

	/** @var   integer  Platform class priority */
	public $priority = 60;

	/** @var   string  The platform name */
	public $platformName = 'wordpress';

	/**
	 * Override profile ID, for use in automated testing only
	 *
	 * @var   int|null
	 */
	public static $profile_id = null;

	/**
	 * Performs heuristics to determine if this platform object is the ideal
	 * candidate for the environment Akeeba Engine is running in.
	 *
	 * @return  boolean  True if this platform applies to this environment
	 */
	public function isThisPlatform()
	{
		if (!defined('WPINC') && !defined('ABSPATH'))
		{
			return false;
		}

		return true;
	}

	/**
	 * Returns an associative array of stock platform directories
	 *
	 * @return  array  Platform directories
	 */
	public function get_stock_directories()
	{
		static $stock_directories = [];

		if (empty($stock_directories))
		{
			$host = $this->get_host();

			$contentFolder =
				rtrim(
					(defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : (rtrim(ABSPATH, '/') . '/wp-content')),
					'/'
				);
			$defaultOutput = $contentFolder . '/backups';

			if (!@is_dir($defaultOutput))
			{
				$this->conditionallyCreateDefaultOutputDirectory($defaultOutput);
			}

			$contentFolder =
				rtrim(
					(defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : (rtrim(ABSPATH, '/') . '/wp-content')),
					'/'
				);
			$pluginsDir = rtrim(
				defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : ($contentFolder . '/plugins'),
				'/'
			);
			$pluginSlug = (class_exists('AkeebaBackupWP') && !empty(\AkeebaBackupWP::$pluginBaseName))
				? \AkeebaBackupWP::$pluginBaseName
				: 'akeebabackupwp/akeebabackupwp.php';
			$abwpDir = dirname($pluginsDir . '/' . $pluginSlug);

			$stock_directories['[SITEROOT]']              = $this->get_site_root();
			$stock_directories['[ROOTPARENT]']            = @realpath($this->get_site_root() . '/..');
			$stock_directories['[SITETMP]']               = APATH_BASE . '/tmp';
			$stock_directories['[DEFAULT_OUTPUT]']        = $defaultOutput;
			$stock_directories['[LEGACY_DEFAULT_OUTPUT]'] = APATH_BASE . '/backups';
			$stock_directories['[LEGACY_DEFAULT_OUTPUT_TESTING]'] = $abwpDir . '/app/backups';
			$stock_directories['[HOST]']                  = empty($host) ? 'unknown_host' : $host;
		}

		return $stock_directories;
	}

	/**
	 * Returns the absolute path to the site's root
	 *
	 * @return  string  The absolute path to our own directory
	 */
	public function get_site_root()
	{
		static $root = null;

		if (empty($root) || is_null($root))
		{
			$root = ABSPATH;

			if (empty($root) || ($root == DIRECTORY_SEPARATOR) || ($root == '/'))
			{
				// Try to get the current root in a different way
				if (function_exists('getcwd'))
				{
					$root = getcwd();
				}

				if (empty($root))
				{
					$root = '../';
				}
				else
				{
					$adminPos = strpos($root, 'wp-admin');
					if ($adminPos !== false)
					{
						$root = substr($root, 0, $adminPos);
					}
					else
					{
						$root = '../';
					}
					// Degenerate case where $root = 'wp-admin'
					// without a leading slash before entering this
					// if-block
					if (empty($root))
					{
						$root = '../';
					}
				}
			}

			if (!in_array(substr($root, -1), ['/', '\\']))
			{
				$root .= DIRECTORY_SEPARATOR;
			}
		}

		return $root;
	}

	/**
	 * Returns the absolute path to the installer images directory
	 *
	 * @return  string  The absolute path to the installer images directory
	 */
	public function get_installer_images_path()
	{
		return $this->getContainer()->basePath . '/assets/installers';
	}

	/**
	 * Returns the active profile number
	 *
	 * @return  integer  The active profile number
	 */
	public function get_active_profile()
	{
		// Automated testing override
		if (!is_null(self::$profile_id) && (self::$profile_id > 0))
		{
			return self::$profile_id;
		}

		if (defined('AKEEBA_PROFILE'))
		{
			return AKEEBA_PROFILE;
		}

		$session = $this->getContainer()->segment;

		if (!isset($session->profile))
		{
			return 1;

		}

		$profile = (int)$session->profile;

		if (empty($profile) || ($profile < 1))
		{
			$profile = 1;
		}

		return $profile;
	}

	/**
	 * Returns the selected profile's name. If no ID is specified, the current
	 * profile's name is returned.
	 *
	 * @param   integer $id The profile number for which to get the name
	 *
	 * @return  string  The profile's name
	 */
	public function get_profile_name($id = null)
	{
		if (empty($id))
		{
			$id = $this->get_active_profile();
		}

		$id = (int)$id;

		if (empty($id))
		{
			$id = 1;
		}

		$db = $this->getContainer()->db;
		$query = $db->getQuery(true)
			->select($db->qn('description'))
			->from($db->qn('#__ak_profiles'))
			->where($db->qn('id') . ' = ' . (int) $id);
		$db->setQuery($query);

		return $db->loadResult();
	}

	/**
	 * Returns the backup origin as set in the AKEEBA_BACKUP_ORIGIN constant, otherwise "backend"
	 *
	 * @return   string  The backup origin
	 */
	public function get_backup_origin()
	{
		if (defined('AKEEBA_BACKUP_ORIGIN'))
		{
			return AKEEBA_BACKUP_ORIGIN;
		}

		return 'backend';
	}

	/**
	 * Returns a MySQL-formatted timestamp out of the current date
	 *
	 * @param   string $date [optional] The timestamp to use. Omit to use current timestamp.
	 *
	 * @return  string
	 */
	public function get_timestamp_database($date = 'now')
	{
		$date = $this->getContainer()->dateFactory($date);

		return $date->toSql();
	}

	/**
	 * Returns the current timestamp, taking into account any TZ information,
	 * in the format specified by $format.
	 *
	 * @param   string $format Timestamp format string (standard PHP format string)
	 *
	 * @return  string
	 */
	public function get_local_timestamp($format = 'Y-m-d H:i:s')
	{
		// Do I have a forced timezone?
		$tz = $this->get_platform_configuration_option('forced_backup_timezone', 'AKEEBA/DEFAULT');

		// No forced timezone set? Use the default Joomla! behavior.
		if (empty($tz) || ($tz == 'AKEEBA/DEFAULT'))
		{
			$tz = $this->getContainer()->appConfig->get('timezone', 'UTC');
		}

		// Get the current date/time and apply the preferred timezone
		$utcTimeZone = new \DateTimeZone('UTC');
		$dateNow     = $this->getContainer()->dateFactory('now', $utcTimeZone);
		$timezone    = new \DateTimeZone($tz);
		$dateNow->setTimezone($timezone);

		return $dateNow->format($format, true);
	}

	/**
	 * Returns the current host name
	 *
	 * @return  string
	 */
	public function get_host()
	{
		static $deadLockTest = false;

		if (!$deadLockTest)
		{
			$deadLockTest = true;
			$overrideURL = Factory::getConfiguration()->get('akeeba.platform.site_url', '');
			$overrideURL = trim($overrideURL);
		}
		else
		{
			$overrideURL = null;
		}

		if (defined('WPINC') && (function_exists('home_url') || function_exists('network_home_url')))
		{
			if (function_exists('is_multisite') && function_exists('network_home_url') && is_multisite())
			{
				$overrideURL = network_home_url('/', 'https');
			}
			elseif (function_exists('home_url'))
			{
				$overrideURL = home_url('/', 'https');
			}
		}

		if (!empty($overrideURL))
		{
			// An override URL is already specified; use it
			$oURI = new \Awf\Uri\Uri($overrideURL);
		}
		elseif (!array_key_exists('REQUEST_METHOD', $_SERVER))
		{
			// Running under CLI or a broken server
			$url = Platform::getInstance()->get_platform_configuration_option('siteurl', '');
			$oURI = new \Awf\Uri\Uri($url);
		}
		else
		{
			// Running under the web server
			$oURI = \Awf\Uri\Uri::getInstance();
		}

		return $oURI->getHost();
	}

	/**
	 * Returns the site's name. This doesn't apply for Akeeba Solo so we have to fake it.
	 *
	 * @return  string
	 */
	public function get_site_name()
	{
		if (function_exists('get_bloginfo'))
		{
			return get_bloginfo('name', 'raw');
		}
		else
		{
			return "Akeeba Backup";
		}
	}

	/**
	 * Gets the best matching database driver class. $use_platform is ignored in Akeeba Solo.
	 *
	 * @param   boolean $use_platform If set to false, it will forcibly try to assign one of the primitive types
	 *                                (Mysql/Mysqli) and NEVER tell you to use a platform driver class
	 *
	 * @return  string
	 */
	public function get_default_database_driver($use_platform = true)
	{
		if ($use_platform && defined('WPINC'))
		{
			return '\\Akeeba\\Engine\\Driver\\Wordpress';
		}

		$driver = function_exists('mysqli_connect') ? 'Mysqli' : 'Mysql';

		return '\\Akeeba\\Engine\\Driver\\' . $driver;
	}

	/**
	 * Returns a set of options to connect to the default database
	 *
	 * @return  array  Database connection options
	 */
	public function get_platform_database_options()
	{
		static $options;

		if (empty($options))
		{
			if (!defined('WPINC'))
			{
				global $table_prefix;
			}
			else
			{
				global $wpdb;
				$table_prefix = $wpdb->prefix;
			}

			$options = array(
				'host'		=> DB_HOST,
				'user'		=> DB_USER,
				'password'	=> DB_PASSWORD,
				'database'	=> DB_NAME,
				'prefix'	=> $table_prefix
			);
		}

		return $options;
	}

	/**
	 * Provides a platform-specific translation function
	 *
	 * @param   string $key The translation key
	 *
	 * @return  string  The translated string
	 */
	public function translate($key)
	{
		return \Awf\Text\Text::_($key);
	}

	/**
	 * Populates global constants holding the Akeeba application version
	 */
	public function load_version_defines()
	{
		$fileName = APATH_BASE . '/version.php';

		if (file_exists($fileName))
		{
			require_once $fileName;
		}

		if (!defined('AKEEBABACKUP_VERSION'))
		{
			define("AKEEBABACKUP_VERSION", "dev");
		}

		if (!defined('AKEEBABACKUP_PRO'))
		{
			define('AKEEBABACKUP_PRO', true);
		}

		if (!defined('AKEEBABACKUP_DATE'))
		{
			$date = $this->getContainer()->dateFactory();
			define("AKEEBABACKUP_DATE", $date->format('Y-m-d'));
		}
	}

	/**
	 * Returns the platform name and version
	 *
	 * @return  array  An array containing the platform name and version, in this order
	 */
	public function getPlatformVersion()
	{
		global $wp_version;

		// Play safe in case the WordPress version is not correctly detected
		$version = $wp_version ?? '0.0.0';

		return array(
			'name'    => 'WordPress',
			'version' => $version
		);
	}

	/**
	 * Logs platform-specific directories with INFO log level
	 *
	 * @return  array|null
	 */
	public function log_platform_special_directories()
	{
		$site_root = $this->get_site_root();

		Factory::getLog()->log(LogLevel::INFO, "APATH_BASE         :" . APATH_BASE, ['translate_root' => false]);
		Factory::getLog()->log(LogLevel::INFO, "Application Path   :" . $this->getContainer()->basePath, ['translate_root' => false]);
		Factory::getLog()->log(LogLevel::INFO, "Site root          :" . $this->get_site_root(), ['translate_root' => false]);

		// If the release is older than 3 months, issue a warning
		if (self::SHOW_120_DAYS_WARNING && defined('AKEEBABACKUP_DATE'))
		{
			$releaseDate = $this->getContainer()->dateFactory(AKEEBABACKUP_DATE);

			if (time() - $releaseDate->toUnix() > 10368000)
			{
				if (!isset($ret['warnings']))
				{
					$ret['warnings'] = array();
					$ret['warnings'] = array_merge($ret['warnings'], array(
						'Your version of Akeeba Backup is more than 120 days old and most likely already out of date. Please check if a newer version is published and install it.'
					));
				}
			}

		}

		// Detect UNC paths and warn the user
		if(DIRECTORY_SEPARATOR == '\\') {
			if( (substr(ABSPATH, 0, 2) == '\\\\') || (substr(ABSPATH, 0, 2) == '//') ) {
				if (!isset($ret['warnings']))
				{
					$ret['warnings'] = array();
				}

				$ret['warnings'] = array_merge($ret['warnings'], array(
					'Your site\'s root is using a UNC path (e.g. \\\\SERVER\\path\\to\\root). PHP has known bugs which may',
					'prevent it from working properly on a site like this. Please take a look at',
					'https://bugs.php.net/bug.php?id=40163 and https://bugs.php.net/bug.php?id=52376. As a result your',
					'backup may fail.'
				));
			}
		}

		if (empty($ret))
		{
			$ret = null;
		}

		return $ret;
	}

	/**
	 * Loads a platform-specific software configuration option
	 *
	 * @param   string $key     The configuration option's key
	 * @param   mixed  $default The default value to use
	 *
	 * @return  mixed
	 */
	public function get_platform_configuration_option($key, $default)
	{
		$config   = $this->getContainer()->appConfig;
		$altValue = $config->get($key, $default);
		$value    = $config->get('options.' . $key, $altValue);

		// Some configuration options may have to be decrypted
		switch ($key)
		{
			case 'frontend_secret_word':
				$secureSettings = Factory::getSecureSettings();
				$value          = $secureSettings->decryptSettings($value);
				break;
		}

		return $value;
	}

	/**
	 * Returns a list of emails to the Super Administrators
	 *
	 * @return  array
	 */
	public function get_administrator_emails()
	{
		$ret = array();

		$emails = $this->get_platform_configuration_option('frontend_email_address', '');

		if (!empty($emails))
		{
			$emails = explode(',', $emails);
			foreach ($emails as $email)
			{
				$ret[] = trim($email);
			}
		}

		return $ret;
	}

	/**
	 * Sends a very simple email using the platform's mailer facility
	 *
	 * @param   string $to         The recipient's email address
	 * @param   string $subject    The subject of the email
	 * @param   string $body       The body of the email
	 * @param   string $attachFile The file to attach (null to not attach any files)
	 *
	 * @return  boolean
	 */
	public function send_email($to, $subject, $body, $attachFile = null)
	{
		Factory::getLog()->log(LogLevel::DEBUG, "-- Fetching mailer object");

		$mailer = $this->getContainer()->mailer();

		if (!is_object($mailer))
		{
			Factory::getLog()->log(LogLevel::WARNING, "Could not send email to $to - Reason: Mailer object is not an object; please check your system settings");

			return false;
		}

		Factory::getLog()->log(LogLevel::DEBUG, "-- Creating email message");

		$recipient = array($to);
		$mailer->addRecipient($recipient);
		$mailer->setSubject($subject);
		$mailer->setBody($body);

		if (!empty($attachFile))
		{
			Factory::getLog()->log(LogLevel::INFO, "-- Attaching $attachFile");

			if (!file_exists($attachFile) || !(is_file($attachFile) || is_link($attachFile)))
			{
				Factory::getLog()->log(LogLevel::WARNING, "The file does not exist, or it's not a file; no email sent");

				return false;
			}

			if (!is_readable($attachFile))
			{
				Factory::getLog()->log(LogLevel::WARNING, "The file is not readable; no email sent");

				return false;
			}

			$fileSize = @filesize($attachFile);

			if ($fileSize)
			{
				// Check that we have AT LEAST 2.5 times free RAM as the filesize (that's how much we'll need)
				if (!function_exists('ini_get'))
				{
					// Assume 8Mb of PHP memory limit (worst case scenario)
					$totalRAM = 8388608;
				}
				else
				{
					$totalRAM = ini_get('memory_limit');
					if (strstr($totalRAM, 'M'))
					{
						$totalRAM = (int)$totalRAM * 1048576;
					}
					elseif (strstr($totalRAM, 'K'))
					{
						$totalRAM = (int)$totalRAM * 1024;
					}
					elseif (strstr($totalRAM, 'G'))
					{
						$totalRAM = (int)$totalRAM * 1073741824;
					}
					else
					{
						$totalRAM = (int)$totalRAM;
					}
					if ($totalRAM <= 0)
					{
						// No memory limit? Cool! Assume 1Gb of available RAM (which is absurdly abundant as of March 2011...)
						$totalRAM = 1086373952;
					}
				}
				if (!function_exists('memory_get_usage'))
				{
					$usedRAM = 8388608;
				}
				else
				{
					$usedRAM = memory_get_usage();
				}

				$availableRAM = $totalRAM - $usedRAM;

				if ($availableRAM < 2.5 * $fileSize)
				{
					Factory::getLog()->log(LogLevel::WARNING, "The file is too big to be sent by email. Please use a smaller Part Size for Split Archives setting.");
					Factory::getLog()->log(LogLevel::DEBUG, "Memory limit $totalRAM bytes -- Used memory $usedRAM bytes -- File size $fileSize -- Attachment requires approx. " . (2.5 * $fileSize) . " bytes");

					return false;
				}
			}
			else
			{
				Factory::getLog()->log(LogLevel::WARNING, "Your server fails to report the file size of $attachFile. If the backup crashes, please use a smaller Part Size for Split Archives setting");
			}

			$mailer->addAttachment($attachFile);
		}

		Factory::getLog()->log(LogLevel::DEBUG, "-- Sending message");

		try
		{
			$result = $mailer->Send();
		}
		catch (\Exception $e)
		{
			Factory::getLog()->log(LogLevel::WARNING, "Could not email $to:");
			Factory::getLog()->log(LogLevel::WARNING, $e->getMessage());
			$ret = $e->getMessage();
			unset($result);
			unset($mailer);

			return $ret;
		}

		Factory::getLog()->log(LogLevel::DEBUG, "-- Email sent");

		return true;
	}

	/**
	 * Deletes a file from the local server using direct file access or FTP
	 *
	 * @param   string $file File path to delete
	 *
	 * @return  boolean  True on success
	 */
	public function unlink($file)
	{
		try
		{
			$fs = $this->getContainer()->fileSystem;
			$result = $fs->delete($file);
		}
		catch (\RuntimeException $e)
		{
			$result = false;
		}

		if (is_null($result))
		{
			$result = true;
		}

		return $result;
	}

	/**
	 * Moves a file around within the local server using direct file access or FTP
	 *
	 * @param   string $from File path to copy from
	 * @param   string $to   File path to copy to
	 *
	 * @return  boolean  True on success
	 */
	public function move($from, $to)
	{
		try
		{
			$fs = $this->getContainer()->fileSystem;
			$result = $fs->move($from, $to);
		}
		catch (\RuntimeException $e)
		{
			$result = false;
		}

		if (is_null($result))
		{
			$result = true;
		}

		return $result;
	}

	/**
	 * Stores a flash (temporary) variable in the session.
	 *
	 * @param   string $name  The name of the variable to store
	 * @param   string $value The value of the variable to store
	 *
	 * @return  void
	 */
	public function set_flash_variable($name, $value)
	{
		$session = $this->getContainer()->segment;

		$session->setFlash($name, $value);
	}

	/**
	 * Return the value of a flash (temporary) variable from the session and
	 * immediately removes it.
	 *
	 * @param   string $name    The name of the flash variable
	 * @param   mixed  $default Default value, if the variable is not defined
	 *
	 * @return  mixed  The value of the variable or $default if it's not set
	 */
	public function get_flash_variable($name, $default = null)
	{
		$session = $this->getContainer()->segment;

		$value = $session->getFlash($name);

		if (is_null($value))
		{
			$value = $default;
		}

		return $value;
	}

	/**
	 * Perform an immediate redirection to the defined URL
	 *
	 * @param   string $url The URL to redirect to
	 *
	 * @return  void
	 */
	public function redirect($url)
	{
		$this->getContainer()->application->redirect($url);
	}

	/**
	 * Applies the quirk definitions for Akeeba Solo
	 *
	 * @return  void
	 */
	public function apply_quirk_definitions()
	{
		Factory::getConfigurationChecks()->clearConfigurationCheckDefinitions();
		// Output directory unwritable
		Factory::getConfigurationChecks()->addConfigurationCheckDefinition('001', 'critical');
		// Free memory too low
		Factory::getConfigurationChecks()->addConfigurationCheckDefinition('004', 'critical');
		// Output folder within plugin folder
		Factory::getConfigurationChecks()->addConfigurationCheckDefinition('013', 'critical', 'COM_AKEEBA_CPANEL_WARNING_Q013', array('\\Akeeba\\Engine\\Platform\\Wordpress', 'quirk_013'));
		// Cannot create default output directory
		Factory::getConfigurationChecks()->addConfigurationCheckDefinition('014', 'critical', 'COM_AKEEBA_CPANEL_WARNING_Q014', array('\\Akeeba\\Engine\\Platform\\Wordpress', 'quirk_014'));
		// open_basedir on output directory
		Factory::getConfigurationChecks()->addConfigurationCheckDefinition('101', 'high');
		// Less than 10" of max_execution_time with PHP Safe Mode enabled
		Factory::getConfigurationChecks()->addConfigurationCheckDefinition('103', 'high');
		// CRC problems with hash extension not present
		Factory::getConfigurationChecks()->addConfigurationCheckDefinition('202', 'medium');
		// Default output directory in use
		Factory::getConfigurationChecks()->addConfigurationCheckDefinition('203', 'medium');
		// Disabled functions may affect operation
		Factory::getConfigurationChecks()->addConfigurationCheckDefinition('204', 'medium');
		// ZIP format selected
		Factory::getConfigurationChecks()->addConfigurationCheckDefinition('401', 'low');
	}

	public function getPlatformDirectories()
	{
		return array(__DIR__);
	}

	/**
	 * Critical error detection: Output folder within plugin folder
	 *
	 * @return  bool
	 * @since   8.1.0
	 */
	public static function quirk_013()
	{
		if (defined('AKEEBA_BACKUP_TESTING_DISABLE_Q013') && AKEEBA_BACKUP_TESTING_DISABLE_Q013)
		{
			return false;
		}

		$registry = Factory::getConfiguration();
		$outDir   = $registry->get('akeeba.basic.output_directory');

		if (empty($outDir))
		{
			return false;
		}

		$realOutDir = @realpath($outDir);

		if (!@is_dir($realOutDir) || $realOutDir === false)
		{
			return false;
		}

		$contentFolder =
			rtrim(
				(defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : (rtrim(ABSPATH, '/') . '/wp-content')),
				'/'
			);
		$pluginsDir    = rtrim(
			defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : ($contentFolder . '/plugins'),
			'/'
		);
		$pluginSlug    = (class_exists('AkeebaBackupWP') && !empty(\AkeebaBackupWP::$pluginBaseName))
			? \AkeebaBackupWP::$pluginBaseName
			: 'akeebabackupwp/akeebabackupwp.php';

		$abwpDir     = dirname($pluginsDir . '/' . $pluginSlug);
		$abwpDirReal = @realpath($abwpDir);

		if (!@is_dir($abwpDir) || empty($abwpDirReal))
		{
			return false;
		}

		return stripos($realOutDir, $abwpDirReal) === 0
		       || stripos($realOutDir, $abwpDir) === 0
		       || stripos($outDir, $abwpDirReal) === 0
		       || stripos($outDir, $abwpDir) === 0;
	}

	/**
	 * Critical error detection: Cannot create default output directory
	 *
	 * @return  bool
	 * @since   8.1.0
	 */
	public static function quirk_014()
	{
		$stock_dirs    = Platform::getInstance()->get_stock_directories();
		$defaultOutput = $stock_dirs['[DEFAULT_OUTPUT]'] ?? '';

		if (empty($defaultOutput))
		{
			return false;
		}

		if (!@is_dir($defaultOutput))
		{
			return true;
		}

		if (@realpath($defaultOutput) === false)
		{
			return true;
		}

		return false;
	}

	protected function detectProxySettings()
	{
		$host    = defined('WP_PROXY_HOST') ? WP_PROXY_HOST : '';
		$port    = defined('WP_PROXY_PORT') ? WP_PROXY_PORT : 8080;
		$user    = defined('WP_PROXY_USERNAME') ? WP_PROXY_USERNAME : '';
		$pass    = defined('WP_PROXY_PASSWORD') ? WP_PROXY_PASSWORD : '';
		$enabled = !empty($host) && !empty($port);

		$this->setProxySettings($enabled, $host, $port, $user, $pass);
	}

	/**
	 * Tries to create the default output directory if it does not already exist.
	 *
	 * @param   string  $pathName  The path to the output directory
	 *
	 * @return  void
	 */
	protected function conditionallyCreateDefaultOutputDirectory(string $pathName)
	{
		// If it already exists
		if (@is_dir($pathName))
		{
			return;
		}

		$success = @mkdir($pathName, 0755, true);

		if (!$success || !@is_dir($pathName))
		{
			/**
			 * Why return instead of using WordPress' WP_Filesystem? Because WordPress does not, in fact, save the FTP
			 * information anywhere. Therefore, even using WordPress' WP_Filesystem would try to use mkdir() which has
			 * already failed at this point. We can just display a message instead.
			 */
			return;
		}

		// Create files to protect against direct access
		Factory::getFilesystemTools()->ensureNoAccess($pathName, true);
	}
}
