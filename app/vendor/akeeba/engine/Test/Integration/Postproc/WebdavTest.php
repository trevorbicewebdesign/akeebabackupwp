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
use Akeeba\Engine\Postproc\Connector\Davclient;
use Akeeba\Engine\Postproc\Exception\RangeDownloadNotSupported;

/**
 * Integration test for the WebDAV post-processing engine against a local, ephemeral WebDAV server running in Docker.
 *
 * This proves the WebDAV engine (engine/Postproc/Webdav.php) works against a real WebDAV server — it creates the remote
 * directory (MKCOL), uploads (PUT), downloads (GET) and deletes (DELETE) — without needing any external account, so it
 * can run on any developer machine or CI runner that has Docker. The full upload / download / delete lifecycle is
 * verified byte-for-byte (size + SHA-512) for a small file and a large file.
 *
 * The WebDAV engine always uploads each file in a single PUT (no segmented/multipart upload), so supportsMultipart() is
 * false: the large-file test still verifies byte-for-byte fidelity over a multi-megabyte PUT but does not assert a
 * multi-step upload.
 *
 * Lifecycle of the throw-away WebDAV container is managed entirely by this class:
 *   - setUpBeforeClass() pulls/starts `rclone/rclone` (overridable via WEBDAV_IMAGE) running `serve webdav`, publishes
 *     its port to a random local host port, and waits for the server to answer an authenticated request.
 *   - tearDownAfterClass() force-removes the container, leaving nothing behind.
 *
 * `rclone/rclone` is used because it is published as a genuine multi-architecture image (linux/amd64 AND linux/arm64),
 * so it runs natively on Apple Silicon without Rosetta/QEMU emulation, and it serves WebDAV with Basic auth from CLI
 * flags alone (no config file or volume mount needed).
 *
 * No remote directory has to be pre-created: the engine's putFile() issues the MKCOL itself when it finds the configured
 * directory missing, so that path is exercised by the test rather than bypassed.
 *
 * GATING — the whole suite self-skips unless BOTH are true:
 *   1. WEBDAV_TEST is set to a truthy value (explicit opt-in, so the default `vendor/bin/phpunit Test/` run never spins
 *      up a container), and
 *   2. the `docker` CLI exists and its daemon responds.
 * If either is missing, every test reports skipped with a clear reason.
 *
 * Connector coverage map (Akeeba\Engine\Postproc\Connector\Davclient — scoped to what THIS engine uses, plus the
 * informational methods, against a plain-HTTP Basic-auth server):
 *   COVERED: request() for PUT/GET/DELETE/MKCOL/PROPFIND (through the engine's processPart/downloadToFile/delete and the
 *            directory-creation logic), propFind() (independent size read-back and directory listing), options() (DAV
 *            capability advertisement).
 *   NOT COVERED: propPatch() (the engine never sets WebDAV properties); Digest authentication (the Davclient offers both
 *            Basic and Digest, but this server is configured for Basic over plain HTTP); proxy support; HTTPS/TLS peer
 *            verification (the container speaks plain HTTP). Engine path not covered: ranged downloads — the engine
 *            rejects them by design, which testRangedDownloadIsRejected() pins down.
 *
 * @group integration
 * @group postproc
 * @group webdav
 */
class WebdavTest extends AbstractPostprocTestCase
{
	/**
	 * @var int The WebDAV engine uploads each file in a single PUT, so there is no real minimum part size. This value
	 *          only sizes the "large file" test (2.5× this) to exercise a multi-megabyte single PUT.
	 */
	private const MINIMUM_PART_SIZE = 5242880;

	/** @var string Remote sub-directory all test objects are stored under (created by the engine via MKCOL). */
	private const TEST_DIRECTORY = 'akeeba-engine-test';

	/** @var string|null The Docker container ID of the running WebDAV server, null when none was started. */
	private static $containerId = null;

