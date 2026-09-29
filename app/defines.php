<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Do not remove this line. It is required for Akeeba Solo to work.
define('_AKEEBA', 1);

// Uncomment to enable debug mode. When the debug mode is enabled two-factor authentication with Google Authenticator or YubiKey is disabled.
// define('AKEEBADEBUG', 1);
// Uncomment to enable full error display when AKEEBADEBUG is also enabled
// define('AKEEBADEBUG_ERROR_DISPLAY', 1);

// Do not change these paths unless you know what you're doing
define('APATH_BASE', __DIR__);
define('APATH_ROOT', APATH_BASE);

define('APATH_SITE', APATH_BASE);
define('APATH_THEMES', APATH_BASE . '/templates');
define('APATH_TRANSLATION', APATH_BASE . '/languages');
define('APATH_TMP', APATH_BASE . '/tmp');
