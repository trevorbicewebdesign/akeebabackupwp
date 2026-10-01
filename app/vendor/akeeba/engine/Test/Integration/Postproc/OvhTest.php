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
use Akeeba\Engine\Postproc\Connector\Ovh as OvhConnector;

/**
 * Integration test for the OVH Object Storage post-processing engine and connector.
 *
 * OVH Object Storage is an OpenStack Swift store fronted by OVH's public Keystone v3 identity service. Unlike the
 * generic Swift engine (which is pointed at an arbitrary Keystone), the OVH connector
 * (engine/Postproc/Connector/Ovh.php) hard-codes OVH's authentication endpoint (https://auth.cloud.ovh.net) and the
 * "Default" Keystone domain, then talks to the EXPLICITLY configured container URL with the issued X-Auth-Token. There
 * is therefore no local stand-in: this test can only run against a real OVH project, so it is gated on live credentials
 * rather than on Docker, exactly like the live-AWS Amazon S3 test.
 *
 * The test exercises the full upload / download / delete lifecycle against a live OVH container for both a small file
 * and a "large" file, verifying byte-for-byte fidelity (size + SHA-512), plus the informational and accessor methods of
 * the connector: authentication, token handling, container listing, object listing, and the accessors inherited from
 * the Swift base class.
 *
 * OVH (Swift) does NOT segment ordinary uploads: the engine's processPart() performs a single PUT regardless of file
 * size (engine/Postproc/Ovh.php::processPart always returns true after one putObject call). There is therefore no real
 * multipart minimum; getMinimumPartSize() returns a size that still makes the "large" file meaningfully bigger than the
 * small one, and supportsMultipart() is false so the large-file test verifies a multi-megabyte single PUT byte-for-byte
 * but does not assert a multi-step upload.
 *
 * The test runs only when OVH_PROJECTID, OVH_USERNAME, OVH_PASSWORD and OVH_CONTAINERURL are all set
 * (see Test/.env.sample); otherwise it self-skips. OVH_CONTAINERURL is the full storage URL of an EXISTING container
 * (e.g. https://storage.gra.cloud.ovh.net/v1/AUTH_xx…/my-container); the engine never creates the container, only
 * objects inside it (under the `akeeba-engine-test` pseudo-folder).
 *
 * Connector coverage map (engine/Postproc/Connector/Ovh.php and its Swift base class):
 *   COVERED:
 *     - __construct (Ovh)                                (verification connector + engine, via makeConnector)
 *     - authenticate / authenticateV3 (Swift, protected) (engine lifecycle, getToken, every connector test)
 *     - getToken / getTokenExpiration (Swift)            (testGetToken)
 *     - putObject (Swift)                                (small + large lifecycle, uploadViaEngine)
 *     - downloadObject (Swift)                           (lifecycle download)
 *     - deleteObject (Swift)                             (lifecycle delete)
 *     - listContainers (Swift)                           (testListContainers)
 *     - listContents (Swift)                             (testListContents, + getRemoteSize read-back)
 *     - getAuthEndpoint / setAuthEndpoint (Swift)        (testAuthEndpointAccessors — OVH's hard-coded endpoint)
 *     - getStorageEndpoint / setStorageEndpoint (Swift)  (testStorageEndpointAccessors)
 *     - getTenantId / setTenantId (Swift)                (testTenantIdAccessors)
 *     - getUsername / setUsername (Swift)                (testUsernameAccessor)
 *     - getPassword / setPassword (Swift)                (testPasswordAccessor)
 *     - getEndPoints (Swift)                             (testGetEndPoints)
 *   NOT COVERED:
 *     - authenticateV2 (Swift): OVH authenticates with Keystone v3 only, so the v2 path is never taken.
 *     - the authenticationCallback hook (a non-standard-Swift extension point the engine never sets).
 *     - ranged downloads through downloadToFile(): the connector supports a Range header, but the backup engine never
 *       issues a ranged download for this destination, so it is left out of the lifecycle.
 *
 * @group integration
 * @group postproc
 * @group ovh
 */
class OvhTest extends AbstractPostprocTestCase
{
	/**
	 * OVH (Swift) does not segment uploads, so there is no provider-imposed minimum part size. We pick 1 MiB so the
	 * "large" file (2.5x this) is a few MiB — large enough to be a meaningfully different upload from the 256 KiB small
	 * file, yet small enough to keep the network test quick.
	 *
	 * @var int
	 */
	private const NOMINAL_PART_SIZE = 1048576;

