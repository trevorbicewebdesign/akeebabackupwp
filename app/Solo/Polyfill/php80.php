<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * Polyfills for the PHP 8.0 string functions which our code and the backup engine call, for PHP 7.4 (our minimum
 * supported version).
 *
 * Loaded by Composer's autoloader (the "files" entry of app/composer.json), i.e. before anything else of ours runs, in
 * Akeeba Solo and in Akeeba Backup for WordPress alike. Every function is declared only if PHP (or WordPress, which has
 * been polyfilling these since 5.9) has not defined it already.
 */

if (!function_exists('str_contains'))
{
	function str_contains(string $haystack, string $needle): bool
	{
		return $needle === '' || strpos($haystack, $needle) !== false;
	}
}

if (!function_exists('str_starts_with'))
{
	function str_starts_with(string $haystack, string $needle): bool
	{
		return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
	}
}

if (!function_exists('str_ends_with'))
{
	function str_ends_with(string $haystack, string $needle): bool
	{
		return $needle === '' || ($haystack !== '' && substr_compare($haystack, $needle, -strlen($needle)) === 0);
	}
}
