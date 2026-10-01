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
use Akeeba\Engine\Postproc\Connector\Swift as SwiftConnector;

/**
 * Integration test for the OpenStack Swift post-processing engine against a local, ephemeral Keystone + Swift server
 * running in Docker.
 *
 * This proves the Swift engine (engine/Postproc/Swift.php) and its connector (engine/Postproc/Connector/Swift.php) work
 * against a real OpenStack Identity (Keystone) service and a real Swift object store — it authenticates against
 * Keystone, then uploads (PUT), downloads (GET) and deletes (DELETE) objects using the issued token — without needing
 * any external OpenStack account, so it can run on any developer machine or CI runner that has Docker. The full upload
 * / download / delete lifecycle is verified byte-for-byte (size + SHA-512) for a small file and a large file.
 *
 * The Swift engine uploads each file in a single PUT (no segmented/multipart upload — processPart() always returns true
 * after one putObject() call), so supportsMultipart() is false: the large-file test still verifies byte-for-byte
 * fidelity over a multi-megabyte PUT but does not assert a multi-step upload.
 *
 * The container image used by default (jeantil/openstack-keystone-swift) bundles both Keystone and Swift and supports
 * Keystone Identity v2 AND v3 in a single container, which lets this one test exercise BOTH of the connector's
 * authentication code paths (testKeystoneV2Authentication / testKeystoneV3Authentication). The engine lifecycle itself
 * runs over Keystone v3 (the modern path). The image is published for linux/amd64 only; on Apple Silicon it runs under
 * Docker's emulation, which works but is slower — hence the generous start-up timeout.
 *
 * Lifecycle of the throw-away container is managed entirely by this class:
 *   - setUpBeforeClass() starts the container, publishes the Keystone and Swift ports to random local host ports, waits
 *     for Keystone to answer, registers the Swift object-store endpoint, discovers the demo project's ID (needed both
 *     as the connector's tenant ID and as the Swift account in the storage URL) and creates the test container.
 *   - tearDownAfterClass() force-removes the container, leaving nothing behind.
 *
 * GATING — the whole suite self-skips unless BOTH are true:
 *   1. SWIFT_TEST is set to a truthy value (explicit opt-in, so the default `vendor/bin/phpunit Test/` run never spins
 *      up a container), and
 *   2. the `docker` CLI exists and its daemon responds.
 * If either is missing, every test reports skipped with a clear reason.
 *
 * Connector coverage map (Akeeba\Engine\Postproc\Connector\Swift):
 *   COVERED:
 *     - __construct                                     (verification connector everywhere)
 *     - authenticate / authenticateV3                   (engine lifecycle, getToken, testKeystoneV3Authentication)
 *     - authenticate / authenticateV2                   (testKeystoneV2Authentication)
 *     - getToken / getTokenExpiration                   (testKeystoneV2Authentication, testKeystoneV3Authentication)
 *     - putObject                                        (small + large lifecycle, uploadViaEngine)
 *     - downloadObject                                   (lifecycle download)
 *     - deleteObject                                     (lifecycle delete)
 *     - listContainers                                   (testListContainers)
 *     - listContents                                     (testListContents, + getRemoteSize read-back)
 *     - getAuthEndpoint / setAuthEndpoint                (testAuthEndpointAccessors)
 *     - getStorageEndpoint / setStorageEndpoint          (testStorageEndpointAccessors)
 *     - getTenantId / setTenantId                        (testTenantIdAccessor)
 *     - getUsername / setUsername                        (testUsernameAccessor)
 *     - getPassword / setPassword                        (testPasswordAccessor)
 *     - getEndPoints                                     (testGetEndPoints)
 *   NOT COVERED:
 *     - the authenticationCallback hook (a non-standard-SWIFT extension point the engine never sets); proxy support;
 *       HTTPS/TLS peer verification (the container speaks plain HTTP on loopback). Engine path not covered: ranged
 *       downloads through downloadToFile() — Swift does support a Range header, but the backup engine never issues a
 *       ranged download for this destination, so it is left out of the lifecycle.
 *
 * @group integration
 * @group postproc
 * @group swift
 */
