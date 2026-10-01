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
use Akeeba\Engine\Postproc\Connector\Box as BoxConnector;
use Akeeba\Engine\Postproc\Connector\Box\Exception\APIError as BoxAPIError;
use Akeeba\Engine\Postproc\Exception\BadConfiguration;
use ReflectionClass;

/**
 * Integration test for the Box.com post-processing engine and connector.
 *
 * Exercises the full upload / download / delete lifecycle against a live Box.com account for both a small file and a
 * large file, verifying byte-for-byte fidelity (size + SHA-512), plus the connector's informational and folder methods
 * (current user, folder listing/creation/resolution, preflight) that the lifecycle does not assert on directly.
 *
 * The test runs only when BOX_ACCESS_TOKEN, BOX_REFRESH_TOKEN and BOX_DLID are all set (see Test/.env.sample);
 * otherwise it self-skips.
 *
 * Box.com specifics that shape this test:
 *   - OAuth2. The engine sources the access/refresh tokens from `engine.postproc.box.*` but the akeeba.com Download ID
 *     (used to relay token refresh) from the platform option `update_dlid`. configureProvider() seeds it on the shared
 *     test platform via setConfigurationOption() so the engine's makeConnector() does not throw BadConfiguration.
 *   - The Box connector deliberately does NOT implement chunked/multipart uploads (see the class docblock in
 *     engine/Postproc/Connector/Box.php): every upload is single-shot regardless of file size. supportsMultipart()
 *     therefore returns false so the inherited testLargeFileLifecycle() verifies a large single-shot upload without
 *     asserting >1 processPart() step.
 *   - The connector exposes no signed/public-URL method, so there is no public-URL download test.
 *   - listFolder() returns only name => ID maps (no sizes), so there is no clean independent size readback;
 *     getRemoteSize() returns null and the size cross-check is skipped (the download size + SHA-512 check still runs).
 *
 * Connector coverage map (engine/Postproc/Connector/Box.php):
 *   COVERED:
 *     - ping                  (engine makeConnector()/processPart()/download()/delete() all call ping(); lifecycle)
 *     - getCurrentUser        (testGetCurrentUser, + indirectly via ping())
 *     - uploadSingleFile      (small- and large-file lifecycle uploads)
 *     - download              (lifecycle download)
 *     - deleteFileByName      (lifecycle delete, + testDeleteFileByName)
 *     - listFolder            (testListFolder, + transitively via findFileId()/getFolderId())
 *     - createFolder          (testGetFolderIdCreatesFolder, + transitively when uploading into TEST_DIRECTORY)
 *     - getFolderId           (testGetFolderIdCreatesFolder, + transitively on every upload/download/delete)
 *     - preflight             (testPreflight)
 *   NOT COVERED (deliberately):
 *     - refreshToken          (never called directly; exercised only when ping() decides a refresh is due.)
 *
 * A word on Box's tokens, because they behave unlike every other provider here. The access token lasts about an hour,
 * and the refresh token is SINGLE-USE: every refresh mints a replacement and kills the one that was used (they also
 * lapse after 60 days of disuse). So a refresh is not free — it spends a credential. Two consequences:
 *
 *   - There is exactly ONE live pair at any moment, held in self::$liveTokens. Both the engine's connector and this
 *     class's verification connector are seeded from it, and any refresh either of them performs is adopted back into
 *     it (see adoptTokens()). Seeding either one straight from Test/.env would hand it a token the other had already
 *     spent.
 *   - Whatever pair we end on is written back to Test/.env. Without that the suite would eat its own credentials: the
 *     first refresh spends what is in the file, the replacement dies with the process, and every later run starts from
 *     a token Box has already invalidated — which is exactly the state this test was found in.
 *   Engine-level paths NOT covered: downloadToBrowser (the engine sets supportsDownloadToBrowser = false) and ranged
 *   downloads (downloadToFile() with an offset throws RangeDownloadNotSupported by design).
 *
 * @group integration
 * @group postproc
 * @group box
 */
