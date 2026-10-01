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

namespace Akeeba\Engine\Test\Archiver;

use Akeeba\Engine\Archiver\Directftp;
use Akeeba\Engine\Archiver\Directftpcurl;
use Akeeba\Engine\Test\Stub\Util\Transfer\RecordingFtpTransfer;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Regression tests for the DirectFTP archiver's per-file upload logic.
 *
 * These guard the fix for the bug where Directftp::upload() ran chmod() against the *directory* it had just uploaded a
 * file into, setting it to 0644 and thereby stripping the owner-execute bit. That made the directory non-traversable,
 * so every subsequent file destined for the same directory failed to upload — killing the backup at (effectively) the
 * first file. The chmod must target the uploaded *file*, never its parent directory.
 *
 * The Directftpcurl archiver inherits upload() unchanged, so it is covered by the same data provider to prove the fix
 * applies to it too.
 *
 * The tests inject an in-memory RecordingFtpTransfer in place of a real FTP connection, so they need no server and run
 * deterministically in CI.
 *
 * @covers \Akeeba\Engine\Archiver\Directftp
 */
class DirectftpTest extends TestCase
{
	/** @var string Absolute path to a throw-away local source file used as upload content. */
	private $sourceFile;

	protected function setUp(): void
	{
		$this->sourceFile = tempnam(sys_get_temp_dir(), 'akeeba-directftp-test-');
		file_put_contents($this->sourceFile, 'test payload');
	}

	protected function tearDown(): void
	{
		if (is_string($this->sourceFile) && is_file($this->sourceFile))
		{
			@unlink($this->sourceFile);
		}
	}

	/**
	 * Both DirectFTP archiver classes, which share the same upload() implementation.
	 *
	 * @return array<string, array{0: class-string}>
	 */
	public static function archiverClassProvider(): array
	{
		return [
			'Directftp (PHP ext/ftp)' => [Directftp::class],
			'Directftpcurl (cURL)'    => [Directftpcurl::class],
		];
	}

	/**
	 * After uploading a file, chmod() must be applied to that file — never to the directory containing it.
	 *
	 * Before the fix, chmod() was called on the parent directory (e.g. "/installation") with mode 0644.
	 *
	 * @dataProvider archiverClassProvider
	 */
	public function testChmodTargetsTheUploadedFileNotItsDirectory(string $archiverClass): void
	{
		$transfer = new RecordingFtpTransfer();
		$archiver = $this->makeArchiver($archiverClass, $transfer);

		$archiver->addFileRenamed($this->sourceFile, 'installation/index.php');

		$this->assertNotEmpty($transfer->chmodCalls, 'The archiver should chmod the uploaded file.');

		foreach ($transfer->chmodCalls as $call)
		{
			$this->assertContains(
				$call['path'],
				$transfer->uploadedFiles,
				sprintf(
					'chmod() was called on "%s", which is not a file that was uploaded. The archiver must chmod the '
					. 'uploaded file, not a directory (the original bug chmod-ed the directory to 0644).',
					$call['path']
				)
			);
		}

		// Be explicit about the exact target for the single file we uploaded.
		$this->assertSame('/installation/index.php', $transfer->chmodCalls[0]['path']);
	}

	/**
	 * The directory a file is uploaded into must remain traversable (keep its owner-execute bit) afterwards.
	 *
	 * @dataProvider archiverClassProvider
	 */
	public function testParentDirectoryRemainsTraversableAfterUpload(string $archiverClass): void
	{
		$transfer = new RecordingFtpTransfer();
		$archiver = $this->makeArchiver($archiverClass, $transfer);

		$archiver->addFileRenamed($this->sourceFile, 'installation/index.php');

		$this->assertArrayHasKey('/installation', $transfer->directories);
		$this->assertTrue(
			($transfer->directories['/installation'] & 0100) !== 0,
			sprintf(
				'The "/installation" directory was left at mode 0%o, which lacks the owner-execute bit and is no '
				. 'longer traversable. Uploading a file must not change its parent directory permissions.',
				$transfer->directories['/installation']
			)
		);
	}

	/**
	 * Uploading several files into the same directory must all succeed.
	 *
	 * This reproduces the real-world symptom: the installer seeding writes many files under installation/. With the
	 * bug, the first upload bricked the directory and the second threw "Uploading … has failed".
	 *
	 * @dataProvider archiverClassProvider
	 */
	public function testMultipleFilesIntoSameDirectoryAllSucceed(string $archiverClass): void
	{
		$transfer = new RecordingFtpTransfer();
		$archiver = $this->makeArchiver($archiverClass, $transfer);

		$targets = [
			'installation/index.php',
			'installation/framework.php',
			'installation/sql/install.sql',
		];

		foreach ($targets as $target)
		{
			// addFileRenamed() throws a RuntimeException if an upload fails. With the bug, the second file under
			// installation/ would trigger exactly that.
			$archiver->addFileRenamed($this->sourceFile, $target);
		}

		$this->assertSame(
			['/installation/index.php', '/installation/framework.php', '/installation/sql/install.sql'],
			$transfer->uploadedFiles,
			'Every file destined for the same directory tree should upload successfully.'
		);
	}

	/**
	 * Build a Direct(ftp|ftpcurl) archiver wired to the in-memory transfer, bypassing the real FTP connection.
	 *
	 * We instantiate without invoking the constructor (which would try to talk to Factory/Platform) and set the
	 * protected state the upload path needs by reflection.
	 *
	 * @param   class-string           $archiverClass  Directftp::class or Directftpcurl::class.
	 * @param   RecordingFtpTransfer   $transfer       The fake transfer to inject.
	 *
	 * @return  Directftp
	 */
	private function makeArchiver(string $archiverClass, RecordingFtpTransfer $transfer): Directftp
	{
		$reflection = new ReflectionClass($archiverClass);

		/** @var Directftp $archiver */
		$archiver = $reflection->newInstanceWithoutConstructor();

		$this->setProtected($archiver, 'ftpTransfer', $transfer);
		$this->setProtected($archiver, 'initdir', '');

		return $archiver;
	}

	/**
	 * Set a protected/private property on an object, walking up the class hierarchy to find where it is declared.
	 */
	private function setProtected(object $object, string $property, $value): void
	{
		$reflection = new ReflectionClass($object);

		while (!$reflection->hasProperty($property) && $reflection->getParentClass())
		{
			$reflection = $reflection->getParentClass();
		}

		$prop = $reflection->getProperty($property);

		// setAccessible() is required on PHP < 8.1 and a deprecated no-op from 8.1 onwards.
		if (PHP_VERSION_ID < 80100)
		{
			$prop->setAccessible(true);
		}

		$prop->setValue($object, $value);
	}
}
