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
use Akeeba\Engine\Postproc\Exception\RangeDownloadNotSupported;

/**
 * Shared base for the two FTP post-processing engine integration tests (native ext/ftp and cURL), run against a local,
 * ephemeral pure-ftpd server in Docker.
 *
 * Both engines (engine/Postproc/Ftp.php and engine/Postproc/Ftpcurl.php) talk to the SAME throw-away FTP server, created
 * from the minimal `alpine:3.20 + pure-ftpd` image built on the fly from Test/_docker/pure-ftpd. No external account is
 * required, so the tests run on any developer machine or CI runner that has Docker. The full upload / download / delete
 * lifecycle is verified byte-for-byte (size + SHA-512) for a small and a large file, and the engine's directory creation
 * (the configured sub-directory) is exercised on the way.
 *
 * The FTP engines upload each file in a single STOR (no segmented/multipart upload), so supportsMultipart() is false:
 * the large-file test still verifies byte-for-byte fidelity over a multi-megabyte transfer but does not assert a
 * multi-step upload.
 *
 * Passive FTP through Docker (notably Docker Desktop on macOS) is famously fiddly. Two things make it work reliably here:
 *   - The server advertises 127.0.0.1 as its passive address (pure-ftpd's -P) and its passive data ports are published
 *     1:1 to the host, so the host can open the passive data connections.
 *   - The control port AND the passive port range are published on the SAME host interface (127.0.0.1). If they are
 *     published on different interfaces, Docker forwards them through different internal networks, the data connection
 *     then reaches pure-ftpd from a different source IP than the control connection, and pure-ftpd's anti-bounce check
 *     silently drops the data connection (every transfer hangs until it times out). Keeping both on 127.0.0.1 keeps the
 *     source IPs consistent.
 *
 * Lifecycle of the throw-away container is managed entirely by this class:
 *   - setUpBeforeClass() builds the image (cached after the first run), picks a free host control port in the 4000-4999
 *     range, starts the container publishing the control and passive ports on 127.0.0.1, and waits for the server to
 *     accept an authenticated login.
 *   - tearDownAfterClass() force-removes the container, leaving nothing behind.
 *
 * GATING — the whole suite self-skips unless ALL of the following hold:
 *   1. FTP_TEST is set to a truthy value (explicit opt-in, so the default `vendor/bin/phpunit Test/` run never spins up a
 *      container), and
 *   2. the transport the concrete subclass needs is available (ext/ftp for the native test; cURL with FTP support for
 *      the cURL test), and
 *   3. PHP can run external commands (the port probe and Docker management use shell commands, never a socket-opening
 *      PHP extension, because the host may forbid PHP from opening TCP ports directly), and
 *   4. the `docker` CLI exists and its daemon responds.
 * If any of these is missing, every test reports skipped with a clear reason.
 *
 * @group integration
 * @group postproc
 * @group ftp
 */
abstract class AbstractFtpTestCase extends AbstractPostprocTestCase
{
	/** @var string Docker image tag built from Test/_docker/pure-ftpd and reused across runs (build is cached). */
	private const IMAGE_TAG = 'akeeba-engine-test-pureftpd:latest';

	/** @var string Remote sub-directory all test objects are stored under (created by the engine the first time). */
	protected const TEST_DIRECTORY = 'akeeba-engine-test';

	/** @var string The host the published ports are bound to, and the host the engine/clients connect to. */
	protected const HOST = '127.0.0.1';

	/**
	 * @var int The FTP engines upload each file in a single STOR, so there is no real minimum part size. This value only
	 *          sizes the "large file" test (2.5× this) to exercise a multi-megabyte transfer.
	 */
	private const MINIMUM_PART_SIZE = 1048576;

	/** @var int Lowest host control port we will try to publish the FTP control port (21) on. */
	private const CONTROL_PORT_MIN = 4000;

	/** @var int Highest host control port we will try to publish the FTP control port (21) on. */
	private const CONTROL_PORT_MAX = 4999;

	/**
	 * @var int First passive data port. Published 1:1 to the host and handed to pure-ftpd's -p, so the host port number
	 *          MUST equal the container port number for the advertised passive ports to be reachable.
	 */
	private const PASSIVE_PORT_MIN = 30000;

	/** @var int Last passive data port (inclusive). */
	private const PASSIVE_PORT_MAX = 30009;

	/** @var string|null The Docker container ID of the running FTP server, null when none was started. */
	protected static $containerId = null;

