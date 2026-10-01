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
use Akeeba\Engine\Postproc\Connector\Cloudfiles as CloudfilesConnector;

/**
 * Integration test for the RackSpace CloudFiles post-processing engine and connector.
 *
 * Exercises the full upload / download / delete lifecycle against a live RackSpace CloudFiles container for both a
 * small file and a "large" file, verifying byte-for-byte fidelity (size + SHA-512), plus the informational and
 * management methods of the CloudFiles (OpenStack SWIFT) connector: authentication, token handling, option export,
 * container listing, object listing, and the accessor methods inherited from the Swift base class.
 *
 * CloudFiles (SWIFT) does NOT segment ordinary uploads: the engine's processPart() performs a single PUT regardless of
 * file size (engine/Postproc/Cloudfiles.php::processPart always returns true after one putObject call). There is
 * therefore no real multipart minimum; getMinimumPartSize() returns a sensible size that still makes the "large" file
 * meaningfully bigger than the small one, and the large-file test does NOT assert a multipart step count.
 *
 * The test runs only when CLOUDFILES_USERNAME, CLOUDFILES_APIKEY and CLOUDFILES_CONTAINER are all set
 * (see Test/.env.sample); otherwise it self-skips.
 *
 * Connector coverage map (engine/Postproc/Connector/Cloudfiles.php and its Swift base class):
 *   COVERED:
 *     - __construct                                     (everywhere — verification connector + engine)
 *     - authenticate (public override)                  (testAuthenticate, + getVerificationConnector everywhere)
 *     - getCurrentOptions                               (testGetCurrentOptions)
 *     - getToken                                        (testGetToken)
 *     - getTokenExpiration                              (testGetToken)
 *     - putObject  (Swift)                              (small + large lifecycle, uploadViaEngine)
 *     - downloadObject  (Swift)                         (lifecycle download, ranged download in downloadToFile)
 *     - deleteObject  (Swift)                           (lifecycle delete)
 *     - listContainers  (Swift)                         (testListContainers)
 *     - listContents  (Swift)                           (testListContents, + getRemoteSize verification)
 *     - getStorageEndpoint / setStorageEndpoint  (Swift)(testStorageEndpointAccessors)
 *     - getAuthEndpoint / setAuthEndpoint  (Swift)      (testAuthEndpointAccessors)
 *     - getTenantId / setTenantId  (Swift)              (testTenantIdAccessors)
 *     - getUsername / setUsername  (Swift)              (testUsernameAccessor)
 *     - getPassword / setPassword  (Swift)              (testPasswordAccessor)
 *     - getEndPoints  (Swift)                           (testGetEndPoints — empty for CloudFiles, asserted to be array)
 *   NOT COVERED:
 *     - (none) — every public method is exercised either by the lifecycle or by an explicit test below.
 *   Notes:
 *     - CloudFiles has no signed/temp-URL method on this connector (the engine sets supportsDownloadToBrowser=false),
 *       so there is no signed-URL download test.
 *     - getEndPoints() is populated by Swift::authenticateV2(), but the CloudFiles subclass overrides authenticate()
 *       with its own RAX-KSKEY flow that does NOT populate endPoints; the accessor is still exercised and asserted to
 *       return an array. See ENGINE RISKS in the accompanying report.
 *
 * @group integration
 * @group postproc
 * @group cloudfiles
 */
class CloudfilesTest extends AbstractPostprocTestCase
{
	/**
	 * CloudFiles (SWIFT) does not segment uploads, so there is no provider-imposed minimum part size. We pick 1 MiB so
	 * the "large" file (2.5x this) is a few MiB — large enough to be a meaningfully different upload from the 256 KiB
	 * small file, yet small enough to keep the network test quick.
	 *
	 * @var int
	 */
	private const NOMINAL_PART_SIZE = 1048576;

	/** @var string Container sub-directory all test objects are stored under. */
	private const TEST_DIRECTORY = 'akeeba-engine-test';

	/** @var CloudfilesConnector|null Independent connector used to read back stored object sizes and list contents. */
	private $verificationConnector;

	/**
	 * authenticate() obtains a usable token and, after authentication, getCurrentOptions() reports a populated token,
	 * region and storage endpoint.
	 */
	public function testAuthenticate(): void
	{
		$connector = $this->getVerificationConnector();
		$connector->authenticate();

		$options = $connector->getCurrentOptions();

		$this->assertNotEmpty($options['token'], 'After authentication the connector should hold a non-empty token.');
		$this->assertNotEmpty($options['region'], 'After authentication the connector should know its region.');
		$this->assertNotEmpty(
			$options['storageEndpoint'],
			'After authentication the connector should have resolved its storage endpoint.'
		);
		$this->assertStringStartsWith(
			'https://', (string) $options['storageEndpoint'],
			'The resolved storage endpoint should be an HTTPS URL.'
		);
	}

