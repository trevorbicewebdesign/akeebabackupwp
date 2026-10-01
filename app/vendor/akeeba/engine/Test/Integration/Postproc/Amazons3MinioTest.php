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
use Akeeba\S3\Request as S3Request;

/**
 * Integration test for the Amazon S3 post-processing engine against a local, ephemeral Minio server running in Docker.
 *
 * This proves the Amazon S3 engine (engine/Postproc/Amazons3.php) works against an S3-compatible service reached through
 * a CUSTOM endpoint with path-style addressing, and — unlike the live-AWS test — it needs no cloud credentials, so it can
 * exercise the genuine multipart upload path (supportsMultipart() = true) on any developer machine or CI runner that has
 * Docker. The full upload / download / delete lifecycle is verified byte-for-byte (size + SHA-512) for a small file
 * (single-shot) and a large file (real multipart, more than one processPart() step).
 *
 * Lifecycle of the throw-away Minio container is managed entirely by this class:
 *   - setUpBeforeClass() pulls/starts `minio/minio` (overridable via MINIO_IMAGE), publishes its port to a random local
 *     host port, waits for the health endpoint, and creates the test bucket.
 *   - tearDownAfterClass() force-removes the container, leaving nothing behind.
 *
 * The bucket is created with the bundled S3 library directly (a PUT-bucket Akeeba\S3\Request), so the test does not rely
 * on a shell, `mc`, or any particular Minio image internals — only on the S3 API the engine itself speaks.
 *
 * GATING — the whole suite self-skips unless BOTH are true:
 *   1. MINIO_TEST is set to a truthy value (explicit opt-in, so the default `vendor/bin/phpunit Test/` run never spins up
 *      a container), and
 *   2. the `docker` CLI exists and its daemon responds.
 * If either is missing, every test reports skipped with a clear reason.
 *
 * Connector coverage map (Akeeba\S3\Connector — scoped to the methods THIS engine uses, against a custom endpoint):
 *   COVERED: putObject, startMultipart/uploadMultipart/finalizeMultipart (multipart lifecycle), getObject, deleteObject,
 *            headObject, getAuthenticatedURL (path-style pre-signed URL, downloaded and verified), getBucket.
 *   NOT COVERED: listBuckets, getBucketLocation, RRS/storage-class uploads (the engine omits the storage-class header for
 *            non-AWS endpoints anyway), EC2 IAM-role provisioning. Engine path not covered: ranged downloads.
 *
 * @group integration
 * @group postproc
 * @group amazons3
 * @group minio
 */
class Amazons3MinioTest extends AbstractPostprocTestCase
{
	/** @var int Minio's S3-compatible minimum multipart part size is 5 MiB, same as Amazon S3. */
	private const MINIMUM_PART_SIZE = 5242880;

	/** @var string Bucket sub-directory all test objects are stored under. */
	private const TEST_DIRECTORY = 'akeeba-engine-test';

	/** @var string|null The Docker container ID of the running Minio server, null when none was started. */
	private static $containerId = null;

	/** @var string|null The host endpoint of the Minio server, e.g. "127.0.0.1:49160". */
	private static $endpoint = null;

	/** @var string|null Reason the whole suite is being skipped, null when Minio is up and usable. */
	private static $skipReason = null;

	/** @var S3Connector|null Independent S3 client used to read back stored object metadata. */
	private $verificationConnector;

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		self::$containerId = null;
		self::$endpoint    = null;
		self::$skipReason  = null;

		// 1. Explicit opt-in. Keeps the default test run from ever starting a container.
		if (!self::isTruthy(getenv('MINIO_TEST')))
		{
			self::$skipReason = 'The Minio integration test is opt-in. Set MINIO_TEST=1 (and have Docker running) to '
				. 'enable it (see Test/.env.sample).';

			return;
		}

		// 2. Docker must be installed and its daemon must respond.
		if (!self::canRunCommands())
		{
			self::$skipReason = 'PHP cannot execute external commands (exec/shell_exec are disabled), so the Minio '
				. 'Docker container cannot be managed.';

			return;
		}

