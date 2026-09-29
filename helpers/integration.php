<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Bootstrap file for Akeeba Solo for WordPress
use Akeeba\Engine\Platform as EnginePlatform;
use Awf\Application\Application;
use Awf\Container\Container;
use Awf\Input\Input;
use Composer\Autoload\ClassLoader;
use Composer\CaBundle\CaBundle;
use Solo\Application\AppConfig;
use Solo\Application\UserManager;
use Solo\Pythia\Oracle\Wordpress;

defined('AKEEBASOLO') or die;

global $akeebaBackupWordPressContainer;
global $akeebaBackupWordPressAutoloader;

/**
 * Some folks who don't understand how PHP works try to load WordPress' PHPMailer by doing a require_once either on
 * the legacy shim (wp-includes/class-phpmailer.php), or on the modern class files
 * (wp-includes/PHPMailer/PHPMailer.php and wp-includes/PHPMailer/Exception.php) WITHOUT wrapping them in
 * class_exists() check.
 *
 * This is problematic for many reasons:
 * - If another plugin (or the core) has loaded the OTHER kind of files, the require_once check will try to load the
 *   same class twice, breaking the execution environment.
 * - If another plugin has registered an autoloader with a different copy of PHPMailer, like we do, and a second 3PD
 *   plugin tried to use PHPMailer using a class_exists() check without setting the second parameter to false, they
 *   will try to load WordPress' copy of PHPMailer under the same namespace, breaking the execution environment.
 *
 * The CORRECT solution is the standard class_exists() safety check which has been common knowledge since the early
 * 2000s:
 *
 * if (!class_exists(PHPMailer\PHPMailer\PHPMailer::class, false)) {
 *     require_once ABSPATH . WPINC . '/class-phpmailer.php';
 * }
 *
 * The require_once MUST NOT BE USED as a code safety feature against double definition attempt of a class or f
 * unction. Code safety requires using class_exists() and function_exists().
 *
 * Some folks, like ThemeCatcher (makers of QuForm), apparently are strangers to decades-old PHP code safety standards
 * and will publish support pages accusing us for their own shitty code without having the decency to contact us
 * first.
 *
 * We can't fix stupid, but we CAN and DO work around it. The simple way we chose to deal with this particular brand
 * of stupid is by including WordPress' PHPMailer class before including our own autoloader. This means that you now
 * have WordPress' files loading PHPMailer in the require_once/include_once cache, so even when morons like those
 * people rely on this mechanism for code safety they won't manage to shoot their feet. Since the PHPMailer class is
 * loaded, our Composer autoloader won't even try to load our copy of PHPMailer UNLESS we are running outside of
 * WordPress, i.e. our CLI backup script which is why we have a copy of PHPMailer to begin with.
 *
 * The code is backward- and forward-compatible:
 * - On WordPress <= 5.4 it loads wp-includes/class-phpmailer.php which registers the legacy, non-namespaced PHPMailer
 *   class. On these versions of WordPress, our Composer autoloader WILL load our newer, namespaced version of
 *   PHPMailer without breaking core WordPress or 3PD plugins.
 * - On WordPress >= 5.5 it loads wp-includes/class-phpmailer.php which registers the new, namespaced PHPMailer class.
 *   On those versions of WordPress, our Composer autoloader WILL NOT load our copy of PHPMailer. Plugins loading
 *   directly either this legacy shim, or the modern class files, will NOT break.
 * - On future WordPress versions removing the legacy shim, the modern class files are included. On those versions of
 *   WordPress, again, our Composer autoloader WILL NOT load our copy of PHPMailer. Plugins loading directly the
 *   legacy shim WILL fail because that (future) versions of WordPress does not include the shim. Plugins loading
 *   directly the modern class files WILL NOT break.
 */