	/**
	 * getCurrentOptions() returns the full option set needed to re-instantiate a connector without re-authenticating
	 * (this is exactly what the engine caches in volatile.postproc.cloudfiles.options).
	 */
	public function testGetCurrentOptions(): void
	{
		$connector = $this->getVerificationConnector();
		$connector->authenticate();

		$options = $connector->getCurrentOptions();

		foreach (['token', 'tokenExpiration', 'tenantId', 'container', 'userContract', 'authEndpoint', 'region', 'storageEndpoint', 'apiVersion'] as $key)
		{
			$this->assertArrayHasKey($key, $options, sprintf('getCurrentOptions() must expose the "%s" key.', $key));
		}

		$this->assertSame(
			$this->credential('CLOUDFILES_CONTAINER'), $options['container'],
			'getCurrentOptions() should report the container the connector was configured with.'
		);

		// A connector primed with these options should be usable without a fresh authorization round-trip: listing the
		// container's contents must succeed.
		$primed = new CloudfilesConnector(
			$this->credential('CLOUDFILES_USERNAME'),
			$this->credential('CLOUDFILES_APIKEY'),
			$options
		);

		// SWIFT/CloudFiles pseudo-folders are filtered through the listing's "prefix" parameter, NOT by appending the
		// directory to the request URL: the URL is always the container itself. Passing the directory as the request
		// path makes RackSpace look for a (non-existent) container named "<container>/<directory>" and return HTTP 404.
		$this->assertIsArray(
			$this->normaliseListing($primed->listContents('', true, null, 1000, self::TEST_DIRECTORY . '/')),
			'A connector primed with stored options should be able to list the container without re-authenticating.'
		);
	}

