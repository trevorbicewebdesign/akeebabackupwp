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

namespace Akeeba\Engine\Test\Postproc\Connector\Backblaze;

use Akeeba\Engine\Postproc\Connector\Backblaze\Allowed;
use PHPUnit\Framework\TestCase;

final class AllowedTest extends TestCase
{
	/**
	 * The live b2_authorize_account v4 response keys each allowed.buckets[] entry id/name. For a key restricted to a
	 * single bucket the scalar bucketId/bucketName must be seeded from that entry, otherwise Backblaze::getBucketId()
	 * never matches its fast path and falls through to b2_list_buckets — which a least-privilege writeFiles,listFiles
	 * key may not call. This is the exact scenario from the field bug report.
	 *
	 * @see  https://www.backblaze.com/apidocs/b2-authorize-account
	 */
	public function testV4SingleBucketSeedsScalarFieldsFromIdNameKeys(): void
	{
		$allowed = new Allowed([
			'buckets'      => [
				['id' => 'abc123def456', 'name' => 'my-bucket'],
			],
			'capabilities' => ['writeFiles', 'listFiles'],
			'namePrefix'   => 'myprefix/',
		]);

		$this->assertSame('abc123def456', $allowed->bucketId, 'bucketId must be seeded from the v4 id key');
		$this->assertSame('my-bucket', $allowed->bucketName, 'bucketName must be seeded from the v4 name key');
		$this->assertTrue($allowed->isBucketAllowed('my-bucket'), 'The restricted bucket must be reported as allowed');
		$this->assertFalse($allowed->isBucketAllowed('other-bucket'), 'A different bucket must not be allowed');
	}

	/**
	 * v4 may carry more than one bucket. Every named bucket must resolve as allowed, and the scalar fields seed from
	 * the first entry.
	 */
	public function testV4MultiBucketResolvesEveryEntry(): void
	{
		$allowed = new Allowed([
			'buckets'      => [
				['id' => 'id-one', 'name' => 'bucket-one'],
				['id' => 'id-two', 'name' => 'bucket-two'],
			],
			'capabilities' => ['writeFiles'],
		]);

		$this->assertSame('id-one', $allowed->bucketId);
		$this->assertSame('bucket-one', $allowed->bucketName);
		$this->assertTrue($allowed->isBucketAllowed('bucket-one'));
		$this->assertTrue($allowed->isBucketAllowed('bucket-two'));
		$this->assertFalse($allowed->isBucketAllowed('bucket-three'));
	}

	/**
	 * The legacy bucketId/bucketName key spelling inside buckets[] must keep working, so an older API shape (or a
	 * serialized state captured before this fix) still resolves.
	 */
	public function testV4AcceptsLegacyBucketIdBucketNameKeys(): void
	{
		$allowed = new Allowed([
			'buckets'      => [
				['bucketId' => 'legacy-id', 'bucketName' => 'legacy-bucket'],
			],
			'capabilities' => ['writeFiles'],
		]);

		$this->assertSame('legacy-id', $allowed->bucketId);
		$this->assertSame('legacy-bucket', $allowed->bucketName);
		$this->assertTrue($allowed->isBucketAllowed('legacy-bucket'));
	}

	/**
	 * The pre-v4 shape carried the scalar bucketId/bucketName directly, with no buckets[] array. That must still work.
	 */
	public function testLegacyScalarOnlyShapeStillWorks(): void
	{
		$allowed = new Allowed([
			'bucketId'     => 'scalar-id',
			'bucketName'   => 'scalar-bucket',
			'capabilities' => ['writeFiles'],
		]);

		$this->assertSame('scalar-id', $allowed->bucketId);
		$this->assertSame('scalar-bucket', $allowed->bucketName);
		$this->assertTrue($allowed->isBucketAllowed('scalar-bucket'));
		$this->assertFalse($allowed->isBucketAllowed('nope'));
	}