class BoxTest extends AbstractPostprocTestCase
{
	/**
	 * @var int  A small part size, in bytes, used to size the large-file test.
	 *
	 * Box does not support multipart uploads through this connector, so there is no provider-imposed minimum. We pick a
	 * deliberately small value so the "large" file stays small and the test runs quickly while still being meaningfully
	 * larger than the single-shot small file.
	 */
	private const PART_SIZE = 1048576;

	/** @var string Folder (relative to the Box root) all test objects are stored under. */
	private const TEST_DIRECTORY = 'akeeba-engine-test';

	/**
	 * A syntactically plausible refresh token which Box will reject.
	 *
	 * The failure-surfacing tests must never send the REAL refresh token to the relay: Box rotates it on every use, so a
	 * refresh that succeeded would spend the credential the rest of the suite depends on.
	 */
	private const INVALID_REFRESH_TOKEN = 'ThisIsNotARealBoxRefreshToken00000000000000000000000000000000000';

	/** @var BoxConnector|null Independent connector used to verify folder/file state out of band. */
	private $verificationConnector;

	/**
	 * The Box token pair currently believed to be live, shared by every test in this class.
	 *
	 * Static on purpose. Box rotates its refresh token on every refresh, so the pair in Test/.env is spent the moment
	 * the first test refreshes; each subsequent test has to start from the replacement, not from the file.
	 *
	 * @var array{access_token: string, refresh_token: string, token_expiration: int}|null
	 */
	private static $liveTokens = null;

	protected function tearDown(): void
	{
		// The engine tells nobody when it refreshes: it just writes the new pair into the profile configuration. Pick it
		// up now, BEFORE parent::tearDown() disposes of the platform and takes the configuration with it — otherwise we
		// walk into the next test still holding a refresh token Box has already invalidated.
		//
		// Nothing is lost by reading it this early. parent::tearDown() drives the engine again (to delete this test's
		// remote objects), but by then the access token it would use is the one minted moments ago in the test body, and
		// it is good for an hour — so that pass has no reason to refresh again.
		$config  = Factory::getConfiguration();
		$refresh = trim((string) $config->get('engine.postproc.box.refresh_token', ''));

		if ($refresh !== '' && $refresh !== ($this->tokens()['refresh_token'] ?? ''))
		{
			$this->adoptTokens(
				[
					'access_token'     => trim((string) $config->get('engine.postproc.box.access_token', '')),
					'refresh_token'    => $refresh,
					'token_expiration' => (int) $config->get('engine.postproc.box.token_expiration', 0),
				]
			);
		}

		parent::tearDown();
	}

	// =================================================================================================================
	// Failure surfacing
	//
	// The akeeba.com relay reports every one of these failures as an error payload carrying an HTTP *200* status, so
	// nothing throws on its own: the connector has to notice that no access token came back. Before it did, it kept the
	// old dead token and reported success, and the user met the problem later as a bare "Unexpected HTTP status 401".
	//
	// Every test here uses a DELIBERATELY INVALID refresh token. The real one must never be sent to the relay by these
	// tests: Box rotates on every use, so a *successful* refresh would spend the credential the rest of the suite needs.
	// =================================================================================================================

	/**
	 * A refresh token Box rejects must surface as an APIError which says so.
	 */
	public function testInvalidRefreshTokenIsReported(): void
	{
		$connector = new BoxConnector('not-a-real-access-token', self::INVALID_REFRESH_TOKEN, $this->credential('BOX_DLID'));

		try
		{
			$connector->refreshToken();

			$this->fail('A refresh token Box rejects must throw, not be silently swallowed.');
		}
		catch (BoxAPIError $e)
		{
			$this->assertStringContainsString('invalid_grant', $e->getMessage());
			// Box's own reason, and what the user is supposed to do about it.
			$this->assertStringContainsStringIgnoringCase('refresh token', $e->getMessage());
			$this->assertStringContainsStringIgnoringCase('reconnect', $e->getMessage());
		}
	}

