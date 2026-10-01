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
use Akeeba\Engine\Postproc\Connector\OneDriveBusiness as OneDriveBusinessConnector;

/**
 * Integration test for the OneDrive for Business post-processing engine and its Microsoft Graph connector.
 *
 * Exercises the full upload / download / delete lifecycle against a live OneDrive for Business (or SharePoint) Drive for
 * both a small file (single-shot PUT upload) and a large file (genuine resumable, multi-fragment upload session),
 * verifying byte-for-byte fidelity (size + SHA-512), plus the informational/auxiliary connector methods (drive info,
 * folder listing, drive enumeration, signed URLs, resumable session lifecycle).
 *
 * The test runs only when ONEDRIVEBUSINESS_ACCESS_TOKEN, ONEDRIVEBUSINESS_REFRESH_TOKEN and ONEDRIVEBUSINESS_DLID are
 * all set (see Test/.env.sample); otherwise it self-skips, so the default `vendor/bin/phpunit Test/` run never touches
 * the network.
 *
 * The engine instantiates `Akeeba\Engine\Postproc\Connector\OneDriveBusiness` (engine/Postproc/Onedrivebusiness.php
 * line 96, via the `ConnectorOneDrive` alias). That class extends `Akeeba\Engine\Postproc\Connector\OneDrive`, so the
 * effective public surface is the union of both classes. The coverage map below enumerates that union.
 *
 * Connector coverage map (engine/Postproc/Connector/OneDriveBusiness.php + its parent OneDrive.php):
 *   COVERED:
 *     - __construct                                     (every test instantiates the connector)
 *     - ping                                            (testPing, + every engine call path pings first)
 *     - getDriveInformation                             (testGetDriveInformation, + ping() calls it internally)
 *     - createUploadSession (overridden in Business)    (large-file lifecycle + testUploadSessionLifecycle)
 *     - uploadPart                                      (large-file lifecycle + testUploadSessionLifecycle)
 *     - destroyUploadSession                            (testUploadSessionLifecycle)
 *     - simpleUpload                                    (small-file lifecycle)
 *     - download                                        (lifecycle download via engine->downloadToFile)
 *     - delete                                          (lifecycle delete; also proactively called before every upload)
 *     - makeDirectory                                   (engine processPart() creates the test directory)
 *     - getSignedUrl                                    (testGetSignedUrl — verified by downloading through the URL)
 *     - getRawContents (overridden in Business)         (listContents() calls it; testListContents)
 *     - listContents                                    (testListContents, + getRemoteSize readback, + makeDirectory)
 *     - getDrives (Business)                            (testGetDrives)
 *     - setDriveId (Business)                            (testSetDriveId, + constructor calls it)
 *     - refreshToken                                    (testRefreshToken — skipped unless ONEDRIVEBUSINESS_TEST_REFRESH=1)
 *   NOT COVERED (high-level helpers the engine itself never calls; the stepped engine drives uploadPart directly):
 *     - upload          (auto-selecting wrapper around simpleUpload/resumableUpload)
 *     - resumableUpload (blocking whole-file resumable upload)
 *   Engine-level paths NOT covered: downloadToBrowser (delegates to getSignedUrl, covered at connector level) and
 *   ranged downloads (downloadToFile throws RangeDownloadNotSupported by contract).
 *
 * @group integration
 * @group postproc
 * @group onedrivebusiness
 */
class OnedrivebusinessTest extends AbstractPostprocTestCase
{
	/**
	 * @var int Minimum multipart fragment size, in bytes.
	 *
	 * Graph requires resumable-upload fragments to be a multiple of 320 KiB (327680 bytes). The engine derives its
	 * fragment size as `chunk_upload_size` (MiB) * 1024 * 1024, so we use 5 MiB = 5242880 bytes = 16 × 320 KiB, the
	 * smallest whole-MiB value that is also a clean 320 KiB multiple. This is also the value the engine must exceed
	 * (along with the 4 MiB simple-upload ceiling) before it switches to the multipart path.
	 */
	private const MINIMUM_PART_SIZE = 5242880;

	/** @var int Chunk size configured on the engine, in MiB. 5 MiB keeps fragments to a clean 320 KiB multiple. */
	private const CHUNK_UPLOAD_SIZE_MIB = 5;

	/** @var string Drive sub-directory all test objects are stored under. */
	private const TEST_DIRECTORY = 'akeeba-engine-test';