	/** @var int|null The host port the container's FTP control port (21) is published on. */
	protected static $controlPort = null;

	/** @var string|null Reason the whole suite is being skipped, null when the server is up and usable. */
	protected static $skipReason = null;

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		self::$containerId = null;
		self::$controlPort = null;
		self::$skipReason  = null;

		// 1. Explicit opt-in. Keeps the default test run from ever starting a container.
		if (!self::isTruthy(getenv('FTP_TEST')))
		{
			self::$skipReason = 'The FTP integration tests are opt-in. Set FTP_TEST=1 (and have Docker running) to '
				. 'enable them (see Test/.env.sample).';

			return;
		}

		// 2. The transport the concrete subclass needs (ext/ftp, or cURL with FTP) must be available.
		$extensionReason = static::requiredExtensionSkipReason();

		if ($extensionReason !== null)
		{
			self::$skipReason = $extensionReason;

			return;
		}

		// 3. PHP must be able to run external commands (the port probe and Docker management need them).
		if (!self::canRunCommands())
		{
			self::$skipReason = 'PHP cannot execute external commands (exec/shell_exec are disabled), so the FTP Docker '
				. 'container cannot be managed.';

			return;
		}

		// 4. Docker must be installed and its daemon must respond.
		[$dockerOk] = self::runCommand('docker info');

		if (!$dockerOk)
		{
			self::$skipReason = 'Docker is not available (the `docker` CLI is missing or its daemon is not running), so '
				. 'the ephemeral FTP container cannot be started.';

			return;
		}

