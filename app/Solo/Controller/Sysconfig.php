<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Controller;


use Awf\Database\Driver;
use Awf\Text\Text;
use Solo\Helper\Utils;

class Sysconfig extends ControllerDefault
{
	/**
	 * The top-level variables the System Configuration page posts. Anything else in the request is not stored.
	 *
	 * Keep this in sync with the page (Solo/ViewTemplates/Sysconfig). The database connection fields (dbdriver, dbhost,
	 * dbuser, dbpass, dbname) are in POSTED_DATABASE_FIELDS: only standalone Solo has them, and they are not stored blindly.
	 *
	 * @var  string[]
	 */
	private const POSTED_FIELDS = [
		'darkmode', 'useencryption', 'no_flush', 'timezone', 'localtime', 'timezonetext', 'forced_backup_timezone',
		'wp_cron_override_time', 'showDeleteOnRestore', 'showBrowserDownload', 'under_tools', 'live_site',
		'session_timeout', 'dateformat', 'stats_enabled', 'accurate_php_cli', 'proxy_host', 'proxy_port', 'proxy_user',
		'proxy_pass', 'prefix',
		'fs_driver', 'fs_host', 'fs_port', 'fs_username', 'fs_password', 'fs_directory',
		'mail_online', 'mail_mailer', 'mail_mailfrom', 'mail_fromname', 'mail_smtphost', 'mail_smtpport',
		'mail_smtpauth', 'mail_smtpsecure', 'mail_smtpuser', 'mail_smtppass',
		'oauth2_client_box', 'box_client_id', 'box_client_secret',
		'oauth2_client_dropbox', 'dropbox_client_id', 'dropbox_client_secret',
		'oauth2_client_googledrive', 'googledrive_client_id', 'googledrive_client_secret',
		'oauth2_client_onedrivebusiness', 'onedrivebusiness_client_id', 'onedrivebusiness_client_secret',
	];

	/**
	 * The database connection fields of the Database tab, which exists only in standalone Solo (not inside a CMS).
	 *
	 * They are stored only when they differ from the stored values AND the new settings can connect to the database.
	 *
	 * @var  string[]
	 */
	private const POSTED_DATABASE_FIELDS = ['dbdriver', 'dbhost', 'dbuser', 'dbpass', 'dbname'];

	/**
	 * The table (without the prefix) whose absence means that the database holds no installation of Solo: without the users
	 * table nobody can log in.
	 */
	private const AUTHORITATIVE_TABLE = 'ak_users';

	/**
	 * The keys of the options[] array the System Configuration page posts. Anything else in it is not stored.
	 *
	 * @var  string[]
	 */
	private const POSTED_OPTIONS = [
		'failure_timeout', 'failure_email_address', 'failure_email_subject', 'failure_email_body',
		'backup_age_show', 'backup_age_failed', 'backup_age_max_hours',
		'backuponupdate_core_manual', 'backuponupdate_core_manual_profile',
		'legacyapi_enabled', 'jsonapi_enabled', 'frontend_secret_word', 'frontend_email_on_finish',
		'frontend_email_when', 'frontend_email_address', 'frontend_email_subject', 'frontend_email_body',
		'desktop_notifications', 'push_preference', 'push_apikey', 'update_dlid', 'minstability',
		'no_php_version_check',
	];

