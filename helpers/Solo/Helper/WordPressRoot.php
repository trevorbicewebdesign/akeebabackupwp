<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Helper;

/**
 * Locates the WordPress installation the plugin lives in, when the plugin's own entry points run outside WordPress.
 *
 * WordPress' core directory (ABSPATH) is the one containing wp-settings.php. Unlike wp-config.php, which WordPress also
 * looks for one level up and which layouts such as Bedrock move elsewhere, wp-settings.php always sits in the core
 * directory. Sites which give WordPress its own directory (e.g. Bedrock's web/wp) keep the core one level below the
 * folder we would otherwise look in, so each candidate folder's immediate subfolders are checked as well.
 */
abstract class WordPressRoot
{
	/**
	 * Returns the first WordPress core directory found in, or right below, any of the candidate folders.
	 *
	 * @param   string[]  $candidates  Folders to look in, in order of preference
	 *
	 * @return  string|null  The WordPress core directory (ABSPATH), or null if none was found
	 */
	public static function find(array $candidates): ?string
	{
		foreach ($candidates as $candidate)
		{
			if (!is_string($candidate) || $candidate === '')
			{
				continue;
			}

			$candidate = rtrim($candidate, '/\\');

			if (@is_file($candidate . '/wp-settings.php'))
			{
				return $candidate;
			}

			foreach (@glob($candidate . '/*/wp-settings.php') ?: [] as $file)
			{
				return dirname($file);
			}
		}

		return null;
	}
}