if (defined('ABSPATH') && defined('WPINC') && !class_exists(\PHPMailer\PHPMailer\PHPMailer::class, false))
{
	$legacyShimFilePath = ABSPATH . WPINC . '/class-phpmailer.php';
	$modernFile1 = ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
	$modernFile2 = ABSPATH . WPINC . '/PHPMailer/Exception.php';
	$hasErrorReporting = function_exists('error_reporting');

	if (file_exists($legacyShimFilePath))
	{
		if ($hasErrorReporting)
		{
			$oldErrorReporting = error_reporting(0);
		}

		require_once $legacyShimFilePath;

		if ($hasErrorReporting)
		{
			error_reporting($oldErrorReporting);
		}

	}
	elseif (file_exists($modernFile1) && file_exists($modernFile2))
	{
		require_once($modernFile1);
		require_once($modernFile2);
	}
}

// Load the constants and the Composer autoloader
defined('APATH_BASE') || require_once(__DIR__ . '/defines.php');
$akeebaBackupWordPressAutoloader   = include __DIR__ . '/../app/vendor/autoload.php';

if (!$akeebaBackupWordPressAutoloader instanceof ClassLoader)
{
	echo 'ERROR: Composer Autoloader not found' . PHP_EOL;

	exit(1);
}

defined('AKEEBA_CACERT_PEM') || define('AKEEBA_CACERT_PEM', CaBundle::getBundledCaBundlePath());

// Add PSR-4 overrides for the application namespace
call_user_func(
	function (ClassLoader $akeebaBackupWordPressAutoloader) {
		$prefixes     = $akeebaBackupWordPressAutoloader->getPrefixesPsr4();
		$soloPrefixes = array_filter(array_map('realpath', $prefixes['Solo\\'] ?? []));

		if (!in_array(__DIR__ . '/Solo', $prefixes['Solo\\'] ?? []))
		{
			$akeebaBackupWordPressAutoloader->setPsr4(
				'Solo\\', array_unique(
					array_merge(
						[
							realpath(__DIR__ . '/Solo'),
							realpath(__DIR__ . '/../app/Solo'),
						],
						$soloPrefixes
					)
				)
			);
		}
	}, $akeebaBackupWordPressAutoloader
);