	/**
	 * An unrestricted key carries neither buckets[] nor scalar bucket fields. Every bucket must then be allowed.
	 */
	public function testUnrestrictedKeyAllowsAnyBucket(): void
	{
		$allowed = new Allowed([
			'capabilities' => ['listBuckets', 'writeFiles'],
		]);

		$this->assertSame('', (string) $allowed->bucketName);
		$this->assertTrue($allowed->isBucketAllowed('any-bucket'));
		$this->assertTrue($allowed->isBucketAllowed('another-bucket'));
	}

	/**
	 * A deleted bucket comes back from v4 as {"id": "...", "name": null}. Seeding the scalar fields from such an entry
	 * would leave bucketName empty, and a bucket with no name matches nothing — so the real, named bucket sitting behind
	 * it in the list would become unreachable and every upload to it would fail.
	 *
	 * @see  https://www.backblaze.com/apidocs/b2-authorize-account
	 */
	public function testNullNamedBucketDoesNotShadowANamedOne(): void
	{
		$allowed = new Allowed([
			'buckets'      => [
				['id' => 'deleted-id', 'name' => null],
				['id' => 'live-id', 'name' => 'live-bucket'],
			],
			'capabilities' => ['writeFiles'],
		]);

		$this->assertSame('live-id', $allowed->bucketId, 'The scalar fields must seed from the first NAMED bucket');
		$this->assertSame('live-bucket', $allowed->bucketName);
		$this->assertTrue($allowed->isBucketAllowed('live-bucket'));
	}

	/**
	 * When we cannot name a bucket we cannot prove it is disallowed either — and the bucket we were asked about may well
	 * BE the unnamed one. isBucketAllowed() is only a courtesy check that turns a remote 401 into a legible error;
	 * Backblaze enforces the restriction regardless. So defer to the API rather than block what might be a valid upload.
	 */
	public function testAnUnnamedBucketDefersTheDecisionToTheApi(): void
	{
		$allowed = new Allowed([
			'buckets'      => [
				['id' => 'unnamed-id', 'name' => null],
			],
			'capabilities' => ['writeFiles'],
		]);

		$this->assertTrue(
			$allowed->isBucketAllowed('some-bucket'),
			'With no name to match against, the API — not us — must decide'
		);
	}

	/**
	 * With every bucket named, an unlisted bucket is definitively disallowed and we should say so locally.
	 */
	public function testAllNamedBucketsStillRejectAnUnlistedBucket(): void
	{
		$allowed = new Allowed([
			'buckets'      => [
				['id' => 'id-one', 'name' => 'bucket-one'],
				['id' => 'id-two', 'name' => 'bucket-two'],
			],
			'capabilities' => ['writeFiles'],
		]);

		$this->assertFalse($allowed->isBucketAllowed('bucket-three'));
	}

	/**
	 * The properties are private and reached through __get(), so isset() and empty() route through __isset(). Without
	 * one, PHP concludes the property is unset and empty($allowed->bucketName) comes back true for a perfectly good
	 * bucket name — which silently disables any caller that guards on it. Backblaze::getBucketId() does exactly that.
	 */
	public function testEmptyAndIssetSeeTheRealPropertyValues(): void
	{
		$allowed = new Allowed([
			'buckets'      => [
				['id' => 'the-id', 'name' => 'the-bucket'],
			],
			'capabilities' => ['writeFiles'],
		]);

		$this->assertTrue(isset($allowed->bucketName));
		$this->assertFalse(empty($allowed->bucketName), 'empty() must see the actual bucket name, not a phantom unset');
		$this->assertFalse(empty($allowed->bucketId));

		// namePrefix was never supplied, so it really is unset — and must still report as such.
		$this->assertTrue(empty($allowed->namePrefix));
	}

	/**
	 * The API may send buckets as an explicit null for an unrestricted key. Callers iterate the property, so it has to
	 * be an array no matter what arrives.
	 */
	public function testNullBucketsIsNormalisedToAnArray(): void
	{
		$allowed = new Allowed([
			'buckets'      => null,
			'capabilities' => ['listBuckets', 'writeFiles'],
		]);

		$this->assertSame([], $allowed->buckets);
		$this->assertTrue($allowed->isBucketAllowed('any-bucket'));
	}
}
