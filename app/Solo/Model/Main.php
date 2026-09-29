<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Model;

use Akeeba\Engine\Factory;
use Akeeba\Engine\Platform;
use Akeeba\Engine\Util\Complexify;
use Akeeba\Engine\Util\RandomValue;
use Awf\Database\Installer;
use Awf\Download\Download;
use Awf\Mvc\Model;
use Awf\Timer\Timer;
use Awf\Uri\Uri;
use DirectoryIterator;
use Exception;
use RuntimeException;
use Solo\Application;
use Solo\Helper\HashHelper;
use Solo\Helper\SecretWord;
use Solo\PostUpgradeScript;
use stdClass;

class Main extends Model
{
	/**
	 * Checks the database for missing / outdated tables and runs the appropriate SQL scripts if necessary.
	 *
	 * @param   bool  $allowMarkingAsStuck  Should I allow the database update to be marked as stuck? The only time I
	 *                                      am not doing that is the plugin activation (what WP calls "install").
	 *
	 * @return  $this
	 *
	 * @throws Exception
	 */
	public function checkAndFixDatabase($allowMarkingAsStuck = true)
	{
		$params = $this->container->appConfig;

		if ($allowMarkingAsStuck)
		{
			// First of all let's check if we are already updating
			$stuck = $params->get('updatedb', 0);

			if ($stuck)
			{
				// If we throw there is no way for the user to address the problem. DO NOT UNCOMMENT NEXT LINE.
				// throw new RuntimeException('Previous database update is flagged as stuck');
			}

			// Then set the flag
			$params->set('updatedb', 1);
			$params->saveConfiguration();
		}

		// Update the database, if necessary
		$dbInstaller = new Installer($this->container);
		$dbInstaller->updateSchema();

		if ($allowMarkingAsStuck)
		{
			// And finally remove the flag if everything went fine
			$params->set('updatedb', null);
			$params->saveConfiguration();
		}

		return $this;
	}

	/**
	 * Returns a list of Akeeba Engine backup profiles in a format suitable for use with Html\Select::genericList
	 *
	 * @param   bool  $includeId  Should I include the profile ID in front of the name?
	 *
	 * @return  array
	 */
	public function getProfileList($includeId = true)
	{
		$db = $this->container->db;

		$query = $db->getQuery(true)
			->select([
				$db->qn('id') . ' as ' . $db->qn('value'),
				$db->qn('description') . ' as ' . $db->qn('text'),
			])->from($db->qn('#__ak_profiles'));
		$db->setQuery($query);

		$records = $db->loadAssocList();

		$ret = [];

		if (!empty($records))
		{
			foreach ($records as $profile)
			{
				$description = $profile['text'];

				if ($includeId)
				{
					$description = '#' . $profile['value'] . '. ' . $description;
				}

				$ret[] = $this->getContainer()->html->select->option( $profile['value'], $description);
			}
		}

		return $ret;
	}

	/**
	 * Gets a list of profiles which will be displayed as quick icons in the interface
	 *
	 * @return  stdClass[]  Array of objects; each has the properties `id` and `description`
	 */
	public function getQuickIconProfiles()
	{
		$db = $this->container->db;

		$query = $db->getQuery(true)
			->select([
				$db->qn('id'),
				$db->qn('description'),
			])->from($db->qn('#__ak_profiles'))
			->where($db->qn('quickicon') . ' = ' . $db->q(1))
			->order($db->qn('id') . " ASC");
		$db->setQuery($query);

		$ret = $db->loadObjectList();

		if (empty($ret))
		{
			$ret = [];
		}

		return $ret;
	}

	/**
	 * Returns the details for the latest backup, for use in the "Latest backup" cell in the control panel
	 *
	 * @return  array  The latest backup information. Empty if there is no latest backup (of course!)
	 */
	public function getLatestBackupDetails()
	{
		$db    = $this->container->db;
		$query = $db->getQuery(true)
			->select('MAX(' . $db->qn('id') . ')')
			->from($db->qn('#__ak_stats'));
		$db->setQuery($query);
		$id = $db->loadResult();

		$backup_types = Factory::getEngineParamsProvider()->loadScripting();

		if (empty($id))
		{
			return [];
		}

		$record = Platform::getInstance()->get_statistics($id);

		if (array_key_exists($record['type'], $backup_types['scripts']))
		{
			$record['type_translated'] = Platform::getInstance()->translate($backup_types['scripts'][$record['type']]['text']);
		}
		else
		{
			$record['type_translated'] = '';
		}

		return $record;
	}

	/**
	 * Check the Akeeba Engine's settings encryption status and proceed to enabling / disabling encryption if necessary.
	 *
	 * @return  void
	 */
	public function checkEngineSettingsEncryption()
	{
		$secretKeyFile = APATH_BASE . '/Solo/secretkey.php';

		// Different secretkey.php path when using WordPress
		if (defined('ABSPATH'))
		{
			$secretKeyFile = rtrim(
				                 (defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : (rtrim(ABSPATH, '/') . '/wp-content')),
				                 '/'
			                 ) . '/akeebabackup_secretkey.php';
		}

		// We have to look inside the application config, not  the platform options
		$encryptionEnabled = $this->container->appConfig->get('useencryption', -1);
		$fileExists        = @file_exists($secretKeyFile);

		if ($fileExists && ($encryptionEnabled == 0))
		{
			// We have to disable the encryption
			$this->disableEngineSettingsEncryption($secretKeyFile);
		}
		elseif (!$fileExists && ($encryptionEnabled != 0))
		{
			// We have to enable the encryption
			$this->enableEngineSettingsEncryption($secretKeyFile);
		}
	}

	/**
	 * Do I have to warn the user about putting a Download ID in the Core version?
	 *
	 * @return  boolean
	 */
	public function mustWarnAboutDownloadIdInCore()
	{
		$ret   = false;
		$isPro = AKEEBABACKUP_PRO;

		if ($isPro)
		{
			return $ret;
		}

		$downloadId = Platform::getInstance()->get_platform_configuration_option('update_dlid', '');

		if (preg_match('/^([0-9]{1,}:)?[0-9a-f]{32}$/i', $downloadId))
		{
			$ret = true;
		}

		return $ret;
	}

