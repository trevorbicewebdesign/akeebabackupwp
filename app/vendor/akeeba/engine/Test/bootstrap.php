<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License version 3, or later
 *
 * This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, version 3.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program. If not, see
 * <https://www.gnu.org/licenses/>.
 */

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Load environment variables from an optional, git-ignored Test/.env file.
 *
 * This lets developers configure the integration tests (database connection, the misbehaving-file backup test, …)
 * without exporting variables into their shell every time. Variables already present in the real environment take
 * precedence over the ones defined in the file, so you can still override on a per-run basis.
 *
 * The format is a minimal subset of the usual dotenv syntax: one KEY=VALUE per line, blank lines and lines starting
 * with '#' are ignored, optional surrounding single or double quotes around the value are stripped, and an optional
 * leading "export " is allowed.
 */
(static function (string $envFile): void {
	if (!is_file($envFile) || !is_readable($envFile))
	{
		return;
	}

	foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line)
	{
		$line = trim($line);

		if ($line === '' || $line[0] === '#' || strpos($line, '=') === false)
		{
			continue;
		}

		if (strncmp($line, 'export ', 7) === 0)
		{
			$line = substr($line, 7);
		}

		[$key, $value] = array_map('trim', explode('=', $line, 2));

		if ($key === '')
		{
			continue;
		}

		// Strip a single pair of matching surrounding quotes
		if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && substr($value, -1) === $value[0])
		{
			$value = substr($value, 1, -1);
		}

		// Do not clobber variables already defined in the real environment
		if (getenv($key) !== false)
		{
			continue;
		}

		putenv($key . '=' . $value);
		$_ENV[$key]    = $value;
		$_SERVER[$key] = $value;
	}
})(__DIR__ . '/.env');

define('AKEEBAENGINE', 1);