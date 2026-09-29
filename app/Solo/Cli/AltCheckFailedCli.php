<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Cli;

use Akeeba\Engine\Platform;

class AltCheckFailedCli extends AbstractCliApplication
{

	/**
	 * Method to run the application routines.  Most likely you will want to instantiate a controller
	 * and execute it, or perform some sort of task directly.
	 *
	 * @return  void
	 */
	protected function doExecute()
	{
		// Get the backup profile and description
		$profile = $this->getContainer()->input->get('profile', 1, 'int');

		$debugmessage = '';

		if ($this->getContainer()->input->get('debug', -1, 'int') != -1)
		{
			if (!defined('AKEEBADEBUG'))
			{
				define('AKEEBADEBUG', 1);
			}

			$debugmessage = "*** DEBUG MODE ENABLED ***\n";
		}

		$version      = AKEEBABACKUP_VERSION;
		$date         = AKEEBABACKUP_DATE;
		$start_backup = time();

		$phpversion     = PHP_VERSION;
		$phpenvironment = PHP_SAPI;

		if ($this->getContainer()->input->get('quiet', -1, 'int') == -1)
		{
			$softwareName = defined('ABSPATH') ? 'Akeeba Backup' : 'Akeeba Solo';

			$year = gmdate('Y');
			echo <<<ENDBLOCK
$softwareName Alternate CLI Backup Script version $version ($date)
Copyright (C) 2014-$year Nicholas K. Dionysopoulos / Akeeba Ltd
-------------------------------------------------------------------------------
$softwareName is Free Software, distributed under the terms of the GNU General
Public License version 3 or, at your option, any later version.
This program comes with ABSOLUTELY NO WARRANTY as per sections 15 & 16 of the
license. See http://www.gnu.org/licenses/gpl-3.0.html for details.
-------------------------------------------------------------------------------
You are using PHP $phpversion ($phpenvironment)
$debugmessage

ENDBLOCK;
		}

		// Log some paths
		if ($this->getContainer()->input->get('quiet', -1, 'int') == -1)
		{
			echo "Site paths determined by this script:\n";
			echo "APATH_BASE : " . APATH_BASE . "\n";
		}

		$startup_check = true;

		$url = Platform::getInstance()->get_platform_configuration_option('siteurl', '');
		if (empty($url))
		{
			echo <<<ENDTEXT
ERROR:
	This script could not detect your Akeeba Solo installation's URL. Please
	visit Akeeba Solo's main page at least once before running this script.
	When you do that, Akeeba Solo will record the URL to itself and make it
	available to this script.

ENDTEXT;
			$startup_check = false;
		}

		// Get the front-end backup settings
		$frontend_enabled = Platform::getInstance()->get_platform_configuration_option('legacyapi_enabled', '');
		$secret           = Platform::getInstance()->get_platform_configuration_option('frontend_secret_word', '');

		if (!$frontend_enabled)
		{
			echo <<<ENDTEXT
ERROR:
	Your Akeeba Solo installation's Legacy Front-end API feature is currently
	disabled. Please log in to Akeeba Solo, click on the System Configuration
	icon in the system management pane towards the bottom of the page and
	set Enable Legacy Front-end Backup API to Yes. Do not forget to also set 
	a Secret Word!

ENDTEXT;
			$startup_check = false;
		}
		elseif (empty($secret))
		{
			echo <<<ENDTEXT
ERROR:
	You have set Enable Legacy Front-end API to Yes, but you forgot to set a
	Secret Word. This script can not continue with an empty Secret Word.
	Please log in to Akeeba Solo, click on the System Configuration
	icon in the system management pane towards the bottom of the page and
	set a Secret Word.

ENDTEXT;
			$startup_check = false;
		}

		// Detect cURL or fopen URL
		$method = null;
		if (function_exists('curl_init'))
		{
			$method = 'curl';
		}
		elseif (function_exists('fsockopen'))
		{
			$method = 'fsockopen';
		}

		if (empty($method))
		{
			if (function_exists('ini_get'))
			{
				if (ini_get('allow_url_fopen'))
				{
					$method = 'fopen';
				}
			}
		}

		$overridemethod = $this->getContainer()->input->get('method', '', 'cmd');

		if (!empty($overridemethod))
		{
			$method = $overridemethod;
		}

		if (empty($method))
		{
			echo <<<ENDTEXT
ERROR:
	Could not find any supported method for running the front-end backup
	feature of Akeeba Solo. Please check with your host that at least
	one of the following features are supported in your PHP configuration:

	1. The cURL extension
	2. The fsockopen() function
	3. The fopen() URL wrappers, i.e. allow_url_fopen is enabled

	If neither method is available you will not be able to run a backup using
	this CRON helper script.

ENDTEXT;
			$startup_check = false;
		}

		if (!$startup_check)
		{
			echo "\n\nCHECK FOR FAILURES ABORTED DUE TO CONFIGURATION ERRORS\n\n";
			$this->close(255);
		}

		// Perform the backup
		$url    = rtrim($url, '/');
		$secret = urlencode($secret);
		if (defined('ABSPATH'))
		{
			$tempURL = Platform::getInstance()->get_platform_configuration_option('ajaxurl', '');
			$url     = empty($tempURL) ? ($url . '/wp-admin/admin-ajax.php') : $tempURL;
		}
		else
		{
			$url .= '/index.php';
		}

		$url .= defined('ABSPATH') ? '?action=akeebabackup_check' : '?view=check';
		$url .= "&key={$secret}";

		$timestamp = date('Y-m-d H:i:s');

		$result = $this->fetchURL($url, $method);

		echo "[{$timestamp}] Got $result\n";

		if (empty($result) || ($result === false))
		{
			echo "[{$timestamp}] No message received\n";
			echo <<<ENDTEXT
ERROR:
Your check for failures attempt has timed out, or a fatal PHP error has occurred.

ENDTEXT;
		}
		elseif (strpos($result, '200 ') !== false)
		{
			echo "[{$timestamp}] Checks finalization message received\n";
			echo <<<ENDTEXT

Checks are finished successfully.

ENDTEXT;
		}
		elseif (strpos($result, '500 ') !== false)
		{
			// Backup error
			echo "[{$timestamp}] Error signal received\n";
			echo <<<ENDTEXT
ERROR:
An error has occurred. The server's response was:

$result

ENDTEXT;
		}
		elseif (strpos($result, '403 ') !== false)
		{
			// This should never happen: invalid authentication or front-end backup disabled
			echo "[{$timestamp}] Connection denied (403) message received\n";
			echo <<<ENDTEXT
ERROR:
	The server denied the connection. Please make sure that Enable Legacy
	Front-end API is set to Yes and a valid secret word is in place.

	Server response: $result

	Check failed.

ENDTEXT;
		}
		else
		{
			// Unknown result?!
			echo "[{$timestamp}] Could not parse the server response.\n";
			echo <<<ENDTEXT
ERROR:
	We could not understand the server's response. Most likely an error
	has occurred. The server's response was:

$result

	If you do not see "200 OK" at the end of this output, checks failed.

ENDTEXT;
		}
	}

