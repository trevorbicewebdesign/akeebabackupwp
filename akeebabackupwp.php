<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/*
Plugin Name: Akeeba Backup CORE for WordPress
Plugin URI: https://www.akeeba.com
Description: The complete backup solution for WordPress
Version: 9.1.2
Requires at least: 6.3.0
Tested up to: 6.6
Requires PHP: 7.4
Author: Akeeba Ltd
Author URI: https://www.akeeba.com
Network: true
License: GPL-3.0-or-later
*/

// Make sure we are being called from WordPress itself.
defined('WPINC') or die;

/**
 * Make sure we have a minimum PHP version which can run this basic plugin bootup code.
 *
 * This file deliberately contains very simple PHP code which will even run on ancient PHP versions (7.0, released all
 * the way back in 2015). If your server does not even meet this minimum, we'll just not load the plugin to avoid a
 * PHP error which would end up disabling the plugin altogether.
 *
 * Why do that? It's possible you installed the plugin running a modern PHP version, then you screwed up your server
 * configuration so that it runs an ancient PHP version, or tried to run a CLI script with an ancient PHP version. This
 * kind of simple mistake should not result in your backup solution being permanently deactivated by WordPress with
 * barely a misleading email blaming our software. That's why!
 */
if (version_compare(PHP_VERSION, '7.0.0', 'lt'))
{
	return;
}


/**
 * Wrapping everything in a try-catch block to avoid automatic plugin deactivation.
 *
 * WordPress has this annoying feature which deactivates a plugin if there is an exception thrown. Normally this is a
 * good protection against badly written plugins. However, it will also disable plugins when unforeseen problems outside
 * the plugin's control occur, e.g. the database connection goes away while the plugin is initialising.
 *
 * Wrapping everything in a try-catch allows us to not load the plugin **for this request only**, in hope that the next
 * request will see the environmental defect fixed.
 */
try
{
	// Make sure the plugin is not being double loaded. This should never happen.
	if (defined('AKEEBABACKUPWP_ALREADY_LOADED'))
	{
		return;
	}

	define('AKEEBABACKUPWP_ALREADY_LOADED', true);

	// Include the version.php file. This tells us the minimum PHP version for this version of Akeeba Backup.
	if (file_exists(__DIR__ . '/app/version.php'))
	{
		require_once __DIR__ . '/app/version.php';
	}

	// Make sure the minimum PHP version requirement for this version is met.
	if (version_compare(PHP_VERSION, defined('AKEEBABACKUP_MINPHP') ? AKEEBABACKUP_MINPHP : '7.4.0', 'lt')) {
		return;
	}

	// Preload our helper classes
	require_once __DIR__ . '/helpers/AkeebaBackupWP.php';
	require_once __DIR__ . '/helpers/AkeebaBackupWPUpdater.php';

	// Initialization of our helper class
	AkeebaBackupWP::initialization(__FILE__);

	/**
	 * Redirect to the ANGIE installer if the installer currently exists
	 */
	AkeebaBackupWP::redirectIfInstallationPresent();

	// Quit early if it is the wrong PHP version. This is fail-safe in case the two previous checks didn't catch this.
	if (AkeebaBackupWP::$wrongPHP)
	{
		return;
	}

	// Initialization of the integrated updater
	AkeebaBackupWP::loadIntegratedUpdater();

	// Install the plugin hooks which make the plugin actually work.
	AkeebaBackupWP::installHooks(__FILE__);
}
catch (Throwable $e)
{
	return;
}