	/** @var OneDriveBusinessConnector|null Independent connector used to read back stored object sizes. */
	private $verificationConnector;

	/**
	 * ping() succeeds against valid tokens and reports whether a refresh was needed.
	 */
	public function testPing(): void
	{
		$result = $this->getVerificationConnector()->ping();

		$this->assertIsArray($result, 'ping() should return an array.');
		$this->assertArrayHasKey('needs_refresh', $result, 'ping() result should carry the needs_refresh flag.');
	}

	/**
	 * getDriveInformation() returns a populated Drive resource for the configured Drive.
	 */
	public function testGetDriveInformation(): void
	{
		$connector = $this->getVerificationConnector();
		$connector->ping();

		$info = $connector->getDriveInformation();

		$this->assertIsArray($info, 'getDriveInformation() should return an array.');
		$this->assertArrayHasKey('id', $info, 'The Drive information should carry a Drive ID.');
		$this->assertNotEmpty($info['id'], 'The Drive ID should not be empty.');
	}

	/**
	 * getDrives() enumerates at least the configured Drive, keyed by Drive ID.
	 */
	public function testGetDrives(): void
	{
		$connector = $this->getVerificationConnector();
		$connector->ping();

		$drives = $connector->getDrives();

		$this->assertIsArray($drives, 'getDrives() should return an array.');
		$this->assertNotEmpty($drives, 'The account should expose at least one Drive.');

		// Keys are Drive IDs (or the empty string for the personal Drive); values are human-readable descriptions.
		foreach ($drives as $value)
		{
			$this->assertIsString($value, 'Each Drive description should be a string.');
		}
	}

	/**
	 * setDriveId() swaps the effective Drive: a null/empty id targets the personal Drive root, a non-empty id targets a
	 * specific Drive. We assert both forms keep the connector usable by pinging after each switch.
	 */
	public function testSetDriveId(): void
	{
		$connector = $this->getVerificationConnector();
		$connector->ping();

		$driveId = $this->credential('ONEDRIVEBUSINESS_DRIVE');

		if ($driveId === '')
		{
			// Without an explicit Drive ID we can only meaningfully exercise the personal-Drive (null) branch.
			$connector->setDriveId(null);

			$this->assertIsArray(
				$connector->getDriveInformation(),
				'After selecting the personal Drive the connector should still resolve Drive information.'
			);

			return;
		}

		// Explicit Drive: switching to it and back to the personal root must both leave the connector usable.
		$connector->setDriveId($driveId);
		$this->assertIsArray(
			$connector->getDriveInformation(),
			'After selecting the configured Drive the connector should resolve Drive information.'
		);

		$connector->setDriveId(null);
		$this->assertIsArray(
			$connector->getDriveInformation(),
			'After reverting to the personal Drive the connector should resolve Drive information.'
		);

		// Restore the configured Drive for any later use of this connector instance.
		$connector->setDriveId($driveId);
	}

	/**
	 * listContents() / getRawContents() return the uploaded object in the test directory with its correct size.
	 */
	public function testListContents(): void
	{
		[$remotePath, $localFile] = $this->uploadTemporaryObject();

		$connector = $this->getVerificationConnector();
		$connector->ping();

		$listing = $connector->listContents(self::TEST_DIRECTORY);

		$this->assertIsArray($listing, 'listContents() should return an array.');
		$this->assertArrayHasKey('files', $listing, 'listContents() should report a files collection.');

		$baseName = basename($remotePath);

		$this->assertArrayHasKey(
			$baseName, $listing['files'],
			'The uploaded object should appear in the directory listing.'
		);
		$this->assertSame(
			filesize($localFile), (int) $listing['files'][$baseName],
			'The listed file size should match the uploaded file size.'
		);
	}

	/**
	 * getSignedUrl() returns a working public download URL. The URL is verified by downloading it and comparing the
	 * bytes (size + SHA-512) against the original, as required for any sharing/link method.
	 */
	public function testGetSignedUrl(): void
	{
		[$remotePath, $localFile] = $this->uploadTemporaryObject();

		$connector = $this->getVerificationConnector();
		$connector->ping();

		$url = $connector->getSignedUrl($remotePath);

		$this->assertIsString($url);
		$this->assertStringStartsWith('https://', $url, 'The signed URL should be an HTTPS URL.');

		$downloaded = $this->createTempFileName();
		$this->httpDownload($url, $downloaded);
		$this->assertFilesEqual($localFile, $downloaded);
	}