	/**
	 * Do I need to tell the user to set up a Download ID?
	 *
	 * @return  boolean
	 */
	public function needsDownloadID()
	{
		// Do I need a Download ID?
		$ret   = true;
		$isPro = AKEEBABACKUP_PRO;

		if (!$isPro)
		{
			$ret = false;
		}
		else
		{
			$dlid = Platform::getInstance()->get_platform_configuration_option('update_dlid', '');

			if (preg_match('/^([0-9]{1,}:)?[0-9a-f]{32}$/i', $dlid))
			{
				$ret = false;
			}
		}

		return $ret;
	}

	/**
	 * Update the cached live site's URL for the front-end backup feature (altbackup.php)
	 * and the detected Joomla! libraries path
	 *
	 * @return void
	 */
	public function updateMagicParameters()
	{
		$dirtyFlag     = false;
		$baseURL       = Uri::base(false, $this->container);
		$storedBaseURL = $this->container->appConfig->get('options.siteurl');

		if ($storedBaseURL != $baseURL)
		{
			$this->container->appConfig->set('options.siteurl', $baseURL);
			$dirtyFlag = true;
		}

		if (defined('WPINC'))
		{
			$ajaxURL       = admin_url('admin-ajax.php');
			$storedAjaxURL = $this->container->appConfig->get('options.ajaxurl');

			if ($storedAjaxURL != $ajaxURL)
			{
				$this->container->appConfig->set('options.ajaxurl', $ajaxURL);
				$dirtyFlag = true;
			}
		}

		if (defined('WPINC'))
		{
			$greatestPhpVersion = $this->container->appConfig->get('options.greatest_php_version_seen', '0.0.0');

			if (@version_compare(PHP_VERSION, $greatestPhpVersion, 'gt'))
			{
				$this->container->appConfig->set('options.greatest_php_version_seen', PHP_VERSION);
				$dirtyFlag = true;
			}
		}

		if (!$this->container->appConfig->get('options.confwiz_upgrade', 0))
		{
			$this->markOldProfilesConfigured();
			$this->container->appConfig->set('options.confwiz_upgrade', 1);
			$dirtyFlag = true;
		}

		if ($dirtyFlag)
		{
			try
			{
				$this->container->appConfig->saveConfiguration();
			}
			catch (RuntimeException $e)
			{
				// Do nothing; if the magic parameters are missing nobody dies
			}
		}
	}

	/**
	 * Flags stuck backups as invalid
	 *
	 * @return void
	 */
	public function flagStuckBackups()
	{
		try
		{
			// Invalidate stale backups
			Factory::resetState([
				'global' => true,
				'log'    => false,
				'maxrun' => $this->container->appConfig->get('options.failure_timeout', 180),
			]);
		}
		catch (Exception $e)
		{
			// This will fail if the output directory is unwriteable / unreadable / missing.
		}
	}

	public function notifyFailed()
	{
		$config = $this->container->appConfig;

		// Invalidate stale backups
		$this->flagStuckBackups();

		// Get the last execution and search for failed backups AFTER that date
		$last = $this->getLastCheck();

		// Get failed backups
		$filters = [
			['field' => 'status', 'operand' => '=', 'value' => 'fail'],
			['field' => 'backupstart', 'operand' => '>', 'value' => $last],
		];

		$failed = Platform::getInstance()->get_statistics_list(['filters' => $filters]);

		// Well, everything went ok.
		if (!$failed)
		{
			return [
				'message' => ["No need to run: no failed backups or they were already notificated"],
				'result'  => true,
			];
		}

		// Whops! Something went wrong, let's start notifing
		$emails = $config->get('options.failure_email_address', '');
		$emails = explode(',', $emails);

		if (!$emails)
		{
			$emails = Platform::getInstance()->get_administrator_emails();
		}

		if (empty($emails))
		{
			return [
				'message' => ["WARNING! Failed backup(s) detected, but there are no configured Super Administrators to receive notifications"],
				'result'  => false,
			];
		}

		$failedReport = [];

		foreach ($failed as $fail)
		{
			$string = "Description : " . $fail['description'] . "\n";
			$string .= "Start time  : " . $fail['backupstart'] . "\n";
			$string .= "Origin      : " . $fail['origin'] . "\n";
			$string .= "Type        : " . $fail['type'] . "\n";
			$string .= "Profile ID  : " . $fail['profile_id'] . "\n";
			$string .= "Backup ID   : " . $fail['id'];

			$failedReport[] = $string;
		}

		$failedReport = implode("\n#-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+#\n", $failedReport);

		$email_subject = $config->get('options.failure_email_subject', '');

		if (!$email_subject)
		{
			$email_subject = <<<ENDSUBJECT
THIS EMAIL IS SENT FROM YOUR SITE "[SITENAME]" - Failed backup(s) detected
ENDSUBJECT;
		}

		$email_body = $config->get('options.failure_email_body', '');

		if (!$email_body)
		{
			$email_body = <<<ENDBODY
================================================================================
FAILED BACKUP ALERT
================================================================================

Your site has determined that there are failed backups.

The following backups are found to be failing:

[FAILEDLIST]

================================================================================
WHY AM I RECEIVING THIS EMAIL?
================================================================================

This email has been automatically sent by scritp you, or the person who built
or manages your site, has installed and explicitly configured. This script looks
for failed backups and sends an email notification to all Super Users.

If you do not understand what this means, please do not contact the authors of
the software. They are NOT sending you this email and they cannot help you.
Instead, please contact the person who built or manages your site.

================================================================================
WHO SENT ME THIS EMAIL?
================================================================================

This email is sent to you by your own site, [SITENAME]

ENDBODY;
		}

		$email_subject = Factory::getFilesystemTools()->replace_archive_name_variables($email_subject);
		$email_body    = Factory::getFilesystemTools()->replace_archive_name_variables($email_body);
		$email_body    = str_replace('[FAILEDLIST]', $failedReport, $email_body);

		foreach ($emails as $email)
		{
			Platform::getInstance()->send_email($email, $email_subject, $email_body);
		}

		// Let's update the last time we check, so we will avoid to send
		// the same notification several times
		$this->updateLastCheck(intval($last));

		return [
			'message' => [
				"WARNING! Found " . count($failed) . " failed backup(s)",
				"Sent " . count($emails) . " notifications",
			],
			'result'  => true,
		];
	}

