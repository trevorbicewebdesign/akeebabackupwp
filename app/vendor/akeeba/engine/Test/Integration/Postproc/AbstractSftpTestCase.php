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
 * Shared base for the two SFTP post-processing engine integration tests (native ext/ssh2 and cURL), run against a local,
 * ephemeral SFTP server in Docker.
 *
 * Both engines (engine/Postproc/Sftp.php and engine/Postproc/Sftpcurl.php) talk to the SAME throw-away SFTP server, the
 * stock `jmcombs/sftp:latest` image (a maintained, multi-architecture drop-in fork of atmoz/sftp, so it runs natively on
 * Apple Silicon). No external account is required, so the tests run on any developer machine or CI runner that has
 * Docker. The full upload / download / delete lifecycle is verified byte-for-byte (size + SHA-512) for a small and a
 * large file, and the engine's directory creation (the configured initial directory) is exercised on the way.
 *
 * SFTP is a single TCP/IP connection (no separate data channel), so — unlike the FTP tests — there are no passive ports
 * to publish and none of the passive-mode source-IP gymnastics: the container publishes only its SSH port (22) on a free
 * host port in the 4000-4999 range, bound to 127.0.0.1.
 *
 * The SFTP engines upload each file in a single transfer (no segmented/multipart upload), so supportsMultipart() is
 * false: the large-file test still verifies byte-for-byte fidelity over a multi-megabyte transfer but does not assert a
 * multi-step upload. The SFTP engines also do not support downloadToBrowser() (there is no credentials-in-URL scheme a
 * browser could follow for SFTP), which testEngineDoesNotSupportDownloadToBrowser() pins down.
 *
 * atmoz/sftp chroots the virtual user to its home directory and only lets it write inside a sub-directory that the image
 * creates and chowns to the user (here: `/upload`). We therefore point the engine's initial directory at a folder INSIDE
 * that writable area (`/upload/akeeba-engine-test`); the engine creates it itself on connect, exercising its mkdir path.
 *
 * Lifecycle of the throw-away container is managed entirely by this class:
 *   - setUpBeforeClass() picks a free host port in the 4000-4999 range, starts the `atmoz/sftp` container publishing the
 *     SSH port on 127.0.0.1 with a single virtual user and a writable `/upload` sub-directory, and waits for the server
 *     to accept an authenticated login.
 *   - tearDownAfterClass() force-removes the container, leaving nothing behind.
 *
 * GATING — the whole suite self-skips unless ALL of the following hold:
 *   1. SFTP_TEST is set to a truthy value (explicit opt-in, so the default `vendor/bin/phpunit Test/` run never spins up
 *      a container), and
 *   2. the transport the concrete subclass needs is available (ext/ssh2 for the native test; cURL with SFTP support for
 *      the cURL test), and
 *   3. PHP can run external commands (the port probe and Docker management use shell commands, never a socket-opening
 *      PHP extension, because the host may forbid PHP from opening TCP ports directly), and
 *   4. the `docker` CLI exists and its daemon responds.
 * If any of these is missing, every test reports skipped with a clear reason.
 *
 * @group integration
 * @group postproc
 * @group sftp
 */
abstract class AbstractSftpTestCase extends AbstractPostprocTestCase
{
	/**
	 * @var string The stock, ready-made SFTP server image. Pulled on first use (no local build). jmcombs/sftp is a
	 *             maintained, genuinely multi-architecture (linux/amd64 AND linux/arm64) drop-in fork of atmoz/sftp — it
	 *             takes the identical "<user>:<pass>:::<dir>" command argument — so it runs natively on Apple Silicon
	 *             without emulation. Override with SFTP_IMAGE (e.g. atmoz/sftp:latest) if needed.
	 */
	private const IMAGE_TAG = 'jmcombs/sftp:latest';

	/** @var string The writable sub-directory atmoz/sftp creates and chowns to the virtual user (the chroot home itself is root-owned and not writable). */
	protected const UPLOAD_DIRECTORY = 'upload';

	/** @var string Remote sub-directory (inside the writable area) all test objects are stored under, created by the engine. */
	protected const TEST_DIRECTORY = 'akeeba-engine-test';

	/** @var string The host the published port is bound to, and the host the engine/clients connect to. */
	protected const HOST = '127.0.0.1';

	/**
	 * @var int The SFTP engines upload each file in a single transfer, so there is no real minimum part size. This value
	 *          only sizes the "large file" test (2.5× this) to exercise a multi-megabyte transfer.
	 */
	private const MINIMUM_PART_SIZE = 1048576;

	/** @var int Lowest host port we will try to publish the SSH port (22) on. */
	private const SSH_PORT_MIN = 4000;

	/** @var int Highest host port we will try to publish the SSH port (22) on. */
	private const SSH_PORT_MAX = 4999;

	/** @var string|null The Docker container ID of the running SFTP server, null when none was started. */
	protected static $containerId = null;

	/** @var int|null The host port the container's SSH port (22) is published on. */
	protected static $sshPort = null;

