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
use RuntimeException;

/**
 * Integration test for the BackBlaze B2 post-processing engine with a MULTI-BUCKET application key.
 *
 * Backblaze's v4 API replaced the single scalar bucketId/bucketName in a key's `allowed` information with a buckets[]
 * array, because an application key can now be restricted to several buckets at once. Our Allowed object seeds its
 * scalar fields from the FIRST entry of that array, and getBucketId() used to compare the bucket it wanted against
 * nothing but those scalars. Ask a two-bucket key for its second bucket and the comparison missed, so getBucketId()
 * fell through to its b2_list_buckets fallback.
 *
 * That fallback used to paper over the miss: under API v1, b2_list_buckets quietly filtered its listing down to what a
 * restricted key could see. Since v2 it does not — asking a bucket-restricted key for an unfiltered listing is simply
 * unauthorized. So the moment we moved every endpoint to v4, the second bucket of a multi-bucket key stopped resolving
 * altogether. getBucketId() now scans the whole allowed buckets[] list and never needs the fallback at all.
 *
 * BackblazeTest cannot catch this (its key is unrestricted, so the fallback always rescues it) and neither can
 * BackblazeRestrictedTest (its key has exactly one bucket, which is the one the scalars are seeded from — the very case
 * that always worked). Only a key with MORE THAN ONE bucket exercises it, and the test targets the SECOND bucket:
 * targeting the first would pass even with the bug still in place.
 *
 * WHY THIS TEST MINTS ITS OWN KEY: the BackBlaze web console cannot create a multi-bucket key at all — it offers "all
 * buckets" or exactly one. Multi-bucket keys exist only through the b2_create_key API, which since v4 takes a plural
 * `bucketIds` array (the singular `bucketId` was removed). There is therefore no key we could ask you to paste into
 * Test/.env. Instead the test mints an ephemeral one from your master key, scoped to two buckets you already own, and
 * deletes it again in tearDownAfterClass. It is also given a ten-minute `validDurationInSeconds` so that BackBlaze
 * expires it by itself even if teardown never runs (a crashed process, a killed test run). The key material is held in
 * memory for the duration of the test and is never written to disk or logged.
 *
 * Because it creates a real credential on a real account, it is OPT-IN: it runs only when BACKBLAZE_MULTIBUCKET_TEST is
 * truthy AND the master BACKBLAZE_ID / BACKBLAZE_KEY are set (that key needs writeKeys and deleteKeys), AND the account
 * has at least two buckets. Otherwise it self-skips. No buckets are created or destroyed — objects are stored under the
 * `akeeba-engine-test` prefix and removed on teardown, exactly like the other BackBlaze tests.
 *
 * @group integration
 * @group postproc
 * @group backblaze
 */
class BackblazeMultiBucketTest extends AbstractPostprocTestCase
{
	/** @var int BackBlaze B2's minimum multipart part size is 5 MiB. */
	private const MINIMUM_PART_SIZE = 5242880;

	/** @var string Bucket sub-directory all test objects are stored under. */
	private const TEST_DIRECTORY = 'akeeba-engine-test';

	/** @var string Name of the ephemeral key, so a stray one is recognisable in the B2 console. */
	private const KEY_NAME = 'akeeba-engine-test-multibucket';

	/**
	 * @var int How long the minted key stays valid. Short on purpose: tearDownAfterClass deletes it, and this is only
	 *          the backstop for when teardown never runs. Long enough for the large-file multipart lifecycle.
	 */
	private const KEY_LIFETIME = 600;

	/** @var string|null The Key ID of the ephemeral multi-bucket key. Held in memory only. */
	private static $applicationKeyId;

	/** @var string|null The secret of the ephemeral multi-bucket key. Held in memory only, never logged. */
	private static $applicationKey;

	/** @var string|null The FIRST bucket the ephemeral key may access. */
	private static $bucket1;

	/** @var string|null The SECOND bucket — the one the whole lifecycle runs against. */
	private static $bucket2;

	/** @var string|null Why the test cannot run, if it cannot. */
	private static $skipReason;

	/** @var BackblazeConnector|null Independent connector used to read back stored object sizes. */
	private $verificationConnector;

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		self::$applicationKeyId = null;
		self::$applicationKey   = null;
		self::$skipReason       = null;