	/**
	 * Performs any post-upgrade actions
	 *
	 * @param   bool  $schemaUpgrade  Should I allow upgrading the database tables?
	 *
	 * @return  bool True if we took any actions, false otherwise
	 */
	public function postUpgradeActions(bool $schemaUpgrade = false)
	{
		// Check the last update_version stored in the database
		$db = $this->container->db;

		$query = $db->getQuery(true)
			->select($db->qn('data'))
			->from($db->qn('#__ak_params'))
			->where($db->qn('tag') . ' = ' . $db->q('update_version'));

		try
		{
			$lastVersion = $db->setQuery($query, 0, 1)->loadResult();
		}
		catch (Exception $e)
		{
			$lastVersion = null;
		}

		// If it's our current version we don't have to do anything, just return
		if ($lastVersion == AKEEBABACKUP_VERSION)
		{
			return false;
		}

		// Do we have to try and update the database schema?
		if ($schemaUpgrade)
		{
			try
			{
				$this->checkAndFixDatabase(false);
			}
			catch (\Throwable $e)
			{
			}
		}

		// Load and execute the PostUpgradeScript class
		if (class_exists(PostUpgradeScript::class))
		{
			$upgradeScript = new PostUpgradeScript($this->container);
			$upgradeScript->execute();
		}

		// Remove the old update_version from the database
		$query = $db->getQuery(true)
			->delete($db->qn('#__ak_params'))
			->where($db->qn('tag') . ' = ' . $db->q('update_version'));

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (Exception $e)
		{
			// Don't panic
		}

		// Insert the new update_version to the database
		$query = $db->getQuery(true)
			->insert($db->qn('#__ak_params'))
			->columns([$db->qn('tag'), $db->qn('data')])
			->values($db->q('update_version') . ', ' . $db->q(AKEEBABACKUP_VERSION));

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (Exception $e)
		{
			// Don't panic
		}

		return true;
	}

	/**
	 * Akeeba Solo / Backup for WordPress 1.3.2 displays a popup if your profile is not already configured by
	 * Configuration Wizard, the Configuration page or imported from the Profiles page. This bit of code makes sure that
	 * existing profiles will be marked as already configured just the FIRST time you upgrade to the new version from an
	 * old version.
	 */
	public function markOldProfilesConfigured()
	{
		// Get all profiles
		$db = $this->container->db;

		$query = $db->getQuery(true)
			->select([
				$db->qn('id'),
			])->from($db->qn('#__ak_profiles'))
			->order($db->qn('id') . " ASC");
		$db->setQuery($query);
		$profiles = $db->loadColumn();

		// Save the current profile number
		$session    = $this->getContainer()->segment;
		$oldProfile = $session->profile;

		// Update all profiles
		foreach ($profiles as $profile_id)
		{
			/**
			 * This is used to mark old profiles, from before Akeeba Backup for WordPress / Akeeba Solo 1.3.2, as
			 * already configured to avoid the Configuration Wizard being shown. However, this has the effect of not
			 * showing the Configuration Wizard on fresh installations using the default backup profile. This cannot be
			 * properly fixed in WordPress since there is no code being triggered on plugin installation, only on plugin
			 * activation. However, plugin deactivation and activation can happen multiple times _while the plugin is
			 * already installed_. This means we can not reliably set the options.confwiz_upgrade flag only on new
			 * installations and migrate everything else like we did in Joomla! (and copied over here). As a result we
			 * have to simply not perform any migration at all on the default backup profile. Since it's been several
			 * years since we implemented the wizard popup this change is relatively safe and will only annoy, dunno,
			 * maybe ten holdouts when they finally migrate? Something like that.
			 */
			if ($profile_id == 1)
			{
				continue;
			}

			Factory::nuke();
			Platform::getInstance()->load_configuration($profile_id);
			$config = Factory::getConfiguration();
			$config->set('akeeba.flag.confwiz', 1);
			Platform::getInstance()->save_configuration($profile_id);
		}

		// Restore the old profile
		Factory::nuke();
		Platform::getInstance()->load_configuration($oldProfile);
	}

	/**
	 * Check the strength of the Secret Word for front-end and remote backups. If it is insecure return the reason it
	 * is insecure as a string. If the Secret Word is secure return an empty string.
	 *
	 * @return  string
	 */
	public function getFrontendSecretWordError()
	{
		// Is frontend backup enabled?
		$febEnabled = (Platform::getInstance()->get_platform_configuration_option('legacyapi_enabled', 0) != 0) || (Platform::getInstance()->get_platform_configuration_option('jsonapi_enabled', 0) != 0);

		if (!$febEnabled)
		{
			return '';
		}

		$secretWord = Platform::getInstance()->get_platform_configuration_option('frontend_secret_word', '');

		try
		{
			Complexify::isStrongEnough($secretWord);
		}
		catch (RuntimeException $e)
		{
			// Ah, the current Secret Word is bad. Create a new one if necessary.
			$session   = $this->container->segment;
			$newSecret = $session->get('newSecretWord', null);

			if (empty($newSecret))
			{
				$random    = new RandomValue();
				$newSecret = $random->generateString(32);
				$session->set('newSecretWord', $newSecret);
			}

			return $e->getMessage();
		}

		return '';
	}

