<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Bootstrap file for Akeeba Solo for WordPress

use Awf\Application\Application;
use Solo\Application\WordpressUserPrivileges;

/**
 * Make sure we are being called from WordPress itself
 */
defined('WPINC') or die;

defined('AKEEBASOLO') or define('AKEEBASOLO', 1);

// A trick to prevent raw views from rendering the entire WP back-end interface
if (defined('AKEEBABACKUPWP_OBFLAG'))
{
	@ob_get_clean();
}

/** @var \Solo\Container $container Comes from \AkeebaBackupWP::bootApplication */

if ($container->input->get->getBool('_ak_reset_session', false))
{
	$container->session->clear();
}

try
{
	// Load the application configuration
	$container->appConfig->loadConfiguration();

	// Prepare the user manager
	$container->userManager->registerPrivilegePlugin('akeeba', WordpressUserPrivileges::class);

	$application = $container->application;

	$application->initialise();
	$application->route();
	$application->dispatch();
	$application->render();

	// Persist messages if they exist.
	if (count($application->messageQueue))
	{
		$application->getContainer()->segment->setFlash('application_queue', $application->messageQueue);
	}

	$application->getContainer()->session->commit();

	if (defined('AKEEBABACKUPWP_OBFLAG'))
	{
		@ob_start();
	}
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