		[$dockerOk] = self::runCommand('docker info');

		if (!$dockerOk)
		{
			self::$skipReason = 'Docker is not available (the `docker` CLI is missing or its daemon is not running), so '
				. 'the ephemeral Minio container cannot be started.';

			return;
		}

		try
		{
			self::startMinioContainer();
		}
		catch (\Throwable $e)
		{
			self::stopMinioContainer();

			self::$skipReason = 'Could not start the ephemeral Minio container: ' . $e->getMessage();
		}
	}

	public static function tearDownAfterClass(): void
	{
		self::stopMinioContainer();

		self::$containerId = null;
		self::$endpoint    = null;
		self::$skipReason  = null;

		parent::tearDownAfterClass();
	}

	/**
	 * headObject() returns metadata for an uploaded object, including its exact byte size.
	 */
	public function testHeadObject(): void
	{
		[$remotePath, $localFile] = $this->uploadTemporaryObject();

		$headers = $this->getVerificationConnector()->headObject(self::bucket(), $remotePath);

		$this->assertArrayHasKey('size', $headers, 'A HEAD response should report the object size.');
		$this->assertSame(
			filesize($localFile), (int) $headers['size'],
			'The HEAD-reported size must match the uploaded file size.'
		);
	}

	/**
	 * getAuthenticatedURL() returns a working, time-limited v4 path-style pre-signed URL that Minio serves correctly.
	 *
	 * For a local Minio endpoint there is no per-bucket DNS, so the bucket must stay in the URL *path*
	 * (preSignedBucketInURL = true, path-style). This previously hit a bug in the vendored Akeeba S3 library:
	 * Signature\V4::getAuthorizationHeader() stripped the leading "/<bucket>/" from the canonical URI it signed for
	 * non-AWS pre-signed URLs (a heuristic meant for virtual-hosted services), so the signed path ("/<object>") did not
	 * match the requested path ("/<bucket>/<object>") and Minio rejected the URL with HTTP 403 SignatureDoesNotMatch.
	 * Fixed upstream (akeeba/s3 commit af2346e): the stripping is now gated on !getPreSignedBucketInURL(), so the bucket
	 * is kept in the signed canonical URI for path-style URLs. Real Amazon S3 was always unaffected (virtual-hosted v4
	 * URLs, where the signed and requested paths agree) — see Amazons3Test::testGetAuthenticatedURL.
	 *
	 * This is a regression test for that fix: a future HTTP 403 SignatureDoesNotMatch means the v4 path-style signing
	 * broke again and must be fixed, so it fails the test (the assertSame(200) below) rather than skipping.
	 */
	public function testGetAuthenticatedURL(): void
	{
		[$remotePath, $localFile] = $this->uploadTemporaryObject();

		$url = $this->getVerificationConnector()->getAuthenticatedURL(self::bucket(), $remotePath, 60, false);

		$this->assertIsString($url);
		$this->assertStringStartsWith('http://', $url, 'The authenticated URL should be an HTTP URL (Minio, no TLS).');

		[$code, $body] = $this->httpFetch($url);

		$this->assertSame(
			200, $code,
			'The v4 path-style pre-signed URL should serve the object (HTTP 200). A 403 SignatureDoesNotMatch means the '
			. 'akeeba/s3 V4 path-style signing fix (commit af2346e) has regressed. Response body: ' . substr($body, 0, 300)
		);

		$downloaded = $this->createTempFileName();
		file_put_contents($downloaded, $body);
		$this->assertFilesEqual($localFile, $downloaded);
	}

	/**
	 * getBucket() lists the objects in the bucket. We upload an object under the test prefix and assert it shows up.
	 */
	public function testGetBucket(): void
	{
		[$remotePath] = $this->uploadTemporaryObject();

		$objects = $this->getVerificationConnector()->getBucket(self::bucket(), self::TEST_DIRECTORY . '/', null, null, '');

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
		return self::$containerId !== null && self::$skipReason === null;
	}

	protected function getSkipMessage(): string
	{
		return self::$skipReason ?? 'Minio is not available.';
	}

	protected function configureProvider(): void
	{
		$config = Factory::getConfiguration();

		$config->set('engine.postproc.amazons3.accesskey', self::rootUser());
		$config->set('engine.postproc.amazons3.secretkey', self::rootPassword());
		$config->set('engine.postproc.amazons3.bucket', self::bucket());
		$config->set('engine.postproc.amazons3.customendpoint', self::$endpoint);
		// Minio in this container speaks plain HTTP.
		$config->set('engine.postproc.amazons3.usessl', 0);
		$config->set('engine.postproc.amazons3.dualstack', 0);
		// Path-style addressing is required: there is no per-bucket DNS for a local Minio endpoint.
		$config->set('engine.postproc.amazons3.pathaccess', 1);
		$config->set('engine.postproc.amazons3.signature', 'v4');
		$config->set('engine.postproc.amazons3.region', 'us-east-1');
		$config->set('engine.postproc.amazons3.directory', self::TEST_DIRECTORY);
		// legacy=0 keeps multipart enabled so the large-file test exercises the genuine multipart code path.
		$config->set('engine.postproc.amazons3.legacy', 0);
		// Keep the bucket in the PATH of v4 pre-signed URLs. Without this the library produces virtual-hosted-style URLs
		// (bucket.127.0.0.1) which cannot resolve for a local Minio endpoint with no per-bucket DNS.
		$config->set('engine.postproc.amazons3.preSignedBucketInURL', 1);
	}

	protected function getMinimumPartSize(): int
	{
		return self::MINIMUM_PART_SIZE;
	}

	protected function getRemoteSize(string $remotePath): ?int
	{
		try
		{
			$headers = $this->getVerificationConnector()->headObject(self::bucket(), $remotePath);
		}
		catch (\Throwable $e)
		{
			// A missing object (e.g. after deletion) surfaces as a CannotGetFile exception from headObject().
			return null;
		}

		return isset($headers['size']) ? (int) $headers['size'] : null;
	}

	// ------------------------------------------------------------------------------------------------------------------
	// Docker container lifecycle
	// ------------------------------------------------------------------------------------------------------------------

	/**
	 * Start the ephemeral Minio container, resolve its published port, wait for it to become ready, and create the
	 * test bucket. Throws on any failure so the caller can record a skip reason.
	 */
	private static function startMinioContainer(): void
	{
		$image = self::env('MINIO_IMAGE', 'minio/minio:latest');

		// Publish container port 9000 to a random free host port bound to loopback only.
		$command = sprintf(
			'docker run -d -p 127.0.0.1::9000 -e %s -e %s %s server /data',
			escapeshellarg('MINIO_ROOT_USER=' . self::rootUser()),
			escapeshellarg('MINIO_ROOT_PASSWORD=' . self::rootPassword()),
			escapeshellarg($image)
		);

		[$ok, $out] = self::runCommand($command);

		if (!$ok || trim($out) === '')
		{
			throw new \RuntimeException('`docker run` failed: ' . trim($out));
		}

		self::$containerId = trim($out);

		// Resolve the random host port Docker assigned to container port 9000.
		[$portOk, $portOut] = self::runCommand('docker port ' . escapeshellarg(self::$containerId) . ' 9000/tcp');

		if (!$portOk || !preg_match('/:(\d+)\s*$/', trim($portOut), $m))
		{
			throw new \RuntimeException('Could not resolve the published Minio port: ' . trim($portOut));
		}

		self::$endpoint = '127.0.0.1:' . $m[1];

		self::waitForMinioReady();
		self::createBucket();
	}

	/**
	 * Best-effort removal of the Minio container, ignoring any error.
	 */
	private static function stopMinioContainer(): void
	{
		if (self::$containerId === null || !self::canRunCommands())
		{
			return;
		}

		self::runCommand('docker rm -f ' . escapeshellarg(self::$containerId));
	}

	/**
	 * Poll Minio's readiness endpoint until it answers HTTP 200, up to a sane timeout.
	 */
	private static function waitForMinioReady(): void
	{
		$url      = 'http://' . self::$endpoint . '/minio/health/ready';
		$deadline = time() + 30;

		do
		{
			if (self::httpStatus($url) === 200)
			{
				return;
			}

			usleep(250000);
		}
		while (time() < $deadline);

		throw new \RuntimeException('Minio did not become ready within 30 seconds at ' . self::$endpoint);
	}

	/**
	 * Create the test bucket with a PUT-bucket request through the bundled S3 library (no shell or `mc` needed). A
	 * pre-existing bucket (HTTP 409 BucketAlreadyOwnedByYou) is treated as success.
	 */
	private static function createBucket(): void
	{
		$request = new S3Request('PUT', self::bucket(), '', self::makeS3Configuration());
		$request->setHeader('Content-Length', '0');

		$response = $request->getResponse();
		$code     = $response->getCode();

		if ($code !== 200 && $code !== 409)
		{
			$detail = $response->getError()->isError() ? $response->getError()->getMessage() : '';

			throw new \RuntimeException(sprintf('Creating the Minio bucket failed (HTTP %d) %s', $code, $detail));
		}
	}

	// ------------------------------------------------------------------------------------------------------------------
	// S3 client helpers
	// ------------------------------------------------------------------------------------------------------------------

	/**
	 * Build an independent S3 client used to verify stored objects, configured exactly like the engine's own connector
	 * for the Minio endpoint (custom endpoint, path style, plain HTTP, v4 signatures).
	 *
	 * @return  S3Connector
	 */
	private function getVerificationConnector(): S3Connector
	{
		if (!$this->verificationConnector instanceof S3Connector)
		{
			$this->verificationConnector = new S3Connector(self::makeS3Configuration());
		}

		return $this->verificationConnector;
	}

	/**
	 * Build the S3 client configuration pointing at the running Minio container.
	 *
	 * @return  S3Configuration
	 */
	private static function makeS3Configuration(): S3Configuration
	{
		$configuration = new S3Configuration(self::rootUser(), self::rootPassword(), 'v4', 'us-east-1');
		$configuration->setSSL(false);
		$configuration->setUseDualstackUrl(false);
		$configuration->setEndpoint(self::$endpoint);
		$configuration->setSignatureMethod('v4');
		$configuration->setRegion('us-east-1');
		// Path style is mandatory for a host:port endpoint with no per-bucket DNS.
		$configuration->setUseLegacyPathStyle(true);
		// v4 pre-signed URLs default to virtual-hosted style (bucket.host); force the bucket into the path so the URL
		// resolves against the local Minio endpoint.
		$configuration->setPreSignedBucketInURL(true);

		return $configuration;
	}

	/**
	 * Fetch a URL with cURL, returning [httpStatus, body]. Transport errors fail the test. Non-2xx HTTP responses are
	 * returned (not failed) so the caller can distinguish, e.g., the documented 403 SignatureDoesNotMatch case.
	 *
	 * @param   string  $url  The URL to fetch.
	 *
	 * @return  array{0:int,1:string}
	 */
	private function httpFetch(string $url): array
	{
		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
		]);

		$body  = curl_exec($ch);
		$error = curl_error($ch);
		$code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

		$this->assertNotFalse($body, 'Fetching the authenticated URL failed at the transport level: ' . $error);

		return [$code, (string) $body];
	}

	// ------------------------------------------------------------------------------------------------------------------
	// Low-level helpers
	// ------------------------------------------------------------------------------------------------------------------

	/**
	 * Return the HTTP status code for a GET request to the given URL, or 0 on a transport error.
	 *
	 * @param   string  $url  The URL to probe.
	 *
	 * @return  int
	 */
	private static function httpStatus(string $url): int
	{
		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_NOBODY         => false,
			CURLOPT_RETURNTRANSFER => true,
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

	private static function rootUser(): string
	{
		return self::env('MINIO_ROOT_USER', 'minioadmin');
	}

	private static function rootPassword(): string
	{
		return self::env('MINIO_ROOT_PASSWORD', 'minioadmin');
	}

	private static function bucket(): string
	{
		return self::env('MINIO_BUCKET', 'akeeba-engine-test-bucket');
	}
}
