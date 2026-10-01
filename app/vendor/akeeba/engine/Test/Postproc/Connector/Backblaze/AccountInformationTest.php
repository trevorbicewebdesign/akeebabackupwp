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

use Akeeba\Engine\Postproc\Connector\Backblaze\AccountInformation;
use Akeeba\Engine\Postproc\Connector\Backblaze\Allowed;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AccountInformation, built from recorded b2_authorize_account responses.
 *
 * v3 moved apiUrl, downloadUrl, allowed and the part sizes out of the top level and down into apiInfo.storageApi. Every
 * one of those is load-bearing — an apiUrl that silently reads as null sends every subsequent API call to a relative URL
 * — yet none of the un-nesting was covered by a test.
 *
 * @see  https://www.backblaze.com/apidocs/b2-authorize-account
 */
final class AccountInformationTest extends TestCase
{
	/**
	 * The v4 response nests everything useful under apiInfo.storageApi. It all has to be lifted to the top level.
	 */
	public function testV4NestedResponseIsLiftedToTheTopLevel(): void
	{
		$info = new AccountInformation($this->fixture('authorize_v4.json'));

		$this->assertSame('a1b2c3d4e5f6', $info->accountId);
		$this->assertSame('https://api001.backblazeb2.com', $info->apiUrl, 'apiUrl must be lifted out of apiInfo.storageApi');
		$this->assertSame('https://f001.backblazeb2.com', $info->downloadUrl, 'downloadUrl must be lifted out of apiInfo.storageApi');
		$this->assertSame(5000000, $info->absoluteMinimumPartSize);
		$this->assertSame(100000000, $info->recommendedPartSize);
		$this->assertInstanceOf(Allowed::class, $info->allowed, 'allowed must be lifted and inflated into an Allowed object');
	}

	/**
	 * v4 dropped minimumPartSize. It is documented as an alias of recommendedPartSize, and uploadFile() divides by it,
	 * so it must never be left empty.
	 */
	public function testMinimumPartSizeDefaultsToTheRecommendedOne(): void
	{
		$info = new AccountInformation($this->fixture('authorize_v4.json'));

		$this->assertSame(
			$info->recommendedPartSize, $info->minimumPartSize,
			'minimumPartSize must fall back to recommendedPartSize when the API omits it'
		);
	}

	/**
	 * The flat pre-v4 response has no apiInfo at all. Anyone holding a serialized AccountInformation from before the v4
	 * switch, or authorizing against an older version, must still get a usable object.
	 */
	public function testPreV4FlatResponseStillWorks(): void
	{
		$info = new AccountInformation($this->fixture('authorize_v1.json'));

		$this->assertSame('a1b2c3d4e5f6', $info->accountId);
		$this->assertSame('https://api001.backblazeb2.com', $info->apiUrl);
		$this->assertSame('https://f001.backblazeb2.com', $info->downloadUrl);
		$this->assertSame('akeeba-backups-legacy', $info->allowed->bucketName);
	}

	/**
	 * An empty response must not blow up on property access: the connector reads ->allowed unconditionally.
	 */
	public function testEmptyResponseStillYieldsAnAllowedObject(): void
	{
		$info = new AccountInformation([]);

		$this->assertInstanceOf(Allowed::class, $info->allowed);
		$this->assertSame([], $info->allowed->buckets);
	}

	/**
	 * A freshly built object is valid; the connector relies on this to skip re-authorizing on every call.
	 */
	public function testAFreshlyBuiltObjectIsValid(): void
	{
		$this->assertTrue((new AccountInformation($this->fixture('authorize_v4.json')))->isValid());
	}

	/**
	 * Decode a recorded b2_authorize_account response.
	 *
	 * @param   string  $fixture  Filename under Test/_data/Backblaze/
	 *
	 * @return  array
	 */
	private function fixture(string $fixture): array
	{
		$json = file_get_contents(__DIR__ . '/../../../_data/Backblaze/' . $fixture);

		$this->assertNotFalse($json, sprintf('Cannot read the %s fixture', $fixture));

		return json_decode($json, true);
	}
}
