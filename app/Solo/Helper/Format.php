<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Helper;

abstract class Format
{
	/**
	 * Format a size in bytes in a human readable format (e.g. 1.2Gb)
	 *
	 * @param   integer             $sizeInBytes     The size to convert, in bytes
	 * @param   integer             $decimals        Accuracy, in decimal points (default: 2)
	 * @param   boolean|string|int  $force_unit      Force a particular unit? Either one of the names b, Kb, Mb, Gb, Tb,
	 *                                               Pb, Eb (case-insensitive), or the equivalent integer index 0 to 6,
	 *                                               or false for automatic determination of the best unit. An
	 *                                               unrecognised name falls back to automatic determination; an index
	 *                                               outside 0-6 is clamped into range.
	 * @param   string              $dec_char        Decimal separator character, default dot
	 * @param   string              $thousands_char  Thousands separator character, default none
	 *
	 * @return  string  The formatted number
	 */
	public static function fileSize($sizeInBytes, $decimals = 2, $force_unit = false, $dec_char = '.', $thousands_char = '')
	{
		if ($sizeInBytes <= 0)
		{
			return '-';
		}

		$units  = array('b', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB');
		$byName = array('B' => 0, 'KB' => 1, 'MB' => 2, 'GB' => 3, 'TB' => 4, 'PB' => 5, 'EB' => 6);

		if ($force_unit === false || $force_unit === null)
		{
			$unit = (int) floor(log($sizeInBytes, 2) / 10);
		}
		elseif (is_string($force_unit) && !is_numeric($force_unit))
		{
			// The documented form, e.g. 'Mb'. A name we do not know is not worth a fatal error in a display helper.
			$unit = $byName[strtoupper($force_unit)] ?? (int) floor(log($sizeInBytes, 2) / 10);
		}
		else
		{
			$unit = (int) $force_unit;
		}

		/**
		 * Never index past either end of $units, whatever the caller asked for.
		 *
		 * This is not only about hostile input: PHP_INT_MAX is roughly 8 EiB, so an ordinary integer
		 * size is enough to push the computed index to 6, and a float size past it entirely.
		 */
		$unit = max(0, min($unit, count($units) - 1));

		if ($unit == 0)
		{
			$decimals = 0;
		}

		return number_format($sizeInBytes / (1024 ** $unit), $decimals, $dec_char, $thousands_char) . ' ' . $units[$unit];

	}
} 