	/** @var string|null Reason the whole suite is being skipped, null when the server is up and usable. */
	protected static $skipReason = null;

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		self::$containerId = null;
		self::$sshPort     = null;
		self::$skipReason  = null;

		// 1. Explicit opt-in. Keeps the default test run from ever starting a container.
		if (!self::isTruthy(getenv('SFTP_TEST')))
		{
			self::$skipReason = 'The SFTP integration tests are opt-in. Set SFTP_TEST=1 (and have Docker running) to '
				. 'enable them (see Test/.env.sample).';

			return;
		}

		// 2. The transport the concrete subclass needs (ext/ssh2, or cURL with SFTP) must be available.
		$extensionReason = static::requiredExtensionSkipReason();

		if ($extensionReason !== null)
		{
			self::$skipReason = $extensionReason;

			return;
		}

		// 3. PHP must be able to run external commands (the port probe and Docker management need them).
		if (!self::canRunCommands())
		{
			self::$skipReason = 'PHP cannot execute external commands (exec/shell_exec are disabled), so the SFTP Docker '
				. 'container cannot be managed.';

			return;
		}

		// 4. Docker must be installed and its daemon must respond.
		[$dockerOk] = self::runCommand('docker info');

		if (!$dockerOk)
		{
			self::$skipReason = 'Docker is not available (the `docker` CLI is missing or its daemon is not running), so '
				. 'the ephemeral SFTP container cannot be started.';

			return;
		}

