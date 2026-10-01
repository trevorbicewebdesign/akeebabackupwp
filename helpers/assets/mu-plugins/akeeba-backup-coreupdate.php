<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * Akeeba Backup for WordPress – Backup on Update
 *
 * This plugin will automatically take a backup of your site before updating WordPress, as long as you are doing the
 * WordPress update from the wp-admin section of your site, and you have configured Akeeba Backup to take backups on
 * update.
 */

// Sanity check: make sure the main plugin exists, and it has all files we expect it to have.
$plugin_dir  = get_option('akeebabackupwp_plugin_dir', 'akeebabackupwp');
$plugin_path = WP_PLUGIN_DIR . '/' . $plugin_dir;

if (
	!@is_dir($plugin_path)
	|| !@is_file($plugin_path . '/app/version.php')
	|| !@is_file(WP_PLUGIN_DIR . '/' . $plugin_dir . '/helpers/AkeebaBackupWP.php')
)
{
	return;
}

// Let's double check that the file is at the same version of the component
define('AKEEBABACKUP_MUPLUGIN_COREUPDATE_VERSION', '9.2.0');

// Disable version check for alpha versions
if (stripos(AKEEBABACKUP_MUPLUGIN_COREUPDATE_VERSION, 'rev') === false)
{
	if (!defined('AKEEBABACKUP_VERSION'))
	{
		// No version file? Be safe and stop here
		if (!file_exists($plugin_path . '/app/version.php'))
		{
			return;
		}

		require_once $plugin_path . '/app/version.php';
	}

	// This should never happen, but let's be safe rather than sorry
	if (!defined('AKEEBABACKUP_VERSION'))
	{
		return;
	}

	if (!version_compare(AKEEBABACKUP_MUPLUGIN_COREUPDATE_VERSION, AKEEBABACKUP_VERSION, 'eq'))
	{
		// Two different versions? Abort! Abort!
		return;
	}
}

// This should never happen, but maybe our code could be triggered by some kind of CLI scripts, so better be safe than sorry
if (!isset($_SERVER['REQUEST_URI']))
{
	return;
}

$current_url = $_SERVER['REQUEST_URI'];

// Check we're on the correct page
if (stripos($current_url, 'wp-admin/update-core.php') === false)
{
	return;
}

// Missing action or not the action we're looking for
if (!isset($_REQUEST['action']) || !in_array($_REQUEST['action'], ['do-core-upgrade', 'do-core-reinstall']))
{
	return;
}

// Avoid endless loops
if (isset($_REQUEST['akeeba_autoupdate_ignore']))
{
	return;
}

if (!class_exists('AkeebaBackupWP'))
{
	require_once WP_PLUGIN_DIR . '/' . $plugin_dir . '/helpers/AkeebaBackupWP.php';
}

$backup_profile = AkeebaBackupWP::getProfileManualCoreUpdate();

// No backup profile means the feature is disabled
if (is_null($backup_profile))
{
	return;
}

if (!function_exists('find_core_update'))
{
	require_once ABSPATH . 'wp-admin/includes/update.php';
}

// Do we really have an update to perform?
$version = isset($_POST['version']) ? $_POST['version'] : false;
$locale  = isset($_POST['locale']) ? $_POST['locale'] : 'en_US';
$update  = find_core_update($version, $locale);

if (!$update)
{
	return;
}

$return_url = admin_url() . 'update-core.php?action=' . $_REQUEST['action'] . '&akeeba_autoupdate_ignore=1';

$return_form = [
	'_wpnonce'         => $_POST['_wpnonce'],
	'_wp_http_referer' => $_POST['_wp_http_referer'],
	'upgrade'          => 1,
	'version'          => $version,
	'locale'           => $locale,
];

$backup_url = admin_url() . 'admin.php?page=' . $plugin_dir . '/akeebabackupwp.php&view=backup&autostart=1&backuponupdate=1';
$backup_url .= '&profile=' . $backup_profile;

// Let's use already generated nonce, and tell AWF which action we used to generate it
$backup_url .= '&_wpnonce=' . $_POST['_wpnonce'];
$backup_url .= '&_wpaction=upgrade-core';

$backup_url .= '&returnurl=' . urlencode($return_url);
$backup_url .= '&returnform=' . base64_encode(json_encode($return_form));

if (!headers_sent())
{
	header("Location: $backup_url", true, 302);
}

// Let's add a fallback for redirections, in case headers were already sent
echo '<script type="text/javascript">window.location = "' . $backup_url . '";	</script>' . "\n";
echo 'If you are not redirected in 10 seconds, please click <a href="' . $backup_url . '">here</a> to take a backup before updating WordPress.';

die();