	public function save()
	{
		$this->csrfProtection();

		$urlredirect = $this->input->get('urlredirect', null, 'raw');

		$data = $this->input->getData();

		unset($data['view']);
		unset($data['task']);
		unset($data['layout']);
		unset($data['token']);

		if (isset($data['urlredirect']))
		{
			unset($data['urlredirect']);
		}

		// The request also carries the cookies (PHP's $_REQUEST) and whatever else was sent. Store only what the page posts.
		$data = $this->filterPostedData($data);

		// The database settings are validated before they are stored; they are not part of the generic loop below.
		$database      = $this->extractDatabaseSettings($data);
		$databaseError = null;
		$missingTable  = null;

		$keys = array_keys($data);
		$checkboxKeys = array(
			'mail.online', 'mail.smtpauth', 'options.legacyapi_enabled', 'options.jsonapi_enabled', 'options.frontend_email_on_finish',
			'options.usesvnsource', 'options.displayphpwarning'
		);

		foreach ($keys as $key)
		{
			if (strpos($key, 'fs_') === 0)
			{
				$data['fs.' . substr($key, 3)] = $data[$key];
				unset($data[$key]);
				$key = 'fs.' . substr($key, 3);
			}
			elseif (strpos($key, 'mail_') === 0)
			{
				$data['mail.' . substr($key, 5)] = $data[$key];
				unset($data[$key]);
				$key = 'mail.' . substr($key, 5);
			}

			if (in_array($key, $checkboxKeys))
			{
				$data[$key] = in_array($data[$key], array('on', 'yes', 'true', 1, true));
			}
			elseif ($key == 'options')
			{
				foreach ($data[$key] as $k => $v)
				{
					$check = 'options.' . $k;

					if (in_array($check, $checkboxKeys))
					{
						$data[$key][$k] = in_array($data[$key][$k], array('on', 'yes', 'true', 1, true));
					}
				}
			}
		}

		$config = $this->container->appConfig;

		$database = $this->getChangedDatabaseSettings($database);

		if (!empty($database) && $this->connectionSettingsChanged($database))
		{
			try
			{
				$this->checkDatabaseConnection($database);
			}
			catch (\Throwable $e)
			{
				$databaseError = $e->getMessage();
				$database      = [];
			}
		}

		// The new settings connect (or the prefix is the only change): are Solo's tables where they point to?
		if (!empty($database) && !$this->soloTablesExist($database))
		{
			$missingTable = (string) (array_key_exists('prefix', $database) ? $database['prefix'] : $config->get('prefix', '')) . self::AUTHORITATIVE_TABLE;
		}

		foreach ($database as $k => $v)
		{
			$config->set($k, $v);
		}

		foreach ($data as $k => $v)
		{
			if (is_array($v))
			{
				foreach ($v as $sk => $sv)
				{
					$config->set($k . '.' . $sk, $sv);
				}
			}
			else
			{
				$config->set($k, $v);
			}
		}

		$this->container->appConfig->saveConfiguration();

		// Sanitise the redirection URL. Redirecting to a raw base64_decode() of it is an open redirect.
		$url = Utils::safeDecodeReturnUrl($urlredirect);

		if (empty($url))
		{
			$url = $this->container->router->route('index.php');
		}

		// Akeeba Backup for WordPress: reset update information
		if (defined('WPINC'))
		{
			$transient = (object) [
				'response' => []
			];
			\AkeebaBackupWPUpdater::getUpdateInformation($transient);
		}

		// Finally, redirect
		if ($databaseError !== null)
		{
			$this->setRedirect(
				$url,
				Text::sprintf('SOLO_SYSCONFIG_DATABASE_NOT_CHANGED', htmlspecialchars($databaseError, ENT_QUOTES, 'UTF-8')),
				'error'
			);

			return;
		}

		if ($missingTable !== null)
		{
			$this->setRedirect(
				$url,
				Text::sprintf('SOLO_SYSCONFIG_DATABASE_NO_TABLES', htmlspecialchars($missingTable, ENT_QUOTES, 'UTF-8')),
				'warning'
			);

			return;
		}

		$this->setRedirect($url, Text::_('SOLO_SYSCONFIG_SAVE'));
	}

	/**
	 * Keeps only the request variables the System Configuration page posts, with scalar values.
	 *
	 * @param   array  $data  The request variables
	 *
	 * @return  array
	 */
	private function filterPostedData(array $data): array
	{
		$ret = [];

		foreach ($data as $key => $value)
		{
			if ($key === 'options')
			{
				if (!is_array($value))
				{
					continue;
				}

				foreach ($value as $optionKey => $optionValue)
				{
					if (in_array($optionKey, self::POSTED_OPTIONS, true) && is_scalar($optionValue))
					{
						$ret['options'][$optionKey] = $optionValue;
					}
				}

				continue;
			}

			if (in_array($key, self::POSTED_FIELDS, true) && is_scalar($value))
			{
				$ret[$key] = $value;
			}

			// The Database tab does not exist inside a CMS: there the connection is the CMS' and these are never stored
			if (in_array($key, self::POSTED_DATABASE_FIELDS, true) && is_scalar($value) && !$this->container->segment->get('insideCMS', false))
			{
				$ret[$key] = $value;
			}
		}

		return $ret;
	}

	/**
	 * Removes the database settings (connection fields and table prefix) from the posted data and returns them.
	 *
	 * @param   array  $data  The posted data; modified.
	 *
	 * @return  array
	 */
	private function extractDatabaseSettings(array &$data): array
	{
		$database = [];

		foreach (array_merge(self::POSTED_DATABASE_FIELDS, ['prefix']) as $key)
		{
			if (array_key_exists($key, $data))
			{
				$database[$key] = $data[$key];
				unset($data[$key]);
			}
		}

		return $database;
	}

	/**
	 * Returns only the database settings which differ from the stored ones.
	 *
	 * A setting which was not posted is unchanged. An empty password is unchanged too, as long as the server and the user
	 * it belongs to are the same: it is what a form which was not meant to change the password posts. (The price is that
	 * the password of an unchanged user cannot be emptied from this page.)
	 *
	 * @param   array  $database  The posted database settings
	 *
	 * @return  array
	 */
	private function getChangedDatabaseSettings(array $database): array
	{
		$config  = $this->container->appConfig;
		$changed = [];

		foreach ($database as $key => $value)
		{
			$value   = (string) $value;
			$current = (string) $config->get($key, '');

			if ($key === 'dbdriver' ? (strtolower($value) === strtolower($current)) : ($value === $current))
			{
				continue;
			}

			$changed[$key] = $value;
		}

		if (isset($changed['dbpass']) && $changed['dbpass'] === '' && !isset($changed['dbhost']) && !isset($changed['dbuser']) && !isset($changed['dbdriver']))
		{
			unset($changed['dbpass']);
		}

		return $changed;
	}

