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

// Assigned inside the try block below; the catch block must be able to tell it does not exist yet
$application = null;

try
{
	// Load the application configuration
	$configPath = $akeebaSoloContainer->appConfig->getDefaultPath();

	if (is_file($configPath))
	{
		try
		{
			$akeebaSoloContainer->appConfig->loadConfiguration();
		}
		catch (Throwable $configException)
		{
			/**
			 * The configuration file exists but is empty or damaged. Everyone, not only the administrator, gets to see this
			 * page, so it names neither the location of the file nor the exception and its trace (the generic error page below
			 * would). In debug mode we fall through to the generic page, which has all the details.
			 */
			if (!headers_sent())
			{
				http_response_code(500);
			}

			if (defined('AKEEBADEBUG'))
			{
				throw $configException;
			}

			if (!headers_sent())
			{
				header('Content-Type: text/html; charset=utf-8');
			}

			echo "<h1>Akeeba Solo cannot start</h1>\n";
			echo "<p>" . htmlspecialchars(
					'Akeeba Solo cannot read its configuration file (config.php). It may be empty or damaged. '
					. 'Restore it from a backup, or delete it to run the Setup wizard again.',
					ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'
				) . "</p>\n";

			return;
		}
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
	$debugEnabled = defined('AKEEBADEBUG') && AKEEBADEBUG;

	@http_response_code(500);

	if (!$debugEnabled)
	{
		echo <<< HTML
<h1>Application error</h1>
<hr/>
<p class="small">
	To receive support about this issue, please enable Debug in the application and try reproducing this error. It will
	come back with a much more detailed message. That detailed message is what you need to submit when requesting
	support.
</p>
HTML;

		return;
	}

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
		echo "<div class=\"alert alert-danger\">" . htmlspecialchars(get_class($exc), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' &mdash; ' . htmlspecialchars($exc->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</div>\n";
		echo "<pre class=\"well\">\n";
		echo htmlspecialchars($exc->getTraceAsString(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		echo "</pre>\n";

		return;
	}

	include $filename;
}