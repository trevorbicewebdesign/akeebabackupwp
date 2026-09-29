#!/usr/bin/env php
<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
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

/**
 * This script creates a big file which changes size over time.
 *
 * Usage:
 *
 * php makebigfile.php PATH MODE TIME
 *
 * Where
 * - PATH is the full filesystem path to the file
 * - MODE is the operating mode (grow, shrink or nuke)
 *
 * There are three modes:
 * - `grow`    A 15 MiB file grows by 64 KiB every TIME seconds until it reaches a size of 20 MiB.
 * - `shrink`  A 15 MiB file shrinks by 64 KiB every TIME seconds until it reaches a size of 10 MiB.
 * - `nuke`    A 15 MiB file disappears after TIME milliseconds.
 *
 * TIME is in milliseconds. The default is 120.
 *
 * In grow and shrink modes the file size change takes approximately 10 seconds ±10% giving us the opportunity to test
 * the engine code that catches the file size change.
 *
 * In nuke mode you have to select a TIME which will be smack middle during the file's backup resumption. This takes
 * some trial and error, as it depends on the machine you run this on.
 *
 * To use this file in testing, modify index.php and uncomment this line:
 * define('AKEEBA_DEBUG_BIG_FILE_MULTIPART_DELAY', 100000);
 * This adds a delay of 100000 microseconds (100 milliseconds) between backing up each chunk of the file. It is also
 * recommended to use a backup profile with timing settings min/max/bias 2/1/10 for some extra slowing down of the
 * backup, making sure that the intermediate step breaks and breaks between domains are NOT disabled. We want to
 * simulate a SLOW backup.
 *
 * Then, run something like this:
 *
 * ./makebigfile.php /var/www/multitest/big.dat grow & ./index.php backup:take --profile=3 "Just a test"
 *
 * ./makebigfile.php /var/www/multitest/big.dat nuke & ./index.php backup:take --profile=3 "Just a test"
 */

function getRandomData(int $size): string
{
	$fp   = fopen('/dev/urandom', 'r+');
	$data = fread($fp, $size);
	fclose($fp);

	return $data;
}

function writeRandomData(string $targetFile, int $size): void
{
	$data = getRandomData($size);

	$fp = fopen($targetFile, 'a');
	fwrite($fp, $data);
	fclose($fp);
}

function shrinkFile(string $targetFile, int $size): void
{
	$fp = fopen($targetFile, 'r+');
	fseek($fp, -$size, SEEK_END);
	ftruncate($fp, ftell($fp));
	fclose($fp);
}

global $argc, $argc;

$targetFile  = $argc > 1 ? $argv[1] : '/var/www/multitest/big.dat';
$mode        = $argc > 2 ? $argv[2] : 'grow';
$mode        = in_array($mode, ['grow', 'shrink', 'nuke']) ? $mode : 'grow';
$delay       = $argc > 3 ? $argv[3] : 120;
$initialSize = 15 * 1024 * 1024;
$maxSize     = 20 * 1024 * 1024;
$minSize     = 10 * 1024 * 1024;
$chunkSize   = 64 * 1024;

if (file_exists($targetFile))
{
	@unlink($targetFile);
}

writeRandomData($targetFile, $initialSize);

$start = microtime(true);

switch ($mode)
{
	default:
	case 'grow':
		while (file_exists($targetFile) && filesize($targetFile) < $maxSize)
		{
			writeRandomData($targetFile, $chunkSize);
			clearstatcache($targetFile);

			echo sprintf('%8u', filesize($targetFile)) . "\n";

			usleep(1000 * $delay);
		}

		break;

	case 'shrink':
		while (file_exists($targetFile) && filesize($targetFile) > $minSize)
		{
			shrinkFile($targetFile, $chunkSize);

			clearstatcache($targetFile);

			echo sprintf('%8u', filesize($targetFile)) . "\n";

			usleep(1000 * $delay);
		}

		break;

	case 'nuke':
		$seconds = floor($delay / 1000);
		$msec    = $delay % 1000;

		// time_nanosleep() did not work very well on Linux :(
		sleep($seconds);
		usleep($msec * 1000);

		clearstatcache($targetFile);

		if (file_exists($targetFile))
		{
			@unlink($targetFile);
		}
		break;
}

$end = microtime(true);

echo sprintf('Total time: %0.3f' . "\n\n", $end - $start);