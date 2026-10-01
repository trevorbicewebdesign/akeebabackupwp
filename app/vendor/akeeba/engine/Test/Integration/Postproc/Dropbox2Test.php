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
use Akeeba\Engine\Postproc\Connector\Dropbox2 as Dropbox2Connector;
use Akeeba\Engine\Postproc\Connector\Dropbox2\Exception\APIError as Dropbox2APIError;
use Akeeba\Engine\Postproc\Exception\BadConfiguration;

/**
 * Integration test for the Dropbox (API v2) post-processing engine and connector.
 *
 * Exercises the full upload / download / delete lifecycle against a live Dropbox account for both a small file
 * (single-shot upload, which the connector still services through a 1 MiB-part resumable session) and a large file
 * (a genuine multi-step chunked upload session driven by the engine), verifying byte-for-byte fidelity (size +
 * SHA-512), plus the informational connector methods (account info, folder listing, metadata, shared link, and the
 * authenticated download URL).
 *
 * The test runs only when DROPBOX_ACCESS_TOKEN (or DROPBOX_REFRESH_TOKEN) and DROPBOX_DLID are set (see
 * Test/.env.sample); otherwise it self-skips and never touches the network.
 *
 * Connector coverage map (engine/Postproc/Connector/Dropbox2.php):
 *   COVERED:
 *     - __construct                                     (every test; getVerificationConnector + the engine itself)
 *     - getCurrentAccount                               (testGetCurrentAccount, + transitively via ping())
 *     - ping                                            (transitively: the engine calls ping() before every operation)
 *     - upload / resumableUpload                        (small-file lifecycle: engine single-upload path -> upload())
 *     - createUploadSession / uploadPart / finishUploadSession  (large-file multipart lifecycle, engine chunked path)
 *     - makeDirectory                                   (testMakeDirectory, + the engine creates the dir on first part)
 *     - download                                        (lifecycle download via the engine's downloadToFile())
 *     - delete                                          (lifecycle delete via the engine's delete())
 *     - getMetadata                                     (testGetMetadata, + remote-size verification readback)
 *     - getRawContents                                  (testGetRawContents, + transitively via listContents())
 *     - listContents                                    (testListContents)
 *     - getSharedUrl                                    (testGetSharedUrl — verified by downloading through the URL)
 *     - getAuthenticatedUrl                             (testGetAuthenticatedUrl — verified by downloading through it)
 *     - getNamespaceId / setNamespaceId                 (testNamespaceId)
 *     - refreshToken                                    (testRefreshToken — skipped unless DROPBOX_REFRESH_TOKEN is set)
 *   NOT COVERED (deliberately):
 *     - getRawContentsContinue                          (only reached for >2000-entry folders; not worth materialising.
 *                                                        It is the same fetch() round-trip as getRawContents, which IS
 *                                                        covered. Documented, not tested.)
 *   Engine-level paths NOT covered: ranged downloads (downloadToFile() with an offset throws RangeDownloadNotSupported
 *   by design) and the Dropbox-for-Business team namespace resolution (requires a Business account; see NOTES).
 *
 * @group integration
 * @group postproc
 * @group dropbox
 */
class Dropbox2Test extends AbstractPostprocTestCase
{
	/**
	 * @var int Dropbox upload-session parts must be a multiple of 320 KiB and at most 150 MiB. We use 4 MiB so the
	 *          large-file test produces several parts quickly while honouring the 320 KiB-multiple constraint.
	 */
	private const PART_SIZE_BYTES = 4194304;

	/** @var int The chunk size, in megabytes, written to the engine configuration (must match PART_SIZE_BYTES). */
	private const PART_SIZE_MB = 4;

	/** @var string Account sub-directory all test objects are stored under. */
	private const TEST_DIRECTORY = 'akeeba-engine-test';

	/** @var Dropbox2Connector|null Independent connector used to read back stored object sizes and metadata. */
	private $verificationConnector;

	/**
	 * A syntactically plausible refresh token which Dropbox will reject. Used so the failure tests never send the real
	 * one to the relay.
	 */
	private const INVALID_REFRESH_TOKEN = 'ThisIsNotARealDropboxRefreshToken000000000000000000000000000000';

