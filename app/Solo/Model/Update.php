<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Model;

use Akeeba\Engine\Platform;
use Awf\Container\Container;
use Awf\Download\Download;
use Awf\Mvc\Model;
use Awf\Registry\Registry;
use Awf\Session\Exception;
use Awf\Text\Language;
use Awf\Text\Text;
use Awf\Uri\Uri;

class Update extends Model
{
	/** @var   string  The URL containing the INI update stream URL */
	protected $updateStreamURL = '';

	/** @var   Registry  A registry object holding the update information */
	protected $updateInfo = null;

	/** @var   string  The table where key-value information is stored */
	protected $tableName = '#__ak_storage';

	/** @var   string  The table field which stored the key of the key-value pairs */
	protected $keyField = 'tag';

	/** @var   string  The table field which stored the value of the key-value pairs */
	protected $valueField = 'data';

	/** @var   string  The key tag for the live update serialised information */
	protected $updateInfoTag = 'liveupdate';

	/** @var   string  The key tag for the last check timestamp */
	protected $lastCheckTag = 'liveupdate_lastcheck';

	/** @var   integer  The last update check UNIX timestamp */
	protected $lastCheck = null;

	/** @var   string   Currently installed version */
	protected $currentVersion = '';

	/** @var   string   Currently installed version's date stamp */
	protected $currentDateStamp = '';

	/** @var   string   Minimum stability for reporting updates */
	protected $minStability = 'alpha';

	protected $downloadId = '';

	/**
	 * How to determine if a new version is available.
	 *
	 * - `different` The version numbers are different
	 * - `vcompare` Latest version, based on `version_compare` checks between the two version numbers
	 * - `newest` Only checks the release dates; newer version wins, regardless of the version number
	 * - `smart` Latest version, or newest release date.
	 *
	 * @var   string
	 */
	protected $versionStrategy = 'smart';

	/**
	 * Update constructor.
	 *
	 * @param   Container  $container  The application container
	 */
	public function __construct(?\Awf\Container\Container $container = null, ?Language $language = null)
	{
		parent::__construct($container, $language);

		$this->currentVersion   = defined('AKEEBABACKUP_VERSION') ? AKEEBABACKUP_VERSION : 'dev';
		$this->currentDateStamp = defined('AKEEBABACKUP_DATE') ? AKEEBABACKUP_DATE : gmdate('Y-m-d');
		$this->minStability     = $this->container->appConfig->get('options.minstability', 'stable');
		$this->downloadId       = $this->container->appConfig->get('options.update_dlid', '');

		// Set the update stream URL
		$pro = AKEEBABACKUP_PRO ? 'pro' : 'core';

		// The `updateStreamURL` comes from the integration.php file
		$this->updateStreamURL =
			(isset($container['updateStreamURL']) && !empty($container['updateStreamURL']))
				? $container->updateStreamURL
				: 'http://cdn.akeeba.com/updates/solo' . $pro . '.json';

		// Testing updates in development versions: define AKEEBABACKUP_UPDATE_BASEURL in version.php
		if (defined('AKEEBABACKUP_UPDATE_BASEURL'))
		{
			$pro = AKEEBABACKUP_PRO ? 'pro' : 'core';

			$this->updateStreamURL = AKEEBABACKUP_UPDATE_BASEURL . $pro . '.json';
		}

		$this->load(false);
	}

