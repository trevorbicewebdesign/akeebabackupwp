<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Helper;

/**
 * Where update packages may be downloaded from.
 *
 * The update stream tells us where to download the update package from, and we install whatever we get from there. We
 * also append the Download ID to that URL. Therefore, we only ever accept HTTPS URLs on Akeeba's own servers.
 *
 * This class must not depend on anything else: Akeeba Backup for WordPress' integrated updater loads it directly,
 * possibly before the application's autoloader is available.
 */
abstract class UpdateSource
{
	/**
	 * The hosts we accept update package downloads from.
	 */
	public const ALLOWED_DOWNLOAD_HOSTS = ['akeeba.com', 'www.akeeba.com', 'cdn.akeeba.com'];

	/**
	 * Is this a URL we can download an update package from?
	 *
	 * Only `https://` URLs on one of the ALLOWED_DOWNLOAD_HOSTS, on the default port and without credentials, are
	 * acceptable.
	 *
	 * Developers testing updates can point the update stream elsewhere by defining the AKEEBABACKUP_UPDATE_BASEURL
	 * constant in version.php. In that case, pass it as $testingBaseUrl: URLs on the same scheme, host and port are
	 * accepted as well. Being able to define that constant already requires write access to the site's files.
	 *
	 * @param   mixed        $url             The download URL to check
	 * @param   string|null  $testingBaseUrl  The developer update stream base URL, if any
	 *
	 * @return  bool
	 */
	public static function isAllowedDownloadUrl($url, ?string $testingBaseUrl = null): bool
	{
		if (!is_string($url) || $url === '' || preg_match('/[\x00-\x20\x7F\\\\]/', $url))
		{
			return false;
		}

		$parts = parse_url($url);

		if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])
		    || isset($parts['user']) || isset($parts['pass']))
		{
			return false;
		}

		$scheme = strtolower($parts['scheme']);
		$host   = strtolower($parts['host']);
		$port   = $parts['port'] ?? null;

		if ($scheme === 'https' && in_array($host, self::ALLOWED_DOWNLOAD_HOSTS, true)
		    && ($port === null || $port === 443))
		{
			return true;
		}

		if (empty($testingBaseUrl))
		{
			return false;
		}

		$testing = parse_url($testingBaseUrl);

		return is_array($testing) && !empty($testing['scheme']) && !empty($testing['host'])
		       && $scheme === strtolower($testing['scheme'])
		       && $host === strtolower($testing['host'])
		       && $port === ($testing['port'] ?? null);
	}

	/**
	 * The developer update stream base URL (AKEEBABACKUP_UPDATE_BASEURL), if one is defined.
	 *
	 * @return  string|null
	 */
	public static function getTestingBaseUrl(): ?string
	{
		return defined('AKEEBABACKUP_UPDATE_BASEURL') ? (string) AKEEBABACKUP_UPDATE_BASEURL : null;
	}
}
