<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Do not remove this line. It is required for Akeeba Solo to work.
define('_AKEEBA', 1);

// Uncomment (remove the double slash from) the following line to enable debug mode. When the debug mode is enabled two-factor authentication with Google Authenticator or YubiKey is disabled.
// define('AKEEBADEBUG', 1);

// Always enable Akeeba Backup for WordPress debug mode when WordPress' debug mode is enabled
if (defined('WP_DEBUG') && WP_DEBUG && !defined('AKEEBADEBUG'))
{
	define('AKEEBADEBUG', 1);

	if (!defined('WP_DEBUG_DISPLAY') || WP_DEBUG_DISPLAY != false)
	{
		define('AKEEBADEBUG_ERROR_DISPLAY', 1);
	}
}

// Do not change these paths unless you know what you're doing
define('APATH_BASE', realpath(__DIR__ . '/../app'));
define('APATH_ROOT', APATH_BASE);

define('APATH_SITE', APATH_BASE);
define('APATH_THEMES', __DIR__ . '/templates');
define('APATH_TRANSLATION', APATH_BASE . '/languages');
define('APATH_TMP', APATH_BASE . '/tmp');