	/**
	 * Load the update information into the $this->updateInfo object.
	 *
	 * The update source is `$this->updateStreamURL`, unless the file `APATH_BASE  . 'update.json'` is present.
	 *
	 * Update information is cached. If the information is found in the cache, it will be returned from the cache unless
	 * either of the following conditions is true:
	 * - The cache is expired.
	 * - The `$force` flag is set.
	 * - The file `APATH_BASE  . 'update.json'` is present.
	 *
	 * @param   bool  $force  True to force reload the information from the source.
	 *
	 * @return  void
	 */
	public function load($force = false)
	{
		// Clear the update information and last update check timestamp
		$this->lastCheck  = null;
		$this->updateInfo = null;

		// Get a reference to the database
		$db = $this->container->db;

		/**
		 * Override for automated testing
		 *
		 * If the file update.ini exists (next to version.php) force reloading the update information.
		 */
		$fileTestingUpdates = APATH_BASE . '/update.json';

		if (file_exists($fileTestingUpdates))
		{
			$force           = true;
			$this->lastCheck = 0;
		}
		elseif (!$force)
		{
			// Get the last update timestamp
			$query           = $db->getQuery(true)
				->select($db->qn($this->valueField))
				->from($db->qn($this->tableName))
				->where($db->qn($this->keyField) . '=' . $db->q($this->lastCheckTag));
			$this->lastCheck = $db->setQuery($query)->loadResult();

			if (is_null($this->lastCheck))
			{
				$this->lastCheck = 0;
			}

			// Force-reload the update information if it's older than 6 hours
			$force = abs(time() - $this->lastCheck) >= 21600;
		}

		// Try to load from cache
		if (!$force)
		{
			$query   = $db->getQuery(true)
				->select($db->qn($this->valueField))
				->from($db->qn($this->tableName))
				->where($db->qn($this->keyField) . '=' . $db->q($this->updateInfoTag));
			$rawInfo = $db->setQuery($query)->loadResult();

			if (empty($rawInfo))
			{
				$force = true;
			}
			else
			{
				$this->updateInfo = new Registry();
				$this->updateInfo->loadString($rawInfo, 'JSON');
			}
		}

		// If it's stuck, and we are not forcibly retrying to reload, bail out
		if (!$force && !empty($this->updateInfo) && $this->updateInfo->get('stuck', false))
		{
			return;
		}

		// Maybe we are forced to load from a URL?
		// NOTE: DO NOT MERGE WITH PREVIOUS IF AS THE $force VARIABLE MAY BE MODIFIED THERE!
		if ($force)
		{
			$this->updateInfo = new Registry();
			$this->updateInfo->set('stuck', 1);
			$this->lastCheck = time();

			// Store last update check timestamp
			$this->replaceCommonStorageObject(
				(object) [
					$this->keyField   => $this->lastCheckTag,
					$this->valueField => $this->lastCheck,
				]
			);

			// Store update information
			$this->replaceCommonStorageObject(
				(object) [
					$this->keyField   => $this->updateInfoTag,
					$this->valueField => $this->updateInfo->toString('JSON'),
				]
			);

			// Simulate a PHP crash for automated testing
			if (defined('AKEEBA_TESTS_SIMULATE_STUCK_UPDATE') && AKEEBA_TESTS_SIMULATE_STUCK_UPDATE)
			{
				die(
				sprintf(
					'<p id="automated-testing-simulated-crash">This is a simulated crash for automated testing.</p></p>If you are seeing this outside of an automated testing scenario, please delete the line <code>define(\'AKEEBA_TESTS_SIMULATE_STUCK_UPDATE\', 1);</code> from the %s\version.php file</p>',
					APATH_BASE
				)
				);
			}

			// Try to fetch the update information
			$updateInformation = $this->fetchUpdates();

			if ($updateInformation === null)
			{
				// We are stuck. Darn it!
				return;
			}

			if (is_object($updateInformation))
			{
				$this->updateInfo->loadObject($updateInformation);
			}

			$this->updateInfo->set('loadedUpdate', ($updateInformation !== false) ? 1 : 0);
			$this->updateInfo->set('stuck', 0);

			// Determine the version stability if it was not provided
			$version   = $this->updateInfo->get('version', '');
			$stability = $this->updateInfo->get('maturity', '');
			if (
				!$this->updateInfo->get('stuck', 0)
				&& $this->updateInfo->get('loadedUpdate', 0)
				&& !empty($version)
				&& empty($stability)
			)
			{
				$this->updateInfo->set('maturity', $this->getStability($version));
			}

			// Since we had to load from a URL, commit the update information to db
			$this->replaceCommonStorageObject(
				(object) [
					$this->keyField   => $this->updateInfoTag,
					$this->valueField => $this->updateInfo->toString('JSON'),
				]
			);
		}

		// Check if an update is available and push it to the update information registry
		$this->updateInfo->set('hasUpdate', $this->hasUpdate());

		// Post-process the download URL, appending the Download ID (if defined)
		$link = $this->updateInfo->get('download', '');

		if (!empty($link) && !empty($this->downloadId))
		{
			$link = new Uri($link);
			$link->setVar('dlid', $this->downloadId);
			$this->updateInfo->set('download', $link->toString());
		}
	}

