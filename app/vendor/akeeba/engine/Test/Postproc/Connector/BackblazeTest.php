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

namespace Akeeba\Engine\Test\Postproc\Connector;

use Akeeba\Engine\Postproc\Connector\Backblaze;
use Akeeba\Engine\Postproc\Connector\Backblaze\AccountInformation;
use Akeeba\Engine\Postproc\Connector\Backblaze\Exception\NotAllowed;
use Composer\CaBundle\CaBundle;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the BackBlaze B2 connector's bucket resolution.
 *
 * These make NO network calls. setAccountInformation() short-circuits authorizeAccount() when handed a still-valid
 * object, so we can feed the connector a recorded b2_authorize_account response and exercise getBucketId() offline. The
 * key in the v4 fixture deliberately lacks the listBuckets capability: if getBucketId() ever falls through to its
 * b2_list_buckets fallback, listBuckets() throws NotAllowed before reaching cURL. A test that expects a bucket ID and
 * gets an exception is therefore telling us the fast path missed — which is exactly the bug we are guarding against, and
 * it fails loudly instead of quietly hitting the network.
 *
 * @see  https://www.backblaze.com/apidocs/b2-authorize-account
 */
final class BackblazeTest extends TestCase
{
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		// The connector's default cURL options reference this constant at instantiation time. In production the platform
		// defines it; here we point it at the CA bundle shipped with composer/ca-bundle.
		if (!defined('AKEEBA_CACERT_PEM'))
		{
			define('AKEEBA_CACERT_PEM', CaBundle::getBundledCaBundlePath());
		}
	}

	/**
	 * Every bucket a v4 multi-bucket key is restricted to must resolve — not just the first one.
	 *
	 * This is the regression test for the bug that a naive v1 → v4 bump would have exposed. getBucketId() used to
	 * compare the requested name against the single scalar allowed->bucketName, which Allowed seeds from the FIRST
	 * buckets[] entry. Asking for any other allowed bucket missed and fell through to b2_list_buckets — which under
	 * v1 quietly auto-filtered the listing for a restricted key, and under v4 is flatly unauthorized.
	 */
	public function testGetBucketIdResolvesEveryBucketOfAMultiBucketKey(): void
	{
		$connector = $this->makeConnector('authorize_v4.json');

		$this->assertSame(
			'b100000000000000000000a1', $connector->getBucketId('akeeba-backups-main'),
			'The first allowed bucket must resolve from the key itself'
		);

		$this->assertSame(
			'b200000000000000000000b2', $connector->getBucketId('akeeba-backups-offsite'),
			'The SECOND allowed bucket must resolve too, without falling back to b2_list_buckets'
		);
	}

	/**
	 * A bucket the key is not restricted to has to go looking, and a least-privilege key cannot: it has no listBuckets
	 * capability. Proving we get NotAllowed (rather than a bucket ID, or a network timeout) proves the fallback is still
	 * wired up and that the tests above resolved purely from the key's own allowed list.
	 */
	public function testGetBucketIdFallsBackToListBucketsForAnUnknownBucket(): void
	{
		$connector = $this->makeConnector('authorize_v4.json');

		$this->expectException(NotAllowed::class);

		$connector->getBucketId('a-bucket-this-key-cannot-see');
	}

	/**
	 * A bucket whose name the API would not give us (a deleted one comes back with "name": null) cannot be matched by
	 * name, so it must not shadow the named buckets behind it in the list.
	 */
	public function testUnnamedBucketDoesNotShadowTheNamedOnes(): void
	{
		$connector = $this->makeConnector('authorize_v4.json');
		$allowed   = $connector->getAccountInformation()->allowed;

		$this->assertSame(
			'akeeba-backups-main', $allowed->bucketName,
			'The scalar fields must seed from the first NAMED bucket'
		);
		$this->assertSame('b100000000000000000000a1', $allowed->bucketId);
	}

	/**
	 * A pre-v4 key carried its single restricted bucket in scalar bucketId/bucketName fields, with no buckets[] array at
	 * all. Anyone holding a serialized AccountInformation from before the v4 switch still has that shape, so it has to
	 * keep resolving.
	 */
	public function testGetBucketIdResolvesThePreV4ScalarShape(): void
	{
		$connector = $this->makeConnector('authorize_v1.json');

		$this->assertSame(
			'b900000000000000000000z9', $connector->getBucketId('akeeba-backups-legacy'),
			'A pre-v4 scalar-shaped key must still resolve its bucket'
		);
	}

	/**
	 * The version has to live in exactly one place, and every endpoint has to be built from it. This is a guard against
	 * the state we just cleaned up: an apiURL bumped to v4 while nine other endpoints stayed pinned to a hardcoded v1.
	 */
	public function testTheApiEntryPointIsBuiltFromTheVersionConstant(): void
	{
		$this->assertSame(
			'https://api.backblazeb2.com/b2api/' . Backblaze::apiVersion . '/', Backblaze::apiURL,
			'The authorization URL must be derived from the API version constant, not hardcoded'
		);
	}

	/**
	 * Build a connector primed with a recorded b2_authorize_account response, so that no HTTP call is ever made.
	 *
	 * @param   string  $fixture  Filename under Test/_data/Backblaze/
	 *
	 * @return  Backblaze
	 */
	private function makeConnector(string $fixture): Backblaze
	{
		$json = file_get_contents(__DIR__ . '/../../_data/Backblaze/' . $fixture);

		$this->assertNotFalse($json, sprintf('Cannot read the %s fixture', $fixture));

		$connector = new Backblaze('a1b2c3d4e5f6', 'not-a-real-application-key');
		$connector->setAccountInformation(new AccountInformation(json_decode($json, true)));

		return $connector;
	}
}