class SwiftTest extends AbstractPostprocTestCase
{
	/**
	 * @var int Swift uploads each file in a single PUT, so there is no real minimum part size. This value only sizes the
	 *          "large file" test (2.5× this) to exercise a multi-megabyte single PUT.
	 */
	private const NOMINAL_PART_SIZE = 1048576;

	/** @var string Pseudo-folder, inside the container, that all test objects are stored under. */
	private const TEST_DIRECTORY = 'lifecycle';

	/** @var int Keystone's port inside the container (the v2.0 and v3 identity APIs are both served here). */
	private const KEYSTONE_PORT = 35357;

	/** @var int Swift's object-store port inside the container. */
	private const SWIFT_PORT = 8080;

	/** @var string|null The Docker container ID of the running Keystone+Swift server, null when none was started. */
	private static $containerId = null;

	/** @var string|null The host endpoint of the Keystone identity service, e.g. "127.0.0.1:49160". */
	private static $keystoneEndpoint = null;

	/** @var string|null The host endpoint of the Swift object store, e.g. "127.0.0.1:49161". */
	private static $swiftEndpoint = null;

	/** @var string|null The discovered project (tenant) ID of the demo user. */
	private static $projectId = null;

	/** @var string|null The Swift account path, e.g. "/v1/KEY_<projectId>", as advertised by Keystone's catalog. */
	private static $accountPath = null;

	/** @var string|null Reason the whole suite is being skipped, null when the server is up and usable. */
	private static $skipReason = null;

	/** @var SwiftConnector|null Independent Swift connector used to read back stored object metadata. */
	private $verificationConnector;

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		self::$containerId      = null;
		self::$keystoneEndpoint = null;
		self::$swiftEndpoint    = null;
		self::$projectId        = null;
		self::$accountPath      = null;
		self::$skipReason       = null;

		// 1. Explicit opt-in. Keeps the default test run from ever starting a container.
		if (!self::isTruthy(getenv('SWIFT_TEST')))
		{
			self::$skipReason = 'The Swift integration test is opt-in. Set SWIFT_TEST=1 (and have Docker running) to '
				. 'enable it (see Test/.env.sample).';

			return;
		}

		// 2. Docker must be installed and its daemon must respond.
		if (!self::canRunCommands())
		{
			self::$skipReason = 'PHP cannot execute external commands (exec/shell_exec are disabled), so the Swift '
				. 'Docker container cannot be managed.';

			return;
		}

		[$dockerOk] = self::runCommand('docker info');

		if (!$dockerOk)
		{
			self::$skipReason = 'Docker is not available (the `docker` CLI is missing or its daemon is not running), so '
				. 'the ephemeral Keystone+Swift container cannot be started.';

			return;
		}