	/**
	 * Checks if the mbstring extension is installed and enabled
	 *
	 * @return  bool
	 */
	public function checkMbstring()
	{
		return function_exists('mb_strlen') && function_exists('mb_convert_encoding') &&
			function_exists('mb_substr') && function_exists('mb_convert_case');
	}

	/**
	 * Is the output directory under the configured site root?
	 *
	 * @param   string|null  $outDir  The output directory to check. NULL for the currently configured one.
	 * @param   bool         $solo    Should I check if it's under Solo's web root instead?
	 *
	 * @return  bool  True if the output directory is under the site's web root.
	 *
	 * @since   7.0.3
	 */
	public function isOutputDirectoryUnderSiteRoot($outDir = null, $solo = false)
	{
		// Make sure I have an output directory to check
		$outDir = is_null($outDir) ? $this->getOutputDirectory() : $outDir;
		$outDir = @realpath($outDir);

		// If I can't reliably determine the output directory I can't figure out where it's placed in.
		if ($outDir === false)
		{
			return false;
		}

		// Get the site's root
		$siteRoot = $this->getSiteRoot($solo);
		$siteRoot = @realpath($siteRoot);

		// If I can't reliably determine the site's root I can't figure out its relation to the output directory
		if ($siteRoot === false)
		{
			return false;
		}

		return strpos($outDir, $siteRoot) === 0;
	}

