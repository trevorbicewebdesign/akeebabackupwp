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
use Akeeba\Engine\Postproc\Connector\Backblaze as BackblazeConnector;
use Akeeba\Engine\Postproc\Connector\Backblaze\AccountInformation;

/**
 * Integration test for the BackBlaze B2 post-processing engine and connector.
 *
 * Exercises the full upload / download / delete lifecycle against a live BackBlaze B2 bucket for both a small file
 * (single-shot upload) and a large file (genuine multipart upload), verifying byte-for-byte fidelity (size + SHA-512),
 * plus the informational connector methods (account info, bucket listing, bucket ID lookup, file versions, signed URLs).
 *
 * The test runs only when BACKBLAZE_ID, BACKBLAZE_KEY and BACKBLAZE_BUCKET are all set (see Test/.env.sample); otherwise
 * it self-skips.
 *
 * Connector coverage map (engine/Postproc/Connector/Backblaze.php):
 *   COVERED:
 *     - authorizeAccount / getAccountInformation       (testGetAccountInformation, + indirectly everywhere)
 *     - getBucketId                                     (testGetBucketId, + the lifecycle and signed-URL tests)
 *     - listBuckets, unrestricted key                   (testListBuckets — skipped if the key lacks listBuckets)
 *     - listBuckets, bucket-restricted key              (testListBucketsFiltersByBucketIdForARestrictedKey — the
 *                                                        bucketId-filtered branch, which no other key here can reach)
 *     - getFileVersions                                 (testGetFileVersions, + remote-size verification)
 *     - getSignedUrl                                    (testGetSignedUrl — skipped if the key lacks shareFiles)
 *     - getUploadUrl / uploadSingleFile                 (small-file lifecycle)
 *     - startUpload / getPartUploadUrl / uploadPart / finishUpload  (large-file multipart lifecycle)
 *     - cancelUpload                                    (testCancelUpload)
 *     - downloadFile                                    (lifecycle download)
 *     - downloadFileById                                (testDownloadFileById)
 *     - deleteByFileName                                (lifecycle delete)
 *     - deleteByFileId                                  (indirectly: deleteByFileName loops over deleteByFileId)
 *     - setAccountInformation                           (testSetAccountInformation)
 *   NOT COVERED (left for you to decide whether/how to test):
 *     - uploadFile / uploadLargeFile (high-level helpers the engine itself does not use)
 *   Engine-level paths NOT covered: downloadToBrowser (uses getSignedUrl, covered at connector level) and ranged
 *   downloads (downloadToFile with an offset/length).
 *
 * @group integration
 * @group postproc
 * @group backblaze
 */
class BackblazeTest extends AbstractPostprocTestCase
{
	/** @var int BackBlaze B2's minimum multipart part size is 5 MiB. */
	private const MINIMUM_PART_SIZE = 5242880;

	/** @var string Bucket sub-directory all test objects are stored under. */
	private const TEST_DIRECTORY = 'akeeba-engine-test';

	/** @var BackblazeConnector|null Independent connector used to read back stored object sizes. */
	private $verificationConnector;

	/**
	 * getAccountInformation() returns a valid, fully-populated account information object.
	 */
	public function testGetAccountInformation(): void
	{
		$info = $this->getVerificationConnector()->getAccountInformation();

		$this->assertTrue($info->isValid(), 'The account information should be valid.');
		$this->assertNotEmpty($info->accountId, 'The account information should carry an account ID.');
		$this->assertNotEmpty($info->apiUrl, 'The account information should carry an API URL.');
		$this->assertNotEmpty($info->downloadUrl, 'The account information should carry a download URL.');
		$this->assertGreaterThan(
			0, (int) $info->absoluteMinimumPartSize,
			'The account information should report a positive absolute minimum part size.'
		);
	}

	/**
	 * listBuckets() returns the configured bucket. Skipped when the application key cannot list buckets.
	 */
	public function testListBuckets(): void
	{
		$connector = $this->getVerificationConnector();

		if (!$connector->getAccountInformation()->allowed->canListBuckets())
		{
			$this->markTestSkipped('The BackBlaze B2 application key does not have the listBuckets capability.');
		}

		$buckets = $connector->listBuckets();

		$this->assertNotEmpty($buckets, 'The account should contain at least one bucket.');

		$names = array_map(static fn ($bucket) => $bucket->bucketName, $buckets);

		$this->assertContains(
			$this->credential('BACKBLAZE_BUCKET'), $names,
			'The configured bucket should appear in the list of buckets.'
		);
	}

