<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Cli;

use Akeeba\Engine\Platform;

class AltBackupCli extends AbstractCliApplication
{
	/**
	 * When making HTTPS connections, should we verify the certificate validity and that the hostname matches the one
	 * in the certificate? Turned on by default. You can disable with the --no-verify CLI option.
	 *
	 * @var  bool
	 */
	private $verifySSL = true;


	/**
	 * Method to run the application routines.  Most likely you will want to instantiate a controller
	 * and execute it, or perform some sort of task directly.
	 *
	 * @return  void
	 */
	protected function doExecute()
	{
		// Has the user set the --no-verify option?
		$noVerifyValue   = $this->getContainer()->input->get('no-verify', 999);
		$this->verifySSL = $noVerifyValue != 999;

		// Get the backup profile and description
		$profile = $this->getContainer()->input->get('profile', 1, 'int');

		if ($profile <= 0)
		{
			$profile = 1;
		}

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
		$memusage     = $this->memUsage();

		$phpversion     = PHP_VERSION;
		$phpenvironment = PHP_SAPI;
		$phpos          = PHP_OS;

		$appName = defined('ABSPATH') ? 'Akeeba backup' : 'Akeeba Solo';

		if ($this->getContainer()->input->get('quiet', -1, 'int') == -1)
		{
			$year = gmdate('Y');
			echo <<<ENDBLOCK
$appName Alternate CLI Backup Script version $version ($date)
Copyright (C) 2014-$year Nicholas K. Dionysopoulos / Akeeba Ltd
-------------------------------------------------------------------------------
$appName is Free Software, distributed under the terms of the GNU General
Public License version 3 or, at your option, any later version.
This program comes with ABSOLUTELY NO WARRANTY as per sections 15 & 16 of the
license. See http://www.gnu.org/licenses/gpl-3.0.html for details.
-------------------------------------------------------------------------------
You are using PHP $phpversion ($phpenvironment)
$debugmessage
Current memory usage: $memusage


ENDBLOCK;
		}

		// Attempt to use an infinite time limit, in case you are using the PHP CGI binary instead
		// of the PHP CLI binary. This will not work with Safe Mode, though.
		$safe_mode = true;

		if (function_exists('ini_get'))
		{
			$safe_mode = ini_get('safe_mode');
		}

		if (!$safe_mode && function_exists('set_time_limit'))
		{
			if ($this->getContainer()->input->get('quiet', -1, 'int') == -1)
			{
				echo "Unsetting time limit restrictions.\n";
			}

			@set_time_limit(0);
		}
		elseif (!$safe_mode)
		{
			if ($this->getContainer()->input->get('quiet', -1, 'int') == -1)
			{
				echo "Could not unset time limit restrictions; you may get a timeout error\n";
			}
		}
		else
		{
			if ($this->getContainer()->input->get('quiet', -1, 'int') == -1)
			{
				echo "You are using PHP's Safe Mode; you may get a timeout error\n";
			}
		}

		if ($this->getContainer()->input->get('quiet', -1, 'int') == -1)
		{
			echo "\n";
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
	This script could not detect your $appName installation's URL. Please
	visit {$appName}'s main page at least once before running this script.
	When you do that, $appName will record the URL to itself and make it
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
	Your $appName installation's Legacy Front-end API feature is currently
	disabled. Please log in to $appName, click on the System Configuration
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
	Please log in to $appName, click on the System Configuration
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
	feature of $appName. Please check with your host that at least
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
			echo "\n\nBACKUP ABORTED DUE TO CONFIGURATION ERRORS\n\n";
			$this->close(255);
		}

		echo <<<ENDBLOCK
Starting a new backup with the following parameters:
Profile ID    : $profile
Backup Method : $method


ENDBLOCK;

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

		$url       .= defined('ABSPATH') ? '?action=akeebabackup_legacy' : '?view=remote';
		$url       .= "&key={$secret}&noredirect=1&profile=$profile";
		$old_url   = $url;
		$inLoop    = true;
		$step      = 0;
		$timestamp = date('Y-m-d H:i:s');
		echo "[{$timestamp}] Beginning backing up\n";

		while ($inLoop)
		{
			$timestamp = date('Y-m-d H:i:s');

			$result = $this->fetchURL($url, $method);

			echo "[{$timestamp}] Got $result\n";

			if (empty($result) || ($result === false))
			{
				echo "[{$timestamp}] No message received\n";
				echo <<<ENDTEXT
ERROR:
	Your backup attempt has timed out, or a fatal PHP error has occurred.
	Please check the backup log and your server's error log for more
	information.

Backup failed.

ENDTEXT;
				$this->close(100);

				$inLoop = false;
			}
			elseif (strpos($result, '301 More work required') !== false)
			{
				// Extract the backup ID
				$backupId = null;
				$startPos = strpos($result, 'BACKUPID #"\#\"#');
				$endPos   = false;

				if ($startPos !== false)
				{
					$endPos = strpos($result, '#"\#\"#', $startPos + 15);
				}

				if ($endPos !== false)
				{
					$backupId = substr($result, $startPos + 16, $endPos - $startPos - 16);
				}

				// Construct the new URL and access it

				if ($step == 0)
				{
					$old_url = $url;
				}

				$step++;
				$url = $old_url . '&task=step&step=' . $step;

				if (!is_null($backupId))
				{
					$url .= '&backupid=' . urlencode($backupId);
				}

				echo "[{$timestamp}] Backup progress signal received\n";
			}
			elseif (strpos($result, '200 OK') !== false)
			{
				echo "[{$timestamp}] Backup finalization message received\n";
				echo <<<ENDTEXT

Your backup has finished successfully.

Please review your backup log file for any warning messages. If you see any
such messages, please make sure that your backup is working properly by trying
to restore it on a local server.

ENDTEXT;
				$inLoop = false;

				echo "Backup job finished after approximately " . $this->timeago($start_backup, time(), '', false) . "\n";
				echo "Peak memory usage: " . $this->peakMemUsage() . "\n\n";

				$this->close(0);
			}
			elseif (strpos($result, '500 ERROR -- ') !== false)
			{
				// Backup error
				echo "[{$timestamp}] Error signal received\n";
				echo <<<ENDTEXT
ERROR:
	A backup error has occurred. The server's response was:

$result

Backup failed.

ENDTEXT;
				$inLoop = false;

				$this->close(2);
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

Backup failed.

ENDTEXT;
				$inLoop = false;

				$this->close(103);
			}
			else
			{
				// Unknown result?!
				echo "[{$timestamp}] Could not parse the server response.\n";
				echo <<<ENDTEXT
ERROR:
	We could not understand the server's response. Most likely a backup error
	has occurred. The server's response was:

$result

	If you do not see "200 OK" at the end of this output, the backup has
	failed.

ENDTEXT;
				$inLoop = false;

				$this->close(1);
			}
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
			@curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verifySSL);
			@curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->verifySSL ? 2 : 0);
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