		try
		{
			self::startSftpContainer();
		}
		catch (\Throwable $e)
		{
			self::stopSftpContainer();

			self::$skipReason = 'Could not start the ephemeral SFTP container: ' . $e->getMessage();
		}
	}

	public static function tearDownAfterClass(): void
	{
		self::stopSftpContainer();

		self::$containerId = null;
		self::$sshPort     = null;
		self::$skipReason  = null;

		parent::tearDownAfterClass();
	}

	/**
	 * The SFTP engine does not support ranged downloads and must reject them rather than silently returning the whole
	 * file. This pins down that documented behaviour (engine/Postproc/Ftp.php::downloadToFile(), inherited by Sftp).
	 */
	public function testRangedDownloadIsRejected(): void
	{
		[$remotePath] = $this->uploadTemporaryObject();

		$this->expectException(RangeDownloadNotSupported::class);

		$this->getEngine()->downloadToFile($remotePath, $this->createTempFileName(), 0, 1024);
	}

	/**
	 * Unlike the FTP engines, the SFTP engines do not support downloadToBrowser() (there is no credentials-in-URL scheme
	 * a browser could follow for SFTP). The engine must report this so callers never hand an SFTP path to a browser.
	 */
	public function testEngineDoesNotSupportDownloadToBrowser(): void
	{
		$this->assertFalse(
			$this->getEngine()->supportsDownloadToBrowser(),
			'The SFTP engine must report that it does not support downloadToBrowser().'
		);
	}

	/**
	 * The uploaded object must be visible to an independent directory listing of the configured directory, proving it was
	 * really stored where the engine reported (and not, say, under the local temp name).
	 */
	public function testIndependentListingContainsUploadedObject(): void
	{
		[$remotePath] = $this->uploadTemporaryObject();

		$names = $this->listRemoteDirectory($this->remoteTestDirectory());

		$this->assertContains(
			basename($remotePath),
			$names,
			sprintf('The uploaded object %s should appear in an independent listing of %s.', basename($remotePath), $this->remoteTestDirectory())
		);
	}

	// ------------------------------------------------------------------------------------------------------------------
	// AbstractPostprocTestCase hooks shared by both SFTP engines
	// ------------------------------------------------------------------------------------------------------------------

	protected function isProviderConfigured(): bool
	{
		return self::$containerId !== null && self::$skipReason === null;
	}

	protected function getSkipMessage(): string
	{
		return self::$skipReason ?? 'SFTP is not available.';
	}

	protected function configureProvider(): void
	{
		$config = Factory::getConfiguration();
		$prefix = 'engine.postproc.' . $this->getEngineSlug() . '.';

		$config->set($prefix . 'host', self::HOST);
		$config->set($prefix . 'port', self::$sshPort);
		$config->set($prefix . 'user', self::user());
		$config->set($prefix . 'pass', self::password());
		// A folder INSIDE the writable `/upload` area. The chroot home (the SFTP root) is root-owned and not writable,
		// so the test objects live under the user-owned `/upload`. The engine creates the akeeba-engine-test folder
		// itself on connect, exercising its directory-creation (mkdir) path.
		$config->set($prefix . 'initial_directory', $this->remoteTestDirectory());
		// Password authentication: no key files.
		$config->set($prefix . 'privkey', '');
		$config->set($prefix . 'pubkey', '');
	}

	protected function getMinimumPartSize(): int
	{
		return self::MINIMUM_PART_SIZE;
	}

	/**
	 * The SFTP engines upload each file in a single transfer; they never produce more than one processPart() step.
	 */
	protected function supportsMultipart(): bool
	{
		return false;
	}

	/**
	 * The absolute remote directory the engine is configured to store objects in (inside the writable `/upload` area).
	 *
	 * @return  string
	 */
	protected function remoteTestDirectory(): string
	{
		return '/' . self::UPLOAD_DIRECTORY . '/' . self::TEST_DIRECTORY;
	}

	// ------------------------------------------------------------------------------------------------------------------
	// Transport-specific hooks implemented by the concrete subclasses
	// ------------------------------------------------------------------------------------------------------------------

	/**
	 * Whether the transport this engine needs (ext/ssh2, or cURL with SFTP) is available. Return null when it is, or a
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
	 * connection of our own (not the engine's). The pseudo-entries "." and ".." are filtered out.
	 *
	 * @param   string  $directory  Absolute remote directory path, e.g. "/upload/akeeba-engine-test".
	 *
	 * @return  string[]
	 */
	abstract protected function listRemoteDirectory(string $directory): array;

	// ------------------------------------------------------------------------------------------------------------------
	// Docker container lifecycle
	// ------------------------------------------------------------------------------------------------------------------

	/**
	 * Start the ephemeral SFTP container on a free host port and wait for it to accept logins. Throws on any failure so
	 * the caller can record a skip reason.
	 */
	private static function startSftpContainer(): void
	{
		$image     = self::env('SFTP_IMAGE', self::IMAGE_TAG);
		$lastError = '';

		// The image's command argument defines the virtual user: "<user>:<pass>:[e]:[uid]:[gid]:<dir>" (atmoz/sftp and its
		// jmcombs/sftp fork share this syntax). The trailing "upload" makes the image create /home/<user>/upload owned by
		// the user (the only place it can write, since the chroot home itself is root-owned).
		$userSpec = self::user() . ':' . self::password() . ':::' . self::UPLOAD_DIRECTORY;

		// Try a handful of host ports in the 4000-4999 range. We probe first (best effort), then let `docker run` itself
		// be the authoritative test of whether the port is free, retrying on a binding clash.
		foreach (self::candidateSshPorts() as $port)
		{
			if (!self::isPortLikelyFree($port))
			{
				continue;
			}

			$command = sprintf(
				'docker run -d -p %1$s:%2$d:22 %3$s %4$s',
				self::HOST,
				$port,
				escapeshellarg($image),
				escapeshellarg($userSpec)
			);

			[$ok, $out] = self::runCommand($command);
			$id         = self::extractContainerId($out);

			if ($ok && $id !== '')
			{
				self::$containerId = $id;
				self::$sshPort     = $port;

				self::waitForServerReady();

				return;
			}

			$lastError = trim($out);

			// A port clash is the one error worth retrying on a different host port; anything else is fatal.
			if (!self::isPortClashError($lastError))
			{
				throw new \RuntimeException('`docker run` failed: ' . $lastError);
			}
		}

		throw new \RuntimeException('Could not find a free host port in the '
			. self::SSH_PORT_MIN . '-' . self::SSH_PORT_MAX . ' range (last error: ' . $lastError . ').');
	}

	/**
	 * Best-effort removal of the SFTP container, ignoring any error.
	 */
	private static function stopSftpContainer(): void
	{
		if (self::$containerId === null || !self::canRunCommands())
		{
			return;
		}

		self::runCommand('docker rm -f ' . escapeshellarg(self::$containerId));
	}

	/**
	 * Poll the server until an authenticated login succeeds, up to a sane timeout. The image generates host keys on first
	 * boot (and may run under emulation if SFTP_IMAGE is overridden with a non-native image), so allow a generous deadline.
	 */
	private static function waitForServerReady(): void
	{
		$deadline = time() + 60;

		do
		{
			if (static::serverAcceptsLogin())
			{
				return;
			}

			usleep(250000);
		}
		while (time() < $deadline);

		throw new \RuntimeException('The SFTP server did not accept a login within 60 seconds on port ' . self::$sshPort);
	}

	// ------------------------------------------------------------------------------------------------------------------
	// Free-port discovery (shell-based: never opens a TCP socket from PHP)
	// ------------------------------------------------------------------------------------------------------------------

	/**
	 * The host ports to try, in order. We start at a process-derived offset so concurrent runs are unlikely to fight over
	 * the same port, then walk the whole 4000-4999 range.
	 *
	 * @return  \Generator<int>
	 */
	private static function candidateSshPorts(): \Generator
	{
		$span   = self::SSH_PORT_MAX - self::SSH_PORT_MIN + 1;
		$offset = getmypid() % $span;

		for ($i = 0; $i < $span; $i++)
		{
			yield self::SSH_PORT_MIN + (($offset + $i) % $span);
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
	 * Docker prints to stderr when running a single-arch image under emulation, e.g. if SFTP_IMAGE points at the
	 * amd64-only atmoz/sftp on an arm64 host).
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
		return self::env('SFTP_USERNAME', 'akeeba');
	}

	protected static function password(): string
	{
		return self::env('SFTP_PASSWORD', 'test-password');
	}
}