	/**
	 * listBuckets() must send a bucketId-filtered request when the key is restricted to a bucket, and BackBlaze must
	 * accept it.
	 *
	 * Since API v2, b2_list_buckets refuses an unfiltered listing from a bucket-restricted key: you have to name the
	 * bucket you want. (v1 quietly filtered the listing for you; that workaround is gone.) So listBuckets() asks for
	 * each bucket the key is restricted to, one call apiece, and merges the results.
	 *
	 * Neither of our other keys can reach that branch. This one is unrestricted, so it takes the single unfiltered call;
	 * the BackblazeRestrictedTest key is restricted but deliberately has no listBuckets capability, so it throws before
	 * making any request at all. We therefore drive the branch by handing the connector a real, live authorization —
	 * genuine token, genuine API URL — wrapped in an `allowed` that claims the key is locked to the test bucket. The
	 * request that goes over the wire is byte-for-byte the one a real restricted key would send, so if BackBlaze accepts
	 * it and hands back exactly that bucket, the branch is proven against the live API.
	 */
	public function testListBucketsFiltersByBucketIdForARestrictedKey(): void
	{
		$connector = $this->getVerificationConnector();
		$live      = $connector->getAccountInformation();
		$bucket    = $this->credential('BACKBLAZE_BUCKET');

		if (!$live->allowed->canListBuckets())
		{
			$this->markTestSkipped('The BackBlaze B2 application key does not have the listBuckets capability.');
		}

		$restricted = new BackblazeConnector(
			$this->credential('BACKBLAZE_ID'), $this->credential('BACKBLAZE_KEY')
		);

		$restricted->setAccountInformation(new AccountInformation([
			'accountId'               => $live->accountId,
			'authorizationToken'      => $live->authorizationToken,
			'apiUrl'                  => $live->apiUrl,
			'downloadUrl'             => $live->downloadUrl,
			'recommendedPartSize'     => $live->recommendedPartSize,
			'absoluteMinimumPartSize' => $live->absoluteMinimumPartSize,
			'allowed'                 => [
				'buckets'      => [
					['id' => $connector->getBucketId($bucket), 'name' => $bucket],
				],
				'capabilities' => ['listBuckets', 'listFiles', 'readFiles', 'writeFiles', 'deleteFiles'],
				'namePrefix'   => null,
			],
		]));

		$buckets = $restricted->listBuckets();

		$this->assertCount(1, $buckets, 'A key restricted to one bucket must list exactly that one bucket.');
		$this->assertSame(
			$bucket, $buckets[0]->bucketName,
			'The bucketId-filtered listing must return the bucket the key is restricted to.'
		);
	}

	/**
	 * getBucketId() resolves the configured bucket name to a non-empty bucket ID.
	 */
	public function testGetBucketId(): void
	{
		$bucketId = $this->getVerificationConnector()->getBucketId($this->credential('BACKBLAZE_BUCKET'));

		$this->assertIsString($bucketId);
		$this->assertNotEmpty($bucketId, 'The configured bucket should resolve to a non-empty bucket ID.');
	}

	/**
	 * getFileVersions() lists an uploaded object with the correct name and size.
	 */
	public function testGetFileVersions(): void
	{
		[$remotePath, $localFile] = $this->uploadTemporaryObject();

		$connector = $this->getVerificationConnector();
		$bucketId  = $connector->getBucketId($this->credential('BACKBLAZE_BUCKET'));
		$versions  = $connector->getFileVersions($bucketId, $remotePath);

		$this->assertNotEmpty($versions, 'The uploaded object should have at least one version.');
		$this->assertSame($remotePath, $versions[0]->fileName, 'The version should carry the uploaded file name.');
		$this->assertSame(
			filesize($localFile), (int) $versions[0]->contentLength,
			'The version content length should match the uploaded file size.'
		);
	}

	/**
	 * getSignedUrl() returns a working, time-limited public download URL. Skipped when the key cannot share files.
	 */
	public function testGetSignedUrl(): void
	{
		$connector = $this->getVerificationConnector();

		if (!$connector->getAccountInformation()->allowed->canShareFiles())
		{
			$this->markTestSkipped('The BackBlaze B2 application key does not have the shareFiles capability.');
		}

		[$remotePath, $localFile] = $this->uploadTemporaryObject();

		$url = $connector->getSignedUrl($this->credential('BACKBLAZE_BUCKET'), $remotePath, 60);

		$this->assertIsString($url);
		$this->assertStringStartsWith('https://', $url, 'The signed URL should be an HTTPS URL.');
		$this->assertStringContainsString('Authorization=', $url, 'The signed URL should carry an authorization token.');

		// The signed URL must actually serve the file: download it and verify it byte-for-byte.
		$downloaded = $this->createTempFileName();
		$this->httpDownload($url, $downloaded);
		$this->assertFilesEqual($localFile, $downloaded);
	}

	/**
	 * downloadFileById() retrieves a file by its ID byte-for-byte. This path is NOT exercised by downloadFile(), which
	 * fetches by name, so it gets its own test.
	 */
	public function testDownloadFileById(): void
	{
		[$remotePath, $localFile] = $this->uploadTemporaryObject();

		$connector = $this->getVerificationConnector();
		$bucketId  = $connector->getBucketId($this->credential('BACKBLAZE_BUCKET'));
		$versions  = $connector->getFileVersions($bucketId, $remotePath);

		$this->assertNotEmpty($versions, 'The uploaded object should have at least one version to download by ID.');

		$fileId     = $versions[0]->fileId;
		$downloaded = $this->createTempFileName();

		$connector->downloadFileById($fileId, $downloaded);

		$this->assertFilesEqual($localFile, $downloaded);
	}