	/** @var string|null The host endpoint of the WebDAV server, e.g. "127.0.0.1:49160". */
	private static $endpoint = null;

	/** @var string|null Reason the whole suite is being skipped, null when the server is up and usable. */
	private static $skipReason = null;

	/** @var Davclient|null Independent WebDAV client used to read back stored object metadata. */
	private $verificationConnector;

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		self::$containerId = null;
		self::$endpoint    = null;
		self::$skipReason  = null;

		// 1. Explicit opt-in. Keeps the default test run from ever starting a container.
		if (!self::isTruthy(getenv('WEBDAV_TEST')))
		{
			self::$skipReason = 'The WebDAV integration test is opt-in. Set WEBDAV_TEST=1 (and have Docker running) to '
				. 'enable it (see Test/.env.sample).';

			return;
		}

		// 2. Docker must be installed and its daemon must respond.
		if (!self::canRunCommands())
		{
			self::$skipReason = 'PHP cannot execute external commands (exec/shell_exec are disabled), so the WebDAV '
				. 'Docker container cannot be managed.';

			return;
		}

		[$dockerOk] = self::runCommand('docker info');

		if (!$dockerOk)
		{
			self::$skipReason = 'Docker is not available (the `docker` CLI is missing or its daemon is not running), so '
				. 'the ephemeral WebDAV container cannot be started.';

			return;
		}

