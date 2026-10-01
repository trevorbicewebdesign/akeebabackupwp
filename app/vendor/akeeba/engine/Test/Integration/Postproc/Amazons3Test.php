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
use Akeeba\S3\Configuration as S3Configuration;
use Akeeba\S3\Connector as S3Connector;

/**
 * Integration test for the Amazon S3 post-processing engine against a live AWS S3 bucket.
 *
 * Exercises the full upload / download / delete lifecycle against a real Amazon S3 bucket for both a small file
 * (single-shot upload) and a large file. Unlike the Google Storage S3-interoperability engine, the Amazon S3 engine
 * performs GENUINE multipart uploads (Amazons3::multipartUpload(), 5 MiB parts), so the large-file test asserts a real
 * multi-step upload (supportsMultipart() is left at its inherited default of true). Byte-for-byte fidelity is verified
 * via size + SHA-512, and the S3-library methods the engine relies on are covered directly.
 *
 * The test runs only when AWS_S3_ACCESS_KEY, AWS_S3_SECRET_KEY and AWS_S3_BUCKET are all set (see Test/.env.sample);
 * otherwise the whole suite self-skips and never touches the network. AWS_S3_REGION (default us-east-1) MUST match the
 * bucket's region, because v4 signatures are region-scoped.
 *
 * Connector coverage map (Akeeba\S3\Connector — scoped to the methods THIS engine uses):
 *   COVERED:
 *     - putObject               (small-file lifecycle upload)
 *     - startMultipart / uploadMultipart / finalizeMultipart  (large-file multipart lifecycle)
 *     - getObject               (lifecycle download via the engine's downloadToFile())
 *     - deleteObject            (lifecycle delete via the engine's delete())
 *     - headObject              (testHeadObject, + independent remote-size verification in the lifecycle)
 *     - getAuthenticatedURL     (testGetAuthenticatedURL — downloaded through the URL and verified byte-for-byte)
 *     - getBucket               (testGetBucket — object listing under the test prefix)
 *     - getConfiguration        (indirectly, building/inspecting the connector)
 *   NOT COVERED (out of scope for this engine/test):
 *     - listBuckets, getBucketLocation, RRS/storage-class uploads, EC2 IAM-role credential provisioning
 *   Engine-level paths NOT covered: downloadToBrowser (covered at the library level via getAuthenticatedURL) and ranged
 *   downloads (downloadToFile with an offset/length).
 *
 * @group integration
 * @group postproc
 * @group amazons3
 */
class Amazons3Test extends AbstractPostprocTestCase
{
	/** @var int Amazon S3's minimum multipart part size is 5 MiB. */
	private const MINIMUM_PART_SIZE = 5242880;

	/** @var string Bucket sub-directory all test objects are stored under. */
	private const TEST_DIRECTORY = 'akeeba-engine-test';

	/** @var S3Connector|null Independent S3 client used to read back stored object metadata. */
	private $verificationConnector;

	/**
	 * headObject() returns metadata for an uploaded object, including its exact byte size. This is the same call the
	 * lifecycle uses for independent size verification; here we assert its shape directly.
	 */
	public function testHeadObject(): void
	{
		[$remotePath, $localFile] = $this->uploadTemporaryObject();

		$headers = $this->getVerificationConnector()->headObject($this->credential('AWS_S3_BUCKET'), $remotePath);

		$this->assertArrayHasKey('size', $headers, 'A HEAD response should report the object size.');
		$this->assertSame(
			filesize($localFile), (int) $headers['size'],
			'The HEAD-reported size must match the uploaded file size.'
		);
	}

	/**
	 * getAuthenticatedURL() returns a working, time-limited v4 pre-signed download URL. This is the same library method
	 * the engine's downloadToBrowser() relies on. For real Amazon S3 (virtual-hosted-style URLs) there is no
	 * double-bucket defect, so we download through the URL and verify the bytes.
	 */
	public function testGetAuthenticatedURL(): void
	{
		[$remotePath, $localFile] = $this->uploadTemporaryObject();

		$url = $this->getVerificationConnector()->getAuthenticatedURL(
			$this->credential('AWS_S3_BUCKET'), $remotePath, 60, true
		);

		$this->assertIsString($url);
		$this->assertStringStartsWith('https://', $url, 'The authenticated URL should be an HTTPS URL.');

		$downloaded = $this->createTempFileName();
		$this->httpDownload($url, $downloaded);
		$this->assertFilesEqual($localFile, $downloaded);
	}