	/**
	 * cancelUpload() aborts an in-progress multipart upload. We start a large-file upload, push one part, then cancel,
	 * and confirm no finished file was left behind.
	 */
	public function testCancelUpload(): void
	{
		$connector = $this->getVerificationConnector();
		$bucketId  = $connector->getBucketId($this->credential('BACKBLAZE_BUCKET'));
		$remoteKey = self::TEST_DIRECTORY . '/' . $this->uniqueRemoteName(self::SIZE_SMALL);

		// Start the multipart upload and push a single part so there is genuinely an upload in progress.
		$started   = $connector->startUpload($bucketId, $remoteKey);
		$fileId    = $started->fileId;

		$this->assertNotEmpty($fileId, 'startUpload() should return a file ID for the in-progress upload.');

		$uploadUrl = $connector->getPartUploadUrl($fileId);
		$partFile  = $this->createTestFile(self::SIZE_SMALL);
		$connector->uploadPart($uploadUrl, $partFile, 1, $this->getMinimumPartSize());

		// Cancel it.
		$cancelled = $connector->cancelUpload($fileId);

		$this->assertSame($fileId, $cancelled->fileId, 'cancelUpload() should report the cancelled file ID.');

		// A cancelled upload was never finished, so it must not show up as a stored file version.
		$this->assertEmpty(
			$connector->getFileVersions($bucketId, $remoteKey),
			'A cancelled multipart upload should not leave a finished file behind.'
		);
	}

	/**
	 * setAccountInformation() lets a connector reuse a previously obtained account information object (avoiding a fresh
	 * authorization round-trip), and falls back to authorizing when handed null.
	 */
	public function testSetAccountInformation(): void
	{
		$bucket = $this->credential('BACKBLAZE_BUCKET');
		$source = $this->getVerificationConnector();
		$info   = $source->getAccountInformation();

		// A fresh connector primed with an existing, valid account information object should work without re-authorizing.
		$primed = new BackblazeConnector($this->credential('BACKBLAZE_ID'), $this->credential('BACKBLAZE_KEY'));
		$primed->setAccountInformation($info);

		$this->assertTrue($primed->getAccountInformation()->isValid(), 'The injected account information should be valid.');
		$this->assertSame(
			$source->getBucketId($bucket), $primed->getBucketId($bucket),
			'A connector primed with stored account information should resolve the same bucket ID.'
		);

		// Handed null, the connector should authorize itself and end up with valid account information.
		$reauthorized = new BackblazeConnector($this->credential('BACKBLAZE_ID'), $this->credential('BACKBLAZE_KEY'));
		$reauthorized->setAccountInformation(null);

		$this->assertTrue(
			$reauthorized->getAccountInformation()->isValid(),
			'setAccountInformation(null) should trigger a fresh authorization yielding valid account information.'
		);
	}

	protected function getEngineSlug(): string
	{
		return 'backblaze';
	}

	protected function isProviderConfigured(): bool
	{
		return $this->credential('BACKBLAZE_ID') !== ''
			&& $this->credential('BACKBLAZE_KEY') !== ''
			&& $this->credential('BACKBLAZE_BUCKET') !== '';
	}

	protected function getSkipMessage(): string
	{
		return 'BackBlaze B2 is not configured. Set BACKBLAZE_ID, BACKBLAZE_KEY and BACKBLAZE_BUCKET to enable this '
			. 'integration test (see Test/.env.sample).';
	}

	protected function configureProvider(): void
	{
		$config = Factory::getConfiguration();

		$config->set('engine.postproc.backblaze.accountId', $this->credential('BACKBLAZE_ID'));
		$config->set('engine.postproc.backblaze.applicationKey', $this->credential('BACKBLAZE_KEY'));
		$config->set('engine.postproc.backblaze.bucket', $this->credential('BACKBLAZE_BUCKET'));
		$config->set('engine.postproc.backblaze.directory', self::TEST_DIRECTORY);
		$config->set('engine.postproc.backblaze.disableMultipart', 0);
		// 5 MB chunks keep the multipart upload fast while still exercising the multipart code path on the large file.
		$config->set('engine.postproc.backblaze.chunk_upload_size', 5);
	}

	protected function getMinimumPartSize(): int
	{
		return self::MINIMUM_PART_SIZE;
	}

	protected function getRemoteSize(string $remotePath): ?int
	{
		$connector = $this->getVerificationConnector();
		$bucketId  = $connector->getBucketId($this->credential('BACKBLAZE_BUCKET'));
		$versions  = $connector->getFileVersions($bucketId, $remotePath);

		if (empty($versions))
		{
			return null;
		}

		return (int) $versions[0]->contentLength;
	}

	/**
	 * Get an independent connector instance used solely to verify stored object sizes, separate from the engine's own
	 * connector.
	 *
	 * @return  BackblazeConnector
	 */
	private function getVerificationConnector(): BackblazeConnector
	{
		if (!$this->verificationConnector instanceof BackblazeConnector)
		{
			$this->verificationConnector = new BackblazeConnector(
				$this->credential('BACKBLAZE_ID'),
				$this->credential('BACKBLAZE_KEY')
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