		try
		{
			self::startWebdavContainer();
		}
		catch (\Throwable $e)
		{
			self::stopWebdavContainer();

			self::$skipReason = 'Could not start the ephemeral WebDAV container: ' . $e->getMessage();
		}
	}

	public static function tearDownAfterClass(): void
	{
		self::stopWebdavContainer();

		self::$containerId = null;
		self::$endpoint    = null;
		self::$skipReason  = null;

		parent::tearDownAfterClass();
	}

	/**
	 * options() round-trips an OPTIONS request and parses the server's WebDAV feature list out of the `DAV:` header. A
	 * real WebDAV server advertises at least DAV class 1.
	 *
	 * This also guards the Davclient header-folding fix: a server may split its capabilities across several `DAV:`
	 * headers (Apache mod_dav does), which the parser now folds into one comma-joined value instead of keeping only the
	 * last. The folding itself is unit-tested in Test/Postproc/Connector/Davclient/ParseHeadersTest.
	 */
	public function testOptionsAdvertisesWebDav(): void
	{
		$features = $this->getVerificationConnector()->options();

		$this->assertIsArray($features);
		$this->assertContains(
			'1', $features,
			'The server should advertise DAV class 1 in its OPTIONS response (it is a WebDAV server).'
		);
	}

	/**
	 * propFind() with depth 1 lists the contents of a directory. We upload an object and assert it shows up.
	 */
	public function testPropFindListsUploadedObject(): void
	{
		[$remotePath] = $this->uploadTemporaryObject();

		$listing = $this->getVerificationConnector()->propFind(
			'/' . self::TEST_DIRECTORY . '/', ['{DAV:}getcontentlength'], 1
		);

		$this->assertIsArray($listing);

		$basename = basename($remotePath);
		$found    = false;

		foreach (array_keys($listing) as $href)
		{
			if (basename(rtrim($href, '/')) === $basename)
			{
				$found = true;

				break;
			}
		}

		$this->assertTrue(
			$found,
			sprintf('The uploaded object %s should appear in the directory listing.', $basename)
		);
	}

	/**
	 * The WebDAV engine does not support ranged downloads and must reject them rather than silently returning the whole
	 * file. This pins down that documented behaviour (engine/Postproc/Webdav.php::downloadToFile()).
	 */
	public function testRangedDownloadIsRejected(): void
	{
		[$remotePath] = $this->uploadTemporaryObject();

		$this->expectException(RangeDownloadNotSupported::class);

		$this->getEngine()->downloadToFile($remotePath, $this->createTempFileName(), 0, 1024);
	}

	/**
	 * Regression guard for the `throw new $e;` bug in Webdav::putFile() (fixed to `throw $e;`).
	 *
	 * Before uploading, putFile() probes whether the configured remote directory exists via propFind(). A 404 means
	 * "create it"; any OTHER failure has to be re-thrown UNCHANGED so its real code and message reach processPart()'s
	 * logging and, ultimately, us — otherwise WebDAV upload failures are undiagnosable (see ATS ticket 43105, a Strato
	 * HiDrive target that surfaced this). The old `throw new $e;` instead instantiated a fresh, blank exception of $e's
	 * class, erasing the code (→ 0) and message (→ '').
	 *
	 * Here we force a real, non-404 failure of that directory probe against the live container by pointing the engine at
	 * the right server with the WRONG password: rclone answers PROPFIND with 401 Not Authenticated (which the Davclient
	 * turns into an Exception carrying code 401). We then drive the engine's public processPart() to exhaustion — it
	 * swallows and retries the first two failures, then gives up and throws a RuntimeException chaining the ORIGINAL
	 * failure. We assert that chained exception still carries the real 401 code and a non-empty message, not 0 and ''.
	 *
	 * No remote object is created (the failure happens during the directory probe, before any PUT), so there is nothing
	 * to clean up.
	 */
	public function testNon404DirectoryProbeFailureSurfacesRealCodeAndMessage(): void
	{
		// Point the engine at the running server but with a deliberately wrong password, so the directory-existence
		// probe (propFind) fails with a genuine, non-404 HTTP status (401) from the real container.
		Factory::getConfiguration()->set('engine.postproc.webdav.password', self::password() . '-deliberately-wrong');

		$engine     = $this->getEngine();
		$localFile  = $this->createTestFile(self::SIZE_SMALL);
		$remoteName = $this->uniqueRemoteName(self::SIZE_SMALL);

		// processPart() retries the first two failures (returning false) and throws on the third, wrapping the original
		// failure as the previous exception.
		$caught = null;

		for ($attempt = 0; $attempt < 3; $attempt++)
		{
			try
			{
				$engine->processPart($localFile, $remoteName);
			}
			catch (\RuntimeException $e)
			{
				$caught = $e;

				break;
			}
		}

		$this->assertInstanceOf(
			\RuntimeException::class, $caught,
			'processPart() must eventually throw once the retries against the unauthorised server are exhausted.'
		);

		$previous = $caught->getPrevious();

		$this->assertInstanceOf(
			\Throwable::class, $previous,
			'The give-up exception must chain the original directory-probe failure.'
		);

		// The heart of the regression: the real HTTP status and message must survive. `throw new $e;` would have left
		// these as 0 and ''.
		$this->assertSame(
			401, $previous->getCode(),
			'The real HTTP status (401 Not Authenticated) of the directory probe must survive to processPart().'
		);
		$this->assertNotSame(
			'', $previous->getMessage(),
			'The real failure message must survive to processPart(), not be erased to an empty string.'
		);
	}

	protected function getEngineSlug(): string
	{
		return 'webdav';
	}

	protected function isProviderConfigured(): bool
	{
		return self::$containerId !== null && self::$skipReason === null;
	}

	protected function getSkipMessage(): string
	{
		return self::$skipReason ?? 'WebDAV is not available.';
	}

	protected function configureProvider(): void
	{
		$config = Factory::getConfiguration();

		$config->set('engine.postproc.webdav.url', self::baseUri());
		$config->set('engine.postproc.webdav.username', self::user());
		$config->set('engine.postproc.webdav.password', self::password());
		$config->set('engine.postproc.webdav.directory', self::TEST_DIRECTORY);
	}

	protected function getMinimumPartSize(): int
	{
		return self::MINIMUM_PART_SIZE;
	}

	/**
	 * The WebDAV engine uploads each file in a single PUT; it never produces more than one processPart() step.
	 */
	protected function supportsMultipart(): bool
	{
		return false;
	}

	protected function getRemoteSize(string $remotePath): ?int
	{
		try
		{
			$properties = $this->getVerificationConnector()->propFind($remotePath, ['{DAV:}getcontentlength']);
		}
		catch (\Throwable $e)
		{
			// A missing resource (e.g. after deletion) surfaces as a 404 Exception from the Davclient.
			return null;
		}

		return isset($properties['{DAV:}getcontentlength'])
			? (int) $properties['{DAV:}getcontentlength']
			: null;
	}

	// ------------------------------------------------------------------------------------------------------------------
	// Docker container lifecycle
	// ------------------------------------------------------------------------------------------------------------------

	/** @var int The port rclone's WebDAV server listens on inside the container (unprivileged, so no root bind needed). */
	private const CONTAINER_PORT = 8080;

	/**
	 * Start the ephemeral WebDAV container, resolve its published port and wait for it to start answering authenticated
	 * requests. Throws on any failure so the caller can record a skip reason.
	 */
	private static function startWebdavContainer(): void
	{
		$image = self::env('WEBDAV_IMAGE', 'rclone/rclone:latest');

		// Run `rclone serve webdav` over a local directory, with Basic auth from CLI flags (no config file needed), and
		// publish its port to a random free host port bound to loopback only. Basic auth over plain HTTP is fine here:
		// the server is throw-away and reachable only from localhost.
		$command = sprintf(
			'docker run -d -p 127.0.0.1::%1$d %2$s serve webdav /data --addr :%1$d --user %3$s --pass %4$s',
			self::CONTAINER_PORT,
			escapeshellarg($image),
			escapeshellarg(self::user()),
			escapeshellarg(self::password())
		);

		[$ok, $out] = self::runCommand($command);

		if (!$ok || trim($out) === '')
		{
			throw new \RuntimeException('`docker run` failed: ' . trim($out));
		}

		// `docker run -d` prints the container ID on stdout, but Docker may also emit unrelated lines (e.g. a
		// "platform does not match" WARNING when running a single-arch image under emulation) which 2>&1 mixes in. Pick
		// the last line that looks like a container ID so those extra lines do not corrupt it.
		self::$containerId = self::extractContainerId($out);

		if (self::$containerId === '')
		{
			throw new \RuntimeException('`docker run` did not return a container ID: ' . trim($out));
		}

		// Resolve the random host port Docker assigned to the container's WebDAV port.
		[$portOk, $portOut] = self::runCommand(
			'docker port ' . escapeshellarg(self::$containerId) . ' ' . self::CONTAINER_PORT . '/tcp'
		);

		if (!$portOk || !preg_match('/:(\d+)\s*$/', trim($portOut), $m))
		{
			throw new \RuntimeException('Could not resolve the published WebDAV port: ' . trim($portOut));
		}

		self::$endpoint = '127.0.0.1:' . $m[1];

		self::waitForWebdavReady();
	}

	/**
	 * Extract a Docker container ID from command output, ignoring any extra lines (such as the platform-mismatch
	 * WARNING Docker prints to stderr for an amd64-only image running under emulation).
	 *
	 * @param   string  $output  The combined output of `docker run -d`.
	 *
	 * @return  string  The container ID, or an empty string if none was found.
	 */
	private static function extractContainerId(string $output): string
	{
		$id = '';

		foreach (explode("\n", $output) as $line)
		{
			$line = trim($line);

			if (preg_match('/^[0-9a-f]{12,64}$/', $line))
			{
				$id = $line;
			}
		}

		return $id;
	}

	/**
	 * Best-effort removal of the WebDAV container, ignoring any error.
	 */
	private static function stopWebdavContainer(): void
	{
		if (self::$containerId === null || !self::canRunCommands())
		{
			return;
		}

		self::runCommand('docker rm -f ' . escapeshellarg(self::$containerId));
	}

	/**
	 * Poll the server with an authenticated OPTIONS request until it answers HTTP 200, up to a sane timeout. A 200 proves
	 * Apache/mod_dav is up AND the configured credentials are accepted.
	 */
	private static function waitForWebdavReady(): void
	{
		$url      = self::baseUri();
		$deadline = time() + 30;

		do
		{
			if (self::authenticatedStatus($url, 'OPTIONS') === 200)
			{
				return;
			}

			usleep(250000);
		}
		while (time() < $deadline);

		throw new \RuntimeException('The WebDAV server did not become ready within 30 seconds at ' . self::$endpoint);
	}

	// ------------------------------------------------------------------------------------------------------------------
	// WebDAV client helpers
	// ------------------------------------------------------------------------------------------------------------------

	/**
	 * Build an independent WebDAV client used to verify stored objects, configured exactly like the engine's own
	 * connector for the running container (base URI, Basic credentials, bundled CA bundle).
	 *
	 * @return  Davclient
	 */
	private function getVerificationConnector(): Davclient
	{
		if (!$this->verificationConnector instanceof Davclient)
		{
			$this->verificationConnector = new Davclient([
				'baseUri'  => self::baseUri(),
				'userName' => self::user(),
				'password' => self::password(),
			]);

			$this->verificationConnector->addTrustedCertificates(AKEEBA_CACERT_PEM);
		}

		return $this->verificationConnector;
	}

	// ------------------------------------------------------------------------------------------------------------------
	// Low-level helpers
	// ------------------------------------------------------------------------------------------------------------------

	/**
	 * Return the HTTP status code for an authenticated request of the given method to the URL, or 0 on a transport error.
	 *
	 * @param   string  $url     The URL to probe.
	 * @param   string  $method  The HTTP method to use.
	 *
	 * @return  int
	 */
	private static function authenticatedStatus(string $url, string $method): int
	{
		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_CUSTOMREQUEST  => $method,
			CURLOPT_NOBODY         => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
			CURLOPT_USERPWD        => self::user() . ':' . self::password(),
			CURLOPT_CONNECTTIMEOUT => 2,
			CURLOPT_TIMEOUT        => 5,
		]);

		curl_exec($ch);

		return (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
	}

	/**
	 * Run a shell command, returning [success, combinedOutput]. Success is true only on exit code 0.
	 *
	 * @param   string  $command  The command to run.
	 *
	 * @return  array{0:bool,1:string}
	 */
	private static function runCommand(string $command): array
	{
		$output = [];
		$retval = 1;

		exec($command . ' 2>&1', $output, $retval);

		return [$retval === 0, implode("\n", $output)];
	}

	/**
	 * Whether PHP can execute external commands (exec is available and not disabled).
	 *
	 * @return  bool
	 */
	private static function canRunCommands(): bool
	{
		if (!function_exists('exec'))
		{
			return false;
		}

		$disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

		return !in_array('exec', $disabled, true);
	}

	/**
	 * Whether a value read from the environment is truthy (1, true, yes, on — case-insensitive).
	 *
	 * @param   string|false  $value  The raw environment value.
	 *
	 * @return  bool
	 */
	private static function isTruthy($value): bool
	{
		return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
	}

	/**
	 * Read an environment variable, falling back to a default when unset or empty.
	 *
	 * @param   string  $key      The variable name.
	 * @param   string  $default  The fallback value.
	 *
	 * @return  string
	 */
	private static function env(string $key, string $default): string
	{
		$value = trim((string) (getenv($key) ?: ''));

		return $value !== '' ? $value : $default;
	}

	private static function user(): string
	{
		return self::env('WEBDAV_USERNAME', 'akeeba');
	}

	private static function password(): string
	{
		return self::env('WEBDAV_PASSWORD', 'test-password');
	}

	/**
	 * The base URI of the running WebDAV server (the share is served at the container's root path).
	 *
	 * @return  string
	 */
	private static function baseUri(): string
	{
		return 'http://' . self::$endpoint . '/';
	}
}