	/**
	 * getBucket() lists the objects in the bucket. We upload an object under the test prefix and assert it shows up.
	 */
	public function testGetBucket(): void
	{
		[$remotePath] = $this->uploadTemporaryObject();

		$objects = $this->getVerificationConnector()->getBucket(
			$this->credential('AWS_S3_BUCKET'), self::TEST_DIRECTORY . '/', null, null, ''
		);

		$this->assertIsArray($objects);
		$this->assertArrayHasKey(
			$remotePath, $objects,
			'The uploaded object should appear in the bucket listing under the test prefix.'
		);
	}

	protected function getEngineSlug(): string
	{
		return 'amazons3';
	}

	protected function isProviderConfigured(): bool
	{
		return $this->credential('AWS_S3_ACCESS_KEY') !== ''
			&& $this->credential('AWS_S3_SECRET_KEY') !== ''
			&& $this->credential('AWS_S3_BUCKET') !== '';
	}

	protected function getSkipMessage(): string
	{
		return 'Amazon S3 (live AWS) is not configured. Set AWS_S3_ACCESS_KEY, AWS_S3_SECRET_KEY and AWS_S3_BUCKET '
			. '(and AWS_S3_REGION to match the bucket) to enable this integration test (see Test/.env.sample).';
	}

	protected function configureProvider(): void
	{
		$config = Factory::getConfiguration();

		$config->set('engine.postproc.amazons3.accesskey', $this->credential('AWS_S3_ACCESS_KEY'));
		$config->set('engine.postproc.amazons3.secretkey', $this->credential('AWS_S3_SECRET_KEY'));
		$config->set('engine.postproc.amazons3.bucket', $this->credential('AWS_S3_BUCKET'));
		$config->set('engine.postproc.amazons3.region', $this->region());
		$config->set('engine.postproc.amazons3.signature', $this->signatureMethod());
		$config->set('engine.postproc.amazons3.usessl', 1);
		$config->set('engine.postproc.amazons3.dualstack', 1);
		// Virtual-hosted-style access is the modern Amazon S3 default.
		$config->set('engine.postproc.amazons3.pathaccess', 0);
		$config->set('engine.postproc.amazons3.directory', self::TEST_DIRECTORY);
		// legacy=0 keeps multipart enabled so the large-file test exercises the genuine multipart code path.
		$config->set('engine.postproc.amazons3.legacy', 0);

		// NOTE: acl is intentionally left at the engine default (bucket-owner-full-control). AWS still accepts this
		// specific canned ACL even on buckets where ACLs are disabled (Object Ownership = Bucket owner enforced), so no
		// special handling is required.
	}

	protected function getMinimumPartSize(): int
	{
		return self::MINIMUM_PART_SIZE;
	}

	protected function getRemoteSize(string $remotePath): ?int
	{
		try
		{
			$headers = $this->getVerificationConnector()->headObject($this->credential('AWS_S3_BUCKET'), $remotePath);
		}
		catch (\Throwable $e)
		{
			// A missing object (e.g. after deletion) surfaces as a CannotGetFile exception from headObject().
			return null;
		}

		return isset($headers['size']) ? (int) $headers['size'] : null;
	}

	/**
	 * Build an independent S3 client used solely to verify stored objects, mirroring exactly how the engine configures
	 * its own connector for Amazon S3 (virtual-hosted style, v4 signatures, dual-stack).
	 *
	 * @return  S3Connector
	 */
	private function getVerificationConnector(): S3Connector
	{
		if ($this->verificationConnector instanceof S3Connector)
		{
			return $this->verificationConnector;
		}

		$configuration = new S3Configuration(
			$this->credential('AWS_S3_ACCESS_KEY'),
			$this->credential('AWS_S3_SECRET_KEY'),
			$this->signatureMethod(),
			$this->region()
		);
		$configuration->setSSL(true);
		$configuration->setUseDualstackUrl(true);
		$configuration->setUseLegacyPathStyle(false);

		$this->verificationConnector = new S3Connector($configuration);

		return $this->verificationConnector;
	}

	/**
	 * The configured AWS region, defaulting to us-east-1. v4 signatures are region-scoped, so this must match the
	 * bucket's region.
	 *
	 * @return  string
	 */
	private function region(): string
	{
		$region = $this->credential('AWS_S3_REGION');

		return $region !== '' ? $region : 'us-east-1';
	}

	/**
	 * The S3 signature method, defaulting to v4.
	 *
	 * @return  string
	 */
	private function signatureMethod(): string
	{
		$signature = $this->credential('AWS_S3_SIGNATURE');

		return $signature !== '' ? $signature : 'v4';
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

		$this->assertTrue($ok !== false, 'Downloading the authenticated URL failed: ' . $error);
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