// If we are not called from inside WordPress itself we will need to import its configuration
defined('WPINC')
|| call_user_func(
	function () {
		$foundWpConfig = false;

		$dirParts      = explode(DIRECTORY_SEPARATOR, __DIR__);
		$dirParts      = array_splice($dirParts, 0, -4);
		$filePath      = implode(DIRECTORY_SEPARATOR, $dirParts);
		$foundWpConfig = file_exists($filePath . '/wp-config.php');

		if (!$foundWpConfig)
		{
			$dirParts      = array_splice($dirParts, 0, -1);
			$altFilePath   = implode(DIRECTORY_SEPARATOR, $dirParts);
			$foundWpConfig = file_exists($altFilePath . '/wp-config.php');
		}

		if (!$foundWpConfig)
		{
			$possibleDirs = [getcwd()];

			if (isset($_SERVER['SCRIPT_FILENAME']))
			{
				$possibleDirs[] = dirname($_SERVER['SCRIPT_FILENAME']);
			}

			foreach ($possibleDirs as $scriptFolder)
			{
				// Can't use realpath() because in our dev environment it will resolve the symlinks outside the site root
				$dirParts = explode(DIRECTORY_SEPARATOR, $scriptFolder);

				$filePath = $scriptFolder;

				if (!is_file($filePath . '/wp-config.php'))
				{
					$filePath = implode(DIRECTORY_SEPARATOR, array_slice($dirParts, 0, -2));
				}

				if (!is_file($filePath . '/wp-config.php'))
				{
					$filePath = implode(DIRECTORY_SEPARATOR, array_slice($dirParts, 0, -3));
				}

				if (!is_file($filePath . '/wp-config.php'))
				{
					$filePath = implode(DIRECTORY_SEPARATOR, array_slice($dirParts, 0, -4));
				}

				if (!is_file($filePath . '/wp-config.php'))
				{
					$filePath = implode(DIRECTORY_SEPARATOR, array_slice($dirParts, 0, -5));
				}

				$foundWpConfig = file_exists($filePath . '/wp-config.php');

				if ($foundWpConfig)
				{
					$filePath = dirname(realpath($filePath . '/wp-config.php'));

					break;
				}
			}
		}

		$noWpConfig = (isset($_REQUEST) && isset($_REQUEST['no-wp-config']))
		              || (isset($argv) && in_array('--no-wp-config', $argv))
		              || @file_exists(__DIR__ . '/private/no-wp-config.txt')
		              || @file_exists(__DIR__ . '/private/wp-config.php');

		$oracle = new Wordpress($filePath);

		if ($noWpConfig)
		{
			$oracle->setLoadWPConfig(false);
		}

		if (!$oracle->isRecognised())
		{
			$filePath = realpath($filePath . '/..');
			$oracle   = new Wordpress($filePath);
		}

		if (!$oracle->isRecognised())
		{
			$curDir = __DIR__;
			echo <<< ENDTEXT
ERROR: Could not find wp-config.php

Technical information
--
integration.php directory	$curDir
filePath					$filePath
isRecognised				false
--

ENDTEXT;
			exit(1);
		}

		define('ABSPATH', $filePath);

		if (!defined('AKEEBABACKUPWP_PATH'))
		{
			$absPluginPath = realpath(__DIR__ . '/..');
			$absPluginPath = @is_dir($absPluginPath) ? $absPluginPath : ABSPATH . '/wp-content/plugins/akeebabackupwp';

			define('AKEEBABACKUPWP_PATH', ABSPATH . '/wp-content/plugins/akeebabackupwp');
		}

		$dbInfo = $oracle->getDbInformation();

		if (@file_exists(__DIR__ . '/private/wp-config.php'))
		{
			include_once __DIR__ . '/private/wp-config.php';
		}

		if (!defined('DB_NAME'))
		{
			define('DB_NAME', $dbInfo['name']);
		}
		if (!defined('DB_USER'))
		{
			define('DB_USER', $dbInfo['username']);
		}
		if (!defined('DB_PASSWORD'))
		{
			define('DB_PASSWORD', $dbInfo['password']);
		}
		if (!defined('DB_HOST'))
		{
			define('DB_HOST', $dbInfo['host']);
		}

		global $table_prefix;

		// Apply the table prefix only if it hasn't been already defined before (ie from our saved configuration file)
		$table_prefix = $table_prefix ?? ($dbInfo['prefix'] ?? '');

		// Also apply detected proxy settings
		if (!empty($dbInfo['proxy_host']) && !defined('WP_PROXY_HOST'))
		{
			define('WP_PROXY_HOST', $dbInfo['proxy_host']);
		}
		if (!empty($dbInfo['proxy_port']) && !defined('WP_PROXY_PORT'))
		{
			define('WP_PROXY_PORT', $dbInfo['proxy_port']);
		}
		if (!empty($dbInfo['proxy_user']) && !defined('WP_PROXY_USERNAME'))
		{
			define('WP_PROXY_USERNAME', $dbInfo['proxy_user']);
		}
		if (!empty($dbInfo['proxy_pass']) && !defined('WP_PROXY_PASSWORD'))
		{
			define('WP_PROXY_PASSWORD', $dbInfo['proxy_pass']);
		}
	}
);

// Should I enable debug?
if (defined('AKEEBADEBUG') && defined('AKEEBADEBUG_ERROR_DISPLAY'))
{
	error_reporting(E_ALL | E_NOTICE | E_DEPRECATED);
	ini_set('display_errors', 1);
}

