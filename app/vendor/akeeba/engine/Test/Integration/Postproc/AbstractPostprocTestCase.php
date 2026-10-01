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

namespace Akeeba\Engine\Test\Integration\Postproc;

use Akeeba\Engine\Factory;
use Akeeba\Engine\Postproc\PostProcInterface;
use Akeeba\Engine\Test\AbstractEngineTestCase;
use Akeeba\Engine\Util\RandomValue;
use Composer\CaBundle\CaBundle;
use RuntimeException;

/**
 * Blueprint for remote storage (post-processing) engine integration tests.
 *
 * Each concrete subclass targets one provider (BackBlaze B2, Amazon S3, …) and only has to answer a handful of
 * provider-specific questions: is it configured, how do we configure it, what is its minimum multipart chunk size, and
 * (optionally) how do we independently read back the size of a stored object. Everything else — generating random test
 * files, driving the real stepped upload through the engine's `processPart()` loop, downloading, verifying size and
 * SHA-512 checksum, deleting, and cleaning up — is handled here and is identical for every provider.
 *
 * The whole suite for a provider self-skips unless that provider is configured (see `isProviderConfigured()`), so the
 * default `vendor/bin/phpunit Test/` run never touches the network.
 *
 * We deliberately test through the uniform `PostProcInterface` (the production code path that the backup engine itself
 * uses) rather than the provider-specific connector. The connector differs per provider; the interface does not, which
 * is exactly what lets this single blueprint cover every provider.
 *
 * @group integration
 * @group postproc
 */
abstract class AbstractPostprocTestCase extends AbstractEngineTestCase
{
	/** @var int A 256 KiB file is comfortably below any provider's minimum chunk size, forcing a single-shot upload. */
	protected const SIZE_SMALL = 262144;

	/** @var string[] Absolute paths to local temporary files created during the test, removed on teardown. */
	private $localTempFiles = [];

