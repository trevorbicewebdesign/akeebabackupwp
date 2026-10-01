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

namespace Akeeba\Engine\Test\Util;

use Akeeba\Engine\Factory;
use Akeeba\Engine\Test\AbstractEngineTestCase;
use Akeeba\Engine\Util\Statistics;

final class StatisticsTest extends AbstractEngineTestCase
{
	/** @var string Temp directory created per-test */
	private $tempDir;

	/** @var array Files created per-test, to be cleaned up */
	private $tempFiles = [];

	protected function setUp(): void
	{
		parent::setUp();

		// Create a unique temp directory for each test
		$this->tempDir   = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'akeebatest_' . uniqid('', true);
		$this->tempFiles = [];

		if (!is_dir($this->tempDir))
		{
			mkdir($this->tempDir, 0755, true);
		}
	}

	protected function tearDown(): void
	{
		// Remove all created temp files
		foreach ($this->tempFiles as $file)
		{
			if (file_exists($file))
			{
				@unlink($file);
			}
		}

		// Remove the temp directory
		if (is_dir($this->tempDir))
		{
			@rmdir($this->tempDir);
		}

		parent::tearDown();
	}

	/**
	 * Creates a temp file in the test's temp directory and tracks it for cleanup.
	 */
	private function createTempFile($filename)
	{
		$path = $this->tempDir . DIRECTORY_SEPARATOR . $filename;
		file_put_contents($path, '');
		$this->tempFiles[] = $path;

		return $path;
	}

	// ===== filesexist = 0 tests =====

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\StatisticsProvider::filesExistZeroProvider()
	 */
	public function testFilesExistZeroAlwaysReturnsEmptyArray($stat, $skipNonComplete, $expected)
	{
		$result = Statistics::get_all_filenames($stat, $skipNonComplete);
		$this->assertSame($expected, $result);
	}

	// ===== empty archivename tests =====

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\StatisticsProvider::emptyArchiveNameProvider()
	 */
	public function testEmptyArchiveNameReturnsNull($stat, $skipNonComplete, $expected)
	{
		$result = Statistics::get_all_filenames($stat, $skipNonComplete);
		$this->assertSame($expected, $result);
	}

	// ===== Single-part backup (multipart = 0 or 1) =====

	public function testSinglePartMultipartZeroReturnsOneFile()
	{
		$archiveName = 'backup.jpa';
		$this->createTempFile($archiveName);

		$stat = [
			'filesexist'    => 1,
			'absolute_path' => $this->tempDir . DIRECTORY_SEPARATOR . $archiveName,
			'archivename'   => $archiveName,
			'multipart'     => 0,
			'status'        => 'complete',
		];

		$result = Statistics::get_all_filenames($stat, true);

		$this->assertIsArray($result);
		$this->assertCount(1, $result);
		$this->assertSame($this->tempDir . DIRECTORY_SEPARATOR . $archiveName, $result[0]);
	}

	public function testSinglePartMultipartOneReturnsOneFile()
	{
		$archiveName = 'backup.jpa';
		$this->createTempFile($archiveName);

		$stat = [
			'filesexist'    => 1,
			'absolute_path' => $this->tempDir . DIRECTORY_SEPARATOR . $archiveName,
			'archivename'   => $archiveName,
			'multipart'     => 1,
			'status'        => 'complete',
		];

		$result = Statistics::get_all_filenames($stat, true);

		$this->assertIsArray($result);
		$this->assertCount(1, $result);
		$this->assertSame($this->tempDir . DIRECTORY_SEPARATOR . $archiveName, $result[0]);
	}

	// ===== Multipart backup filename-naming scheme =====

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\StatisticsProvider::multipartNamingProvider()
	 */
	public function testMultipartNamingScheme($archiveName, $multipart, $expectedFilenames)
	{
		// Create all expected files in the temp directory
		foreach ($expectedFilenames as $fn)
		{
			$this->createTempFile($fn);
		}

		$stat = [
			'filesexist'    => 1,
			'absolute_path' => $this->tempDir . DIRECTORY_SEPARATOR . $archiveName,
			'archivename'   => $archiveName,
			'multipart'     => $multipart,
			'status'        => 'complete',
		];

		$result = Statistics::get_all_filenames($stat, true);

		$this->assertIsArray($result);

		// Build expected absolute paths; source code puts main file first, then parts
		$expectedAbsolute = [];
		foreach ($expectedFilenames as $fn)
		{
			$expectedAbsolute[] = $this->tempDir . DIRECTORY_SEPARATOR . $fn;
		}

		// Sort both for stable comparison (source uses array_unique, order may vary)
		sort($result);
		sort($expectedAbsolute);

		$this->assertSame($expectedAbsolute, $result);
	}

	// ===== $skipNonComplete behavior =====

	public function testSkipNonCompleteTrueReturnsNullWhenNoFilesFound()
	{
		// Use a non-existent directory so no files are found
		$stat = [
			'filesexist'    => 1,
			'absolute_path' => '/nonexistent/dir/backup.jpa',
			'archivename'   => 'backup.jpa',
			'multipart'     => 0,
			'status'        => 'complete',
		];

		// Also make sure the configured output directory doesn't have the file
		// The TestPlatform uses sys_get_temp_dir() as root, but Factory config
		// akeeba.basic.output_directory defaults to empty or a directory without the file
		$registry = Factory::getConfiguration();
		$registry->set('akeeba.basic.output_directory', '/nonexistent/output_dir');

		$result = Statistics::get_all_filenames($stat, true);
		$this->assertNull($result);
	}