		if (!self::isTruthy(self::credential('BACKBLAZE_MULTIBUCKET_TEST')))
		{
			self::$skipReason = 'Multi-bucket BackBlaze testing is opt-in because it mints a real (short-lived) '
				. 'application key on your account. Set BACKBLAZE_MULTIBUCKET_TEST=1 to enable it.';

			return;
		}

		$masterId  = self::credential('BACKBLAZE_ID');
		$masterKey = self::credential('BACKBLAZE_KEY');

		if ($masterId === '' || $masterKey === '')
		{
			self::$skipReason = 'BACKBLAZE_ID and BACKBLAZE_KEY must be set: the multi-bucket key is minted from them.';

			return;
		}

		try
		{
			self::provisionMultiBucketKey($masterId, $masterKey);
		}
		catch (RuntimeException $e)
		{
			self::$skipReason = $e->getMessage();
		}
	}

	public static function tearDownAfterClass(): void
	{
		// Best-effort: BackBlaze expires the key by itself after KEY_LIFETIME anyway.
		if (self::$applicationKeyId !== null)
		{
			try
			{
				$auth = self::authorize(self::credential('BACKBLAZE_ID'), self::credential('BACKBLAZE_KEY'));

				self::apiPost($auth, 'b2_delete_key', ['applicationKeyId' => self::$applicationKeyId]);
			}
			catch (RuntimeException $e)
			{
				// Swallow: the key self-expires, and failing teardown must not mask a test result.
			}
		}

		self::$applicationKeyId = null;
		self::$applicationKey   = null;

		parent::tearDownAfterClass();
	}

	protected function getEngineSlug(): string
	{
		return 'backblaze';
	}

	protected function isProviderConfigured(): bool
	{
		return self::$skipReason === null && self::$applicationKey !== null;
	}

	protected function getSkipMessage(): string
	{
		return self::$skipReason ?? 'The ephemeral multi-bucket BackBlaze B2 key could not be created.';
	}

	/**
	 * The whole inherited upload → download → delete lifecycle runs against the SECOND bucket, which is the one that
	 * used to be unreachable. The first bucket would pass even with the bug present, so it would prove nothing.
	 */
	protected function configureProvider(): void
	{
		$config = Factory::getConfiguration();

		$config->set('engine.postproc.backblaze.accountId', self::$applicationKeyId);
		$config->set('engine.postproc.backblaze.applicationKey', self::$applicationKey);
		$config->set('engine.postproc.backblaze.bucket', self::$bucket2);
		$config->set('engine.postproc.backblaze.directory', self::TEST_DIRECTORY);
		$config->set('engine.postproc.backblaze.disableMultipart', 0);
		// 5 MB chunks keep the multipart upload fast while still exercising the multipart code path on the large file.
		$config->set('engine.postproc.backblaze.chunk_upload_size', 5);
	}

	/**
	 * Both buckets the key is restricted to must resolve to a bucket ID, and the two IDs must differ.
	 *
	 * This makes the failure legible: if only the first one resolves, you are looking at the seeded-from-the-first-entry
	 * bug rather than a broken credential.
	 */
	public function testGetBucketIdResolvesBothAllowedBuckets(): void
	{
		$connector = $this->getVerificationConnector();

		$firstId  = $connector->getBucketId(self::$bucket1);
		$secondId = $connector->getBucketId(self::$bucket2);

		$this->assertNotEmpty($firstId, 'The first allowed bucket must resolve to a bucket ID');
		$this->assertNotEmpty($secondId, 'The SECOND allowed bucket must resolve to a bucket ID too');
		$this->assertNotSame($firstId, $secondId, 'The two buckets must not resolve to the same ID');
	}

	/**
	 * The key really is a multi-bucket one: v4 reports both buckets in allowed.buckets[], keyed id/name. If BackBlaze
	 * ever changes that shape again, this fails first and tells you why the rest broke.
	 */
	public function testTheKeyReportsBothBucketsInItsAllowedInformation(): void
	{
		$allowed = $this->getVerificationConnector()->getAccountInformation()->allowed;

		$this->assertCount(2, $allowed->buckets, 'The key must be restricted to exactly two buckets');

		$names = array_column($allowed->buckets, 'bucketName');

		$this->assertContains(self::$bucket1, $names);
		$this->assertContains(self::$bucket2, $names);

		// Both buckets must be allowed, not merely the one the scalar fields were seeded from.
		$this->assertTrue($allowed->isBucketAllowed(self::$bucket1));
		$this->assertTrue($allowed->isBucketAllowed(self::$bucket2));
	}

	protected function getMinimumPartSize(): int
	{
		return self::MINIMUM_PART_SIZE;
	}

	protected function getRemoteSize(string $remotePath): ?int
	{
		$connector = $this->getVerificationConnector();
		$bucketId  = $connector->getBucketId(self::$bucket2);
		$versions  = $connector->getFileVersions($bucketId, $remotePath);

		if (empty($versions))
		{
			return null;
		}

		return (int) $versions[0]->contentLength;
	}

	/**
	 * Get an independent connector instance, authorized as the ephemeral multi-bucket key, used solely to verify stored
	 * object sizes and bucket resolution — separate from the engine's own connector.
	 *
	 * @return  BackblazeConnector
	 */
	private function getVerificationConnector(): BackblazeConnector
	{
		if (!$this->verificationConnector instanceof BackblazeConnector)
		{
			$this->verificationConnector = new BackblazeConnector(self::$applicationKeyId, self::$applicationKey);
		}

		return $this->verificationConnector;
	}

	/**
	 * Mint a short-lived application key restricted to two of the account's existing buckets.
	 *
	 * Uses raw cURL rather than the connector: b2_create_key is not part of the engine's connector (the backup engine
	 * has no business creating credentials), and this runs in setUpBeforeClass, before the test platform the connector
	 * needs has been injected.
	 *
	 * @param   string  $masterId   Key ID of a key with writeKeys and deleteKeys
	 * @param   string  $masterKey  Its secret
	 *
	 * @return  void
	 *
	 * @throws  RuntimeException  When the account cannot supply two buckets, or the key cannot be created
	 */
	private static function provisionMultiBucketKey(string $masterId, string $masterKey): void
	{
		$auth = self::authorize($masterId, $masterKey);

		if (!in_array('writeKeys', $auth['capabilities'], true) || !in_array('deleteKeys', $auth['capabilities'], true))
		{
			throw new RuntimeException(
				'BACKBLAZE_ID/BACKBLAZE_KEY must be a key with the writeKeys and deleteKeys capabilities: the '
				. 'multi-bucket key can only be created through the b2_create_key API, not the B2 web console.'
			);
		}

		$listing = self::apiPost($auth, 'b2_list_buckets', ['accountId' => $auth['accountId']]);
		$buckets = [];

		foreach ($listing['buckets'] ?? [] as $bucket)
		{
			$buckets[$bucket['bucketName']] = $bucket['bucketId'];
		}

		// Prefer explicit choices; otherwise take the configured test bucket plus any other bucket in the account.
		$name1 = self::credential('BACKBLAZE_MULTIBUCKET_BUCKET1') ?: self::credential('BACKBLAZE_BUCKET');
		$name2 = self::credential('BACKBLAZE_MULTIBUCKET_BUCKET2');

		if ($name1 === '' || !isset($buckets[$name1]))
		{
			$name1 = (string) (array_key_first($buckets) ?? '');
		}

		if ($name2 === '' || !isset($buckets[$name2]))
		{
			$name2 = '';

			foreach (array_keys($buckets) as $candidate)
			{
				if ($candidate !== $name1)
				{
					$name2 = $candidate;

					break;
				}
			}
		}

		if ($name1 === '' || $name2 === '' || $name1 === $name2)
		{
			throw new RuntimeException(
				'The BackBlaze account needs at least two buckets for the multi-bucket key test; it has '
				. count($buckets) . '. Create a second bucket, or name two with BACKBLAZE_MULTIBUCKET_BUCKET1 and '
				. 'BACKBLAZE_MULTIBUCKET_BUCKET2.'
			);
		}

		// v4 takes a plural bucketIds array — the singular bucketId was removed. This is the whole point of the test.
		$created = self::apiPost($auth, 'b2_create_key', [
			'accountId'              => $auth['accountId'],
			'keyName'                => self::KEY_NAME,
			'capabilities'           => ['listBuckets', 'listFiles', 'readFiles', 'writeFiles', 'deleteFiles'],
			'bucketIds'              => [$buckets[$name1], $buckets[$name2]],
			'validDurationInSeconds' => self::KEY_LIFETIME,
		]);

		if (empty($created['applicationKeyId']) || empty($created['applicationKey']))
		{
			throw new RuntimeException('b2_create_key did not return a usable application key.');
		}

		self::$applicationKeyId = $created['applicationKeyId'];
		self::$applicationKey   = $created['applicationKey'];
		self::$bucket1          = $name1;
		self::$bucket2          = $name2;
	}

	/**
	 * Authorize against the B2 API and return the bits the other helpers need.
	 *
	 * @param   string  $id   Key ID
	 * @param   string  $key  Key secret
	 *
	 * @return  array{accountId: string, token: string, apiUrl: string, capabilities: string[]}
	 *
	 * @throws  RuntimeException
	 */
	private static function authorize(string $id, string $key): array
	{
		$response = self::curl(
			BackblazeConnector::apiURL . 'b2_authorize_account',
			['Authorization: Basic ' . base64_encode($id . ':' . $key), 'Accept: application/json']
		);

		$storageApi = $response['apiInfo']['storageApi'] ?? [];

		if (empty($response['authorizationToken']) || empty($storageApi['apiUrl']))
		{
			throw new RuntimeException('Could not authorize against BackBlaze B2 with BACKBLAZE_ID / BACKBLAZE_KEY.');
		}

		return [
			'accountId'    => $response['accountId'],
			'token'        => $response['authorizationToken'],
			'apiUrl'       => rtrim($storageApi['apiUrl'], '/'),
			'capabilities' => $storageApi['allowed']['capabilities'] ?? [],
		];
	}

	/**
	 * POST a JSON body to a B2 API endpoint, on the same API version the connector itself uses.
	 *
	 * @param   array   $auth      The array returned by authorize()
	 * @param   string  $endpoint  Endpoint name, e.g. b2_create_key
	 * @param   array   $body      Request body
	 *
	 * @return  array  The decoded response
	 *
	 * @throws  RuntimeException
	 */
	private static function apiPost(array $auth, string $endpoint, array $body): array
	{
		$url = $auth['apiUrl'] . '/b2api/' . BackblazeConnector::apiVersion . '/' . $endpoint;

		return self::curl(
			$url,
			['Authorization: ' . $auth['token'], 'Accept: application/json'],
			json_encode($body)
		);
	}

	/**
	 * Minimal JSON-over-HTTPS helper. Deliberately terse: it exists only to create and delete the ephemeral key.
	 *
	 * @param   string       $url      URL to call
	 * @param   string[]     $headers  Request headers
	 * @param   string|null  $post     JSON body; when null the request is a GET
	 *
	 * @return  array  The decoded response
	 *
	 * @throws  RuntimeException  On a transport error, or when B2 returns an error document
	 */
	private static function curl(string $url, array $headers, ?string $post = null): array
	{
		$ch = curl_init($url);

		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_CAINFO         => AKEEBA_CACERT_PEM,
			CURLOPT_HTTPHEADER     => $headers,
		]);

		if ($post !== null)
		{
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		}

		$raw   = curl_exec($ch);
		$errNo = curl_errno($ch);
		$error = curl_error($ch);

		if (version_compare(PHP_VERSION, '8.5.0', 'lt'))
		{
			curl_close($ch);
		}

		if ($errNo)
		{
			throw new RuntimeException(sprintf('BackBlaze B2 API call failed: cURL error %d, %s', $errNo, $error));
		}

		$decoded = json_decode((string) $raw, true);

		if (!is_array($decoded))
		{
			throw new RuntimeException('BackBlaze B2 returned a response which is not valid JSON.');
		}

		// Never let a key or token reach the message: B2 error documents carry only a code and a description.
		if (isset($decoded['status'], $decoded['code']) && (int) $decoded['status'] >= 400)
		{
			throw new RuntimeException(
				sprintf('BackBlaze B2 API error %s: %s', $decoded['code'], $decoded['message'] ?? '')
			);
		}

		return $decoded;
	}

	/**
	 * Read a credential from the environment, normalised to a trimmed string ('' when unset).
	 *
	 * @param   string  $key  The environment variable name.
	 *
	 * @return  string
	 */
	private static function credential(string $key): string
	{
		return trim((string) (getenv($key) ?: ''));
	}

	/**
	 * Is an environment flag switched on?
	 *
	 * @param   string  $value  The raw value
	 *
	 * @return  bool
	 */
	private static function isTruthy(string $value): bool
	{
		return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
	}
}