	/** @var string Pseudo-folder, inside the container, that all test objects are stored under. */
	private const TEST_DIRECTORY = 'akeeba-engine-test';

	/** @var OvhConnector|null Independent connector used to read back stored object sizes and list contents. */
	private $verificationConnector;

	/**
	 * getToken() authenticates against OVH's Keystone v3 service and returns a non-empty token whose expiration is in the
	 * future.
	 */
	public function testGetToken(): void
	{
		$connector = $this->getVerificationConnector();

		$token = $connector->getToken();

		$this->assertIsString($token);
		$this->assertNotEmpty($token, 'getToken() should return a non-empty authentication token.');
		$this->assertGreaterThan(
			time(), (int) $connector->getTokenExpiration(),
			'The token expiration should be a timestamp in the future.'
		);
	}

	/**
	 * listContainers() lists the project's containers and includes the one carried in the configured container URL.
	 */
	public function testListContainers(): void
	{
		$containers = $this->getVerificationConnector()->listContainers(true);

		$this->assertIsArray($containers, 'listContainers(true) should return an associative array.');
		$this->assertArrayHasKey(
			self::containerName(), $containers,
			'The configured container should appear in the project container listing.'
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
	 * getAuthEndpoint() reports OVH's hard-coded Keystone endpoint and setAuthEndpoint() round-trips a value, returning
	 * the connector for chaining.
	 */
	public function testAuthEndpointAccessors(): void
	{
		$connector = $this->getVerificationConnector();

		$this->assertSame(
			'https://auth.cloud.ovh.net', $connector->getAuthEndpoint(),
			'The OVH connector should default to OVH\'s public Keystone v3 endpoint.'
		);

		$result = $connector->setAuthEndpoint('https://auth.cloud.ovh.net');
		$this->assertInstanceOf(OvhConnector::class, $result, 'setAuthEndpoint() should be chainable.');
		$this->assertSame(
			'https://auth.cloud.ovh.net', $connector->getAuthEndpoint(), 'The auth endpoint should round-trip.'
		);
	}

	/**
	 * getStorageEndpoint() / setStorageEndpoint() round-trip a value and return the connector for chaining.
	 */
	public function testStorageEndpointAccessors(): void
	{
		$connector = $this->getVerificationConnector();

		$this->assertSame(
			self::containerUrl(), $connector->getStorageEndpoint(),
			'The connector should report the configured container URL as its storage endpoint.'
		);

		$result = $connector->setStorageEndpoint(self::containerUrl());
		$this->assertInstanceOf(OvhConnector::class, $result, 'setStorageEndpoint() should be chainable.');
		$this->assertSame(
			self::containerUrl(), $connector->getStorageEndpoint(), 'The storage endpoint should round-trip.'
		);
	}

	/**
	 * getTenantId() / setTenantId() round-trip a value (after authentication the project/tenant ID is populated).
	 */
	public function testTenantIdAccessors(): void
	{
		$connector = $this->getVerificationConnector();
		$connector->getToken();

		$tenantId = $connector->getTenantId();
		$this->assertNotEmpty($tenantId, 'After authentication the connector should know its project (tenant) ID.');

		$connector->setTenantId('some-other-tenant');
		$this->assertSame('some-other-tenant', $connector->getTenantId(), 'The tenant ID should round-trip.');
	}

	/**
	 * getUsername() / setUsername() expose the configured OpenStack username.
	 */
	public function testUsernameAccessor(): void
	{
		$connector = $this->getVerificationConnector();

		$this->assertSame(
			$this->credential('OVH_USERNAME'), $connector->getUsername(),
			'getUsername() should return the configured username.'
		);

		$connector->setUsername('someone-else');
		$this->assertSame('someone-else', $connector->getUsername(), 'The username should round-trip.');
	}

	/**
	 * getPassword() / setPassword() expose the configured OpenStack password.
	 */
	public function testPasswordAccessor(): void
	{
		$connector = $this->getVerificationConnector();

		$this->assertSame(
			$this->credential('OVH_PASSWORD'), $connector->getPassword(),
			'getPassword() should return the configured password.'
		);

		$connector->setPassword('new-password');
		$this->assertSame('new-password', $connector->getPassword(), 'The password should round-trip.');
	}

	/**
	 * getEndPoints() returns an array. After a v3 authentication the connector indexes the object-store endpoints
	 * advertised in OVH's Keystone catalog; we assert the contract (an array) only, since the exact catalog shape is
	 * OVH's to decide.
	 */
	public function testGetEndPoints(): void
	{
		$connector = $this->getVerificationConnector();
		$connector->getToken();

		$this->assertIsArray($connector->getEndPoints(), 'getEndPoints() should always return an array.');
	}

	protected function getEngineSlug(): string
	{
		return 'ovh';
	}

	protected function isProviderConfigured(): bool
	{
		return $this->credential('OVH_PROJECTID') !== ''
			&& $this->credential('OVH_USERNAME') !== ''
			&& $this->credential('OVH_PASSWORD') !== ''
			&& $this->credential('OVH_CONTAINERURL') !== '';
	}

	protected function getSkipMessage(): string
	{
		return 'OVH Object Storage is not configured. Set OVH_PROJECTID, OVH_USERNAME, OVH_PASSWORD and '
			. 'OVH_CONTAINERURL to enable this integration test (see Test/.env.sample).';
	}

	protected function configureProvider(): void
	{
		$config = Factory::getConfiguration();

		$config->set('engine.postproc.ovh.projectid', $this->credential('OVH_PROJECTID'));
		$config->set('engine.postproc.ovh.username', $this->credential('OVH_USERNAME'));
		$config->set('engine.postproc.ovh.password', $this->credential('OVH_PASSWORD'));
		$config->set('engine.postproc.ovh.containerurl', $this->credential('OVH_CONTAINERURL'));
		$config->set('engine.postproc.ovh.directory', self::TEST_DIRECTORY);
	}

	protected function getMinimumPartSize(): int
	{
		// OVH (Swift) does not segment ordinary uploads, so this is purely a "make the large file bigger" knob.
		return self::NOMINAL_PART_SIZE;
	}

	protected function supportsMultipart(): bool
	{
		// OVH Object Storage (OpenStack Swift) uploads every object in a single PUT; it never produces >1 step.
		return false;
	}

	protected function getRemoteSize(string $remotePath): ?int
	{
		$entry = $this->findListedObject($remotePath);

		return $entry === null ? null : (int) $entry->bytes;
	}

	/**
	 * Look up a single object in the container listing by its full remote path, returning the Swift listing entry or null
	 * if it is not present (e.g. after deletion).
	 *
	 * @param   string  $remotePath  The full remote object path (directory + name), as reported by getRemotePath().
	 *
	 * @return  \stdClass|null
	 */
	private function findListedObject(string $remotePath)
	{
		// In Swift the pseudo-folder is expressed through the listing "prefix" parameter (5th argument) with an empty
		// request path; the request URL is always the container itself.
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

	/**
	 * Build (once) an independent OVH connector used to read back stored object metadata and exercise the informational
	 * methods, configured exactly like the engine's own connector.
	 *
	 * @return  OvhConnector
	 */
	private function getVerificationConnector(): OvhConnector
	{
		if (!$this->verificationConnector instanceof OvhConnector)
		{
			$connector = new OvhConnector(
				$this->credential('OVH_PROJECTID'),
				$this->credential('OVH_USERNAME'),
				$this->credential('OVH_PASSWORD')
			);
			$connector->setStorageEndpoint(self::containerUrl());

			$this->verificationConnector = $connector;
		}

		return $this->verificationConnector;
	}

	/**
	 * The full storage URL of the test container, e.g. https://storage.gra.cloud.ovh.net/v1/AUTH_xxx/my-container.
	 *
	 * @return  string
	 */
	private static function containerUrl(): string
	{
		return rtrim(trim((string) (getenv('OVH_CONTAINERURL') ?: '')), '/');
	}

	/**
	 * The container name, i.e. the last path segment of the configured container URL.
	 *
	 * @return  string
	 */
	private static function containerName(): string
	{
		$url = self::containerUrl();
		$pos = strrpos($url, '/');

		return $pos === false ? $url : substr($url, $pos + 1);
	}

	/**
	 * Read a credential from the environment, normalised to a trimmed string ('' when unset).
	 *
	 * @param   string  $key  The environment variable name.
	 *
	 * @return  string
	 */
	private function credential(string $key): string
	{
		return trim((string) (getenv($key) ?: ''));
	}
}