// Create the Container
$akeebaBackupWordPressContainer = $akeebaBackupWordPressContainer ?? call_user_func(
	function () {
		try
		{
			if (!defined('AKEEBABACKUPWP_PATH'))
			{
				$absPluginPath = realpath(__DIR__ . '/..');
				$absPluginPath = @is_dir($absPluginPath) ? $absPluginPath
					: ABSPATH . '/wp-content/plugins/akeebabackupwp';

				define('AKEEBABACKUPWP_PATH', ABSPATH . '/wp-content/plugins/akeebabackupwp');
			}

			// Create objects
			return new \Solo\Container(
				[
					'appConfig'        => function (Container $c) {
						return new AppConfig($c);
					},
					'userManager'      => function (Container $c) {
						return new UserManager($c);
					},
					'input'            => function (Container $c) {
						// WordPress is always escaping the input. WTF!
						// See http://stackoverflow.com/questions/8949768/with-magic-quotes-disabled-why-does-php-wordpress-continue-to-auto-escape-my

						global $AKEEBABACKUPWP_REAL_REQUEST;

						if (isset($AKEEBABACKUPWP_REAL_REQUEST))
						{
							return new Input($AKEEBABACKUPWP_REAL_REQUEST, ['magicQuotesWorkaround' => true]);
						}
						elseif (defined('WPINC'))
						{
							$fakeRequest = array_map('stripslashes_deep', $_REQUEST);

							return new Input($fakeRequest, ['magicQuotesWorkaround' => true]);
						}
						else
						{
							return new Input();
						}
					},
					'application_name' => 'Solo',
					'filesystemBase'   => AKEEBABACKUPWP_PATH . '/app',
					'updateStreamURL'  => (defined('AKEEBABACKUP_PRO') && AKEEBABACKUP_PRO)
						? 'http://cdn.akeeba.com/updates/akeebabackupwp_pro.json'
						: 'http://cdn.akeeba.com/updates/akeebabackupwp_core.json',
					'changelogPath'    => AKEEBABACKUPWP_PATH . 'CHANGELOG.php',
				]
			);
		}
		catch (Exception $exc)
		{
			unset($akeebaBackupWordPressContainer);

			$filename = null;

			if (isset($application))
			{
				if ($application instanceof Application)
				{
					$template = $application->getTemplate();

					if (file_exists(APATH_THEMES . '/' . $template . '/error.php'))
					{
						$filename = APATH_THEMES . '/' . $template . '/error.php';
					}
				}
			}

			if (is_null($filename))
			{
				die($exc->getMessage());
			}

			include $filename;

			die;
		}
	}
);

// Workaround: you have entered a Download ID in the Core version. We have to update you to the Professional version.
call_user_func(
	function ($akeebaBackupWordPressContainer) {
		$downloadId = $akeebaBackupWordPressContainer->appConfig->get('options.update_dlid', '');

		if (!empty($downloadId))
		{
			$akeebaBackupWordPressContainer['updateStreamURL'] = 'http://cdn.akeeba.com/updates/backupwppro.ini';
		}
	}, $akeebaBackupWordPressContainer
);

call_user_func(function($akeebaBackupWordPressContainer) {
	// Include the Akeeba Engine and ALICE factories, if required
	if (defined('AKEEBAENGINE'))
	{
		return;
	}

	define('AKEEBAENGINE', 1);

	$factoryPath = __DIR__ . '/../app/vendor/akeeba/engine/engine/Factory.php';
	$alicePath   = __DIR__ . '/../app/Solo/alice/factory.php';

	// Load the engine
	if (!file_exists($factoryPath))
	{
		echo "ERROR!\n";
		echo "Could not load the backup engine; file does not exist. Technical information:\n";
		echo "Path to " . basename(__FILE__) . ": " . __DIR__ . "\n";
		echo "Path to factory file: $factoryPath\n";
		die("\n");
	}

	try
	{
		require_once $factoryPath;
	}
	catch (Exception $e)
	{
		echo "ERROR!\n";
		echo "Backup engine returned an error. Technical information:\n";
		echo "Error message:\n\n";
		echo $e->getMessage() . "\n\n";
		echo "Path to " . basename(__FILE__) . ":" . __DIR__ . "\n";
		echo "Path to factory file: $factoryPath\n";
		die("\n");
	}

	if (file_exists($alicePath))
	{
		require_once $alicePath;
	}

	EnginePlatform::addPlatform('Wordpress', __DIR__ . '/Platform/Wordpress');
	$platform = EnginePlatform::getInstance();

	$platform->setContainer($akeebaBackupWordPressContainer);
	$platform->load_version_defines();
	$platform->apply_quirk_definitions();
}, $akeebaBackupWordPressContainer);

return $akeebaBackupWordPressContainer;