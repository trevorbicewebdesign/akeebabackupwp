<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * DO NOT RUN THIS FILE DIRECTLY.
 *
 * This is the common code for all web and CLI entry points. It loads the Composer dependencies, brings up the
 * application container, and initialises the Akeeba Engine.
 */

use Akeeba\Engine\Factory;
use Akeeba\Engine\Platform;
use Composer\CaBundle\CaBundle;
use Solo\Container;

defined('AKEEBASOLO') || die;

// Handle debug display
call_user_func(
	function () {
		if (defined('AKEEBADEBUG'))
		{
			define('AKEEBADEBUG_ERROR_DISPLAY', 1);
		}

		if (defined('AKEEBADEBUG') && defined('AKEEBADEBUG_ERROR_DISPLAY'))
		{
			error_reporting(E_ALL | E_NOTICE | E_DEPRECATED);
			ini_set('display_errors', 1);
		}
	}
);

// Default timezone fix (CLI only)
call_user_func(
	function () {
		if (array_key_exists('REQUEST_METHOD', $_SERVER))
		{
			return;
		}

		if (!function_exists('date_default_timezone_get') || !function_exists('date_default_timezone_set'))
		{
			return;
		}

		if (function_exists('error_reporting'))
		{
			$oldLevel = error_reporting(0);
		}

		$serverTimezone = @date_default_timezone_get();

		if (empty($serverTimezone) || !is_string($serverTimezone))
		{
			$serverTimezone = 'UTC';
		}

		if (function_exists('error_reporting'))
		{
			error_reporting($oldLevel);
		}

		@date_default_timezone_set($serverTimezone);
	}
);

// Include the Composer autoloader. This also does a minimum PHP version check.
call_user_func(
	function () {
		if (function_exists('opcache_invalidate'))
		{
			@opcache_invalidate(__DIR__ . '/vendor/autoload.php', true);
			@opcache_invalidate(__DIR__ . '/vendor/composer/autoload_classmap.php', true);
			@opcache_invalidate(__DIR__ . '/vendor/composer/autoload_namespaces.php', true);
			@opcache_invalidate(__DIR__ . '/vendor/composer/autoload_psr4.php', true);
			@opcache_invalidate(__DIR__ . '/vendor/composer/autoload_real.php', true);
			@opcache_invalidate(__DIR__ . '/vendor/composer/autoload_static.php', true);
		}

		if (false === include __DIR__ . '/vendor/autoload.php')
		{
			echo 'ERROR: Composer Autoloader not found' . PHP_EOL;

			exit(1);
		}
	}
);

// Local Debugging — includes overrides.php
call_user_func(function () {
	$overridesFile = __DIR__ . '/../overrides.php';

	if (!@file_exists($overridesFile) || !@is_file($overridesFile) || !@is_readable($overridesFile))
	{
		return;
	}

	// On the web, we check for a local test site
	if (array_key_exists('REQUEST_METHOD', $_SERVER))
	{
		if (strpos($_SERVER['HTTP_HOST'] ?? '', '.local.web') === false)
		{
			return;
		}
	}
	// On CLI, we check for the AKEEBA_LOCAL_DEBUG environment variable
	elseif (!($_SERVER['AKEEBA_LOCAL_DEBUG'] ?? false))
	{
		return;
	}

	include $overridesFile;
});

// Load the platform constants (defines.php)
if (!defined('APATH_BASE'))
{
	require_once __DIR__ . '/defines.php';
}

// Load the integration script, if necessary
global $akeebaSoloContainer;