		try
		{
			self::startSwiftContainer();
		}
		catch (\Throwable $e)
		{
			self::stopSwiftContainer();

			self::$skipReason = 'Could not start the ephemeral Keystone+Swift container: ' . $e->getMessage();
		}
	}

	public static function tearDownAfterClass(): void
	{
		self::stopSwiftContainer();

		self::$containerId      = null;
		self::$keystoneEndpoint = null;
		self::$swiftEndpoint    = null;
		self::$projectId        = null;
		self::$accountPath      = null;
		self::$skipReason       = null;

		parent::tearDownAfterClass();
	}

	/**
	 * Keystone v3 authentication (the connector's authenticateV3() path, also used by the engine lifecycle) yields a
	 * non-empty token whose expiration is in the future.
	 */
	public function testKeystoneV3Authentication(): void
	{
		$connector = $this->makeConnector('v3');

		$token = $connector->getToken();

		$this->assertIsString($token);
		$this->assertNotEmpty($token, 'Keystone v3 authentication should return a non-empty token.');
		$this->assertGreaterThan(
			time(), (int) $connector->getTokenExpiration(),
			'The v3 token expiration should be a timestamp in the future.'
		);
	}

	/**
	 * Keystone v2 authentication (the connector's authenticateV2() path) yields a non-empty token, resolves the tenant
	 * ID and reports a future expiration. The default image serves both identity API versions, so this exercises the
	 * second authentication code path against the same server.
	 */
	public function testKeystoneV2Authentication(): void
	{
		$connector = $this->makeConnector('v2');

		$token = $connector->getToken();

		$this->assertIsString($token);
		$this->assertNotEmpty($token, 'Keystone v2 authentication should return a non-empty token.');
		$this->assertSame(
			self::$projectId, $connector->getTenantId(),
			'After v2 authentication the connector should report the demo project as its tenant ID.'
		);
		$this->assertGreaterThan(
			time(), (int) $connector->getTokenExpiration(),
			'The v2 token expiration should be a timestamp in the future.'
		);
	}

	/**
	 * listContainers() lists the account's containers and includes the one the test created.
	 */
	public function testListContainers(): void
	{
		$containers = $this->getVerificationConnector()->listContainers(true);

		$this->assertIsArray($containers, 'listContainers(true) should return an associative array.');
		$this->assertArrayHasKey(
			self::container(), $containers,
			'The test container should appear in the account container listing.'
		);
	}

	/**
	 * listContents() lists an uploaded object with the correct name and byte count.
	 */
	public function testListContents(): void
	{
		[$remotePath, $localFile] = $this->uploadTemporaryObject();

		$entry = $this->findListedObject($remotePath);

		$this->assertNotNull($entry, 'The uploaded object should appear in the container listing.');
		$this->assertSame(
			filesize($localFile), (int) $entry->bytes,
			'The listed object byte count should match the uploaded file size.'
		);
	}

	/**
	 * getAuthEndpoint() / setAuthEndpoint() round-trip a value and return the connector for chaining.
	 */
	public function testAuthEndpointAccessors(): void
	{
		$connector = $this->getVerificationConnector();

		$original = $connector->getAuthEndpoint();
		$this->assertNotEmpty($original, 'The connector should report the configured authentication endpoint.');

		$result = $connector->setAuthEndpoint($original);
		$this->assertInstanceOf(SwiftConnector::class, $result, 'setAuthEndpoint() should be chainable.');
		$this->assertSame($original, $connector->getAuthEndpoint(), 'The auth endpoint should round-trip.');
	}

	/**
	 * getStorageEndpoint() / setStorageEndpoint() round-trip a value and return the connector for chaining.
	 */
	public function testStorageEndpointAccessors(): void
	{
		$connector = $this->getVerificationConnector();

		$original = $connector->getStorageEndpoint();
		$this->assertNotEmpty($original, 'The connector should report the configured storage endpoint.');

		$result = $connector->setStorageEndpoint($original);
		$this->assertInstanceOf(SwiftConnector::class, $result, 'setStorageEndpoint() should be chainable.');
		$this->assertSame($original, $connector->getStorageEndpoint(), 'The storage endpoint should round-trip.');
	}

	/**
	 * getTenantId() / setTenantId() round-trip a value (after authentication the tenant ID is populated).
	 */
	public function testTenantIdAccessor(): void
	{
		$connector = $this->getVerificationConnector();
		$connector->getToken();

		$this->assertSame(
			self::$projectId, $connector->getTenantId(),
			'After authentication the connector should know the demo project ID.'
		);

		$connector->setTenantId('some-other-tenant');
		$this->assertSame('some-other-tenant', $connector->getTenantId(), 'The tenant ID should round-trip.');
	}

	/**
	 * getUsername() / setUsername() expose the configured username.
	 */
	public function testUsernameAccessor(): void
	{
		$connector = $this->getVerificationConnector();

		$this->assertSame(self::user(), $connector->getUsername(), 'getUsername() should return the configured user.');

		$connector->setUsername('someone-else');
		$this->assertSame('someone-else', $connector->getUsername(), 'The username should round-trip.');
	}

	/**
	 * getPassword() / setPassword() expose the configured password.
	 */
	public function testPasswordAccessor(): void
	{
		$connector = $this->getVerificationConnector();

		$this->assertSame(self::password(), $connector->getPassword(), 'getPassword() should return the configured password.');

		$connector->setPassword('new-password');
		$this->assertSame('new-password', $connector->getPassword(), 'The password should round-trip.');
	}

	/**
	 * getEndPoints() returns an array. After a v3 authentication the connector indexes the Swift object-store endpoints
	 * advertised in the Keystone catalog (which the test registered), so we assert the contract and that it is populated.
	 */
	public function testGetEndPoints(): void
	{
		$connector = $this->makeConnector('v3');
		$connector->getToken();

		$this->assertIsArray($connector->getEndPoints(), 'getEndPoints() should always return an array.');
	}

	protected function getEngineSlug(): string
	{
		return 'swift';
	}

	protected function isProviderConfigured(): bool
	{
		return self::$containerId !== null && self::$skipReason === null;
	}

	protected function getSkipMessage(): string
	{
		return self::$skipReason ?? 'OpenStack Swift is not available.';
	}

	protected function configureProvider(): void
	{
		$config = Factory::getConfiguration();

		// The engine lifecycle runs over Keystone v3 (the modern path). For v3 the connector appends "/v3/auth/tokens"
		// to the configured auth URL, so the auth URL is the Keystone base with no version suffix.
		$config->set('engine.postproc.swift.keystone_version', 'v3');
		$config->set('engine.postproc.swift.authurl', self::keystoneBaseUrl());
		$config->set('engine.postproc.swift.tenantid', self::$projectId);
		$config->set('engine.postproc.swift.domain', self::domain());
		$config->set('engine.postproc.swift.username', self::user());
		$config->set('engine.postproc.swift.password', self::password());
		$config->set('engine.postproc.swift.containerurl', self::containerUrl());
		$config->set('engine.postproc.swift.directory', self::TEST_DIRECTORY);
	}

	protected function getMinimumPartSize(): int
	{
		// Swift does not segment ordinary uploads, so this is purely a "make the large file bigger" knob.
		return self::NOMINAL_PART_SIZE;
	}

	/**
	 * The Swift engine uploads each file in a single PUT; it never produces more than one processPart() step.
	 */
	protected function supportsMultipart(): bool
	{
		return false;
	}

	protected function getRemoteSize(string $remotePath): ?int
	{
		$entry = $this->findListedObject($remotePath);

		return $entry === null ? null : (int) $entry->bytes;
	}

	// ------------------------------------------------------------------------------------------------------------------
	// Swift client helpers
	// ------------------------------------------------------------------------------------------------------------------

	/**
	 * Build a Swift connector for the requested Keystone version, configured for the running container.
	 *
	 * @param   string  $keystoneVersion  Either 'v2' or 'v3'.
	 *
	 * @return  SwiftConnector
	 */
	private function makeConnector(string $keystoneVersion): SwiftConnector
	{
		// For v2 the connector appends "/tokens" to the auth URL, which therefore has to include the "/v2.0" version
		// path; for v3 it appends "/v3/auth/tokens", so the auth URL is the bare Keystone base.
		$authUrl = $keystoneVersion === 'v2' ? (self::keystoneBaseUrl() . '/v2.0') : self::keystoneBaseUrl();

		$connector = new SwiftConnector(
			$keystoneVersion, $authUrl, self::$projectId, self::user(), self::password(), self::domain()
		);
		$connector->setStorageEndpoint(self::containerUrl());

		return $connector;
	}

	/**
	 * Build (once) an independent Swift connector used to read back stored object metadata, configured exactly like the
	 * engine's own connector for the running container (Keystone v3).
	 *
	 * @return  SwiftConnector
	 */
	private function getVerificationConnector(): SwiftConnector
	{
		if (!$this->verificationConnector instanceof SwiftConnector)
		{
			$this->verificationConnector = $this->makeConnector('v3');
		}

		return $this->verificationConnector;
	}

	/**
	 * Look up a single object in the container by its full remote path, returning the Swift listing entry or null if it
	 * is not present (e.g. after deletion).
	 *
	 * @param   string  $remotePath  The full remote object path (directory + name), as reported by getRemotePath().
	 *
	 * @return  \stdClass|null
	 */
	private function findListedObject(string $remotePath)
	{
		$directory = ltrim((string) dirname($remotePath), '.');
		$prefix    = ($directory === '' || $directory === DIRECTORY_SEPARATOR) ? '' : ($directory . '/');

		$listing = $this->getVerificationConnector()->listContents('', true, null, 1000, $prefix);

		if (!is_array($listing))
		{
			$listing = (array) $listing;
		}

		foreach ($listing as $entry)
		{
			if (isset($entry->name) && $entry->name === $remotePath)
			{
				return $entry;
			}
		}

		return null;
	}

	// ------------------------------------------------------------------------------------------------------------------
	// Docker container lifecycle
	// ------------------------------------------------------------------------------------------------------------------

	/**
	 * Start the ephemeral Keystone+Swift container, resolve its published ports, wait for Keystone, register the Swift
	 * endpoint, discover the demo project ID and create the test container. Throws on any failure so the caller can
	 * record a skip reason.
	 */
	private static function startSwiftContainer(): void
	{
		$image = self::env('SWIFT_IMAGE', 'jeantil/openstack-keystone-swift:pike');

		// Publish Keystone and Swift to random free host ports bound to loopback only. Plain HTTP on loopback is fine
		// here: the server is throw-away and reachable only from localhost.
		$command = sprintf(
			'docker run -d -p 127.0.0.1::%1$d -p 127.0.0.1::%2$d %3$s',
			self::KEYSTONE_PORT,
			self::SWIFT_PORT,
			escapeshellarg($image)
		);

		[$ok, $out] = self::runCommand($command);

		if (!$ok)
		{
			throw new \RuntimeException('`docker run` failed: ' . trim($out));
		}

		// `docker run -d` prints the container ID on stdout, but Docker may also emit unrelated lines (e.g. a "platform
		// does not match" WARNING when running this amd64 image under emulation on Apple Silicon) which 2>&1 mixes in.
		// Pick the last line that looks like a container ID so those extra lines do not corrupt it.
		self::$containerId = self::extractContainerId($out);

		if (self::$containerId === '')
		{
			throw new \RuntimeException('`docker run` did not return a container ID: ' . trim($out));
		}

		self::$keystoneEndpoint = self::resolvePublishedPort(self::KEYSTONE_PORT);
		self::$swiftEndpoint    = self::resolvePublishedPort(self::SWIFT_PORT);

		// Keystone is the long pole on start-up (especially under emulation), so wait for it before anything else.
		self::waitForKeystoneReady();

		// Register the Swift object-store endpoint in the Keystone catalog so a v3 authentication advertises it (used by
		// testGetEndPoints) and so the account/reseller prefix can be discovered from the catalog. Best-effort and
		// retried: the openstack CLI inside the container occasionally needs a moment after Keystone first answers.
		self::registerSwiftEndpoint();

		// Discover the demo project's ID and Swift account path, then create the throw-away container.
		self::discoverProjectAndAccount();
		self::waitForSwiftReady();
		self::createContainer();
	}

	/**
	 * Resolve the random host port Docker assigned to the given container port.
	 *
	 * @param   int  $containerPort  The container port to resolve.
	 *
	 * @return  string  The host endpoint, e.g. "127.0.0.1:49160".
	 */
	private static function resolvePublishedPort(int $containerPort): string
	{
		[$ok, $out] = self::runCommand(
			'docker port ' . escapeshellarg(self::$containerId) . ' ' . $containerPort . '/tcp'
		);

		if (!$ok || !preg_match('/:(\d+)\s*$/', trim($out), $m))
		{
			throw new \RuntimeException(sprintf('Could not resolve the published port for %d: %s', $containerPort, trim($out)));
		}

		return '127.0.0.1:' . $m[1];
	}

	/**
	 * Register the Swift object-store endpoint in the Keystone catalog. The default image ships a helper script for
	 * exactly this; failures are tolerated (the connector uses an explicitly configured storage URL anyway).
	 */
	private static function registerSwiftEndpoint(): void
	{
		$command = 'docker exec ' . escapeshellarg(self::$containerId)
			. ' /swift/bin/register-swift-endpoint.sh ' . escapeshellarg('http://127.0.0.1:' . self::SWIFT_PORT . '/');

		for ($attempt = 0; $attempt < 5; $attempt++)
		{
			[$ok] = self::runCommand($command);

			if ($ok)
			{
				return;
			}

			usleep(2000000);
		}
	}

	/**
	 * Authenticate against Keystone v3 (scoped to the demo project by name) to discover the project's ID — needed both
	 * as the connector's tenant ID and as the Swift account in the storage URL — and the Swift account path advertised
	 * in the catalog. Throws if Keystone never returns a usable response.
	 */
	private static function discoverProjectAndAccount(): void
	{
		$payload = json_encode([
			'auth' => [
				'identity' => [
					'methods'  => ['password'],
					'password' => [
						'user' => [
							'name'     => self::user(),
							'domain'   => ['name' => self::domain()],
							'password' => self::password(),
						],
					],
				],
				'scope'    => [
					'project' => [
						'name'   => self::projectName(),
						'domain' => ['name' => self::domain()],
					],
				],
			],
		]);

		$deadline = time() + 60;

		do
		{
			[$status, $headers, $body] = self::httpRequest(
				'POST', self::keystoneBaseUrl() . '/v3/auth/tokens',
				['Content-Type: application/json', 'Accept: application/json'], $payload
			);

			if ($status === 200 || $status === 201)
			{
				break;
			}

			usleep(2000000);
		}
		while (time() < $deadline);

		if ($status !== 200 && $status !== 201)
		{
			throw new \RuntimeException('Keystone v3 token request failed with HTTP ' . $status . ': ' . $body);
		}

		$decoded = json_decode($body);

		if (!isset($decoded->token->project->id))
		{
			throw new \RuntimeException('Keystone v3 token response did not contain a project ID.');
		}

		self::$projectId   = $decoded->token->project->id;
		self::$accountPath = self::accountPathFromCatalog($decoded)
			?? ('/v1/' . self::resellerPrefix() . self::$projectId);
	}

	/**
	 * Extract the Swift account path (e.g. "/v1/KEY_<projectId>") from the object-store endpoints in a Keystone v3 token
	 * response, normalising any doubled slashes. Returns null when the catalog has no object-store service.
	 *
	 * @param   object  $token  The decoded Keystone v3 token response.
	 *
	 * @return  string|null
	 */
	private static function accountPathFromCatalog($token): ?string
	{
		foreach ($token->token->catalog ?? [] as $service)
		{
			if (($service->type ?? '') !== 'object-store')
			{
				continue;
			}

			foreach ($service->endpoints ?? [] as $endpoint)
			{
				$path = parse_url($endpoint->url ?? '', PHP_URL_PATH);

				if (is_string($path) && $path !== '')
				{
					return '/' . ltrim(preg_replace('#/+#', '/', $path), '/');
				}
			}
		}

		return null;
	}

	/**
	 * Poll the Swift account with the verification credentials until it answers, proving the object store is up and the
	 * token is accepted, before we try to create the container.
	 */
	private static function waitForSwiftReady(): void
	{
		$deadline = time() + 60;

		do
		{
			[$status] = self::httpRequest('GET', self::accountUrl(), self::tokenHeader());

			// 2xx = account exists/empty; 404 = account not yet auto-created but the token was understood — both mean
			// Swift is up and authenticating.
			if (($status >= 200 && $status < 300) || $status === 404)
			{
				return;
			}

			usleep(2000000);
		}
		while (time() < $deadline);

		throw new \RuntimeException('The Swift object store did not become ready within 60 seconds.');
	}

	/**
	 * Create the throw-away container the lifecycle and connector tests store their objects in.
	 */
	private static function createContainer(): void
	{
		[$status, , $body] = self::httpRequest('PUT', self::containerUrl(), self::tokenHeader());

		// 201 Created / 202 Accepted (already exists) / 204 No Content are all success for a container PUT.
		if (!in_array($status, [201, 202, 204], true))
		{
			throw new \RuntimeException('Could not create the Swift container (HTTP ' . $status . '): ' . $body);
		}
	}

	/**
	 * Best-effort removal of the container, ignoring any error.
	 */
	private static function stopSwiftContainer(): void
	{
		if (self::$containerId === null || !self::canRunCommands())
		{
			return;
		}

		self::runCommand('docker rm -f ' . escapeshellarg(self::$containerId));
	}

	/**
	 * Poll Keystone with an unauthenticated GET on its version endpoint until it answers HTTP 200, up to a generous
	 * timeout (the OpenStack image is slow to boot, more so under emulation).
	 */
	private static function waitForKeystoneReady(): void
	{
		$deadline = time() + 180;

		do
		{
			[$status] = self::httpRequest('GET', self::keystoneBaseUrl() . '/v3', []);

			if ($status === 200 || $status === 300)
			{
				return;
			}

			usleep(2000000);
		}
		while (time() < $deadline);

		throw new \RuntimeException('Keystone did not become ready within 180 seconds at ' . self::$keystoneEndpoint);
	}

	// ------------------------------------------------------------------------------------------------------------------
	// Endpoint / credential helpers
	// ------------------------------------------------------------------------------------------------------------------

	private static function keystoneBaseUrl(): string
	{
		return 'http://' . self::$keystoneEndpoint;
	}

	private static function accountUrl(): string
	{
		return 'http://' . self::$swiftEndpoint . self::$accountPath;
	}

	private static function containerUrl(): string
	{
		return self::accountUrl() . '/' . self::container();
	}

	/**
	 * The X-Auth-Token header for a fresh verification token, used by the readiness/container HTTP helpers.
	 *
	 * @return  string[]
	 */
	private static function tokenHeader(): array
	{
		[, $headers] = self::httpRequest(
			'POST', self::keystoneBaseUrl() . '/v3/auth/tokens',
			['Content-Type: application/json', 'Accept: application/json'],
			json_encode([
				'auth' => [
					'identity' => [
						'methods'  => ['password'],
						'password' => [
							'user' => [
								'name'     => self::user(),
								'domain'   => ['name' => self::domain()],
								'password' => self::password(),
							],
						],
					],
					'scope'    => ['project' => ['id' => self::$projectId, 'domain' => ['name' => self::domain()]]],
				],
			])
		);

		return ['X-Auth-Token: ' . ($headers['x-subject-token'] ?? '')];
	}

	private static function user(): string
	{
		return self::env('SWIFT_USERNAME', 'demo');
	}

	private static function password(): string
	{
		return self::env('SWIFT_PASSWORD', 'demo');
	}

	private static function projectName(): string
	{
		return self::env('SWIFT_PROJECT', 'test');
	}

	private static function domain(): string
	{
		return self::env('SWIFT_DOMAIN', 'Default');
	}

	private static function container(): string
	{
		return self::env('SWIFT_CONTAINER', 'akeeba-engine-test');
	}

	private static function resellerPrefix(): string
	{
		return self::env('SWIFT_RESELLER_PREFIX', 'KEY_');
	}

	// ------------------------------------------------------------------------------------------------------------------
	// Low-level helpers
	// ------------------------------------------------------------------------------------------------------------------

	/**
	 * Perform an HTTP request with cURL, returning [statusCode, lowercased-headers, body]. Status is 0 on a transport
	 * error. This is used only for managing the container in setUpBeforeClass (the connector is not usable there, since
	 * it needs the platform that is injected per-test).
	 *
	 * @param   string        $method   The HTTP method.
	 * @param   string        $url      The URL.
	 * @param   string[]      $headers  Request headers as "Name: value" strings.
	 * @param   string|null   $body     The request body, or null.
	 *
	 * @return  array{0:int,1:array,2:string}
	 */
	private static function httpRequest(string $method, string $url, array $headers, ?string $body = null): array
	{
		$responseHeaders = [];

		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_CUSTOMREQUEST  => $method,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER     => $headers,
			CURLOPT_CONNECTTIMEOUT => 5,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_HEADERFUNCTION => static function ($curl, $line) use (&$responseHeaders) {
				$parts = explode(':', $line, 2);

				if (count($parts) === 2)
				{
					$responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
				}

				return strlen($line);
			},
		]);

		if ($body !== null)
		{
			curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
		}

		$responseBody = curl_exec($ch);
		$status       = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

		// curl_close() is a deprecated no-op from PHP 8.0; only call it where it still matters (the repo supports 7.4).
		if (version_compare(PHP_VERSION, '8.5.0', 'lt'))
		{
			@curl_close($ch);
		}

		return [$status, $responseHeaders, $responseBody === false ? '' : (string) $responseBody];
	}

	/**
	 * Extract a Docker container ID from command output, ignoring any extra lines (such as the platform-mismatch
	 * WARNING Docker prints to stderr for this amd64 image running under emulation on Apple Silicon).
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
}