	/**
	 * Is there an update available?
	 *
	 * @return  bool
	 */
	public function hasUpdate()
	{
		if (!$this->updateInfo->get('loadedUpdate', 0))
		{
			return false;
		}

		$this->updateInfo->set('minstabilityMatch', 1);
		$this->updateInfo->set('platformMatch', 0);

		// Get the update information as an object
		$tempObject = $this->updateInfo->toObject();

		// Validate the minimum stability
		$this->updateInfo->set('minstabilityMatch', $this->filterStability($tempObject) ? 1 : 0);

		// Validate the platform compatibility
		$this->updateInfo->set('platformMatch', 1);
//		$this->updateInfo->set(
//			'platformMatch',
//			$this->filterPhpVersion($tempObject) && $this->filterPlatformVersion($tempObject)
//				? 1 : 0
//		);

		// A Core version with a Download ID entered will always show an update available
		if (!AKEEBABACKUP_PRO && !empty($this->downloadId))
		{
			return true;
		}

		// Apply the version strategy filter
		return $this->filterVersion($tempObject);
	}

	/**
	 * Returns the update information
	 *
	 * @param   bool  $force  Should we force the fetch of new information?
	 *
	 * @return \Awf\Registry\Registry
	 */
	public function getUpdateInformation(bool $force = false): ?Registry
	{
		if (is_null($this->updateInfo))
		{
			$this->load($force);
		}

		return $this->updateInfo;
	}

	/**
	 * Try to prepare a world-writeable update.zip file in the temporary directory, or throw an exception if it's not
	 * possible.
	 *
	 * @return  void
	 *
	 * @throws  \Exception
	 */
	public function prepareDownload()
	{
		$tmpDir  = defined('AKEEBA_TESTS_UPDATE_TEMP_FOLDER') ? AKEEBA_TESTS_UPDATE_TEMP_FOLDER
			: $this->container['temporaryPath'];
		$tmpFile = $tmpDir . '/update.zip';

		$fs = $this->container->fileSystem;

		if (!is_dir($tmpDir))
		{
			throw new \Exception(Text::sprintf('SOLO_UPDATE_ERR_DOWNLOAD_INVALIDTMPDIR', $tmpDir), 500);
		}

		$fs->delete($tmpFile);

		$fp = @fopen($tmpFile, 'w');

		if ($fp === false)
		{
			$nada = '';
			$fs->write($tmpFile, $nada);
		}
		else
		{
			@fclose($fp);
		}

		$fs->chmod($tmpFile, 0777);
	}

	/**
	 * Step through the download of the update archive.
	 *
	 * If the file APATH_BASE  . 'update.zip' file is present it is used instead (and removed immediately).
	 *
	 * @param   bool  $staggered  Should I try a staggered (multi-step) download? Default is true.
	 *
	 * @return  array  A return array giving the status of the staggered download
	 */

	public function stepDownload($staggered = true)
	{
		$this->load();

		// The restore script expects to find the update inside the temp directory
		$tmpDir        = defined('AKEEBA_TESTS_UPDATE_TEMP_FOLDER') ? AKEEBA_TESTS_UPDATE_TEMP_FOLDER
			: $this->container['temporaryPath'];
		$tmpDir        = rtrim($tmpDir, '/\\');
		$localFilename = $tmpDir . '/update.zip';

		/**
		 * Override for automated testing
		 *
		 * If the file APATH_BASE  . 'update.zip' file is present it is used instead (and removed immediately).
		 */
		$fileOverride = APATH_BASE . 'update.zip';

		if (is_file($fileOverride))
		{
			$size = filesize($localFilename);
			$frag = $this->getState('frag', 0);
			$frag++;

			$ret = [
				"status"    => true,
				"error"     => '',
				"frag"      => $frag,
				"totalSize" => $size,
				"doneSize"  => $size,
				"percent"   => 100,
				"errorCode" => 0,
			];

			// Fake stepped download: frag 1 causes 1 second delay, frag 2 moves the file
			switch ($frag)
			{
				case 0:
					sleep(1);
					$ret['doneSize'] = (int) ($size / 2);
					$ret['percent']  = 50;
					$this->setState('frag', $frag);

					break;

				default:
					$this->setState('frag', null);
					$this->container->fileSystem->move($fileOverride, $localFilename);

					break;
			}

			// Special case for automated tests: if the file is 0 bytes we will just throw an error :)
			if ($size == 0)
			{
				$retArray['status']    = false;
				$retArray['error']     = Text::sprintf(
					'AWF_DOWNLOAD_ERR_LIB_COULDNOTDOWNLOADFROMURL', '@test_override_file@'
				);
				$retArray['errorCode'] = 500;
				$this->container->fileSystem->delete($fileOverride);
			}

			return $ret;
		}

		/**
		 * Back to our regular code. Set up the file import parameters.
		 */
		$params = [
			'file'          => $this->updateInfo->get('download', ''),
			'frag'          => $this->getState('frag', -1),
			'totalSize'     => $this->getState('totalSize', -1),
			'doneSize'      => $this->getState('doneSize', -1),
			'localFilename' => $localFilename,
		];

		$download = new Download($this->container);

		if ($staggered)
		{
			// importFromURL expects the remote URL in the 'url' index
			$params['url'] = $params['file'];
			$retArray      = $download->importFromURL($params);

			// Better it
			unset($params['url']);
		}
		else
		{
			$retArray = [
				"status"    => true,
				"error"     => '',
				"frag"      => 1,
				"totalSize" => 0,
				"doneSize"  => 0,
				"percent"   => 0,
				"errorCode" => 0,
			];

			try
			{
				$result = $download->getFromURL($params['file']);

				if ($result === false)
				{
					throw new Exception(
						Text::sprintf('AWF_DOWNLOAD_ERR_LIB_COULDNOTDOWNLOADFROMURL', $params['file']), 500
					);
				}

				$tmpDir        = APATH_ROOT . '/tmp';
				$tmpDir        = rtrim($tmpDir, '/\\');
				$localFilename = $tmpDir . '/update.zip';

				$fs = $this->container->fileSystem;

				$fs->write($localFilename, $result);

				$retArray['status']    = true;
				$retArray['totalSize'] = strlen($result);
				$retArray['doneSize']  = $retArray['totalSize'];
				$retArray['percent']   = 100;
			}
			catch (\Exception $e)
			{
				$retArray['status']    = false;
				$retArray['error']     = $e->getMessage();
				$retArray['errorCode'] = $e->getCode();
			}
		}

		return $retArray;
	}

