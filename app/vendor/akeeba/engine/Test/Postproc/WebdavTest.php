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

namespace Akeeba\Engine\Test\Postproc;

use Akeeba\Engine\Postproc\Webdav;
use Akeeba\Engine\Test\AbstractEngineTestCase;
use Exception;
use RuntimeException;

/**
 * Unit tests for the WebDAV post-processing engine (engine/Postproc/Webdav.php).
 *
 * These tests exercise putFile()'s directory-existence probe in isolation by injecting a stubbed connector (so no real
 * cURL transport or WebDAV server is needed). They pin down two behaviours of that probe:
 *
 *   1. A propFind() failure whose HTTP status is NOT 404 must be re-thrown UNCHANGED — the original code and message
 *      have to survive so processPart() can log them and we can diagnose customer failures. This is a regression guard
 *      for the `throw new $e;` bug (which instantiated a fresh, blank exception of $e's class and silently discarded the
 *      original code and message, producing useless "WebDAV upload failed, 0:" log lines — see ATS ticket 43105, a
 *      Strato HiDrive WebDAV target). The fix is `throw $e;`.
 *   2. A 404 is still treated as "the directory does not exist yet", so the engine creates it (MKCOL) rather than
 *      re-throwing.
 *
 * @group postproc
 * @group webdav
 */
final class WebdavTest extends AbstractEngineTestCase
{
	/**
	 * A non-404 propFind() failure must propagate out of putFile() as the very same exception object, with its real code
	 * and message intact — not a blank re-instantiation with code 0 and an empty message.
	 */
	public function testPutFilePropagatesNon404FailureUnchanged(): void
	{
		$original  = new Exception('Strato HiDrive refused the request', 507);
		$connector = new WebdavTestConnector();

		// The directory-existence probe (propFind) fails with a non-404 status, exactly like a timeout, TLS error or a
		// 5xx from the WebDAV server.
		$connector->propFindException = $original;

		$engine = new WebdavTestDouble($connector);
		$engine->setDirectory('some/remote/dir');

		try
		{
			$engine->callPutFile(__FILE__, 'backup.jpa');

			$this->fail('putFile() must re-throw a non-404 propFind() failure.');
		}
		catch (Exception $e)
		{
			// The strongest guard against `throw new $e;`: the propagated exception must be the SAME object, not a fresh
			// instance of the same class.
			$this->assertSame(
				$original, $e,
				'putFile() must re-throw the original exception object, not a fresh instance of its class.'
			);

			$this->assertSame(
				507, $e->getCode(),
				'The original exception code must survive (a blank re-instantiation would report 0).'
			);

			$this->assertSame(
				'Strato HiDrive refused the request', $e->getMessage(),
				'The original exception message must survive (a blank re-instantiation would report an empty string).'
			);
		}

		// The failure happened while probing the directory, so no upload should have been attempted.
		$this->assertNotContains(
			'PUT', $connector->methodsCalled(),
			'putFile() must not attempt the upload after the directory probe fails.'
		);
	}

	/**
	 * A 404 from the directory-existence probe means "this directory does not exist yet", so the engine must create it
	 * (MKCOL) and then upload (PUT) rather than re-throwing.
	 */
	public function testPutFileTreats404AsMissingDirectoryAndCreatesIt(): void
	{
		$connector = new WebdavTestConnector();

		// Every propFind() reports the resource is missing.
		$connector->propFindException = new Exception('Not Found', 404);

		$engine = new WebdavTestDouble($connector);
		$engine->setDirectory('backups');

		$localFile = $this->makeLocalFile('test payload');

		try
		{
			$engine->callPutFile($localFile, 'backup.jpa');
		}
		finally
		{
			@unlink($localFile);
		}

		$methods = $connector->methodsCalled();

		$this->assertContains(
			'MKCOL', $methods,
			'A 404 from the directory probe must lead to the directory being created via MKCOL.'
		);
		$this->assertContains(
			'PUT', $methods,
			'Once the directory is (re)created the file must be uploaded via PUT.'
		);
	}

