<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Helper;

use Akeeba\Engine\Factory;
use Awf\Input\Filter;
use Awf\Text\Text;
use Awf\Uri\Uri;

/**
 * Various utility methods
 */
class Utils
{
	/**
	 * Returns the absolute filesystem path to the log file of a backup run.
	 *
	 * The backup engine normally creates akeeba.<tag>.log.php files. On hosts which do not let us write to files with
	 * a .php extension it falls back to akeeba.<tag>.php and, in older versions, to the web accessible akeeba.<tag>.log
	 * file. This method returns whichever of them exists.
	 *
	 * @param   string|null  $tag  The backup run's tag
	 *
	 * @return  string|null  The absolute path to the log file. NULL if no log file exists.
	 */
	public static function getLogFilePath(?string $tag): ?string
	{
		foreach (Factory::getLog()->getAllLogFilenames($tag) as $logFile)
		{
			if (@is_file($logFile))
			{
				return $logFile;
			}
		}

		return null;
	}

	/**
	 * Get the relative path of a directory ($to) against a base directory ($from). Both directories are given as
	 * absolute paths.
	 *
	 * @param   string $from The base directory
	 * @param   string $to   The directory to convert to a relative path
	 *
	 * @return  string  The path of $to relative to $from
	 */
	public static function getRelativePath($from, $to)
	{
		// Some compatibility fixes for Windows paths
		$from = is_dir($from) ? rtrim($from, '\/') . '/' : $from;
		$to   = is_dir($to) ? rtrim($to, '\/') . '/' : $to;
		$from = str_replace('\\', '/', $from);
		$to   = str_replace('\\', '/', $to);

		$from    = explode('/', $from);
		$to      = explode('/', $to);
		$relPath = $to;

		foreach ($from as $depth => $dir)
		{
			/**
			 * $to has run out of segments.
			 *
			 * Getting here proves every earlier segment matched, i.e. $to is a prefix of $from, so the
			 * answer is nothing but up-traversals. Without this branch we would read $to[$depth] — and,
			 * once array_shift() had emptied $relPath, $relPath[0] as well — past the end of the array.
			 */
			if (!array_key_exists($depth, $to))
			{
				$relPath = array_fill(0, count($from) - count($to), '..');

				break;
			}

			// find first non-matching dir
			if ($dir === $to[ $depth ])
			{
				// ignore this directory
				array_shift($relPath);
			}
			else
			{
				// get number of remaining dirs to $from
				$remaining = count($from) - $depth;
				if ($remaining > 1)
				{
					// add traversals up to first matching dir
					$padLength = (count($relPath) + $remaining - 1) * - 1;
					$relPath   = array_pad($relPath, $padLength, '..');
					break;
				}
				else
				{
					$relPath[0] = './' . $relPath[0];
				}
			}
		}

		return implode('/', $relPath);
	}

	/**
	 * Get a dropdown list for database drivers
	 *
	 * @param   string  $selected  Selected value
	 * @param   string  $name      The name (also used for id) of the field, default: driver
	 *
	 * @return  string  HTML
	 */
	public static function engineDatabaseTypesSelect($selected = '', $name = 'driver')
	{
		$connectors = array('mysql', 'mysqli', 'none', 'pdomysql', 'sqlite');

		$html = '<select class="form-control" name="' . $name . '" id="' . $name . '">' . "\n";

		foreach($connectors as $connector)
		{
			$checked   = (strtoupper($selected) == strtoupper($connector)) ? 'selected="selected"' : '';

			$html .= "\t<option value=\"$connector\" $checked>" . Text::_('SOLO_SETUP_LBL_DATABASE_DRIVER_' . $connector) . "</option>\n";
		}

		$html .= "</select>";

		return $html;
	}

	/**
	 * Safely decode a return URL, used in the Backup view.
	 *
	 * Return URLs can have two sources:
	 * - The Backup on Update plugin. In this case the URL is base sixty four encoded and we need to decode it first.
	 * - A custom backend menu item. In this case the URL is a simple string which does not need decoding.
	 *
	 * Telling the two apart is guesswork, so the decode only sticks when the input is strictly valid
	 * base64, re-encodes to exactly what we were given, and decodes to something without control
	 * characters. Anything else is treated as a plain, undecoded URL rather than mangled into binary.
	 *
	 * Further to that, we have to make a few security checks:
	 * - The URL must be internal, i.e. starts with our site's base URL or index.php (this check is executed by Joomla)
	 * - It must not contain single quotes, double quotes, lower than or greater than signs (could be used to execute
	 *   arbitrary JavaScript).
	 *
	 * If any of these violations is detected we return an empty string.
	 *
	 * @param   ?string  $returnUrl
	 *
	 * @return  string
	 */
	static function safeDecodeReturnUrl($returnUrl)
	{
		// Nulls and non-strings are not allowed
		if (is_null($returnUrl) || !is_string($returnUrl))
		{
			return '';
		}

		// Make sure it's not an empty string
		$returnUrl = trim($returnUrl);

		if (empty($returnUrl))
		{
			return '';
		}

		/**
		 * Put back any '+' which travelling through a query string turned into a space.
		 *
		 * Return URLs are handed to us inside a query string, and a '+' there means a space. For 7-bit
		 * ASCII input only the characters '>' and '~' can ever make base64_encode() emit a '+', so this
		 * is narrow — but '~' does occur in URLs, and the old non-strict base64_decode() silently
		 * tolerated the corruption because it skipped the space. Strict decoding does not, so normalise
		 * before testing. Only the candidate is normalised; if the decode is rejected we still return
		 * the string exactly as it reached us.
		 */
		$candidate = strtr($returnUrl, ' ', '+');

		// Decode a base sixty four encoded string.
		$filter  = new Filter();
		$encoded = $filter->clean($candidate, 'base64');

		if (($candidate === $encoded) && (strpos($candidate, 'index.php') === false) && (strpos($candidate, 'akeebabackupwp') === false))
		{
			/**
			 * All three conditions below are load-bearing.
			 *
			 * Strict mode rejects anything which is not valid base64 — a plain relative path such as
			 * 'foo/bar' consists only of base64-alphabet characters and gets this far, but its length
			 * is not a multiple of four. Note that non-strict base64_decode() essentially never returns
			 * false, it just skips invalid characters, which is why strictness is what makes this test
			 * meaningful at all.
			 *
			 * The round trip rejects valid-but-non-canonical encodings, so what we hand back is only
			 * ever something that was genuinely base64 encoded in the first place.
			 *
			 * The control character test rejects alphabet-shaped strings which decode cleanly to
			 * binary — 'ABCD' decodes to the bytes 00 10 83. A return URL is text; a decode result
			 * carrying control characters is not one, so we discard the decode and judge the original
			 * string on its own merits.
			 */
			$possibleReturnUrl = base64_decode($candidate, true);

			if (($possibleReturnUrl !== false)
			    && (base64_encode($possibleReturnUrl) === $candidate)
			    && !preg_match('/[\x00-\x1F\x7F]/', $possibleReturnUrl))
			{
				$returnUrl = $possibleReturnUrl;
			}
		}

		// Check if it's an internal URL
		if (!Uri::isInternal($returnUrl))
		{
			return '';
		}

		$disallowedCharacters = ['"' ,"'", '>', '<'];

		foreach ($disallowedCharacters as $check)
		{
			if (strpos($returnUrl, $check) !== false)
			{
				return '';
			}
		}

		return $returnUrl;
	}

} 
