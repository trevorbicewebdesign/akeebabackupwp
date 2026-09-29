<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Awf\Application\Application;
use Solo\Application\UserAuthenticationGoogle;
use Solo\Application\UserAuthenticationPassword;
use Solo\Application\UserAuthenticationYubikey;
use Solo\Application\UserPrivileges;
use Solo\Container;

defined('AKEEBASOLO') || define('AKEEBASOLO', 1);

/**
 * @var Container $akeebaSoloContainer
 */
require __DIR__ . '/include.php';

try
{
	// Load the application configuration
	$configPath = $akeebaSoloContainer->appConfig->getDefaultPath();

	if (is_file($configPath))
	{
		$akeebaSoloContainer->appConfig->loadConfiguration();
	}

	// Prepare the user manager
	$manager = $akeebaSoloContainer->userManager;
	$manager->registerPrivilegePlugin('akeeba', UserPrivileges::class);
	$manager->registerAuthenticationPlugin('password', UserAuthenticationPassword::class);

	if (!defined('AKEEBADEBUG'))
	{
		$manager->registerAuthenticationPlugin('yubikey', UserAuthenticationYubikey::class);
		$manager->registerAuthenticationPlugin('google', UserAuthenticationGoogle::class);
	}

	// Run the application
	$application = $akeebaSoloContainer->application;

	$application->initialise();
	$application->route();
	$application->dispatch();
	$application->render();
	$application->close();
}
catch (Throwable $exc)
{
	$filename = null;

	if ($application instanceof Application)
	{
		$template = $application->getTemplate();
		$filename = APATH_THEMES . '/' . $template . '/error.php';
		$filename = @file_exists($filename) ? $filename : null;
	}

	if (is_null($filename))
	{
		echo "<h1>Application Error</h1>\n";
		echo "<p>Please submit the following error message and trace in its entirety when requesting support</p>\n";
		echo "<div class=\"alert alert-danger\">" . get_class($exc) . ' &mdash; ' . $exc->getMessage() . "</div>\n";
		echo "<pre class=\"well\">\n";
		echo $exc->getTraceAsString();
		echo "</pre>\n";

		return;
	}

	include $filename;
}