	/**
	 * Creates the restoration.ini file which is used during the update package's extraction. This file tells Akeeba
	 * Restore which package to read and where and how to extract it.
	 *
	 * @return  bool  True on success
	 */
	public function createRestorationINI()
	{
		// Get a password
		$password = base64_encode(random_bytes(32));

		$fs = $this->container->fileSystem;

		$this->setState('update_password', $password);

		// Also save the update_password in the session, we'll need it if this page is reloaded
		$this->container->segment->set('update_password', $password);

		// Get the absolute path to site's root
		$siteRoot = (isset($this->container['filesystemBase'])) ? $this->container['filesystemBase'] : APATH_BASE;
		$siteRoot = str_replace('\\', '/', $siteRoot);
		$siteRoot = str_replace('//', '/', $siteRoot);

		// On WordPress we need to go one level up
		if (defined('WPINC'))
		{
			$parts = explode('/', $siteRoot);
			array_pop($parts);
			$siteRoot = implode('/', $parts);
		}

		$tempdir = $this->container['temporaryPath'];

		$data = "<?php\ndefined('_AKEEBA_RESTORATION') or die();\n";
		$data .= '$restoration_setup = array(' . "\n";

		$ftpOptions = $this->getFTPOptions();
		$engine     = $ftpOptions['enable'] ? 'hybrid' : 'direct';
		$dryRun     = defined('AKEEBABACKUP_UPDATE_DRYRUN') ? '1' : '0';
		$destDir    = defined('AKEEBABACKUP_UPDATE_DRYRUN') ? $tempdir : $siteRoot;

		$data .= <<<ENDDATA
	'kickstart.security.password' => '$password',
	'kickstart.tuning.max_exec_time' => '5',
	'kickstart.tuning.run_time_bias' => '75',
	'kickstart.tuning.min_exec_time' => '0',
	'kickstart.procengine' => '$engine',
	'kickstart.setup.sourcefile' => '{$tempdir}/update.zip',
	'kickstart.setup.destdir' => '$destDir',
	'kickstart.setup.restoreperms' => '0',
	'kickstart.setup.filetype' => 'zip',
	'kickstart.setup.dryrun' => '$dryRun',
ENDDATA;

		// On WordPress we need to remove the akeebabackupwp prefix from the package
		if (defined('WPINC'))
		{
			$data .= "\n\t'kickstart.setup.removepath' => 'akeebabackupwp',\n";
		}

		if ($ftpOptions['enable'])
		{
			// Is the tempdir really writable?
			$writable = @is_writeable($tempdir);

			if ($writable)
			{
				// Let's be REALLY sure
				$fp = @fopen($tempdir . '/test.txt', 'w');
				if ($fp === false)
				{
					$writable = false;
				}
				else
				{
					fclose($fp);
					unlink($tempdir . '/test.txt');
				}
			}

			// If the tempdir is not writable, create a new writable subdirectory
			if (!$writable)
			{
				$newTemp = APATH_BASE . '/tmp/update_tmp';
				$fs->mkdir($newTemp, 0777);

				$tempdir = $newTemp;
			}

			// If we still have no writable directory, we'll try /tmp and the system's temp-directory
			$writable = @is_writeable($tempdir);

			if (!$writable && function_exists('sys_get_temp_dir'))
			{
				$tempdir = sys_get_temp_dir();
			}

			$data .= <<<ENDDATA
	'kickstart.ftp.ssl' => '0',
	'kickstart.ftp.passive' => '1',
	'kickstart.ftp.host' => '{$ftpOptions['host']}',
	'kickstart.ftp.port' => '{$ftpOptions['port']}',
	'kickstart.ftp.user' => '{$ftpOptions['user']}',
	'kickstart.ftp.pass' => '{$ftpOptions['pass']}',
	'kickstart.ftp.dir' => '{$ftpOptions['root']}',
	'kickstart.ftp.tempdir' => '$tempdir',
ENDDATA;
		}

		$data .= ');';


		$configPath = $siteRoot . '/restoration.php';

		if (defined('WPINC'))
		{
			$configPath = $siteRoot . '/app/restoration.php';
		}

		clearstatcache(true, $configPath);

		// Remove the old file, if it's there...
		if (file_exists($configPath))
		{
			$fs->delete($configPath);
		}

		// Write the new file
		$fs->write($configPath, $data);

		// Clear opcode caches for the generated .php file
		if (function_exists('opcache_invalidate'))
		{
			opcache_invalidate($configPath, true);
		}

		if (function_exists('apc_compile_file'))
		{
			apc_compile_file($configPath);
		}

		if (function_exists('wincache_refresh_if_changed'))
		{
			wincache_refresh_if_changed([$configPath]);
		}

		if (function_exists('xcache_asm'))
		{
			xcache_asm($configPath);
		}

		return true;
	}