	/**
	 * A missing Download ID is rejected by the relay (it will not refresh anybody's tokens without one) and must be
	 * reported as such, rather than as a generic authorisation failure.
	 */
	public function testMissingDownloadIdIsReported(): void
	{
		$connector = new BoxConnector('not-a-real-access-token', self::INVALID_REFRESH_TOKEN, '');

		try
		{
			$connector->refreshToken();

			$this->fail('A refresh with no Download ID must throw, not be silently swallowed.');
		}
		catch (BoxAPIError $e)
		{
			$this->assertStringContainsStringIgnoringCase('download id', $e->getMessage());
		}
	}

	/**
	 * An invalid Download ID — as opposed to an absent one — must be reported too. This is what a user whose
	 * subscription has lapsed hits.
	 */
	public function testInvalidDownloadIdIsReported(): void
	{
		$connector = new BoxConnector(
			'not-a-real-access-token', self::INVALID_REFRESH_TOKEN, '00:deadbeefdeadbeefdeadbeefdeadbeef'
		);

		try
		{
			$connector->refreshToken();

			$this->fail('A refresh with an invalid Download ID must throw, not be silently swallowed.');
		}
		catch (BoxAPIError $e)
		{
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
	 * A profile which has lost its access token but kept an unexpired expiry must refresh and recover.
	 *
	 * ping() used to decide whether to refresh from the recorded expiry alone, so a profile in this state concluded its
	 * absent token was still good for the best part of an hour, sent an empty bearer token, and met a bare HTTP 401
	 * which said nothing about the real cause. The closing getCurrentUser() is the part that used to fail: asserting on
	 * ping()'s return value alone would not prove the recovered token works.
	 *
	 * Unlike the failure-surfacing tests above, this one has to send the REAL refresh token — a refusal proves nothing
	 * about recovery. That spends the credential, because Box rotates on every use, so the replacement is adopted
	 * immediately. Do not reorder this test after one which assumes the pair from Test/.env is still live.
	 */
	public function testABlankAccessTokenIsRefreshed(): void
	{
		$connector = new BoxConnector(
			'', $this->tokens()['refresh_token'], $this->credential('BOX_DLID')
		);

		// The state the bug needed: an hour of validity left, on a token we no longer hold.
		$connector->setTokenExpiration(time() + 3600);

		try
		{
			$result = $connector->ping();
		}
		finally
		{
			// Box has invalidated the refresh token we just sent whatever the outcome. If a replacement came back, it
			// is now the only usable credential — adopt it before anything else touches Box.
			$this->adoptTokens($result ?? []);
		}

		$this->assertTrue(
			$result['needs_refresh'],
			'A connector with no access token must refresh, whatever its recorded expiry claims.'
		);
		$this->assertNotEmpty($result['access_token'], 'The refresh should have returned a usable access token.');

		$user = $connector->getCurrentUser();

		$this->assertArrayHasKey(
			'id', $user,
			'After recovering the token the connector must be able to talk to Box.'
		);
	}

	/**
	 * getCurrentUser() returns the authenticated account, proving the supplied tokens work end to end.
	 */
	public function testGetCurrentUser(): void
	{
		$user = $this->getVerificationConnector()->getCurrentUser();

		$this->assertIsArray($user, 'getCurrentUser() should return an array.');
		$this->assertArrayHasKey('type', $user, 'The current-user payload should carry a type.');
		$this->assertSame('user', $user['type'], 'The current-user payload should describe a user object.');
		$this->assertArrayHasKey('id', $user, 'The current-user payload should carry an account ID.');
		$this->assertNotEmpty($user['id'], 'The current-user account ID should not be empty.');
	}

	/**
	 * listFolder() of the root returns the expected array shape and, after an upload, lists the test directory.
	 */
	public function testListFolder(): void
	{
		// Upload an object first so the test directory is guaranteed to exist under the root.
		$this->uploadTemporaryObject();

		$root = $this->getVerificationConnector()->listFolder(0);

		$this->assertIsArray($root, 'listFolder() should return an array.');
		$this->assertArrayHasKey('files', $root, 'listFolder() should return a files sub-array.');
		$this->assertArrayHasKey('folders', $root, 'listFolder() should return a folders sub-array.');
		$this->assertArrayHasKey(
			self::TEST_DIRECTORY, $root['folders'],
			'The test directory should appear among the root folders after an upload.'
		);
	}

	/**
	 * getFolderId() resolves (creating as needed) a nested folder path to a numeric ID, and createFolder() is exercised
	 * for the leaf. A second resolution with $createMissing = false must find the same, now-existing, folder.
	 */
	public function testGetFolderIdCreatesFolder(): void
	{
		$connector = $this->getVerificationConnector();
		$leaf      = 'subfolder-' . getmypid() . '-' . uniqid();
		$path      = self::TEST_DIRECTORY . '/' . $leaf;

		// First call creates the missing leaf folder (exercising createFolder()).
		$createdId = $connector->getFolderId($path, 0, true);

		$this->assertNotEmpty($createdId, 'getFolderId() should return the created folder ID.');

		// Second call, without creation, must resolve to the same existing folder.
		$resolvedId = $connector->getFolderId($path, 0, false);

		$this->assertEquals(
			$createdId, $resolvedId,
			'Resolving an existing folder should return the same ID that creating it did.'
		);

		// Clean up the leaf folder we created.
		try
		{
			$leafId = (int) $createdId;
			$this->deleteFolderById($leafId);
		}
		catch (\Throwable $e)
		{
			// Best effort: leave no debris, but never fail the test on cleanup.
		}
	}

	/**
	 * preflight() succeeds for a brand-new file (no exception thrown) and reports a conflict for a name that already
	 * exists. Skipped if the account/token cannot satisfy the preflight check (e.g. it is rejected for reasons other
	 * than an existing file).
	 */
	public function testPreflight(): void
	{
		[$remotePath, $localFile] = $this->uploadTemporaryObject();

		$connector = $this->getVerificationConnector();

		// A genuinely new name in the same folder should preflight cleanly (no exception).
		$newName = dirname($remotePath) . '/preflight-' . getmypid() . '-' . uniqid() . '.bin';

		try
		{
			$connector->preflight($newName, $localFile);
		}
		catch (\Akeeba\Engine\Postproc\Connector\Box\Exception\APIError $e)
		{
			$this->markTestSkipped('Box preflight is not usable with these credentials: ' . $e->getMessage());
		}

		// Asserting we reached here means preflight() did not throw for a fresh name.
		$this->addToAssertionCount(1);

		// The already-uploaded object's name must conflict: preflight() must throw an APIError.
		try
		{
			$connector->preflight($remotePath, $localFile);
			$this->fail('preflight() should report a conflict for a name that already exists.');
		}
		catch (\Akeeba\Engine\Postproc\Connector\Box\Exception\APIError $e)
		{
			$this->assertNotEmpty($e->getMessage(), 'The preflight conflict error should carry a message.');
		}
	}

	/**
	 * deleteFileByName() returns false for a file that does not exist and true after an upload makes it exist.
	 */
	public function testDeleteFileByName(): void
	{
		$connector = $this->getVerificationConnector();

		// Deleting a non-existent file is a no-op that returns false.
		$missing = self::TEST_DIRECTORY . '/' . $this->uniqueRemoteName(self::SIZE_SMALL);

		$this->assertFalse(
			$connector->deleteFileByName($missing),
			'Deleting a non-existent file should return false.'
		);

		// Upload an object, then delete it by name: that should return true.
		[$remotePath] = $this->uploadTemporaryObject();

		$this->assertTrue(
			$connector->deleteFileByName($remotePath),
			'Deleting an existing file by name should return true.'
		);

		// The object is gone now. Teardown will still attempt to delete it, but that delete is a harmless no-op
		// (deleteFileByName() returns false for a missing file) and teardown swallows any error regardless.
	}

	protected function getEngineSlug(): string
	{
		return 'box';
	}

	protected function isProviderConfigured(): bool
	{
		return $this->credential('BOX_ACCESS_TOKEN') !== ''
			&& $this->credential('BOX_REFRESH_TOKEN') !== ''
			&& $this->credential('BOX_DLID') !== '';
	}

	protected function getSkipMessage(): string
	{
		return 'Box.com is not configured. Set BOX_ACCESS_TOKEN, BOX_REFRESH_TOKEN and BOX_DLID to enable this '
			. 'integration test (see Test/.env.sample).';
	}

	protected function configureProvider(): void
	{
		$config = Factory::getConfiguration();
		$tokens = $this->tokens();

		// Seed from the live pair, NOT straight from the environment. Box invalidates a refresh token the moment it is
		// used, so once any earlier test in this run has refreshed, the value still sitting in Test/.env is dead.
		$config->set('engine.postproc.box.access_token', $tokens['access_token']);
		$config->set('engine.postproc.box.refresh_token', $tokens['refresh_token']);
		$config->set('engine.postproc.box.token_expiration', $tokens['token_expiration']);
		$config->set('engine.postproc.box.directory', self::TEST_DIRECTORY);
		// Use the akeeba.com OAuth2 relay for token refresh (the production default).
		$config->set('engine.postproc.box.oauth2_type', 'akeeba');
		$config->set('engine.postproc.box.oauth2_helper', '');
		$config->set('engine.postproc.box.oauth2_refresh', '');

		// The engine sources the Download ID from the platform option `update_dlid`, not from a config key. Seed it on
		// the shared test platform; without it the engine's makeConnector() throws BadConfiguration.
		$this->platform()->setConfigurationOption('update_dlid', $this->credential('BOX_DLID'));
	}

	protected function getMinimumPartSize(): int
	{
		return self::PART_SIZE;
	}

	protected function supportsMultipart(): bool
	{
		// The Box connector only ever does single-shot uploadSingleFile(); processPart() completes in one step.
		return false;
	}

	protected function getRemoteSize(string $remotePath): ?int
	{
		// Box's listFolder() returns only name => ID maps, with no per-file size, so there is no clean independent size
		// readback. Opt out of the size cross-check (the download size + SHA-512 verification still runs).
		return null;
	}

	/**
	 * Build (once) an independent connector used to verify folder/file state out of band, separate from the engine's
	 * own connector.
	 *
	 * It also mirrors what the engine does *before* it talks to Box: ping(), which renews the access token when it has
	 * lapsed. Box access tokens live only about an hour, so the one configured in Test/.env is stale far more often than
	 * not; without this the verification calls fail with a bare HTTP 401 even when the refresh token is good.
	 *
	 * Note that this cannot rescue an expired *refresh* token. Box refresh tokens are single-use — every refresh mints a
	 * replacement and invalidates the old one — and they lapse after 60 days of disuse. Once BOX_REFRESH_TOKEN in
	 * Test/.env has been spent or has aged out, the only cure is to re-authorise Box and store a fresh pair.
	 *
	 * @return  BoxConnector
	 */
	private function getVerificationConnector(): BoxConnector
	{
		if (!$this->verificationConnector instanceof BoxConnector)
		{
			$tokens = $this->tokens();

			$this->verificationConnector = new BoxConnector(
				$tokens['access_token'],
				$tokens['refresh_token'],
				$this->credential('BOX_DLID')
			);

			// Telling the connector when the access token expires lets ping() decide without a probe request, and — far
			// more importantly — without a needless refresh, each of which burns the single-use refresh token.
			$this->verificationConnector->setTokenExpiration($tokens['token_expiration']);

			$this->adoptTokens($this->verificationConnector->ping());
		}

		return $this->verificationConnector;
	}

	/**
	 * Delete a Box folder by its numeric ID (recursively). Best-effort helper for test cleanup.
	 *
	 * @param   int  $folderId  The numeric folder ID to delete.
	 */
	private function deleteFolderById(int $folderId): void
	{
		$reflection = new ReflectionClass(BoxConnector::class);
		$fetch      = $reflection->getMethod('fetch');
		$fetch->setAccessible(true);

		$fetch->invoke(
			$this->getVerificationConnector(),
			'DELETE',
			BoxConnector::rootUrl,
			"folders/$folderId?recursive=true",
			['expect-status' => 204]
		);
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

	/**
	 * The Box token pair currently believed to be live, seeded from the environment on first use.
	 *
	 * @return  array{access_token: string, refresh_token: string, token_expiration: int}
	 */
	private function tokens(): array
	{
		if (self::$liveTokens === null)
		{
			self::$liveTokens = [
				'access_token'     => $this->credential('BOX_ACCESS_TOKEN'),
				'refresh_token'    => $this->credential('BOX_REFRESH_TOKEN'),
				'token_expiration' => 0,
			];
		}

		return self::$liveTokens;
	}

	/**
	 * Adopt a freshly minted Box token pair as the live one, everywhere it is read from.
	 *
	 * Box rotates its refresh token on every refresh: the one we just spent is dead, and the replacement is the only
	 * usable credential from here on. It has to reach four places or something will still be holding a spent token —
	 * the in-memory store (read by configureProvider() on the next setUp()), the profile configuration (read by the
	 * engine's makeConnector()), the process environment, and Test/.env (read by the next run of the suite).
	 *
	 * @param   array  $pair  The refresh result: access_token, refresh_token and (optionally) token_expiration.
	 *
	 * @return  void
	 */
	private function adoptTokens(array $pair): void
	{
		if (empty($pair['access_token']) || empty($pair['refresh_token']))
		{
			return;
		}

		self::$liveTokens = [
			'access_token'     => $pair['access_token'],
			'refresh_token'    => $pair['refresh_token'],
			'token_expiration' => (int) ($pair['token_expiration'] ?? 0),
		];

		$config = Factory::getConfiguration();
		$config->set('engine.postproc.box.access_token', self::$liveTokens['access_token'], false);
		$config->set('engine.postproc.box.refresh_token', self::$liveTokens['refresh_token'], false);
		$config->set('engine.postproc.box.token_expiration', self::$liveTokens['token_expiration'], false);

		putenv('BOX_ACCESS_TOKEN=' . self::$liveTokens['access_token']);
		putenv('BOX_REFRESH_TOKEN=' . self::$liveTokens['refresh_token']);

		$this->persistTokensToEnvFile();
	}

	/**
	 * Write the live token pair back into Test/.env, replacing the values already there.
	 *
	 * Without this the suite eats its own credentials: the first refresh spends the token in Test/.env and the
	 * replacement dies with the process, so every later run starts from a token Box has already invalidated. Silently
	 * does nothing when there is no writable Test/.env (a CI run supplying the tokens through the real environment).
	 *
	 * @return  void
	 */
	private function persistTokensToEnvFile(): void
	{
		$envFile = dirname(__DIR__, 2) . '/.env';

		if (!is_file($envFile) || !is_writable($envFile))
		{
			return;
		}

		$contents = file_get_contents($envFile);

		if ($contents === false)
		{
			return;
		}

		$values = [
			'BOX_ACCESS_TOKEN'  => self::$liveTokens['access_token'],
			'BOX_REFRESH_TOKEN' => self::$liveTokens['refresh_token'],
		];

		foreach ($values as $key => $value)
		{
			// Only ever touch a real assignment — never a commented-out sample line.
			$pattern = '/^[ \t]*(?:export[ \t]+)?' . preg_quote($key, '/') . '[ \t]*=.*$/m';
			$line    = $key . '=' . $value;

			// A callback, not a replacement string: a token may contain characters preg_replace() would read as
			// backreferences.
			$replaced = preg_replace_callback(
				$pattern, function () use ($line) {
				return $line;
			}, $contents, 1, $count
			);

			$contents = $count ? $replaced : rtrim($contents, "\n") . "\n" . $line . "\n";
		}

		file_put_contents($envFile, $contents);
	}
}