	/**
	 * Fetches a remote URL using curl, fsockopen or fopen
	 *
	 * @param   string  $url     The remote URL to fetch
	 * @param   string  $method  The method to use: curl, fsockopen or fopen (optional)
	 *
	 * @return string The contents of the URL which was fetched
	 */
	private function fetchURL($url, $method = 'curl')
	{
		switch ($method)
		{
			default:
			case 'curl':
				$ch         = curl_init($url);
				$cacertPath = APATH_BASE . '/Awf/Download/Adapter/cacert.pem';

				if (file_exists($cacertPath))
				{
					@curl_setopt($ch, CURLOPT_CAINFO, $cacertPath);
				}
				@curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
				@curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
				@curl_setopt($ch, CURLOPT_HEADER, false);
				@curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				@curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				@curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 180);
				@curl_setopt($ch, CURLOPT_TIMEOUT, 180);
				$result = curl_exec($ch);

				if (version_compare(PHP_VERSION, '8.5.0', 'lt'))
				{
					curl_close($ch);
				}

				return $result;
				break;

			case 'fsockopen':
				$pos      = strpos($url, '://');
				$protocol = strtolower(substr($url, 0, $pos));
				$req      = substr($url, $pos + 3);
				$pos      = strpos($req, '/');
				if ($pos === false)
				{
					$pos = strlen($req);
				}
				$host = substr($req, 0, $pos);

				if (strpos($host, ':') !== false)
				{
					[$host, $port] = explode(':', $host);
				}
				else
				{
					$port = ($protocol == 'https') ? 443 : 80;
				}

				$uri = substr($req, $pos);
				if ($uri == '')
				{
					$uri = '/';
				}

				$crlf = "\r\n";
				$req  = 'GET ' . $uri . ' HTTP/1.0' . $crlf
				        . 'Host: ' . $host . $crlf
				        . $crlf;

				$fp = fsockopen(($protocol == 'https' ? 'ssl://' : '') . $host, $port);
				fwrite($fp, $req);
				$response = '';
				while (is_resource($fp) && $fp && !feof($fp))
				{
					$response .= fread($fp, 1024);
				}
				fclose($fp);

				// split header and body
				$pos = strpos($response, $crlf . $crlf);
				if ($pos === false)
				{
					return ($response);
				}
				$header = substr($response, 0, $pos);
				$body   = substr($response, $pos + 2 * strlen($crlf));

				// parse headers
				$headers = [];
				$lines   = explode($crlf, $header);
				foreach ($lines as $line)
				{
					if (($pos = strpos($line, ':')) !== false)
					{
						$headers[strtolower(trim(substr($line, 0, $pos)))] = trim(substr($line, $pos + 1));
					}
				}

				//redirection?
				if (isset($headers['location']))
				{
					return $this->fetchURL($headers['location'], $method);
				}
				else
				{
					return ($body);
				}

				break;

			case 'fopen':
				$opts = [
					'http' => [
						'method' => "GET",
						'header' => "Accept-language: en\r\n",
					],
				];

				$context = stream_context_create($opts);
				$result  = @file_get_contents($url, false, $context);
				break;
		}

		return $result;
	}
}