	// =================================================================================================================
	// Failure surfacing
	//
	// The akeeba.com relay reports every one of these failures as an error payload carrying an HTTP *200* status, so
	// nothing throws on its own: the connector has to notice that no access token came back. Before it did, it kept the
	// old dead token and reported the refresh as a success, and the user met the problem later as a bare
	// `expired_access_token` from whatever call happened next.
	// =================================================================================================================

	/**
	 * A refresh token Dropbox rejects must surface as an APIError which says so.
	 */
	public function testInvalidRefreshTokenIsReported(): void
	{
		$connector = new Dropbox2Connector(
			'not-a-real-access-token', self::INVALID_REFRESH_TOKEN, $this->credential('DROPBOX_DLID')
		);

		try
		{
			$connector->refreshToken();

			$this->fail('A refresh token Dropbox rejects must throw, not be silently swallowed.');
		}
		catch (Dropbox2APIError $e)
		{
			$this->assertStringContainsString('invalid_grant', $e->getMessage());
			$this->assertStringContainsStringIgnoringCase('refresh token', $e->getMessage());
			$this->assertStringContainsStringIgnoringCase('reconnect', $e->getMessage());
		}
	}

	/**
	 * A missing Download ID is rejected by the relay, which will not refresh anybody's tokens without one.
	 *
	 * Dropbox's relay explains this in `user_message` rather than `error_description`, so this also pins down that the
	 * connector reads both — reading only the latter threw the useful half of the message away.
	 */
	public function testMissingDownloadIdIsReported(): void
	{
		$connector = new Dropbox2Connector('not-a-real-access-token', self::INVALID_REFRESH_TOKEN, '');

		try
		{
			$connector->refreshToken();

			$this->fail('A refresh with no Download ID must throw, not be silently swallowed.');
		}
		catch (Dropbox2APIError $e)
		{
			$this->assertStringContainsString('invalid_dlid', $e->getMessage());
			$this->assertStringContainsStringIgnoringCase('download id', $e->getMessage());
		}
	}

	/**
	 * An invalid Download ID — as opposed to an absent one — must be reported too. This is what a user whose
	 * subscription has lapsed hits.
	 */
	public function testInvalidDownloadIdIsReported(): void
	{
		$connector = new Dropbox2Connector(
			'not-a-real-access-token', self::INVALID_REFRESH_TOKEN, '00:deadbeefdeadbeefdeadbeefdeadbeef'
		);

		try
		{
			$connector->refreshToken();

			$this->fail('A refresh with an invalid Download ID must throw, not be silently swallowed.');
		}
		catch (Dropbox2APIError $e)
		{
			$this->assertStringContainsStringIgnoringCase('download id', $e->getMessage());
			$this->assertStringContainsStringIgnoringCase('subscription', $e->getMessage());
		}
	}

	/**
	 * The engine refuses to build a connector at all when no Download ID is configured, because it could never refresh
	 * the tokens. That guard must fire with a message naming the Download ID.
	 */
	public function testEngineRefusesToRunWithoutADownloadId(): void
	{
		$this->platform()->setConfigurationOption('update_dlid', '');

		$this->expectException(BadConfiguration::class);
		$this->expectExceptionMessageMatches('/Download ID/i');

		$this->getEngine()->delete('/' . self::TEST_DIRECTORY . '/never-created.txt');
	}

	/**
	 * getCurrentAccount() returns a populated account information array.
	 */
	public function testGetCurrentAccount(): void
	{
		$account = $this->getVerificationConnector()->getCurrentAccount();

		$this->assertIsArray($account, 'getCurrentAccount() should return an array.');
		$this->assertArrayHasKey('account_id', $account, 'The account information should carry an account ID.');
		$this->assertNotEmpty($account['account_id'], 'The account ID should not be empty.');
	}

	/**
	 * getMetadata() reports the correct name and size for an uploaded object.
	 */
	public function testGetMetadata(): void
	{
		[$remotePath, $localFile] = $this->uploadTemporaryObject();

		$metadata = $this->getVerificationConnector()->getMetadata('/' . ltrim($remotePath, '/'));

		$this->assertIsArray($metadata, 'getMetadata() should return an array.');
		$this->assertArrayHasKey('size', $metadata, 'The metadata should carry the file size.');
		$this->assertSame(
			filesize($localFile), (int) $metadata['size'],
			'The metadata size should match the uploaded file size.'
		);
	}

