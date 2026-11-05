<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Helper;

class PathContainment
{
	/**
	 * Is $path the same directory as $root, or a filesystem descendant of it?
	 *
	 * Both arguments must already be absolute paths with symlinks and `..` resolved (e.g. the result of
	 * realpath()). Path separators and trailing separators are normalised here; letter case is not folded. A naive
	 * strpos($path, $root) === 0 check is wrong: it also matches sibling directories that merely share $root as a
	 * string prefix, e.g. /var/www/html-backups is NOT inside /var/www/html even though the prefix matches.
	 *
	 * @param   string  $path  The candidate path, already normalised (e.g. via realpath()).
	 * @param   string  $root  The containing directory to test against, already normalised.
	 *
	 * @return  bool  True if $path is $root itself, or a descendant of it.
	 */
	public static function isUnderRoot(string $path, string $root): bool
	{
		// Windows accepts both separators, and the two arguments do not necessarily use the same one (e.g.
		// C:\site\backups vs C:/site). Compare using a single separator, whatever the host OS.
		$path = rtrim(str_replace('\\', '/', $path), '/');
		$root = rtrim(str_replace('\\', '/', $root), '/');

		return ($path === $root) || (strpos($path, $root . '/') === 0);
	}
}