$akeebaSoloContainer = call_user_func(
	function () {
		global $argv;

		$isWeb = array_key_exists('REQUEST_METHOD', $_SERVER);
		$dirParts = [];

		if ($isWeb)
		{
			if (isset($_SERVER['SCRIPT_FILENAME']))
			{
				$scriptFilename = $_SERVER['SCRIPT_FILENAME'];

				if (substr(PHP_OS, 0, 3) == 'WIN')
				{
					$scriptFilename = str_replace('\\', '/', $scriptFilename);

					if (substr($scriptFilename, 0, 2) == '//')
					{
						$scriptFilename = '\\' . substr($scriptFilename, 2);
					}
				}

				$dirParts = explode('/', $_SERVER['SCRIPT_FILENAME']);
			}

			if (count($dirParts) > 2)
			{
				$dirParts = array_splice($dirParts, 0, -2);
				$myDir    = implode(DIRECTORY_SEPARATOR, $dirParts);
			}
		}
		else
		{
			$dirParts = explode(DIRECTORY_SEPARATOR, $argv[0]);

			if (count($dirParts) > 3)
			{
				$dirParts = array_splice($dirParts, 0, -3);
				$myDir    = implode(DIRECTORY_SEPARATOR, $dirParts);
			}
		}

		if (@file_exists(__DIR__ . '/../helpers/integration.php'))
		{
			return require __DIR__ . '/../helpers/integration.php';
		}

		if (@file_exists('../helpers/integration.php'))
		{
			return require '../helpers/integration.php';
		}

		if (isset($myDir) && @file_exists($myDir . '/helpers/integration.php'))
		{
			return require $myDir . '/helpers/integration.php';
		}

		// Create the container if it doesn't come from an integration
		return new Container(
			[
				'application_name' => 'Solo',
			]
		);
	}
);

// Load the version file
if (!defined('AKEEBABACKUP_VERSION') && @file_exists(__DIR__ . '/version.php'))
{
	require_once __DIR__ . '/version.php';
}

// Tell the Akeeba Engine where to find a valid cacert.pem file
defined('AKEEBA_CACERT_PEM') || define('AKEEBA_CACERT_PEM', CaBundle::getBundledCaBundlePath());

// Include the Akeeba Engine factory
call_user_func(
	function ($container) {
		if (defined('AKEEBAENGINE'))
		{
			return;
		}

		define('AKEEBAENGINE', 1);

		try
		{
			if (!class_exists(Factory::class))
			{
				echo "ERROR!\n";
				echo "Could not load the backup engine; cannot autoload Factory class\n\n";

				exit(255);
			}
		}
		catch (Exception $e)
		{
			echo "ERROR!\n";
			echo "Backup engine returned an error. Technical information:\n";
			echo "Error message:\n\n";
			echo $e->getMessage() . "\n\n";
			echo "Path to " . basename(__FILE__) . ":" . __DIR__ . "\n";
			echo "Path to factory file: $factoryPath\n";

			exit(255);
		}

		Platform::addPlatform('Solo', __DIR__ . '/Solo/Platform/Solo');
		$platform = Platform::getInstance();

		$platform->setContainer($container);
		$platform->load_version_defines();
		$platform->apply_quirk_definitions();

		$secretKeyFile = __DIR__ . '/Solo/secretkey.php';

		// Use a different path to secretkey.php when using WordPress
		if (defined('ABSPATH'))
		{
			$secretKeyFile = rtrim(
				                 (defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : (rtrim(ABSPATH, '/') . '/wp-content')),
				                 '/'
			                 ) . '/akeebabackup_secretkey.php';
			Factory::getSecureSettings()->setKeyFilename($secretKeyFile);
		}

		Factory::getSecureSettings()->setKeyFilename($secretKeyFile);
	}, $akeebaSoloContainer
);

/**
 * When the AKEEBA_CLI_APPLICATION_CLASS constant is defined, it tries to execute the CLI application
 */
if (defined('AKEEBA_CLI_APPLICATION_CLASS'))
{
	$appClass = AKEEBA_CLI_APPLICATION_CLASS;

	try
	{
		(new $appClass($akeebaSoloContainer))->initialise()->execute();
	}
	catch (Throwable $e)
	{
		echo <<< TEXT
* * *   E R R O R   * * *
{$e->getCode()}: {$e->getMessage()}
{$e->getFile()}:{$e->getLine()}
{$e->getTraceAsString()}

TEXT;
		exit(255);
	}
}
