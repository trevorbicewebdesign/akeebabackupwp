<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Helper;
use Awf\Input\Filter;
use Awf\Text\Text;
use Awf\Uri\Uri;

/**
 * Various utility methods
 */
class Utils
{
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

		// Decode a base sixty four encoded string.
		$filter  = new Filter();
		$encoded = $filter->clean($returnUrl, 'base64');

		if (($returnUrl == $encoded) && (strpos($returnUrl, 'index.php') === false) && (strpos($returnUrl, 'akeebabackupwp') === false))
		{
			$possibleReturnUrl = base64_decode($returnUrl);

			if ($possibleReturnUrl !== false)
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
