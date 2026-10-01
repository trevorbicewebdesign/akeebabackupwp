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

/**
 * Integration test for the BackBlaze B2 post-processing engine with a BUCKET-RESTRICTED, no-listBuckets application key.
 *
 * This is the regression test for the field bug where Backblaze::getBucketId() could not resolve the bucket for a
 * least-privilege key. getBucketId() has two routes: a fast path that reads the bucket ID straight out of the key's
 * `allowed` information, and a fallback that calls b2_list_buckets. A key restricted to a single bucket and lacking the
 * listBuckets capability — the least-privilege setup you would normally recommend — can only use the fast path; the
 * fallback throws NotAllowed. When the v4 authorization response seeded the `allowed` bucket fields from the wrong keys,
 * the fast path silently missed and every upload, download and delete failed.
 *
 * The regular BackblazeTest could not catch this: its key allows access to all buckets and carries listBuckets, so the
 * fallback always rescued it and the broken fast path stayed invisible. This test deliberately uses a key that has NO
 * fallback available, so the engine is forced through the fast path. It asserts NOTHING about the `allowed` object's
 * fields — it proves the fix purely by black-box behaviour: if the bucket cannot be resolved, the full upload → download
 * → delete lifecycle inherited from AbstractPostprocTestCase simply fails.
 *
 * The restricted application key MUST be:
 *   - restricted to exactly one bucket (the BACKBLAZE_RESTRICTED_BUCKET below),
 *   - WITHOUT the listBuckets capability (this is what removes the fallback and forces the fast path),
 *   - WITH writeFiles, readFiles and deleteFiles (so the upload → download → delete round-trip can complete).
 *
 * It runs only when BACKBLAZE_RESTRICTED_ID, BACKBLAZE_RESTRICTED_KEY and BACKBLAZE_RESTRICTED_BUCKET are all set (see
 * Test/.env.sample); otherwise it self-skips. Objects are stored under the `akeeba-engine-test` prefix and removed on
 * teardown.
 *
 * @group integration
 * @group postproc
 * @group backblaze
 */
class BackblazeRestrictedTest extends AbstractPostprocTestCase
{
	/** @var int BackBlaze B2's minimum multipart part size is 5 MiB. */
	private const MINIMUM_PART_SIZE = 5242880;

	/** @var string Bucket sub-directory all test objects are stored under. */
	private const TEST_DIRECTORY = 'akeeba-engine-test';

	/** @var BackblazeConnector|null Independent connector used to read back stored object sizes. */
	private $verificationConnector;

	protected function getEngineSlug(): string
	{
		return 'backblaze';
	}

	protected function isProviderConfigured(): bool
	{
		return $this->credential('BACKBLAZE_RESTRICTED_ID') !== ''
			&& $this->credential('BACKBLAZE_RESTRICTED_KEY') !== ''
			&& $this->credential('BACKBLAZE_RESTRICTED_BUCKET') !== '';
	}

	protected function getSkipMessage(): string
	{
		return 'A bucket-restricted BackBlaze B2 key is not configured. Set BACKBLAZE_RESTRICTED_ID, '
			. 'BACKBLAZE_RESTRICTED_KEY and BACKBLAZE_RESTRICTED_BUCKET to a key restricted to one bucket and lacking '
			. 'the listBuckets capability to enable this regression test (see Test/.env.sample).';
	}

	protected function configureProvider(): void
	{
		$config = Factory::getConfiguration();

		$config->set('engine.postproc.backblaze.accountId', $this->credential('BACKBLAZE_RESTRICTED_ID'));
		$config->set('engine.postproc.backblaze.applicationKey', $this->credential('BACKBLAZE_RESTRICTED_KEY'));
		$config->set('engine.postproc.backblaze.bucket', $this->credential('BACKBLAZE_RESTRICTED_BUCKET'));
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
		$bucketId  = $connector->getBucketId($this->credential('BACKBLAZE_RESTRICTED_BUCKET'));
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
				$this->credential('BACKBLAZE_RESTRICTED_ID'),
				$this->credential('BACKBLAZE_RESTRICTED_KEY')
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