	/**
	 * Does the change involve a setting which the connection to the database depends on? (The table prefix does not.)
	 *
	 * @param   array  $changed  The changed database settings
	 *
	 * @return  bool
	 */
	private function connectionSettingsChanged(array $changed): bool
	{
		return count(array_intersect_key($changed, array_flip(self::POSTED_DATABASE_FIELDS))) > 0;
	}

	/**
	 * Checks that the application can connect to the database with the new settings.
	 *
	 * @param   array  $changed  The changed database settings
	 *
	 * @return  void
	 * @throws  \Throwable  When it cannot.
	 */
	protected function checkDatabaseConnection(array $changed): void
	{
		$db = Driver::fromOptions($this->getConnectionOptions($changed));
		$db->connect();
		$db->disconnect();
	}

	/**
	 * Does the database the new settings point to hold Solo's tables (under the new table prefix)?
	 *
	 * A change of the table prefix alone is looked up through the connection the application already has. If the tables
	 * cannot be listed, nobody can tell they are missing: the answer is yes.
	 *
	 * @param   array  $changed  The changed database settings
	 *
	 * @return  bool
	 */
	protected function soloTablesExist(array $changed): bool
	{
		$prefix = (string) (array_key_exists('prefix', $changed) ? $changed['prefix'] : $this->container->appConfig->get('prefix', ''));

		try
		{
			if ($this->connectionSettingsChanged($changed))
			{
				$db = Driver::fromOptions($this->getConnectionOptions($changed));
				$db->connect();

				$tables = $db->getTableList();

				$db->disconnect();
			}
			else
			{
				$tables = $this->container->db->getTableList();
			}
		}
		catch (\Throwable $e)
		{
			return true;
		}

		foreach ($tables as $table)
		{
			if (strcasecmp((string) $table, $prefix . self::AUTHORITATIVE_TABLE) === 0)
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * The options to open a connection to the database with the changed settings applied over the stored ones.
	 *
	 * @param   array  $changed  The changed database settings
	 *
	 * @return  array
	 */
	private function getConnectionOptions(array $changed): array
	{
		$config = $this->container->appConfig;
		$new    = function (string $key, $default = '') use ($changed, $config) {
			return array_key_exists($key, $changed) ? $changed[$key] : $config->get($key, $default);
		};

		$options = [
			'driver'   => preg_replace('/[^A-Z0-9_\.-]/i', '', (string) $new('dbdriver', 'mysqli')),
			'database' => $new('dbname'),
			'select'   => true,
			'host'     => $new('dbhost', 'localhost'),
			'user'     => $new('dbuser'),
			'password' => $new('dbpass'),
			'charset'  => $config->get('dbcharset', 'utf8'),
			'prefix'   => $new('prefix'),
			'ssl'      => [],
		];

		// The Database tab does not edit the SSL settings: connect the way the application does now.
		if ($config->get('dbencryption', 0) && in_array(strtolower($options['driver']), ['mysqli', 'pdomysql']))
		{
			$options['ssl'] = [
				'enable'             => true,
				'cipher'             => $config->get('dbsslcipher', '') ?: '',
				'ca'                 => $config->get('dbsslca', '') ?: '',
				'capath'             => $config->get('dbsslcapath', '') ?: '',
				'key'                => $config->get('dbsslkey', '') ?: '',
				'cert'               => $config->get('dbsslcert', '') ?: '',
				'verify_server_cert' => $config->get('dbsslverifyservercert', true) ?: false,
			];
		}

		return $options;
	}

	public function apply()
	{
		$this->save();

		// The message (and its type) is whatever save() set: "saved", or why the database settings were not, or the warning.
		$this->setRedirect($this->container->router->route('index.php?view=sysconfig'));
	}

    public function testemail()
    {
        // CSRF prevention
        $this->csrfProtection();

        $config = $this->container->appConfig;
        $mailer = $this->container->mailer();
        $user   = $this->container->userManager->getUser();

        $from     = $config->get('mail.mailfrom');
        $fromName = $config->get('mail.fromname');

        $subject  = Text::sprintf('SOLO_SYSCONFIG_TESTEMAIL_SUBJECT', $this->container->appConfig->get('base_url', ''));
        $body     = Text::_('SOLO_SYSCONFIG_TESTEMAIL_BODY');

        try
        {
            $mailer->sendMail($from, $fromName, $user->getEmail(), $subject, $body);
            $type = 'info';
            $msg  = Text::_('SOLO_SYSCONFIG_TESTMEMAIL_SENT');
        }
        catch(\Exception $e)
        {
            $type = 'error';
            $msg  = $e->getMessage();
        }

        $this->setRedirect($this->container->router->route('index.php?view=sysconfig'), $msg, $type);
    }
}