	/**
	 * Returns an array with the configured FTP options
	 *
	 * @return  array
	 */
	public function getFTPOptions()
	{
		// Initialise from Joomla! Global Configuration
		$config = $this->container->appConfig;

		$retArray = [
			'enable'  => $config->get('fs.driver', 'file') == 'ftp',
			'host'    => $config->get('fs.host', 'localhost'),
			'port'    => $config->get('fs.port', '21'),
			'user'    => $config->get('fs.username', ''),
			'pass'    => $config->get('fs.password', ''),
			'root'    => $config->get('fs.directory', ''),
			'tempdir' => APATH_BASE . '/tmp',
		];

		return $retArray;
	}

	/**
	 * Finalises the update. Reserved for future use. DO NOT REMOVE.
	 */
	public function finalise()
	{
		// Clear the compiled templates
		$tmp = $this->container['temporaryPath'] . '/compiled_templates';
		$this->container->fileSystem->rmdir($tmp, true);
	}

	/**
	 * Get the currently used update stream URL
	 *
	 * @return string
	 */
	public function getUpdateStreamURL()
	{
		return $this->updateStreamURL;
	}

	/**
	 * Normalise the version number to a PHP-format version string.
	 *
	 * @param   string  $version  The whatever-format version number
	 *
	 * @return  string  A standard formatted version number
	 */
	public function sanitiseVersion($version)
	{
		$test                   = strtolower($version);
		$alphaQualifierPosition = strpos($test, 'alpha-');
		$betaQualifierPosition  = strpos($test, 'beta-');
		$betaQualifierPosition2 = strpos($test, '-beta');
		$rcQualifierPosition    = strpos($test, 'rc-');
		$rcQualifierPosition2   = strpos($test, '-rc');
		$rcQualifierPosition3   = strpos($test, 'rc');
		$devQualifiedPosition   = strpos($test, 'dev');

		if ($alphaQualifierPosition !== false)
		{
			$betaRevision = substr($test, $alphaQualifierPosition + 6);
			if (!$betaRevision)
			{
				$betaRevision = 1;
			}
			$test = substr($test, 0, $alphaQualifierPosition) . '.a' . $betaRevision;
		}
		elseif ($betaQualifierPosition !== false)
		{
			$betaRevision = substr($test, $betaQualifierPosition + 5);
			if (!$betaRevision)
			{
				$betaRevision = 1;
			}
			$test = substr($test, 0, $betaQualifierPosition) . '.b' . $betaRevision;
		}
		elseif ($betaQualifierPosition2 !== false)
		{
			$betaRevision = substr($test, $betaQualifierPosition2 + 5);

			if (!$betaRevision)
			{
				$betaRevision = 1;
			}

			$test = substr($test, 0, $betaQualifierPosition2) . '.b' . $betaRevision;
		}
		elseif ($rcQualifierPosition !== false)
		{
			$betaRevision = substr($test, $rcQualifierPosition + 5);
			if (!$betaRevision)
			{
				$betaRevision = 1;
			}
			$test = substr($test, 0, $rcQualifierPosition) . '.rc' . $betaRevision;
		}
		elseif ($rcQualifierPosition2 !== false)
		{
			$betaRevision = substr($test, $rcQualifierPosition2 + 3);

			if (!$betaRevision)
			{
				$betaRevision = 1;
			}

			$test = substr($test, 0, $rcQualifierPosition2) . '.rc' . $betaRevision;
		}
		elseif ($rcQualifierPosition3 !== false)
		{
			$betaRevision = substr($test, $rcQualifierPosition3 + 5);

			if (!$betaRevision)
			{
				$betaRevision = 1;
			}

			$test = substr($test, 0, $rcQualifierPosition3) . '.rc' . $betaRevision;
		}
		elseif ($devQualifiedPosition !== false)
		{
			$betaRevision = substr($test, $devQualifiedPosition + 6);
			if (!$betaRevision)
			{
				$betaRevision = '';
			}
			$test = substr($test, 0, $devQualifiedPosition) . '.dev' . $betaRevision;
		}

		return $test;
	}