	/**
	 * The resumable upload session lifecycle: createUploadSession() opens a session, uploadPart() pushes a fragment, and
	 * destroyUploadSession() aborts it cleanly so no finished file is left behind. This covers destroyUploadSession(),
	 * which the stepped engine path never calls (it always runs sessions to completion).
	 */
	public function testUploadSessionLifecycle(): void
	{
		$connector = $this->getVerificationConnector();
		$connector->ping();

		$remoteName = $this->uniqueRemoteName(self::MINIMUM_PART_SIZE);
		$remotePath = self::TEST_DIRECTORY . '/' . $remoteName;

		// Make sure the target directory exists, then open a resumable session.
		$connector->makeDirectory(self::TEST_DIRECTORY);
		$sessionUrl = $connector->createUploadSession($remotePath);

		$this->assertIsString($sessionUrl, 'createUploadSession() should return an upload URL.');
		$this->assertStringStartsWith('https://', $sessionUrl, 'The upload session URL should be an HTTPS URL.');

		// Push a single fragment (a clean 320 KiB multiple) so there is a genuine in-progress upload.
		$partFile = $this->createTestFile(self::MINIMUM_PART_SIZE * 2);
		$result   = $connector->uploadPart($sessionUrl, $partFile, 0, self::MINIMUM_PART_SIZE);

		$this->assertIsArray($result, 'uploadPart() should return the Graph response array.');
		$this->assertArrayNotHasKey(
			'name', $result,
			'A single fragment of a two-fragment file should not complete the upload.'
		);

		// Abort the session. A cancelled session must not leave a finished file in the directory.
		$connector->destroyUploadSession($sessionUrl);

		$listing = $connector->listContents(self::TEST_DIRECTORY);

		$this->assertArrayNotHasKey(
			$remoteName, $listing['files'] ?? [],
			'An aborted upload session should not leave a finished file behind.'
		);
	}

	/**
	 * refreshToken() exchanges the refresh token (through the akeeba.com OAuth2 relay) for a fresh access token.
	 *
	 * This consumes a refresh-token round-trip and, depending on the OAuth2 app, can rotate the refresh token, which
	 * would invalidate the stored ONEDRIVEBUSINESS_REFRESH_TOKEN for subsequent runs. It is therefore opt-in: set
	 * ONEDRIVEBUSINESS_TEST_REFRESH=1 to run it.
	 */
	public function testRefreshToken(): void
	{
		if ($this->credential('ONEDRIVEBUSINESS_TEST_REFRESH') !== '1')
		{
			$this->markTestSkipped(
				'Token refresh is destructive (it may rotate the refresh token). Set ONEDRIVEBUSINESS_TEST_REFRESH=1 to run it.'
			);
		}

		$connector = $this->getVerificationConnector();
		$result    = $connector->refreshToken();

		$this->assertIsArray($result, 'refreshToken() should return an array.');
		$this->assertArrayHasKey('access_token', $result, 'The refresh result should carry a fresh access token.');
		$this->assertNotEmpty($result['access_token'], 'The refreshed access token should not be empty.');

		// The connector should remain usable with the refreshed token.
		$this->assertIsArray(
			$connector->getDriveInformation(),
			'After a token refresh the connector should still resolve Drive information.'
		);
	}

	/**
	 * A profile which has lost its access token but kept an unexpired expiry must refresh and recover.
	 *
	 * ping() used to decide whether to refresh from the recorded expiry alone, so a profile in this state concluded its
	 * absent token was still good, sent an empty bearer token, and met an authorisation error which said nothing about
	 * the real cause. The closing getDriveInformation() is the part that used to fail: asserting on ping()'s return
	 * value alone would not prove the recovered token works.
	 *
	 * Opt-in for the same reason as testRefreshToken(): it sends the real refresh token, which the OAuth2 app may
	 * rotate.
	 */
	public function testABlankAccessTokenIsRefreshed(): void
	{
		if ($this->credential('ONEDRIVEBUSINESS_TEST_REFRESH') !== '1')
		{
			$this->markTestSkipped(
				'Token refresh is destructive (it may rotate the refresh token). Set ONEDRIVEBUSINESS_TEST_REFRESH=1 to run it.'
			);
		}

		$connector = new OneDriveBusinessConnector(
			'',
			$this->credential('ONEDRIVEBUSINESS_REFRESH_TOKEN'),
			$this->credential('ONEDRIVEBUSINESS_DLID')
		);

		// The state the bug needed: an hour of validity left, on a token we no longer hold.
		$connector->setTokenExpiration(time() + 3600);

		$result = $connector->ping();

		$this->assertTrue(
			$result['needs_refresh'],
			'A connector with no access token must refresh, whatever its recorded expiry claims.'
		);
		$this->assertNotEmpty($result['access_token'], 'The refresh should have returned a usable access token.');

		$this->assertIsArray(
			$connector->getDriveInformation(),
			'After recovering the token the connector must be able to talk to OneDrive.'
		);
	}