	/**
	 * The externally observable end-to-end path: processPart() swallows and retries the first two failures, then gives
	 * up on the third and throws a RuntimeException that wraps the ORIGINAL failure as its previous exception. That
	 * previous exception must still carry the real code and message so the failure can be diagnosed.
	 */
	public function testProcessPartSurfacesTheRealFailureAfterExhaustingRetries(): void
	{
		$original  = new Exception('TLS handshake timed out', 522);
		$connector = new WebdavTestConnector();

		$connector->propFindException = $original;

		$engine = new WebdavTestDouble($connector);
		$engine->setDirectory('remote/dir');

		// The first two attempts are swallowed and signalled as "retry" (false).
		$this->assertFalse(
			$engine->processPart(__FILE__, 'backup.jpa'),
			'The first upload failure should be retried, not thrown.'
		);
		$this->assertFalse(
			$engine->processPart(__FILE__, 'backup.jpa'),
			'The second upload failure should be retried, not thrown.'
		);

		try
		{
			$engine->processPart(__FILE__, 'backup.jpa');

			$this->fail('processPart() must throw once the retries are exhausted.');
		}
		catch (RuntimeException $e)
		{
			$previous = $e->getPrevious();

			$this->assertInstanceOf(
				Exception::class, $previous,
				'The give-up RuntimeException must chain the original failure as its previous exception.'
			);
			$this->assertSame(
				$original, $previous,
				'The chained previous exception must be the original object, not a blank re-instantiation.'
			);
			$this->assertSame(
				522, $previous->getCode(),
				'The real failure code must survive all the way to processPart() (not become 0).'
			);
			$this->assertSame(
				'TLS handshake timed out', $previous->getMessage(),
				'The real failure message must survive all the way to processPart() (not become an empty string).'
			);
		}
	}

	/**
	 * Write a throw-away local file with the given contents and return its absolute path. The caller is responsible for
	 * removing it.
	 *
	 * @param   string  $contents  The file contents.
	 *
	 * @return  string
	 */
	private function makeLocalFile(string $contents): string
	{
		$fileName = tempnam(sys_get_temp_dir(), 'akeeba-webdav-unit-');

		if ($fileName === false)
		{
			$this->fail('Could not create a temporary file for the WebDAV unit test.');
		}

		file_put_contents($fileName, $contents);

		return $fileName;
	}
}

/**
 * A test double for the WebDAV engine that lets us inject a stubbed connector and drive putFile() directly, without any
 * real configuration, cURL transport or WebDAV server.
 */
final class WebdavTestDouble extends Webdav
{
	/** @var object The stubbed connector returned by makeConnector(). */
	private $stubConnector;

	public function __construct($stubConnector)
	{
		parent::__construct();

		$this->stubConnector = $stubConnector;
	}

	/**
	 * Set the remote directory the engine would otherwise read from the backup profile configuration.
	 *
	 * @param   string  $directory  The remote directory.
	 *
	 * @return  void
	 */
	public function setDirectory(string $directory): void
	{
		$this->directory = $directory;
	}

	/**
	 * Public seam onto the protected putFile(), so the directory-existence probe can be tested in isolation.
	 *
	 * @param   string  $absolute_filename  The path to the local file which will be uploaded to WebDAV.
	 * @param   string  $basename           The remote base name.
	 *
	 * @return  array
	 *
	 * @throws  Exception
	 */
	public function callPutFile($absolute_filename, $basename)
	{
		return $this->putFile($absolute_filename, $basename);
	}

	protected function makeConnector()
	{
		return $this->stubConnector;
	}
}

/**
 * A minimal stand-in for the Davclient connector. propFind() throws whatever exception the test configures (to simulate
 * a 404 or a non-404 failure) and request() merely records the HTTP methods it was asked to perform.
 */
final class WebdavTestConnector
{
	/** @var Exception|null The exception propFind() should throw, or null to have it succeed. */
	public $propFindException = null;

	/** @var array<int,array> The list of [method, path] pairs passed to request(). */
	public $requests = [];

	/**
	 * @param   string  $url         The path being probed.
	 * @param   array   $properties  The requested WebDAV properties.
	 * @param   int     $depth       The PROPFIND depth.
	 *
	 * @return  array
	 *
	 * @throws  Exception  Whatever the test configured via $propFindException.
	 */
	public function propFind($url, array $properties, $depth = 0)
	{
		if ($this->propFindException !== null)
		{
			throw $this->propFindException;
		}

		return [];
	}

	/**
	 * @param   string       $method  The HTTP method (MKCOL, PUT, …).
	 * @param   string       $url     The target path.
	 * @param   string|null  $body    The optional request body.
	 *
	 * @return  array
	 */
	public function request($method, $url, $body = null)
	{
		$this->requests[] = [$method, $url];

		return [];
	}

	/**
	 * The HTTP methods request() was asked to perform, in order.
	 *
	 * @return  string[]
	 */
	public function methodsCalled(): array
	{
		return array_column($this->requests, 0);
	}
}