	public function getStability($version)
	{
		$versionParts    = explode('.', $version);
		$lastVersionPart = array_pop($versionParts);

		if (substr($lastVersionPart, 0, 1) == 'a')
		{
			return 'alpha';
		}

		if (substr($lastVersionPart, 0, 1) == 'b')
		{
			return 'beta';
		}

		if (substr($lastVersionPart, 0, 2) == 'rc')
		{
			return 'rc';
		}

		if (substr($lastVersionPart, 0, 3) == 'dev')
		{
			return 'alpha';
		}

		return 'stable';
	}

	/**
	 * Checks if there is an update taking into account only the release date. If the release date is the same then it
	 * takes into account the version.
	 *
	 * @param   string  $version
	 * @param   string  $date
	 *
	 * @return  bool
	 */
	private function hasUpdateByNewest($version, $date)
	{
		if (empty($this->currentDateStamp))
		{
			$mine = $this->container->dateFactory('2000-01-01 00:00:00');
		}
		else
		{
			try
			{
				$mine = $this->container->dateFactory($this->currentDateStamp);
			}
			catch (\Exception $e)
			{
				$mine = $this->container->dateFactory('2000-01-01 00:00:00');
			}
		}

		$theirs = $this->container->dateFactory($date);

		/**
		 * Do we have the same time? This happens when we release two versions in the same day. In such cases we have to
		 * check vs the version number.
		 */
		if ($mine->toUnix() == $theirs->toUnix())
		{
			return $this->hasUpdateByVersion($version, $date);
		}

		return ($theirs->toUnix() > $mine->toUnix());
	}

	/**
	 * Checks if there is an update by comparing the version numbers using version_compare()
	 *
	 * @param   string  $version
	 * @param   string  $date
	 *
	 * @return  bool
	 */
	private function hasUpdateByVersion($version, $date)
	{
		$mine = $this->currentVersion;

		if (empty($mine))
		{
			$mine = '0.0.0';
		}

		if (empty($version))
		{
			$version = '0.0.0';
		}

		return version_compare($version, $mine, 'gt');
	}

	/**
	 * Checks if there is an update by looking for a different version number
	 *
	 * @param   string  $version
	 *
	 * @return  bool
	 */
	private function hasUpdateByDifferentVersion($version, $date)
	{
		$mine = $this->currentVersion;

		if (empty($mine))
		{
			$mine = '0.0.0';
		}

		if (empty($version))
		{
			$version = '0.0.0';
		}

		return version_compare($version, $mine, 'gt');
	}