	/**
	 * getToken() returns a non-empty token and getTokenExpiration() a future timestamp.
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
	 * listContainers() lists the account's containers and includes the configured one.
	 */
	public function testListContainers(): void
	{
		$connector  = $this->getVerificationConnector();
		$connector->authenticate();
		$containers = $connector->listContainers(true);

		$this->assertIsArray($containers, 'listContainers(true) should return an associative array.');
		$this->assertArrayHasKey(
			$this->credential('CLOUDFILES_CONTAINER'), $containers,
			'The configured container should appear in the account container listing.'
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
	 * getStorageEndpoint() / setStorageEndpoint() round-trip a value and return the connector for chaining.
	 */
	public function testStorageEndpointAccessors(): void
	{
		$connector = $this->getVerificationConnector();
		$connector->authenticate();

		$original = $connector->getStorageEndpoint();
		$this->assertNotEmpty($original, 'After authentication the storage endpoint accessor should be populated.');

		$result = $connector->setStorageEndpoint($original);
		$this->assertInstanceOf(CloudfilesConnector::class, $result, 'setStorageEndpoint() should be chainable.');
		$this->assertSame($original, $connector->getStorageEndpoint(), 'The storage endpoint should round-trip.');
	}

	/**
	 * getAuthEndpoint() / setAuthEndpoint() round-trip a value.
	 */
	public function testAuthEndpointAccessors(): void
	{
		$connector = $this->getVerificationConnector();

		$original = $connector->getAuthEndpoint();
		$this->assertNotEmpty($original, 'The CloudFiles connector ships with a default authentication endpoint.');

		$connector->setAuthEndpoint($original);
		$this->assertSame($original, $connector->getAuthEndpoint(), 'The auth endpoint should round-trip.');
	}

	/**
	 * getTenantId() / setTenantId() round-trip a value (after authentication the tenant ID is populated).
	 */
	public function testTenantIdAccessors(): void
	{
		$connector = $this->getVerificationConnector();
		$connector->authenticate();

		$tenantId = $connector->getTenantId();
		$this->assertNotEmpty($tenantId, 'After authentication the connector should know its tenant ID.');

		$connector->setTenantId($tenantId);
		$this->assertSame($tenantId, $connector->getTenantId(), 'The tenant ID should round-trip.');
	}

	/**
	 * getUsername() / setUsername() expose the configured username.
	 */
	public function testUsernameAccessor(): void
	{
		$connector = $this->getVerificationConnector();

		$this->assertSame(
			$this->credential('CLOUDFILES_USERNAME'), $connector->getUsername(),
			'getUsername() should return the configured username.'
		);

		$connector->setUsername('someone-else');
		$this->assertSame('someone-else', $connector->getUsername(), 'The username should round-trip.');
	}

	/**
	 * getPassword() / setPassword() expose the configured API key (mapped to the Swift "password").
	 */
	public function testPasswordAccessor(): void
	{
		$connector = $this->getVerificationConnector();

		$this->assertSame(
			$this->credential('CLOUDFILES_APIKEY'), $connector->getPassword(),
			'getPassword() should return the configured API key.'
		);

		$connector->setPassword('new-key');
		$this->assertSame('new-key', $connector->getPassword(), 'The API key should round-trip.');
	}

	/**
	 * getEndPoints() returns an array. The CloudFiles authenticate() override uses the RAX-KSKEY flow which does not
	 * populate the SWIFT endPoints map, so this is expected to be an (empty) array; we assert the contract only.
	 */
	public function testGetEndPoints(): void
	{
		$connector = $this->getVerificationConnector();
		$connector->authenticate();

		$this->assertIsArray($connector->getEndPoints(), 'getEndPoints() should always return an array.');
	}

	protected function getEngineSlug(): string
	{
		return 'cloudfiles';
	}

	protected function isProviderConfigured(): bool
	{
		return $this->credential('CLOUDFILES_USERNAME') !== ''
			&& $this->credential('CLOUDFILES_APIKEY') !== ''
			&& $this->credential('CLOUDFILES_CONTAINER') !== '';
	}

	protected function getSkipMessage(): string
	{
		return 'RackSpace CloudFiles is not configured. Set CLOUDFILES_USERNAME, CLOUDFILES_APIKEY and '
			. 'CLOUDFILES_CONTAINER to enable this integration test (see Test/.env.sample).';
	}

	protected function configureProvider(): void
	{
		$config = Factory::getConfiguration();

		$config->set('engine.postproc.cloudfiles.username', $this->credential('CLOUDFILES_USERNAME'));
		$config->set('engine.postproc.cloudfiles.apikey', $this->credential('CLOUDFILES_APIKEY'));
		$config->set('engine.postproc.cloudfiles.container', $this->credential('CLOUDFILES_CONTAINER'));
		$config->set('engine.postproc.cloudfiles.directory', self::TEST_DIRECTORY);
	}

	protected function getMinimumPartSize(): int
	{
		// CloudFiles (SWIFT) does not segment ordinary uploads, so this is purely a "make the large file bigger" knob.
		return self::NOMINAL_PART_SIZE;
	}

	protected function supportsMultipart(): bool
	{
		// CloudFiles (OpenStack SWIFT) uploads every object in a single PUT; it never produces >1 processPart() step.
		return false;
	}

	protected function getRemoteSize(string $remotePath): ?int
	{
		$entry = $this->findListedObject($remotePath);

		if ($entry === null)
		{
			return null;
		}

		return (int) $entry->bytes;
	}

	/**
	 * Look up a single object in the container listing by its full remote path, returning the SWIFT listing entry or
	 * null if it is not present.
	 *
	 * @param   string  $remotePath  The full remote object path (directory + name), as reported by getRemotePath().
	 *
	 * @return  \stdClass|null
	 */
	private function findListedObject(string $remotePath)
	{
		$connector = $this->getVerificationConnector();
		$connector->authenticate();

		// List the directory the object lives in, then match by full name. In SWIFT/CloudFiles the pseudo-folder is
		// expressed through the listing "prefix" parameter (5th argument) with an empty request path; appending it to
		// the request path instead would make RackSpace 404 looking for a container of that name.
		$directory = ltrim((string) dirname($remotePath), '.');
		$prefix    = ($directory === '' || $directory === DIRECTORY_SEPARATOR) ? '' : ($directory . '/');

		$listing = $this->normaliseListing($connector->listContents('', true, null, 1000, $prefix));

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
	 * Normalise the value returned by listContents()/listContainers() to a plain array of objects we can iterate.
	 *
	 * @param   mixed  $listing  The raw return value.
	 *
	 * @return  array
	 */
	private function normaliseListing($listing): array
	{
		if (is_array($listing))
		{
			return $listing;
		}

		if (is_object($listing))
		{
			return (array) $listing;
		}

		return [];
	}

	/**
	 * Get an independent connector instance used solely to verify stored object sizes and exercise informational
	 * methods, separate from the engine's own connector.
	 *
	 * @return  CloudfilesConnector
	 */
	private function getVerificationConnector(): CloudfilesConnector
	{
		if (!$this->verificationConnector instanceof CloudfilesConnector)
		{
			$this->verificationConnector = new CloudfilesConnector(
				$this->credential('CLOUDFILES_USERNAME'),
				$this->credential('CLOUDFILES_APIKEY'),
				['container' => $this->credential('CLOUDFILES_CONTAINER')]
			);
		}

		return $this->verificationConnector;
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
