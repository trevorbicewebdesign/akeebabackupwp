<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Helper;

/**
 * A helper class to escape JSON data
 */
class Escape
{
	/**
	 * Escapes a string returned from Text::_() for use with Javascript
	 *
	 * @param   $string  string  The string to escape
	 * @param   $extras  string  The characters to escape
	 *
	 * @return  string  The escaped string
	 */
	static function escapeJS($string, $extras = '')
	{
		// Make sure we escape single quotes, slashes and brackets
		if (empty($extras))
		{
			$extras = "'\\[]\"";
		}

		return addcslashes($string, $extras);
	}
} 