	private function hasUpdateByDateAndVersion($version, $date)
	{
		$isCurrentDev = in_array(substr($this->currentVersion, 0, 3), ['dev', 'rev']);
		$isUpdateDev  = in_array(substr($version, 0, 3), ['dev', 'rev']);

		// Development (rev*) to numbered version; numbered to development; or development to development: use the date
		if ($isCurrentDev || $isUpdateDev)
		{
			return $this->hasUpdateByNewest($version, $date);
		}

		// Identical version number? Use the date
		if ($version == $this->currentVersion)
		{
			return $this->hasUpdateByNewest($version, $date);
		}

		// Otherwise only by version number
		return $this->hasUpdateByVersion($version, $date);
	}

	/**
	 * Store information in the `#__akeeba_common` table.
	 *
	 * @param   object  $o  The object to store
	 *
	 * @return  void
	 * @since   8.1.0
	 */
	private function replaceCommonStorageObject(object $o)
	{
		$db = $this->container->db;

		// Try to start a transaction. This may fail if someone forced our table to by MyISAM.
		try
		{
			$hasTransaction = true;
			$db->transactionStart();
		}
		catch (\Exception $e)
		{
			$hasTransaction = false;
		}

		try
		{
			$query = $db->getQuery(true)
				->delete($db->quoteName($this->tableName))
				->where($db->quoteName($this->keyField) . ' = ' . $db->quote($o->{$this->keyField}));
			$db->setQuery($query)->execute();

			$db->insertObject($this->tableName, $o, $this->keyField);

			$isDone = true;
		}
		catch (\Throwable $e)
		{
			$isDone = false;
		}

		// If we could not run the insert just give up
		if (!$isDone)
		{
			return;
		}

		// If I have a transaction open, commit it.
		if ($hasTransaction)
		{
			try
			{
				$db->transactionCommit();
			}
			catch (\Exception $e)
			{
				// Well, the DB server couldn't commit the transaction. Give up.
			}
		}
	}


	/**
	 * Fetch the updates from the external source.
	 *
	 * @return  object|false|null  NULL stuck updates. FALSE could not retrieve updates. Otherwise, best match update
	 *                             object
	 * @since   8.1.0
	 */
	private function fetchUpdates()
	{
		// First, check for the existence of the file used in automated testing
		$fileTestingUpdates = APATH_BASE . '/update.json';

		if (is_file($fileTestingUpdates))
		{
			$rawInfo = @file_get_contents($fileTestingUpdates);
			$this->container->fileSystem->delete($fileTestingUpdates);

			try
			{
				return json_decode($rawInfo, false, 512, JSON_THROW_ON_ERROR) ?: new \stdClass();
			}
			catch (\JsonException $e)
			{
				return new \stdClass();
			}
		}

		$options     = [];
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

		$download = new Download($this->container);
		$download->setAdapterOptions($options);

		$rawInfo = $download->getFromURL($this->updateStreamURL);

		if ($rawInfo === false)
		{
			return $rawInfo;
		}

		try
		{
			$versionsList = json_decode($rawInfo, false, 512, JSON_THROW_ON_ERROR);
		}
		catch (\JsonException $e)
		{
			return null;
		}

		// Let's get the latest update (highest available version, whatever it may be)
		$latestVersion = array_reduce(
			$versionsList,
			fn(?object $carry, object $item) => ($carry === null)
				? $item
				: (version_compare($item->version, $carry->version, 'gt') ? $item : $carry),
			null
		);

		// Filter by PHP
		// $versionsList = array_filter($versionsList, [$this, 'filterPhpVersion']);

		// Filter by platform (only on WordPress and ClassicPress)
		// $versionsList = array_filter($versionsList, [$this, 'filterPlatformVersion']);

		// Filter by version (must be newer or equal to myself)
		$versionsList = array_filter($versionsList, [$this, 'filterVersion']);

		// Filter by stability (must be at least as stable as the minimum requested stability)
		$versionsList = array_filter($versionsList, [$this, 'filterStability']);

		// No updates left? Keep the latest update, whatever it is.
		if (empty($versionsList))
		{
			return $latestVersion;
		}

		// Otherwise, return the newest version
		return array_reduce(
			$versionsList,
			fn(?object $carry, object $item) => ($carry === null)
				? $item
				: (version_compare($item->version, $carry->version, 'gt') ? $item : $carry),
			null
		);
	}