	protected function getEngineSlug(): string
	{
		return 'onedrivebusiness';
	}

	protected function isProviderConfigured(): bool
	{
		return $this->credential('ONEDRIVEBUSINESS_ACCESS_TOKEN') !== ''
			&& $this->credential('ONEDRIVEBUSINESS_REFRESH_TOKEN') !== ''
			&& $this->credential('ONEDRIVEBUSINESS_DLID') !== '';
	}

	protected function getSkipMessage(): string
	{
		return 'OneDrive for Business is not configured. Set ONEDRIVEBUSINESS_ACCESS_TOKEN, ONEDRIVEBUSINESS_REFRESH_TOKEN '
			. 'and ONEDRIVEBUSINESS_DLID (and optionally ONEDRIVEBUSINESS_DRIVE) to enable this integration test '
			. '(see Test/.env.sample).';
	}

	protected function configureProvider(): void
	{
		$config = Factory::getConfiguration();

		$config->set('engine.postproc.onedrivebusiness.access_token', $this->credential('ONEDRIVEBUSINESS_ACCESS_TOKEN'));
		$config->set('engine.postproc.onedrivebusiness.refresh_token', $this->credential('ONEDRIVEBUSINESS_REFRESH_TOKEN'));
		$config->set('engine.postproc.onedrivebusiness.directory', self::TEST_DIRECTORY);
		$config->set('engine.postproc.onedrivebusiness.drive', $this->credential('ONEDRIVEBUSINESS_DRIVE'));
		$config->set('engine.postproc.onedrivebusiness.oauth2_type', 'akeeba');

		// Enable chunked uploads with a small, 320 KiB-aligned fragment size so the large-file test exercises the
		// resumable (multi-fragment) upload path quickly. 5 MiB = 16 × 320 KiB.
		$config->set('engine.postproc.onedrivebusiness.chunk_upload', 1);
		$config->set('engine.postproc.onedrivebusiness.chunk_upload_size', self::CHUNK_UPLOAD_SIZE_MIB);

		// The engine reads the Akeeba Download ID from the platform's update_dlid option, not from the engine config.
		// Provide it to the test platform so the live connector can relay token refreshes through akeeba.com.
		$this->platform()->setConfigurationOption('update_dlid', $this->credential('ONEDRIVEBUSINESS_DLID'));
	}

	protected function getMinimumPartSize(): int
	{
		return self::MINIMUM_PART_SIZE;
	}

	protected function getRemoteSize(string $remotePath): ?int
	{
		$connector = $this->getVerificationConnector();
		$connector->ping();

		$directory = trim(dirname($remotePath), '/.');
		$baseName  = basename($remotePath);

		try
		{
			$listing = $connector->listContents($directory);
		}
		catch (\Throwable $e)
		{
			return null;
		}

		if (!isset($listing['files'][$baseName]))
		{
			return null;
		}

		return (int) $listing['files'][$baseName];
	}

	/**
	 * Get an independent connector instance used solely to verify stored object sizes and to exercise connector methods
	 * the engine does not call directly, separate from the engine's own connector.
	 *
	 * @return  OneDriveBusinessConnector
	 */
	private function getVerificationConnector(): OneDriveBusinessConnector
	{
		if (!$this->verificationConnector instanceof OneDriveBusinessConnector)
		{
			$driveId = $this->credential('ONEDRIVEBUSINESS_DRIVE');

			$this->verificationConnector = new OneDriveBusinessConnector(
				$this->credential('ONEDRIVEBUSINESS_ACCESS_TOKEN'),
				$this->credential('ONEDRIVEBUSINESS_REFRESH_TOKEN'),
				$this->credential('ONEDRIVEBUSINESS_DLID'),
				$driveId !== '' ? $driveId : null,
				OneDriveBusinessConnector::helperUrl
			);
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

		$this->assertTrue($ok !== false, 'Downloading the signed URL failed: ' . $error);
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