	/**
	 * getRawContents() lists the directory the object was uploaded into and includes that object.
	 */
	public function testGetRawContents(): void
	{
		[$remotePath, $localFile] = $this->uploadTemporaryObject();

		$raw = $this->getVerificationConnector()->getRawContents('/' . self::TEST_DIRECTORY);

		$this->assertIsArray($raw, 'getRawContents() should return an array.');
		$this->assertArrayHasKey('entries', $raw, 'The raw folder listing should carry an entries array.');

		$names = array_map(static fn ($entry) => $entry['name'] ?? '', $raw['entries']);

		$this->assertContains(
			basename($remotePath), $names,
			'The uploaded object should appear in the raw folder listing.'
		);
	}

	/**
	 * listContents() returns the processed folders/files structure and the uploaded object's size.
	 */
	public function testListContents(): void
	{
		[$remotePath, $localFile] = $this->uploadTemporaryObject();

		$contents = $this->getVerificationConnector()->listContents('/' . self::TEST_DIRECTORY);

		$this->assertIsArray($contents, 'listContents() should return an array.');
		$this->assertArrayHasKey('files', $contents, 'The processed listing should carry a files array.');

		$baseName = basename($remotePath);

		$this->assertArrayHasKey(
			$baseName, $contents['files'],
			'The uploaded object should appear in the processed file listing.'
		);
		$this->assertSame(
			filesize($localFile), (int) $contents['files'][$baseName],
			'The processed listing should report the uploaded file size.'
		);
	}

	/**
	 * getSharedUrl() returns a public shared link that actually serves the file. Skipped when the account/scope cannot
	 * create shared links (e.g. some team policies disable public sharing).
	 */
	public function testGetSharedUrl(): void
	{
		[$remotePath, $localFile] = $this->uploadTemporaryObject();

		$connector = $this->getVerificationConnector();

		try
		{
			$url = $connector->getSharedUrl('/' . ltrim($remotePath, '/'));
		}
		catch (\Throwable $e)
		{
			$this->markTestSkipped('This Dropbox account cannot create public shared links: ' . $e->getMessage());
		}

		$this->assertIsString($url);
		$this->assertStringStartsWith('https://', $url, 'The shared URL should be an HTTPS URL.');

		// A Dropbox shared link points at a preview page; appending dl=1 makes it serve the raw file bytes.
		$downloadUrl = $url . (strpos($url, '?') === false ? '?' : '&') . 'dl=1';

		$downloaded = $this->createTempFileName();
		$this->httpDownload($downloadUrl, $downloaded);
		$this->assertFilesEqual($localFile, $downloaded);
	}

	/**
	 * getAuthenticatedUrl() returns a working direct-download URL (a Dropbox temporary link) that serves the file
	 * byte-for-byte. This is the URL the engine's downloadToBrowser() hands back, so we verify it end-to-end.
	 */
	public function testGetAuthenticatedUrl(): void
	{
		[$remotePath, $localFile] = $this->uploadTemporaryObject();

		$connector = $this->getVerificationConnector();

		// Mint the temporary link with a valid token (assume the Test/.env access token may be stale, so force a
		// refresh first). The link itself is tokenless and stays valid for its whole lifetime, so the subsequent
		// download is robust regardless of token expiry.
		$connector->ping(true);

		$url = $connector->getAuthenticatedUrl('/' . ltrim($remotePath, '/'));

		$this->assertIsString($url);
		$this->assertStringStartsWith('https://', $url, 'The authenticated URL should be an HTTPS URL.');

		$downloaded = $this->createTempFileName();
		$this->httpDownload($url, $downloaded);
		$this->assertFilesEqual($localFile, $downloaded);
	}

	/**
	 * makeDirectory() is idempotent: creating an already-existing directory must not raise an error.
	 */
	public function testMakeDirectory(): void
	{
		$connector = $this->getVerificationConnector();
		$dir       = '/' . self::TEST_DIRECTORY . '/subdir-' . getmypid() . '-' . uniqid();

		// First creation.
		$connector->makeDirectory($dir);

		// Re-creating the same directory must be a no-op rather than an error.
		$connector->makeDirectory($dir);

		$metadata = $connector->getMetadata($dir);

		$this->assertIsArray($metadata, 'The created directory should have metadata.');
		$this->assertSame('folder', $metadata['.tag'] ?? '', 'The created path should be a folder.');

		// Clean up the directory we created.
		$connector->delete($dir, false);
	}