	public function testSkipNonCompleteFalseReturnsEmptyArrayWhenNoFilesFound()
	{
		$stat = [
			'filesexist'    => 1,
			'absolute_path' => '/nonexistent/dir/backup.jpa',
			'archivename'   => 'backup.jpa',
			'multipart'     => 0,
			'status'        => 'complete',
		];

		$registry = Factory::getConfiguration();
		$registry->set('akeeba.basic.output_directory', '/nonexistent/output_dir');

		$result = Statistics::get_all_filenames($stat, false);
		$this->assertSame([], $result);
	}

	// ===== File found in configured output directory (fallback) =====

	public function testFallbackToOutputDirectoryWhenPrimaryPathMissing()
	{
		$archiveName = 'backup_fallback.jpa';
		$outputDir   = $this->tempDir;

		// Create the file in what will be the configured output directory
		$this->createTempFile($archiveName);

		$stat = [
			'filesexist'    => 1,
			'absolute_path' => '/nonexistent/dir/' . $archiveName,
			'archivename'   => $archiveName,
			'multipart'     => 0,
			'status'        => 'complete',
		];

		$registry = Factory::getConfiguration();
		$registry->set('akeeba.basic.output_directory', $outputDir);

		$result = Statistics::get_all_filenames($stat, true);

		$this->assertIsArray($result);
		$this->assertCount(1, $result);
		$this->assertSame($outputDir . DIRECTORY_SEPARATOR . $archiveName, $result[0]);
	}

	// ===== 'run' status edge case =====

	public function testRunningBackupBruteForcesSinglePartFile()
	{
		$archiveName = 'running_backup.jpa';
		$this->createTempFile($archiveName);

		$stat = [
			'filesexist'    => 1,
			'absolute_path' => $this->tempDir . DIRECTORY_SEPARATOR . $archiveName,
			'archivename'   => $archiveName,
			'multipart'     => 0,
			'status'        => 'run',
		];

		$result = Statistics::get_all_filenames($stat, true);

		$this->assertIsArray($result);

		// Should contain at least the main archive file
		$this->assertContains($this->tempDir . DIRECTORY_SEPARATOR . $archiveName, $result);
	}

	// ===== Multipart: partial files (some parts missing) =====

	public function testMultipartWithSomePartsMissing()
	{
		// Declare multipart=3, but only create the .j01 part file (main jpa and .j02 are missing)
		// The test_file for multipart check is $filenames[1] = .j01
		// If .j01 exists but .jpa doesn't, we get only .j01 in result
		$archiveName = 'partial.jpa';
		$part1       = 'partial.j01';

		// Only create .j01, not the main .jpa or .j02
		$this->createTempFile($part1);

		$stat = [
			'filesexist'    => 1,
			'absolute_path' => $this->tempDir . DIRECTORY_SEPARATOR . $archiveName,
			'archivename'   => $archiveName,
			'multipart'     => 3,
			'status'        => 'complete',
		];

		$result = Statistics::get_all_filenames($stat, false);

		// Result should be an array with only the files that exist
		$this->assertIsArray($result);
		$this->assertContains($this->tempDir . DIRECTORY_SEPARATOR . $part1, $result);
		$this->assertNotContains($this->tempDir . DIRECTORY_SEPARATOR . $archiveName, $result);
	}

	// ===== filesexist with non-zero value treated as truthy =====

	public function testFilesExistNonZeroIsNotShortCircuited()
	{
		// filesexist = 1 means "has files", so the method proceeds normally
		// We verify it doesn't return [] immediately like filesexist=0 would
		$archiveName = 'valid.jpa';
		$this->createTempFile($archiveName);

		$stat = [
			'filesexist'    => 1,
			'absolute_path' => $this->tempDir . DIRECTORY_SEPARATOR . $archiveName,
			'archivename'   => $archiveName,
			'multipart'     => 0,
			'status'        => 'complete',
		];

		$result = Statistics::get_all_filenames($stat, true);

		// Should not be an empty array (the filesexist=0 shortcut)
		$this->assertNotSame([], $result);
		$this->assertIsArray($result);
		$this->assertCount(1, $result);
	}

	// ===== ZIP multipart naming =====

	public function testZipMultipartProducesCorrectExtensions()
	{
		$archiveName = 'backup.zip';
		$part1       = 'backup.z01';
		$part2       = 'backup.z02';

		$this->createTempFile($archiveName);
		$this->createTempFile($part1);
		$this->createTempFile($part2);

		$stat = [
			'filesexist'    => 1,
			'absolute_path' => $this->tempDir . DIRECTORY_SEPARATOR . $archiveName,
			'archivename'   => $archiveName,
			'multipart'     => 3,
			'status'        => 'complete',
		];

		$result = Statistics::get_all_filenames($stat, true);

		$this->assertIsArray($result);

		$expected = [
			$this->tempDir . DIRECTORY_SEPARATOR . $archiveName,
			$this->tempDir . DIRECTORY_SEPARATOR . $part1,
			$this->tempDir . DIRECTORY_SEPARATOR . $part2,
		];

		sort($result);
		sort($expected);

		$this->assertSame($expected, $result);
	}
}