	/** @var string[] Remote object paths uploaded during the test, removed on teardown. */
	private $remoteObjects = [];

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		// The connectors reference the AKEEBA_CACERT_PEM constant for cURL certificate verification. In production the
		// platform defines it; here we point it at the CA bundle shipped with composer/ca-bundle.
		if (!defined('AKEEBA_CACERT_PEM'))
		{
			define('AKEEBA_CACERT_PEM', CaBundle::getBundledCaBundlePath());
		}
	}

	protected function setUp(): void
	{
		parent::setUp();

		if (!$this->isProviderConfigured())
		{
			$this->markTestSkipped($this->getSkipMessage());
		}

		$this->localTempFiles = [];
		$this->remoteObjects  = [];

		// Select and configure the post-processing engine under test.
		Factory::getConfiguration()->set('akeeba.advanced.postproc_engine', $this->getEngineSlug());
		$this->configureProvider();
	}

	protected function tearDown(): void
	{
		// Best-effort removal of any remote objects this test created, so the bucket is left clean even on failure.
		foreach ($this->remoteObjects as $remotePath)
		{
			try
			{
				$this->getEngine()->delete($remotePath);
			}
			catch (\Throwable $e)
			{
				// Ignore: the object may already be gone, or the test may have failed before the engine was usable.
			}
		}

		foreach ($this->localTempFiles as $file)
		{
			if (is_file($file))
			{
				@unlink($file);
			}
		}

		$this->localTempFiles = [];
		$this->remoteObjects  = [];

		parent::tearDown();
	}

	/**
	 * A small file is uploaded in a single shot, then downloaded and verified byte-for-byte, then deleted.
	 */
	public function testSmallFileLifecycle(): void
	{
		$this->assertLifecycle(self::SIZE_SMALL, false);
	}

	/**
	 * A large file (more than twice the provider's minimum chunk size) exercises the multipart upload path, then is
	 * downloaded and verified byte-for-byte, then deleted.
	 *
	 * Providers whose engine always uploads in a single shot (no segmented/multipart support) override
	 * supportsMultipart() to return false; the file is still uploaded and verified byte-for-byte, but the
	 * "more than one processPart() step" assertion is skipped because it could never hold for them.
	 */
	public function testLargeFileLifecycle(): void
	{
		// Twice the minimum chunk size plus a slack chunk guarantees at least three data parts: a genuine multipart run.
		$size = 2 * $this->getMinimumPartSize() + intdiv($this->getMinimumPartSize(), 2);

		$this->assertLifecycle($size, $this->supportsMultipart());
	}

	/**
	 * Run the full upload → verify → download → verify → delete lifecycle for a file of the given size.
	 *
	 * @param   int   $size              Size of the test file, in bytes.
	 * @param   bool  $expectMultipart   When true, assert the upload actually took more than one `processPart()` step.
	 */
	private function assertLifecycle(int $size, bool $expectMultipart): void
	{
		$engine     = $this->getEngine();
		$localFile  = $this->createTestFile($size);
		$localSize  = filesize($localFile);
		$remoteName = $this->uniqueRemoteName($size);

		// --- Upload (real stepped processPart loop) ---------------------------------------------------------------
		$steps               = $this->uploadViaEngine($engine, $localFile, $remoteName);
		$remotePath          = $engine->getRemotePath();
		$this->remoteObjects[] = $remotePath;

		$this->assertNotEmpty($remotePath, 'The engine should report the remote path of the uploaded file.');

		if ($expectMultipart)
		{
			$this->assertGreaterThan(
				1, $steps,
				'A large file should be uploaded over multiple processPart() steps (multipart upload).'
			);
		}

		// --- Independently verify the stored size matches the local file ------------------------------------------
		// A non-null result proves the provider supports independent size queries; we use that fact below to also
		// assert the object is actually gone after deletion. (null means the provider opted out of this extra check.)
		$remoteSize         = $this->getRemoteSize($remotePath);
		$sizeCheckSupported = $remoteSize !== null;

		if ($sizeCheckSupported)
		{
			$this->assertSame(
				$localSize, $remoteSize,
				sprintf('The size stored remotely (%d) must match the local file size (%d).', $remoteSize, $localSize)
			);
		}

		// --- Download and verify size + SHA-512 -------------------------------------------------------------------
		$downloaded = $this->createTempFileName();
		$engine->downloadToFile($remotePath, $downloaded);

		$this->assertFilesEqual($localFile, $downloaded);

		// --- Delete and confirm it is gone ------------------------------------------------------------------------
		$engine->delete($remotePath);
		$this->remoteObjects = array_values(array_diff($this->remoteObjects, [$remotePath]));

		if ($sizeCheckSupported)
		{
			$this->assertNull(
				$this->getRemoteSize($remotePath),
				'The remote object should no longer exist after it has been deleted.'
			);
		}
	}

	/**
	 * Drive a complete upload through the engine's `processPart()` state machine.
	 *
	 * `processPart()` returns false while more multipart chunks remain and true once the upload is complete; this mirrors
	 * how the backup engine itself uploads a file across multiple steps. Between steps the engine persists its state in
	 * the shared Configuration registry, which is preserved here because we reuse the same Factory across the loop.
	 *
	 * @param   PostProcInterface  $engine      The engine under test.
	 * @param   string             $localFile   Absolute path to the local file to upload.
	 * @param   string             $remoteName  Base name to store the file under, relative to the configured directory.
	 *
	 * @return  int  The number of `processPart()` steps it took (1 for a single-shot upload, more for multipart).
	 */
	protected function uploadViaEngine(PostProcInterface $engine, string $localFile, string $remoteName): int
	{
		$steps   = 0;
		$maxSteps = 10000;

		do
		{
			$done = $engine->processPart($localFile, $remoteName);
			$steps++;

			if ($steps > $maxSteps)
			{
				throw new RuntimeException('The upload did not complete within a sane number of steps.');
			}
		}
		while ($done === false);

		return $steps;
	}

	/**
	 * Upload a throw-away random file through the engine and return its details. Useful for provider-specific connector
	 * tests (signed URLs, file listings, …) that need an object to exist remotely. The object is registered for
	 * automatic deletion on teardown.
	 *
	 * @param   int  $size  Size of the file to upload, in bytes.
	 *
	 * @return  array{0:string,1:string}  [remotePath, localFilePath]
	 */
	protected function uploadTemporaryObject(int $size = self::SIZE_SMALL): array
	{
		$engine     = $this->getEngine();
		$localFile  = $this->createTestFile($size);
		$remoteName = $this->uniqueRemoteName($size);

		$this->uploadViaEngine($engine, $localFile, $remoteName);

		$remotePath            = $engine->getRemotePath();
		$this->remoteObjects[] = $remotePath;

		return [$remotePath, $localFile];
	}

	/**
	 * Register a remote object path for best-effort deletion on teardown.
	 *
	 * Connector-level tests that create remote objects directly (bypassing the engine's processPart loop) should call
	 * this so the object is cleaned up even if the test fails. Provider tests must not reach into the private
	 * bookkeeping; this is the supported seam.
	 *
	 * @param   string  $remotePath  The remote object path, as the engine's delete() expects it.
	 */
	protected function registerRemoteObjectForCleanup(string $remotePath): void
	{
		$this->remoteObjects[] = $remotePath;
	}

	/**
	 * Return the configured post-processing engine instance.
	 *
	 * @return  PostProcInterface
	 */
	protected function getEngine(): PostProcInterface
	{
		return Factory::getPostprocEngine($this->getEngineSlug());
	}

	/**
	 * Build a remote object base name that is unique to this process and run, so concurrent or repeated runs against the
	 * same bucket never collide.
	 *
	 * @param   int  $size  Size of the file, used only to make the name self-describing.
	 *
	 * @return  string
	 */
	protected function uniqueRemoteName(int $size): string
	{
		return sprintf('engine-test-%d-%d-%s.bin', $size, getmypid(), uniqid());
	}

	/**
	 * Assert that two files are byte-for-byte identical (same size and same SHA-512 checksum).
	 *
	 * @param   string  $referenceFile  Absolute path to the reference file (the local original).
	 * @param   string  $fileToCheck    Absolute path to the file to check (the downloaded copy).
	 */
	protected function assertFilesEqual(string $referenceFile, string $fileToCheck): void
	{
		clearstatcache(true, $referenceFile);
		clearstatcache(true, $fileToCheck);

		$this->assertFileExists($referenceFile, 'The reference file must exist.');
		$this->assertFileExists($fileToCheck, 'The downloaded file must exist.');

		$this->assertSame(
			filesize($referenceFile), filesize($fileToCheck),
			'The downloaded file size must match the original file size.'
		);

		$this->assertSame(
			hash_file('sha512', $referenceFile), hash_file('sha512', $fileToCheck),
			'The downloaded file SHA-512 checksum must match the original file checksum.'
		);
	}

	// --------------------------------------------------------------------------------------------------------------
	// Random test-file generation (ported from connector_development AbstractCommand)
	// --------------------------------------------------------------------------------------------------------------

	/**
	 * Create a temporary file of the requested size filled with random data. It is removed automatically on teardown.
	 *
	 * @param   int  $size  Size in bytes.
	 *
	 * @return  string  Absolute path to the created file.
	 */
	protected function createTestFile(int $size): string
	{
		$fileName = $this->createTempFileName();
		$this->createRandomFile($fileName, $size);

		return $fileName;
	}

	/**
	 * Return a fresh temporary file name (the file is created empty) tracked for automatic removal on teardown.
	 *
	 * @return  string
	 */
	protected function createTempFileName(): string
	{
		$fileName = tempnam(sys_get_temp_dir(), 'akeeba-postproc-test-');

		if ($fileName === false)
		{
			throw new RuntimeException('Could not create a temporary file for the post-processing test.');
		}

		$this->localTempFiles[] = $fileName;

		return $fileName;
	}

	/**
	 * Fill a file with $size bytes of random data.
	 *
	 * On Linux/macOS with exec/shell_exec available we use `dd` reading from /dev/urandom (fast, low memory). Otherwise
	 * we fall back to repeatedly writing a 16 KiB block of random data generated by the engine's RandomValue utility.
	 *
	 * @param   string  $fileName  Absolute path to the file to (over)write.
	 * @param   int     $size      Size in bytes.
	 */
	private function createRandomFile(string $fileName, int $size): void
	{
		$isWindows    = DIRECTORY_SEPARATOR === '\\';
		$hasShellExec = function_exists('shell_exec');
		$hasExec      = function_exists('exec');

		if (!$isWindows && ($hasShellExec || $hasExec) && is_readable('/dev/urandom'))
		{
			[$blockSize, $count] = $this->blockSizeAndCount($size);

			$cmd = sprintf(
				'dd if=/dev/urandom of=%s bs=%u count=%u 2>/dev/null',
				escapeshellarg($fileName), $blockSize, $count
			);

			if ($hasShellExec)
			{
				shell_exec($cmd);
			}
			else
			{
				exec($cmd);
			}

			clearstatcache(true, $fileName);

			if (is_file($fileName) && filesize($fileName) === $size)
			{
				return;
			}
		}

		$this->createRandomFileTheHardWay($fileName, $size);
	}

	/**
	 * Pick a `dd` block size and count that reproduces $size exactly.
	 *
	 * For small files a single block is fine. For larger files we look for the largest block size out of a handful of
	 * candidates that divides $size exactly, falling back to a 1-byte block (always exact) if none does.
	 *
	 * @param   int  $size  Size in bytes.
	 *
	 * @return  array{0:int,1:int}  [blockSize, count]
	 */
	private function blockSizeAndCount(int $size): array
	{
		if ($size <= 5242880)
		{
			return [$size, 1];
		}

		foreach ([10485760, 5242880, 2621440, 1048576, 524288] as $blockSize)
		{
			if ($size % $blockSize === 0)
			{
				return [$blockSize, intdiv($size, $blockSize)];
			}
		}

		return [1, $size];
	}

	/**
	 * Fill a file with random data without relying on external commands.
	 *
	 * @param   string  $fileName  Absolute path to the file to (over)write.
	 * @param   int     $size      Size in bytes.
	 */
	private function createRandomFileTheHardWay(string $fileName, int $size): void
	{
		$randVal = new RandomValue();
		$buffer  = $randVal->generate(16384);

		$fp = fopen($fileName, 'w');

		if ($fp === false)
		{
			throw new RuntimeException(sprintf('Cannot create temporary file %s', $fileName));
		}

		$remaining = $size;

		while ($remaining > 0)
		{
			$chunk   = ($remaining < strlen($buffer)) ? substr($buffer, 0, $remaining) : $buffer;
			$written = fwrite($fp, $chunk);

			if ($written === false || $written === 0)
			{
				break;
			}

			$remaining -= $written;
		}

		fclose($fp);
	}

	// --------------------------------------------------------------------------------------------------------------
	// Provider-specific hooks
	// --------------------------------------------------------------------------------------------------------------

	/**
	 * The slug identifying the post-processing engine, e.g. 'backblaze'. Maps to engine/Postproc/<Ucfirst>.php.
	 *
	 * @return  string
	 */
	abstract protected function getEngineSlug(): string;

	/**
	 * Whether the provider has been configured (all required credentials present in the environment).
	 *
	 * @return  bool
	 */
	abstract protected function isProviderConfigured(): bool;

	/**
	 * The message shown when the provider is not configured and the suite self-skips.
	 *
	 * @return  string
	 */
	abstract protected function getSkipMessage(): string;

	/**
	 * Set the `engine.postproc.<slug>.*` configuration keys (credentials, bucket, chunk size, …) on the active
	 * Configuration, using Factory::getConfiguration()->set().
	 *
	 * @return  void
	 */
	abstract protected function configureProvider(): void;

	/**
	 * The provider's minimum multipart chunk size, in bytes. Used to size the large-file test.
	 *
	 * @return  int
	 */
	abstract protected function getMinimumPartSize(): int;

	/**
	 * Independently read back the size of a stored object, bypassing the engine, to confirm the upload stored exactly
	 * the right number of bytes. Return null to skip this extra check (the download size/checksum check still applies).
	 *
	 * @param   string  $remotePath  The remote object path, as reported by the engine's getRemotePath().
	 *
	 * @return  int|null  The stored size in bytes, null if the object does not exist or the check is unsupported.
	 */
	abstract protected function getRemoteSize(string $remotePath): ?int;

	/**
	 * Whether the provider's engine uploads large files over multiple processPart() steps (segmented/multipart).
	 *
	 * Defaults to true. Providers whose engine always uploads in a single shot — e.g. Box, RackSpace CloudFiles, and
	 * the S3-interoperability Google Storage engine (which hard-disables multipart) — override this to return false so
	 * testLargeFileLifecycle() still verifies byte-for-byte fidelity but does not assert a multi-step upload.
	 *
	 * @return  bool
	 */
	protected function supportsMultipart(): bool
	{
		return true;
	}
}