	/**
	 * getNamespaceId()/setNamespaceId() round-trip the namespace ID used for Dropbox-for-Business team spaces.
	 */
	public function testNamespaceId(): void
	{
		$connector = $this->getVerificationConnector();

		// A fresh connector defaults to the user's personal namespace (empty string).
		$fresh = new Dropbox2Connector(
			$this->credential('DROPBOX_ACCESS_TOKEN'),
			$this->credential('DROPBOX_REFRESH_TOKEN'),
			$this->credential('DROPBOX_DLID')
		);

		$this->assertSame('', $fresh->getNamespaceId(), 'A fresh connector should default to the personal namespace.');

		$fresh->setNamespaceId('1234567890');
		$this->assertSame('1234567890', $fresh->getNamespaceId(), 'setNamespaceId() should round-trip the value.');

		$fresh->setNamespaceId('');
		$this->assertSame('', $fresh->getNamespaceId(), 'setNamespaceId(\'\') should restore the personal namespace.');
	}

	/**
	 * refreshToken() exchanges the refresh token for a fresh access token via the akeeba.com relay. Skipped unless a
	 * refresh token is configured (an access token alone cannot be refreshed).
	 */
	public function testRefreshToken(): void
	{
		if ($this->credential('DROPBOX_REFRESH_TOKEN') === '')
		{
			$this->markTestSkipped('No DROPBOX_REFRESH_TOKEN configured; cannot exercise token refresh.');
		}

		$connector = new Dropbox2Connector(
			$this->credential('DROPBOX_ACCESS_TOKEN'),
			$this->credential('DROPBOX_REFRESH_TOKEN'),
			$this->credential('DROPBOX_DLID')
		);

		$result = $connector->refreshToken();

		$this->assertIsArray($result, 'refreshToken() should return the refresh response array.');
		$this->assertArrayHasKey('access_token', $result, 'The refresh response should carry a new access token.');
		$this->assertNotEmpty($result['access_token'], 'The refreshed access token should not be empty.');

		// The freshly refreshed connector must still be able to talk to Dropbox.
		$account = $connector->getCurrentAccount();
		$this->assertArrayHasKey('account_id', $account, 'The connector should work after refreshing its token.');
	}

	/**
	 * A profile which has lost its access token but kept an unexpired expiry must refresh and recover.
	 *
	 * ping() used to decide whether to refresh from the recorded expiry alone, so a profile in this state concluded its
	 * absent token was still good, sent an empty bearer token, and met an authorisation error which said nothing about
	 * the real cause. The closing getCurrentAccount() is the part that used to fail: asserting on ping()'s return value
	 * alone would not prove the recovered token works.
	 */
	public function testABlankAccessTokenIsRefreshed(): void
	{
		if ($this->credential('DROPBOX_REFRESH_TOKEN') === '')
		{
			$this->markTestSkipped('No DROPBOX_REFRESH_TOKEN configured; cannot exercise token refresh.');
		}

		$connector = new Dropbox2Connector(
			'',
			$this->credential('DROPBOX_REFRESH_TOKEN'),
			$this->credential('DROPBOX_DLID')
		);

		// The state the bug needed: an hour of validity left, on a token we no longer hold.
		$connector->setTokenExpiration(time() + 3600);

		$result = $connector->ping();

		$this->assertTrue(
			$result['needs_refresh'],
			'A connector with no access token must refresh, whatever its recorded expiry claims.'
		);
		$this->assertNotEmpty($result['access_token'], 'The refresh should have returned a usable access token.');

		$account = $connector->getCurrentAccount();

		$this->assertArrayHasKey(
			'account_id', $account,
			'After recovering the token the connector must be able to talk to Dropbox.'
		);
	}

	protected function getEngineSlug(): string
	{
		return 'dropbox2';
	}

	protected function isProviderConfigured(): bool
	{
		$hasToken = $this->credential('DROPBOX_ACCESS_TOKEN') !== ''
			|| $this->credential('DROPBOX_REFRESH_TOKEN') !== '';

		return $hasToken && $this->credential('DROPBOX_DLID') !== '';
	}