	/**
	 * Did the user set up an output directory inside a folder intended for system files?
	 *
	 * The idea is that this will cause trouble for two reasons. First, you are mixing user-generated with system
	 * content which might be a REALLY BAD idea in and of itself. Second, some if not all of these folders are meant to
	 * be web-accessible. I cannot possibly protect them against web access without breaking anything.
	 *
	 * @param   string|null  $outDir  The output directory to check. NULL for the currently configured one.
	 * @param   bool         $solo    Should I check if it's in Solo's system folders instead?
	 *
	 * @return  bool  True if the output directory is inside a CMS system folder
	 *
	 * @since   7.0.3
	 */
	public function isOutputDirectoryInSystemFolder($outDir = null, $solo = false)
	{
		// Make sure I have an output directory to check
		$outDir = is_null($outDir) ? $this->getOutputDirectory() : $outDir;
		$outDir = @realpath($outDir);

		// If I can't reliably determine the output directory I can't figure out where it's placed in.
		if ($outDir === false)
		{
			return false;
		}

		// If the directory is not under the site's root it doesn't belong to the CMS. Simple, huh?
		if (!$this->isOutputDirectoryUnderSiteRoot($outDir, $solo))
		{
			return false;
		}

		// Check if we are using the default output directory. This is always allowed.
		$stockDirs     = Platform::getInstance()->get_stock_directories();
		$defaultOutDir = realpath($stockDirs['[DEFAULT_OUTPUT]']);

		// If I can't reliably determine the default output folder I can't figure out its relation to the output folder
		if ($defaultOutDir === false)
		{
			return false;
		}

		// Get the site's root
		$siteRoot = $this->getSiteRoot($solo);
		$siteRoot = @realpath($siteRoot);

		// If I can't reliably determine the site's root I can't figure out its relation to the output directory
		if ($siteRoot === false)
		{
			return false;
		}

		foreach ($this->getSystemFolders() as $folder)
		{
			// Is this a partial or an absolute search?
			$partialSearch = substr($folder, -1) == '/';

			clearstatcache(true);

			$absolutePath = realpath($siteRoot . '/' . $folder);

			if ($absolutePath === false)
			{
				continue;
			}

			if (!$partialSearch)
			{
				if (trim($outDir, '/\\') == trim($absolutePath, '/\\'))
				{
					return true;
				}

				continue;
			}

			// Partial search
			if (strpos($outDir, $absolutePath . DIRECTORY_SEPARATOR) === 0)
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Does the output directory contain the security-enhancing files?
	 *
	 * This only checks for the presence of .htaccess, web.config, index.php, index.html and index.html but not their
	 * contents. The idea is that an advanced user may want to customise them for some reason or another.
	 *
	 * @param   string|null  $outDir  The output directory to check. NULL for the currently configured one.
	 *
	 * @return  bool  True if all of the security-enhancing files are present.
	 *
	 * @since   7.0.3
	 */
	public function hasOutputDirectorySecurityFiles($outDir = null)
	{
		// Make sure I have an output directory to check
		$outDir = is_null($outDir) ? $this->getOutputDirectory() : $outDir;
		$outDir = @realpath($outDir);

		// If I can't reliably determine the output directory I can't figure out where it's placed in.
		if ($outDir === false)
		{
			return true;
		}

		$files = [
			'.htaccess',
			'web.config',
			'index.php',
			'index.html',
			'index.htm',
		];

		foreach ($files as $file)
		{
			$filePath = $outDir . '/' . $file;

			if (!@file_exists($filePath) || !is_file($filePath))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Checks whether the given output directory is directly accessible over the web.
	 *
	 * @param   string|null  $outDir  The output directory to check. NULL for the currently configured one.
	 * @param   bool         $solo    Check if it's accessible under Solo's web root? False to test under site's root.
	 *
	 * @return  array
	 *
	 * @since   7.0.3
	 */
	public function getOutputDirectoryWebAccessibleState($outDir = null, $solo = false)
	{
		$inCMS = $this->container->segment->get('insideCMS', false);
		$ret   = [
			'readFile'   => false,
			'listFolder' => false,
			'isSystem'   => $this->isOutputDirectoryInSystemFolder(),
			'hasRandom'  => $this->backupFilenameHasRandom(),
		];

		// Make sure I have an output directory to check
		$outDir = is_null($outDir) ? $this->getOutputDirectory() : $outDir;
		$outDir = @realpath($outDir);

		// If I can't reliably determine the output directory I can't figure out its web path
		if ($outDir === false)
		{
			return $ret;
		}

		$checkFile     = $this->getAccessCheckFile($outDir);
		$checkFilePath = $outDir . '/' . $checkFile;

		if (is_null($checkFile))
		{
			return $ret;
		}

		$webPath = $this->getOutputDirectoryWebPath($outDir, $solo);

		if (is_null($webPath))
		{
			@unlink($checkFilePath);

			return $ret;
		}

		// Construct a URL for the check file
		if ($solo)
		{
			$baseURL = rtrim(Uri::base(), '/');
		}
		elseif ($inCMS)
		{
			$baseURL = $this->getWordPressUrl();
		}
		else
		{
			$baseURL = Factory::getConfiguration()->get('akeeba.platform.site_url', '');
		}

		if (empty($baseURL))
		{
			return $ret;
		}


		$baseURL  = rtrim($baseURL, '/');
		$checkURL = $baseURL . '/' . $webPath . '/' . $checkFile;

		// Try to download the file's contents
		$downloader = new Download($this->container);

		$options = [
			CURLOPT_SSL_VERIFYPEER => 0,
			CURLOPT_SSL_VERIFYHOST => 0,
			CURLOPT_FOLLOWLOCATION => 1,
			CURLOPT_TIMEOUT        => 10,
		];

		if ($downloader->getAdapterName() == 'fopen')
		{
			$options = [
				'http' => [
					'follow_location' => true,
					'timeout'         => 10,
				],
				'ssl'  => [
					'verify_peer' => false,
				],
			];
		}

		$proxyParams = Platform::getInstance()->getProxySettings();

		if ($proxyParams['enabled'])
		{
			$options['proxy'] = [
				'host' => $proxyParams['host'],
				'port' => $proxyParams['port'],
				'user' => $proxyParams['user'],
				'pass' => $proxyParams['pass'],
			];
		}

		$downloader->setAdapterOptions($options);

		$result = $downloader->getFromURL($checkURL);

		if ($result === 'AKEEBA BACKUP WEB ACCESS CHECK')
		{
			$ret['readFile'] = true;
		}

		// Can I list the directory contents?
		$folderURL     = $baseURL . '/' . $webPath . '/';
		$folderListing = $downloader->getFromURL($folderURL);

		@unlink($checkFilePath);

		if (($folderListing !== false) && (strpos($folderListing, basename($checkFile, '.txt')) !== false))
		{
			$ret['listFolder'] = true;
		}

		return $ret;
	}

	/**
	 * Get the web path, relative to the site's root, for the output directory.
	 *
	 * Returns the relative path or NULL if determining it was not possible.
	 *
	 * @param   string|null  $outDir  The output directory to check. NULL for the currently configured one.
	 * @param   bool         $solo    Set true to get path relative to Solo's root. False for relative to site's root.
	 *
	 * @return  string|null  The relative web path to the output directory
	 *
	 * @since   7.0.3
	 */
	public function getOutputDirectoryWebPath($outDir = null, $solo = false)
	{
		// Make sure I have an output directory to check
		$outDir = is_null($outDir) ? $this->getOutputDirectory() : $outDir;
		$outDir = @realpath($outDir);

		// If I can't reliably determine the output directory I can't figure out its web path
		if ($outDir === false)
		{
			return null;
		}

		// Get the site's root
		$siteRoot = $this->getSiteRoot($solo);
		$siteRoot = @realpath($siteRoot);

		// If I can't reliably determine the site's root I can't figure out its relation to the output directory
		if ($siteRoot === false)
		{
			return null;
		}

		// The output directory is NOT under the site's root.
		if (strpos($outDir, $siteRoot) !== 0)
		{
			return null;
		}

		$relPath = trim(substr($outDir, strlen($siteRoot)), '/\\');
		$isWin   = DIRECTORY_SEPARATOR == '\\';

		if ($isWin)
		{
			$relPath = str_replace('\\', '/', $relPath);
		}

		return $relPath;
	}

	/**
	 * Get the semi-random name of a .txt file used to check the output folder's direct web access.
	 *
	 * If the file does not exist we will create it.
	 *
	 * Returns the file name or NULL if creating it was not possible.
	 *
	 * @param   string|null  $outDir  The output directory to check. NULL for the currently configured one.
	 *
	 * @return  string|null  The base name of the check file
	 *
	 * @since   7.0.3
	 */
	public function getAccessCheckFile($outDir = null)
	{
		// Make sure I have an output directory to check
		$outDir = is_null($outDir) ? $this->getOutputDirectory() : $outDir;
		$outDir = @realpath($outDir);

		// If I can't reliably determine the output directory I can't put a file in it
		if ($outDir === false)
		{
			return null;
		}

		$secureSettings = Factory::getSecureSettings();
		$something      = HashHelper::md5($outDir . $secureSettings->getKey());
		$fileName       = 'akaccesscheck_' . $something . '.txt';
		$filePath       = $outDir . '/' . $fileName;

		$result = @file_put_contents($filePath, 'AKEEBA BACKUP WEB ACCESS CHECK');

		return ($result === false) ? null : $fileName;
	}

	/**
	 * Does the backup filename contain the [RANDOM] variable?
	 *
	 * @return  bool
	 *
	 * @since   7.0.3
	 */
	public function backupFilenameHasRandom()
	{
		$registry     = Factory::getConfiguration();
		$templateName = $registry->get('akeeba.basic.archive_name');

		return strpos($templateName, '[RANDOM]') !== false;
	}

	/**
	 * Return the configured output directory for the currently loaded backup profile
	 *
	 * @return  string
	 * @since   7.0.3
	 */
	public function getOutputDirectory()
	{
		$registry = Factory::getConfiguration();

		return $registry->get('akeeba.basic.output_directory', '[DEFAULT_OUTPUT]', true);
	}

	/**
	 * Convert the old, plaintext log files (.log) into their .log.php counterparts.
	 *
	 * @param   int  $timeOut  Maximum time, in seconds, to spend doing this conversion.
	 *
	 * @return  void
	 *
	 * @since   7.0.3
	 */
	public function convertLogFiles($timeOut = 10)
	{
		$registry = Factory::getConfiguration();
		$logDir   = $registry->get('akeeba.basic.output_directory', '[DEFAULT_OUTPUT]', true);

		$timer = new Timer($timeOut, 75);

		// Part I. Remove these obsolete files first
		$killFiles = [
			'akeeba.log',
			'akeeba.backend.log',
			'akeeba.frontend.log',
			'akeeba.cli.log',
			'akeeba.json.log',
		];

		foreach ($killFiles as $fileName)
		{
			$path = $logDir . '/' . $fileName;

			if (@is_file($path))
			{
				@unlink($path);
			}
		}

		if ($timer->getTimeLeft() <= 0.01)
		{
			return;
		}

		// Part II. Convert .log files.
		try
		{
			$di = new DirectoryIterator($logDir);
		}
		catch (Exception $e)
		{
			return;
		}

		foreach ($di as $file)
		{

			try
			{
				if (!$file->isFile())
				{
					continue;
				}
				$baseName = $file->getFilename();
				if (substr($baseName, 0, 7) !== 'akeeba.')
				{
					continue;
				}
				if (substr($baseName, -4) !== '.log')
				{
					continue;
				}
				$this->convertLogFile($file->getPathname());
				if ($timer->getTimeLeft() <= 0.01)
				{
					return;
				}
			}
			catch (Exception $e)
			{
				/**
				 * Someone did something stupid, like using the site's root as the backup output directory while having
				 * an open_basedir restriction. Sorry, mate, you get insecure junk. We had warned you. You didn't heed
				 * the warning. That's your problem now.
				 */
			}
		}
	}

	/**
	 * Update the database configuration for automation scripts.
	 *
	 * This transcribes the database connection parameters from the site's configuration into the file
	 * wp-content/plugins/akeebabackupwp/helpers/private/wp-config.php which is loaded by integration.php whenever we
	 * are not inside a CMS context.
	 *
	 * If the file cannot be written to the automation scripts fall back to trying to include the wp-config.php file,
	 * minus its final require_once line – just like WP-CLI does.
	 */
	public function updateAutomationConfiguration()
	{
		global $table_prefix;

		$fileContents = "<?" . "php\nglobal \$table_prefix;\n";
		$fileContents .= sprintf("\$table_prefix = '%s';\n", str_replace("'", "\\'", $table_prefix));

		$defines = [
			'DB_NAME'     => DB_NAME,
			'DB_USER'     => DB_USER,
			'DB_PASSWORD' => DB_PASSWORD,
			'DB_HOST'     => DB_HOST,
		];

		foreach ($defines as $define => $value)
		{
			$fileContents .= sprintf("define('%s', '%s');\n", $define, str_replace("'", "\\'", $value));
		}

		$target     = AKEEBABACKUPWP_PATH . '/helpers/private/wp-config.php';
		$needsWrite = true;

		if (@file_exists($target))
		{
			$needsWrite = @file_get_contents($target) !== $fileContents;
		}

		if (!$needsWrite)
		{
			return;
		}

		@file_put_contents($target, $fileContents);
	}

	/**
	 * Converts a log file from .log to .log.php
	 *
	 * @param   string  $filePath
	 *
	 * @return  void
	 *
	 * @since   7.0.3
	 */
	protected function convertLogFile($filePath)
	{
		// The name of the converted log file is the same with the extension .php appended to it.
		$newFile = $filePath . '.php';

		// If the new log file exists I should return immediately
		if (@file_exists($newFile))
		{
			return;
		}

		// Try to open the converted log file (.log.php)
		$fp = @fopen($newFile, 'w');

		if ($fp === false)
		{
			return;
		}

		// Try to open the source log file (.log)
		$sourceFP = @fopen($filePath, 'r');

		if ($sourceFP === false)
		{
			@fclose($fp);

			return;
		}

		// Write the die statement to the source log file
		fwrite($fp, '<' . '?' . 'php die(); ' . '?' . ">\n");

		// Copy data, 512KB at a time
		while (!feof($sourceFP))
		{
			$chunk = @fread($sourceFP, 524288);

			if ($chunk === false)
			{
				break;
			}

			$result = fwrite($fp, $chunk);

			if ($result === false)
			{
				break;
			}
		}

		// Close both files
		@fclose($sourceFP);
		@fclose($fp);

		// Delete the original (.log) file
		@unlink($filePath);
	}

	/**
	 * Translate an absolute filesystem path into a relative URL
	 *
	 * @param   string  $fileName  The full filesystem path of a file or directory
	 *
	 * @return  string  The relative URL (or empty string if it's outside the site's root)
	 */
	protected function translatePath($fileName)
	{
		$fileName = str_replace('\\', '/', $fileName);

		$appRoot = str_replace('\\', '/', APATH_BASE);
		$appRoot = rtrim($appRoot, '/');

		if (strpos($fileName, $appRoot) === 0)
		{
			$fileName = substr($fileName, strlen($appRoot) + 1);

			$fileName = trim($fileName, '/');
		}
		else
		{
			return '';
		}

		return $fileName;
	}

	/**
	 * Disables the Akeeba Engine settings encryption. It will load the settings for each profile, decrypt the settings,
	 * commit them to database and finally remove the secret key file.
	 *
	 * @param   string  $secretKeyFile  The path to the secret key file
	 *
	 * @return  void
	 */
	protected function disableEngineSettingsEncryption($secretKeyFile)
	{
		$key = Factory::getSecureSettings()->getKey();

		// Loop all profiles and decrypt their settings
		$db       = $this->container->db;
		$query    = $db->getQuery(true)
			->select('*')
			->from($db->qn('#__ak_profiles'));
		$profiles = $db->setQuery($query)->loadObjectList();

		foreach ($profiles as $profile)
		{
			$id     = $profile->id;
			$config = Factory::getSecureSettings()->decryptSettings($profile->configuration, $key);
			$sql    = $db->getQuery(true)
				->update($db->qn('#__ak_profiles'))
				->set($db->qn('configuration') . ' = ' . $db->q($config))
				->where($db->qn('id') . ' = ' . $db->q($id));
			$db->setQuery($sql);
			$db->execute();
		}

		// Decrypt the Secret Word settings in the database
		SecretWord::enforceDecrypted('frontend_secret_word', null, $this->container);

		// Finally, remove the key file
		$fs = $this->container->fileSystem;
		try
		{
			$fs->delete($secretKeyFile);
		}
		catch (Exception $e)
		{

		}
	}

	/**
	 * Enables the Akeeba Engine settings encryption. It will first try to create a new crypto-safe secret key, load the
	 * settings for each profile, encrypt the settings, then commit them to the database.
	 *
	 * @param   string  $secretKeyFile  The path to the secret key file
	 *
	 * @return  void
	 */
	protected function enableEngineSettingsEncryption($secretKeyFile)
	{
		$key = $this->createEngineSettingsKeyFile($secretKeyFile);

		if (empty($key) || ($key == false))
		{
			return;
		}

		// Loop all profiles and encrypt their settings
		$db       = $this->container->db;
		$query    = $db->getQuery(true)
			->select('*')
			->from($db->qn('#__ak_profiles'));
		$profiles = $db->setQuery($query)->loadObjectList();

		if (!empty($profiles))
		{
			foreach ($profiles as $profile)
			{
				$id     = $profile->id;
				$config = Factory::getSecureSettings()->encryptSettings($profile->configuration, $key);
				$sql    = $db->getQuery(true)
					->update($db->qn('#__ak_profiles'))
					->set($db->qn('configuration') . ' = ' . $db->q($config))
					->where($db->qn('id') . ' = ' . $db->q($id));
				$db->setQuery($sql);
				$db->execute();
			}
		}
	}

	/**
	 * Create the key file for the engine settings and return the crypto-safe random key
	 *
	 * @param   string  $secretKeyFile  The location of the secret key file
	 *
	 * @return  boolean|string  The key, or false if we could not create it
	 */
	protected function createEngineSettingsKeyFile($secretKeyFile)
	{
		$key       = random_bytes(64);

		$encodedKey = base64_encode($key);

		$fileContents = "<?php defined('AKEEBAENGINE') or die(); define('AKEEBA_SERVERKEY', '$encodedKey'); ?>";

		$fs = $this->container->fileSystem;

		try
		{
			$fs->write($secretKeyFile, $fileContents);

			return $key;
		}
		catch (Exception $e)
		{
			return false;
		}
	}

	/**
	 * Return the list of system folders, relative to the site's root
	 *
	 * @param   bool  $solo  Return Solo folders? If false, returns system folders for the site being backed up.
	 *
	 * @return  array
	 * @since   7.0.3
	 */
	protected function getSystemFolders($solo = false)
	{
		if ($solo)
		{
			return [
				'Awf/',
				'cli/',
				'fonts/',
				'languages/',
				'media/',
				'Solo/',
				'templates/',
				'tmp/',
			];
		}

		$scriptType = Factory::getConfiguration()->get('akeeba.platform.scripttype', 'generic');

		switch ($scriptType)
		{
			case 'wordpress':
				return [
					'wp-admin/',
					'wp-content',
					'wp-content/cache/',
					'wp-content/mu-plugins/',
					'wp-content/plugins/',
					'wp-content/themes/',
					'wp-content/upgrade/',
					'wp-content/uploads/',
					'wp-includes/',
				];
				break;

			case 'joomla':
				return [
					'administrator',
					'administrator/cache/',
					'administrator/components/',
					'administrator/help/',
					'administrator/includes/',
					'administrator/language/',
					'administrator/logs/',
					'administrator/manifests/',
					'administrator/modules/',
					'administrator/templates/',
					'cache/',
					'cli/',
					'components/',
					'images/',
					'includes/',
					'language/',
					'layouts/',
					'libraries/',
					'media/',
					'modules/',
					'plugins/',
					'templates/',
					'tmp/',
				];
				break;

			case 'generic':
			default:
				return [];
				break;
		}
	}

	/**
	 * Return the currently configured site root directory
	 *
	 * @param   bool  $solo  Should I get Solo's root instead?
	 *
	 * @return  string
	 * @since   7.0.3
	 */
	protected function getSiteRoot($solo = false)
	{
		if (!$solo)
		{
			return Platform::getInstance()->get_site_root();
		}

		return APATH_BASE;
	}

	/**
	 * Returns the URL to the WordPress site we're running in
	 *
	 * @return  string
	 */
	protected function getWordPressUrl()
	{
		$overrideURL = Factory::getConfiguration()->get('akeeba.platform.site_url', '');
		$overrideURL = trim($overrideURL);

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
			$url  = Platform::getInstance()->get_platform_configuration_option('siteurl', '');
			$oURI = new \Awf\Uri\Uri($url);
		}
		else
		{
			// Running under the web server
			$oURI = \Awf\Uri\Uri::getInstance();
		}

		return $oURI->toString();
	}

	public function notifyFailedUploads()
	{
		$config = $this->container->appConfig;

		// Get profile ID with a post proc engine
		$profileIDsWithPostProc = $this->getProfileIDsWithPostProcEngines();

		if (empty($profileIDsWithPostProc))
		{
			return [
				'message' => ["No need to run: no backup profiles with remote storage currently set up."],
				'result'  => true,
			];
		}

		// Get the last execution and search for failed uploads AFTER that date
		$last = $this->getLastCheck('akeeba_checkfailedupload');

		// Get backup records with failed uploads
		$filters = [
			['field' => 'profile_id', 'operand' => 'IN', 'value' => $profileIDsWithPostProc],
			['field' => 'status', 'operand' => '=', 'value' => 'complete'],
			['field' => 'type', 'operand' => '!=', 'value' => 'dbonly'],
			['field' => 'filesexist', 'operand' => '=', 'value' => '1'],
			// ['field' => 'frozen', 'operand' => '=', 'value' => '0'], // Solo doesn't have "frozen" column in stats
			['field' => 'remote_filename', 'operand' => 'EMPTY', 'value' => ''],
			['field' => 'backupstart', 'operand' => '>', 'value' => $last],
		];

		$failedUploads = Platform::getInstance()->get_statistics_list(['filters' => $filters]);

		// Well, everything went ok.
		if (!$failedUploads)
		{
			return [
				'message' => ["No need to run: no backups with failed uploads, or notifications were already sent."],
				'result'  => true,
			];
		}

		// Whops! Something went wrong, let's start notifing
		$emails = $config->get('options.uploadfailure_email_address', '');
		$emails = explode(',', $emails);

		if (!$emails || empty($emails[0]))
		{
			$emails = Platform::getInstance()->get_administrator_emails();
		}

		if (empty($emails))
		{
			return [
				'message' => ["Failed uploads(s) detected, but there are no configured Super Administrators to receive notifications"],
				'result'  => false,
			];
		}

		$failedReport = [];

		foreach ($failedUploads as $fail)
		{
			$string = "Description : " . $fail['description'] . "\n";
			$string .= "Start time  : " . $fail['backupstart'] . "\n";
			$string .= "Origin      : " . $fail['origin'] . "\n";
			$string .= "Type        : " . $fail['type'] . "\n";
			$string .= "Profile ID  : " . $fail['profile_id'] . "\n";
			$string .= "Backup ID   : " . $fail['id'];

			$failedReport[] = $string;
		}

		$failedReport = implode("\n#-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+#\n", $failedReport);

		$email_subject = $config->get('options.uploadfailure_email_subject', '');

		if (!$email_subject)
		{
			$email_subject = <<<ENDSUBJECT
THIS EMAIL IS SENT FROM YOUR SITE "[SITENAME]" - Failed to upload the following backup(s) to remote storage
ENDSUBJECT;
		}

		$email_body = $config->get('options.uploadfailure_email_body', '');

		if (!$email_body)
		{
			$email_body = <<<ENDBODY
================================================================================
FAILED BACKUP UPLOAD ALERT
================================================================================

Your site has determined that there are backups which failed to upload to remote
storage.

The following backups failed to upload to remote storage:

[FAILEDLIST]

================================================================================
WHY AM I RECEIVING THIS EMAIL?
================================================================================

This email has been automatically sent by a script that you, or the person who
built or manages your site, has installed and explicitly configured. This script
looks for backups which failed to upload to remote storage and sends an email
notification to all Super Users.

If you do not understand what this means, please do not contact the authors of
the software. They are NOT sending you this email and they cannot help you.
Instead, please contact the person who built or manages your site.

================================================================================
WHO SENT ME THIS EMAIL?
================================================================================

This email is sent to you by your own site, [SITENAME]

ENDBODY;
		}

		$email_subject = Factory::getFilesystemTools()->replace_archive_name_variables($email_subject);
		$email_body    = Factory::getFilesystemTools()->replace_archive_name_variables($email_body);
		$email_body    = str_replace('[FAILEDLIST]', $failedReport, $email_body);

		foreach ($emails as $email)
		{
			$email = trim($email);

			if (empty($email))
			{
				continue;
			}

			try
			{
				$mailer = $this->container->mailer;
				$mailer->sendEmail($email, $email_subject, $email_body);
			}
			catch (Exception $e)
			{
			}
		}

		// Let's update the last time we check, so we will avoid to send
		// the same notification several times
		$this->updateLastCheck(true, 'akeeba_checkfailedupload');

		return [
			'message' => [
				sprintf(
					'Found %d failed upload(s)',
					count($failedUploads)
				),
				sprintf(
					"Sent %s notifications",
					count($emails)
				),
			],
			'result'  => false,
		];
	}

	protected function getProfileIDsWithPostProcEngines(): array
	{
		$db = $this->container->db;

		try
		{
			$query      = $db->getQuery(true)
				->select($db->qn('id'))
				->from($db->qn('#__ak_profiles'));
			$profileIDs = $db->setQuery($query)->loadColumn() ?: [];
		}
		catch (Exception $e)
		{
			return [];
		}

		$currentProfileId     = Platform::getInstance()->get_active_profile() ?: 1;
		$applicableProfileIDs = [];

		foreach ($profileIDs as $profileID)
		{
			try
			{
				Platform::getInstance()->load_configuration($profileID);
				$engine = Factory::getConfiguration()->get('akeeba.advanced.postproc_engine') ?: 'none';
			}
			catch (Exception $e)
			{
				continue;
			}

			if ($engine === 'none')
			{
				continue;
			}

			$applicableProfileIDs[] = $profileID;
		}

		Platform::getInstance()->load_configuration($currentProfileId);

		return $applicableProfileIDs;
	}

	private function updateLastCheck($exists, $tag = 'akeeba_checkfailed')
	{
		$db = $this->container->db;

		$now = Platform::getInstance()->get_timestamp_database();

		if ($exists)
		{
			$query = $db->getQuery(true)
				->update($db->qn('#__ak_storage'))
				->set($db->qn('lastupdate') . ' = ' . $db->q($now))
				->where($db->qn('tag') . ' = ' . $db->q($tag));
		}
		else
		{
			$query = $db->getQuery(true)
				->insert($db->qn('#__ak_storage'))
				->columns([$db->qn('tag'), $db->qn('lastupdate')])
				->values($db->q($tag) . ', ' . $db->q($now));
		}

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (Exception $exc)
		{

		}
	}

	private function getLastCheck($tag = 'akeeba_checkfailed')
	{
		$db = $this->container->db;

		$query = $db->getQuery(true)
			->select($db->qn('lastupdate'))
			->from($db->qn('#__ak_storage'))
			->where($db->qn('tag') . ' = ' . $db->q($tag));

		$datetime = $db->setQuery($query)->loadResult();

		if (!intval($datetime))
		{
			$datetime = $db->getNullDate();
		}

		return $datetime;
	}

}