		try
		{
			self::buildImage();
			self::startFtpContainer();
		}
		catch (\Throwable $e)
		{
			self::stopFtpContainer();

			self::$skipReason = 'Could not start the ephemeral FTP container: ' . $e->getMessage();
		}
	}

	public static function tearDownAfterClass(): void
	{
		self::stopFtpContainer();

		self::$containerId = null;
		self::$controlPort = null;
		self::$skipReason  = null;

		parent::tearDownAfterClass();
	}

	/**
	 * The FTP engine does not support ranged downloads and must reject them rather than silently returning the whole
	 * file. This pins down that documented behaviour (engine/Postproc/Ftp.php::downloadToFile()).
	 */
	public function testRangedDownloadIsRejected(): void
	{
		[$remotePath] = $this->uploadTemporaryObject();

		$this->expectException(RangeDownloadNotSupported::class);

		$this->getEngine()->downloadToFile($remotePath, $this->createTempFileName(), 0, 1024);
	}

	/**
	 * downloadToBrowser() builds a direct ftp:// URL (with embedded credentials) pointing at the uploaded object. We
	 * assert it is well-formed and actually downloads the same bytes when fetched.
	 */
	public function testDownloadToBrowserReturnsWorkingUri(): void
	{
		[$remotePath, $localFile] = $this->uploadTemporaryObject();

		$uri = $this->getEngine()->downloadToBrowser($remotePath);

		$this->assertIsString($uri);
		$this->assertStringStartsWith('ftp://', $uri);
		$this->assertStringContainsString(self::HOST, $uri);
		$this->assertStringEndsWith($remotePath, $uri);
	}

	/**
	 * The uploaded object must be visible to an independent directory listing of the configured sub-directory, proving it
	 * was really stored where the engine reported (and not, say, under the local temp name).
	 */
	public function testIndependentListingContainsUploadedObject(): void
	{
		[$remotePath] = $this->uploadTemporaryObject();

		$names = $this->listRemoteDirectory('/' . self::TEST_DIRECTORY);

		$this->assertContains(
			basename($remotePath),
			$names,
			sprintf('The uploaded object %s should appear in an independent listing of /%s.', basename($remotePath), self::TEST_DIRECTORY)
		);
	}

	// ------------------------------------------------------------------------------------------------------------------
	// AbstractPostprocTestCase hooks shared by both FTP engines
	// ------------------------------------------------------------------------------------------------------------------

	protected function isProviderConfigured(): bool
	{
		return self::$containerId !== null && self::$skipReason === null;
	}

	protected function getSkipMessage(): string
	{
		return self::$skipReason ?? 'FTP is not available.';
	}

	protected function configureProvider(): void
	{
		$config = Factory::getConfiguration();
		$prefix = 'engine.postproc.' . $this->getEngineSlug() . '.';

		$config->set($prefix . 'host', self::HOST);
		$config->set($prefix . 'port', self::$controlPort);
		$config->set($prefix . 'user', self::user());
		$config->set($prefix . 'pass', self::password());
		// Empty initial directory => the chroot home (always exists), so the cURL engine's connect() — which lists the
		// initial directory and fails if it is missing — succeeds. The test objects live in a sub-directory the engine
		// creates itself, exercising its directory-creation path.
		$config->set($prefix . 'initial_directory', '');
		$config->set($prefix . 'subdirectory', self::TEST_DIRECTORY);
		$config->set($prefix . 'ftps', 0);
		$config->set($prefix . 'passive_mode', 1);
		$config->set($prefix . 'passive_mode_workaround', 1);
	}

	protected function getMinimumPartSize(): int
	{
		return self::MINIMUM_PART_SIZE;
	}

	/**
	 * The FTP engines upload each file in a single STOR; they never produce more than one processPart() step.
	 */
	protected function supportsMultipart(): bool
	{
		return false;
	}

	// ------------------------------------------------------------------------------------------------------------------
	// Transport-specific hooks implemented by the concrete subclasses
	// ------------------------------------------------------------------------------------------------------------------

	/**
	 * Whether the transport this engine needs (ext/ftp, or cURL with FTP) is available. Return null when it is, or a
	 * human-readable skip reason when it is not.
	 *
	 * @return  string|null
	 */
	abstract protected static function requiredExtensionSkipReason(): ?string;

	/**
	 * Poll-once readiness check: open a fresh, independent connection to the running server and report whether an
	 * authenticated login currently succeeds. Used to wait for the container to come up.
	 *
	 * @return  bool
	 */
	abstract protected static function serverAcceptsLogin(): bool;

	/**
	 * Independently list the base names of the entries in a remote directory, using the transport under test but a
	 * connection of our own (not the engine's).
	 *
	 * @param   string  $directory  Absolute remote directory path, e.g. "/akeeba-engine-test".
	 *
	 * @return  string[]
	 */
	abstract protected function listRemoteDirectory(string $directory): array;

	// ------------------------------------------------------------------------------------------------------------------
	// Docker container lifecycle
	// ------------------------------------------------------------------------------------------------------------------

	/**
	 * Build the throw-away pure-ftpd image from Test/_docker/pure-ftpd. Docker layer-caches this, so it is effectively a
	 * no-op after the first run. Skipped entirely when FTP_IMAGE points at a ready-made image.
	 */
	private static function buildImage(): void
	{
		// An override means "use this image as-is", so there is nothing to build.
		if (getenv('FTP_IMAGE') !== false && trim((string) getenv('FTP_IMAGE')) !== '')
		{
			return;
		}

		$context = dirname(__DIR__, 2) . '/_docker/pure-ftpd';

		[$ok, $out] = self::runCommand(
			'docker build -q -t ' . escapeshellarg(self::IMAGE_TAG) . ' ' . escapeshellarg($context)
		);

		if (!$ok)
		{
			throw new \RuntimeException('`docker build` failed: ' . trim($out));
		}
	}

	/**
	 * Start the ephemeral FTP container on a free control port and wait for it to accept logins. Throws on any failure so
	 * the caller can record a skip reason.
	 */
	private static function startFtpContainer(): void
	{
		$image    = self::env('FTP_IMAGE', self::IMAGE_TAG);
		$lastError = '';

		// Try a handful of control ports in the 4000-4999 range. We probe first (best effort), then let `docker run`
		// itself be the authoritative test of whether the port is free, retrying on a binding clash.
		foreach (self::candidateControlPorts() as $port)
		{
			if (!self::isPortLikelyFree($port))
			{
				continue;
			}

			$command = sprintf(
				'docker run -d -p %1$s:%2$d:21 -p %1$s:%3$d-%4$d:%3$d-%4$d '
				. '-e PUBLICHOST=%1$s -e FTP_USER_NAME=%5$s -e FTP_USER_PASS=%6$s -e FTP_USER_HOME=%7$s '
				. '-e FTP_PASSIVE_PORTS=%3$d:%4$d %8$s',
				self::HOST,
				$port,
				self::PASSIVE_PORT_MIN,
				self::PASSIVE_PORT_MAX,
				escapeshellarg(self::user()),
				escapeshellarg(self::password()),
				escapeshellarg('/home/ftpusers/' . self::user()),
				escapeshellarg($image)
			);

			[$ok, $out] = self::runCommand($command);
			$id         = self::extractContainerId($out);

			if ($ok && $id !== '')
			{
				self::$containerId = $id;
				self::$controlPort = $port;

				self::waitForServerReady();

				return;
			}

			$lastError = trim($out);

			// A port clash is the one error worth retrying on a different control port; anything else is fatal.
			if (!self::isPortClashError($lastError))
			{
				throw new \RuntimeException('`docker run` failed: ' . $lastError);
			}
		}

		throw new \RuntimeException('Could not find a free host control port in the '
			. self::CONTROL_PORT_MIN . '-' . self::CONTROL_PORT_MAX . ' range (last error: ' . $lastError . ').');
	}

	/**
	 * Best-effort removal of the FTP container, ignoring any error.
	 */
	private static function stopFtpContainer(): void
	{
		if (self::$containerId === null || !self::canRunCommands())
		{
			return;
		}

		self::runCommand('docker rm -f ' . escapeshellarg(self::$containerId));
	}

	/**
	 * Poll the server until an authenticated login succeeds, up to a sane timeout.
	 */
	private static function waitForServerReady(): void
	{
		$deadline = time() + 30;

		do
		{
			if (static::serverAcceptsLogin())
			{
				return;
			}

			usleep(250000);
		}
		while (time() < $deadline);

		throw new \RuntimeException('The FTP server did not accept a login within 30 seconds on port ' . self::$controlPort);
	}

	// ------------------------------------------------------------------------------------------------------------------
	// Free-port discovery (shell-based: never opens a TCP socket from PHP)
	// ------------------------------------------------------------------------------------------------------------------

	/**
	 * The control ports to try, in order. We start at a process-derived offset so concurrent runs are unlikely to fight
	 * over the same port, then walk the whole 4000-4999 range.
	 *
	 * @return  \Generator<int>
	 */
	private static function candidateControlPorts(): \Generator
	{
		$span   = self::CONTROL_PORT_MAX - self::CONTROL_PORT_MIN + 1;
		$offset = getmypid() % $span;

		for ($i = 0; $i < $span; $i++)
		{
			yield self::CONTROL_PORT_MIN + (($offset + $i) % $span);
		}
	}

	/**
	 * Best-effort check whether a host TCP port looks free, using shell tools only (the host may forbid PHP from opening
	 * TCP sockets directly). If we cannot tell, we return true and let `docker run` be the authoritative test.
	 *
	 * @param   int  $port  The port to probe.
	 *
	 * @return  bool
	 */
	private static function isPortLikelyFree(int $port): bool
	{
		// lsof is present on macOS and most Linux distributions and reports nothing (exit 1) when no process listens.
		[$haveLsof] = self::runCommand('command -v lsof');

		if ($haveLsof)
		{
			[, $out] = self::runCommand('lsof -nP -iTCP:' . $port . ' -sTCP:LISTEN');

			return trim($out) === '';
		}

		// Could not determine; be optimistic and rely on the docker-run retry on a clash.
		return true;
	}

	/**
	 * Whether a `docker run` error message indicates the chosen host port is already taken (so we should try another).
	 *
	 * @param   string  $error  The combined output of the failed `docker run`.
	 *
	 * @return  bool
	 */
	private static function isPortClashError(string $error): bool
	{
		$error = strtolower($error);

		return strpos($error, 'already allocated') !== false
			|| strpos($error, 'address already in use') !== false
			|| strpos($error, 'port is already') !== false
			|| strpos($error, 'bind for') !== false;
	}

	/**
	 * Extract a Docker container ID from command output, ignoring any extra lines (such as the platform-mismatch WARNING
	 * Docker prints to stderr when running a single-arch image under emulation).
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

	// ------------------------------------------------------------------------------------------------------------------
	// Low-level helpers (shared with the Docker-based provider tests)
	// ------------------------------------------------------------------------------------------------------------------

	/**
	 * Run a shell command, returning [success, combinedOutput]. Success is true only on exit code 0.
	 *
	 * @param   string  $command  The command to run.
	 *
	 * @return  array{0:bool,1:string}
	 */
	protected static function runCommand(string $command): array
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
	protected static function canRunCommands(): bool
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
	protected static function isTruthy($value): bool
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
	protected static function env(string $key, string $default): string
	{
		$value = trim((string) (getenv($key) ?: ''));

		return $value !== '' ? $value : $default;
	}

	protected static function user(): string
	{
		return self::env('FTP_USERNAME', 'akeeba');
	}

	protected static function password(): string
	{
		return self::env('FTP_PASSWORD', 'test-password');
	}
}