	protected function getSkipMessage(): string
	{
		return 'Dropbox is not configured. Set DROPBOX_ACCESS_TOKEN (and/or DROPBOX_REFRESH_TOKEN) plus DROPBOX_DLID '
			. 'to enable this integration test (see Test/.env.sample).';
	}

	protected function configureProvider(): void
	{
		$config = Factory::getConfiguration();

		$config->set('engine.postproc.dropbox2.access_token', $this->credential('DROPBOX_ACCESS_TOKEN'));
		$config->set('engine.postproc.dropbox2.refresh_token', $this->credential('DROPBOX_REFRESH_TOKEN'));
		$config->set('engine.postproc.dropbox2.directory', self::TEST_DIRECTORY);
		$config->set('engine.postproc.dropbox2.oauth2_type', 'akeeba');
		$config->set('engine.postproc.dropbox2.team', $this->credential('DROPBOX_TEAM') === '1' ? 1 : 0);

		// Enable chunked uploads with a SMALL part size so the large-file test genuinely exercises the multi-step
		// upload-session path (createUploadSession -> uploadPart* -> finishUploadSession) without moving a lot of data.
		// Dropbox requires session parts to be a multiple of 320 KiB; 4 MiB satisfies that and matches PART_SIZE_BYTES.
		$config->set('engine.postproc.dropbox2.chunk_upload', true);
		$config->set('engine.postproc.dropbox2.chunk_upload_size', self::PART_SIZE_MB);

		// The engine sources the Download ID from the platform option `update_dlid`, not from a config key. Seed it on
		// the shared test platform; without it the engine's makeConnector() throws BadConfiguration.
		$this->platform()->setConfigurationOption('update_dlid', $this->credential('DROPBOX_DLID'));
	}

	protected function getMinimumPartSize(): int
	{
		return self::PART_SIZE_BYTES;
	}

	protected function getRemoteSize(string $remotePath): ?int
	{
		$connector = $this->getVerificationConnector();

		try
		{
			$metadata = $connector->getMetadata('/' . ltrim($remotePath, '/'));
		}
		catch (\Throwable $e)
		{
			// not_found (deleted or never created) -> the object does not exist.
			return null;
		}

		if (!is_array($metadata) || !isset($metadata['size']))
		{
			return null;
		}

		return (int) $metadata['size'];
	}

	/**
	 * Get an independent connector instance used solely to verify stored objects, separate from the engine's own
	 * connector. It mirrors the engine's own connector construction (tokens, dlid, akeeba.com refresh relay).
	 *
	 * It also mirrors what the engine does *before* it talks to Dropbox: ping(), which renews the access token when it
	 * has lapsed. Dropbox access tokens live only four hours, so the one configured in Test/.env is stale far more
	 * often than not — without this the verification calls fail with `expired_access_token` even though the (long-lived)
	 * refresh token is perfectly good. The engine never had this problem; only this test's own connector did.
	 *
	 * @return  Dropbox2Connector
	 */
	private function getVerificationConnector(): Dropbox2Connector
	{
		if (!$this->verificationConnector instanceof Dropbox2Connector)
		{
			$this->verificationConnector = new Dropbox2Connector(
				$this->credential('DROPBOX_ACCESS_TOKEN'),
				$this->credential('DROPBOX_REFRESH_TOKEN'),
				$this->credential('DROPBOX_DLID')
			);

			$this->verificationConnector->ping();
		}

		return $this->verificationConnector;
	}

	/**
	 * Download a URL straight to a local file with cURL, failing the test on any HTTP or transport error.
	 *
	 * @param   string  $url     The URL to download.
	 * @param   string  $target  Absolute path to write the downloaded bytes to.
	 */
	private function httpDownload(string $url, string $target): void
	{
		$fp = fopen($target, 'wb');

		if ($fp === false)
		{
			$this->fail('Could not open the download target file for writing: ' . $target);
		}

		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_FILE           => $fp,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_FAILONERROR    => true,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_CAINFO         => AKEEBA_CACERT_PEM,
		]);

		$ok    = curl_exec($ch);
		$error = curl_error($ch);

		curl_close($ch);
		fclose($fp);

		$this->assertTrue($ok !== false, 'Downloading the URL failed: ' . $error);
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