	private function filterPlatformVersion(object $item): bool
	{
		if (!defined('ABSPATH'))
		{
			return true;
		}

		$platform       = function_exists('classicpress_version') ? 'classicpress' : 'wordpress';
		$currentVersion = $this->container->segment->get('platformVersionForUpdates', '0.0');

		if ($currentVersion === '0.0' && function_exists('get_bloginfo'))
		{
			try
			{
				$currentVersion = get_bloginfo('version');
			}
			catch (\Throwable $e)
			{
				$currentVersion = '0.0';
			}
		}

		if ($currentVersion === '0.0')
		{
			return true;
		}

		if (!isset($item->platforms) || empty($item->platforms)
		    || !isset($item->platforms->{$platform})
		    || empty($item->platforms->{$platform})
		    || !is_array($item->platforms->{$platform})
		)
		{
			return false;
		}

		foreach ($item->platforms->{$platform} as $version)
		{
			if (substr($version, -1) === '+')
			{
				if (version_compare($currentVersion, substr($version, 0, -1), 'ge'))
				{
					return true;
				}

				continue;
			}

			if (strpos($currentVersion, $version . '.') === 0)
			{
				return true;
			}
		}

		return false;
	}

	private function filterStability(object $item): bool
	{
		switch ($this->minStability)
		{
			case 'alpha':
				return in_array($item->maturity, ['stable', 'rc', 'beta', 'alpha']);

			case 'beta':
				return in_array($item->maturity, ['stable', 'rc', 'beta']);

			case 'rc':
				return in_array($item->maturity, ['stable', 'rc']);

			default:
				return $item->maturity === 'stable';
		}
	}

	private function filterPhpVersion(object $item): bool
	{
		$inCMS  = $this->getContainer()->segment->get('insideCMS', false)
			|| defined('WPINC') || defined('ABSPATH');

		if ($inCMS)
		{
			return true;
		}

		if ($this->container->appConfig->get('options.no_php_version_check', 0))
		{
			return true;
		}

		$testAgainst  = $this->getPhpVersionToCheckAgainst();
		$parts        = explode('.', $testAgainst);
		$majorVersion = $parts[0] ?? 0;
		$minorVersion = $parts[1] ?? '0';

		if ($majorVersion === 0 || intval($majorVersion) != $majorVersion)
		{
			$majorVersion = 8;
			$minorVersion = 0;
		}

		return in_array($majorVersion . '.' . $minorVersion, $item->php ?: []);
	}

	/**
	 * Which installed PHP version to check against?
	 *
	 * If “Do not check minimum supported PHP version” is enabled, we return a ridiculously high version, effectively
	 * disabling the check.
	 *
	 * If it's the standalone application, we return the current version.
	 *
	 * If it's Akeeba Backup for WordPress we return the highest of the following versions:
	 * — The currently installed plugin's minimum supported PHP version.
	 * — The PHP version we are running under.
	 * — The highest PHP version we have ever seen.
	 *
	 * This is a deliberate choice when running under WordPress. Some sites have CLI scripts running under obsolete
	 * versions of PHP which are too old for our software. Since updates are retrieved under these versions, the
	 * update detected is incompatible with that version, therefore it's cached as incompatible.
	 *
	 * When the user views the update information on the web, under a modern PHP version, they don't understand why
	 * their latest and greatest PHP version is not supported and reported as too old.
	 *
	 * @return  string
	 * @since   8.2.5
	 */
	private function getPhpVersionToCheckAgainst()
	{
		$inCMS      = $this->container->segment->get('insideCMS', false);
		$hasWPClass = class_exists(\AkeebaBackupWP::class);

		if (!$inCMS && !$hasWPClass)
		{
			return PHP_VERSION;
		}

		$possibleVersions = [
			\AkeebaBackupWP::$minimumPHP,
			PHP_VERSION,
			$this->container->appConfig->get('options.greatest_php_version_seen', '0.0.0') ?: '0.0.0',
		];

		return array_reduce(
			$possibleVersions,
			function ($carry, $item) {
				return @version_compare($item, $carry, 'gt') ? $item : $carry;
			},
			'0.0.0'
		);
	}

	private function filterVersion(object $item): bool
	{
		// Apply the version strategy
		$version = $item->version ?? null;
		$date    = $item->date ?? null;

		switch ($this->versionStrategy)
		{
			case 'newest':
				return !empty($date) && $this->hasUpdateByNewest($version, $date);

			case 'vcompare':
				return !empty($version) && $this->hasUpdateByVersion($version, $date);

			case 'different':
				return !empty($version) && $this->hasUpdateByDifferentVersion($version, $date);

			case 'smart':
			default:
				return !empty($date) && !empty($version) && $this->hasUpdateByDateAndVersion($version, $date);
		}
	}
}
