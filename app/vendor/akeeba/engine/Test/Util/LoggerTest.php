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
use Akeeba\Engine\Util\Logger;
use ReflectionObject;

final class LoggerTest extends AbstractEngineTestCase
{
	private const TAG = 'unittest';

	/** @var string The output directory the log files are created in */
	private $outputDirectory;

	protected function setUp(): void
	{
		parent::setUp();

		$this->outputDirectory = sys_get_temp_dir() . '/akeeba-logger-test-' . bin2hex(random_bytes(6));

		mkdir($this->outputDirectory, 0777, true);

		Factory::getConfiguration()->set('akeeba.basic.output_directory', $this->outputDirectory, false);
		Factory::getConfiguration()->set('akeeba.basic.log_level', 4, false);
	}

	protected function tearDown(): void
	{
		$this->removeRecursive($this->outputDirectory);

		parent::tearDown();
	}

	public function testOpenCreatesALogPhpFile(): void
	{
		$logger = new Logger();
		$logger->open(self::TAG);
		$logger->debug('Hello world');
		$logger->close();

		$this->assertFileExists($this->path('.log.php'));
		$this->assertFileDoesNotExist($this->path('.php'));
		$this->assertFileDoesNotExist($this->path('.log'));
		$this->assertStringContainsString('Hello world', file_get_contents($this->path('.log.php')));
	}

	public function testOpenFallsBackToAPhpFileByDefault(): void
	{
		$this->blockLogFile('.log.php');

		$logger = new Logger();
		$logger->open(self::TAG);
		$logger->debug('Hello world');
		$logger->close();

		$this->assertFileExists($this->path('.php'));
		$this->assertFileDoesNotExist($this->path('.log'));
		$this->assertStringContainsString('Hello world', file_get_contents($this->path('.php')));
	}

	public function testOpenFallsBackToALogFileWhenAllowed(): void
	{
		$this->blockLogFile('.log.php');

		$logger = new Logger(true);
		$logger->open(self::TAG);
		$logger->debug('Hello world');
		$logger->close();

		$this->assertFileExists($this->path('.log'));
		$this->assertFileDoesNotExist($this->path('.php'));
		$this->assertStringContainsString('Hello world', file_get_contents($this->path('.log')));
	}

	public function testOpenPausesLoggingWhenNoLogFileCanBeCreated(): void
	{
		$this->blockLogFile('.log.php');
		$this->blockLogFile('.php');

		$logger = new Logger();
		$logger->open(self::TAG);
		$logger->debug('Hello world');

		$this->assertTrue($this->isPaused($logger), 'Logging must be paused when there is nowhere to write to');
		$this->assertFileDoesNotExist($this->path('.log'), 'A web accessible log file must not be created');
	}

	public function testResetCreatesAProtectedLogPhpFile(): void
	{
		$logger = new Logger();
		$logger->reset(self::TAG);

		$this->assertFileExists($this->path('.log.php'));
		$this->assertStringStartsWith('<' . '?php die();', file_get_contents($this->path('.log.php')));
		$this->assertFalse($this->isPaused($logger));
	}

	public function testResetFallsBackToAPhpFileByDefault(): void
	{
		$this->blockLogFile('.log.php');

		$logger = new Logger();
		$logger->reset(self::TAG);

		$this->assertFileExists($this->path('.php'));
		$this->assertFileDoesNotExist($this->path('.log'));
		$this->assertStringStartsWith('<' . '?php die();', file_get_contents($this->path('.php')));
	}

	public function testResetKeepsLoggingPausedWhenNoLogFileCanBeCreated(): void
	{
		$this->blockLogFile('.log.php');
		$this->blockLogFile('.php');

		$logger = new Logger();
		$logger->reset(self::TAG);

		$this->assertTrue($this->isPaused($logger), 'Logging must stay paused when there is nowhere to write to');
		$this->assertFileDoesNotExist($this->path('.log'), 'A web accessible log file must not be created');
	}

	public function testResetRemovesLogFilesLeftOverFromOtherFlavours(): void
	{
		file_put_contents($this->path('.php'), "stale\n");
		file_put_contents($this->path('.log'), "stale\n");

		$logger = new Logger();
		$logger->reset(self::TAG);

		$this->assertFileExists($this->path('.log.php'));
		$this->assertFileDoesNotExist($this->path('.php'));
		$this->assertFileDoesNotExist($this->path('.log'));
	}

	public function testGetLastTimestampFindsThePhpFlavour(): void
	{
		file_put_contents($this->path('.php'), "test\n");
		touch($this->path('.php'), 1234567890);

		$logger = new Logger();

		$this->assertSame(1234567890, $logger->getLastTimestamp(self::TAG));
	}

	public function testGetLastTimestampReturnsNullWithoutALogFile(): void
	{
		$logger = new Logger();

		$this->assertNull($logger->getLastTimestamp(self::TAG));
	}

	public function testGetAllLogFilenames(): void
	{
		$logger = new Logger();

		$this->assertSame(
			[$this->path('.log.php'), $this->path('.php'), $this->path('.log')],
			$logger->getAllLogFilenames(self::TAG)
		);
	}

	/**
	 * Returns the absolute path to the tagged log file with the given suffix
	 */
	private function path(string $suffix): string
	{
		return $this->outputDirectory . '/akeeba.' . self::TAG . $suffix;
	}

	/**
	 * Makes it impossible to create the log file with the given suffix.
	 *
	 * This emulates hosts such as WP Engine which refuse to let us write to files with a .php extension.
	 */
	private function blockLogFile(string $suffix): void
	{
		mkdir($this->path($suffix));
	}

	private function isPaused(Logger $logger): bool
	{
		$refProperty = (new ReflectionObject($logger))->getProperty('paused');
		$refProperty->setAccessible(true);

		return (bool) $refProperty->getValue($logger);
	}

	private function removeRecursive(string $path): void
	{
		if (!@is_dir($path))
		{
			@unlink($path);

			return;
		}

		foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry)
		{
			$this->removeRecursive($path . '/' . $entry);
		}

		@rmdir($path);
	}
}
