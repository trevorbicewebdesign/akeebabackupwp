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
use Akeeba\Engine\Postproc\Connector\GoogleDrive as GoogleDriveConnector;

/**
 * Integration test for the Google Drive post-processing engine and connector.
 *
 * Exercises the full upload / download / delete lifecycle against a live Google Drive account for both a small file
 * (Google Drive always uses a resumable session, even for small files — see the engine's processPart) and a large file
 * (genuine multi-chunk resumable upload), verifying byte-for-byte fidelity (size + SHA-512), plus the connector's
 * informational and helper methods (drive info, team drives, folder/file ID resolution, folder creation, listing,
 * the simple/resumable/auto upload helpers, the shared-with-me toggle).
 *
 * The test runs only when GOOGLEDRIVE_ACCESS_TOKEN, GOOGLEDRIVE_REFRESH_TOKEN and GOOGLEDRIVE_DLID are all set (see
 * Test/.env.sample); otherwise it self-skips. GOOGLEDRIVE_TEAM_DRIVE is optional (empty = personal Drive).
 *
 * Google Drive identifies files by an opaque ID, not by path. The engine maps the human-readable remote path to an ID
 * via the connector's getIdForFile() for downloads and deletes; we use the same mechanism for the independent size
 * read-back in getRemoteSize().
 *
 * Connector coverage map (engine/Postproc/Connector/GoogleDrive.php):
 *   COVERED:
 *     - __construct                                     (every test; lifecycle + getVerificationConnector)
 *     - ping                                            (lifecycle, indirectly via the engine; testPing explicitly)
 *     - getDriveInformation                             (testGetDriveInformation, + ping uses it as its probe)
 *     - getTeamDrives                                   (testGetTeamDrives)
 *     - getRawContents                                  (transitively via getIdForFile/getIdForFolder/preprocessUploadPath)
 *     - listContents                                    (testListContents, + getRemoteSize read-back)
 *     - getIdForFile                                    (lifecycle download/delete; testGetIdForFile)
 *     - getIdForFolder                                  (transitively via preprocessUploadPath; testCreateFolderAndGetIdForFolder)
 *     - createFolder                                    (testCreateFolderAndGetIdForFolder)
 *     - delete                                          (lifecycle delete; testGetIdForFile cleanup)
 *     - download                                        (lifecycle download)
 *     - simpleUpload                                    (testSimpleUpload)
 *     - createUploadSession                             (lifecycle large-file upload, via the engine's processPart)
 *     - uploadPart                                      (lifecycle large-file upload, via the engine's processPart)
 *     - resumableUpload                                 (testResumableUpload)
 *     - upload                                          (testUploadHelperAutoSelects — small and large)
 *     - preprocessUploadPath                            (every engine upload; testResumableUpload/testSimpleUpload)
 *     - setUploadToSharedWithMe / isUploadToSharedWithMe(testSharedWithMeToggle)
 *   NOT COVERED (by design):
 *     - none. All public connector methods are exercised. The protected fetch() and the FileCloseAware /
 *       ProxyAware traits expose no public surface of their own.
 *   Engine-level paths NOT covered: oauthCallback / oauthOpen / getDrives (interactive AJAX/OAuth helpers, no headless
 *   path), downloadToBrowser (the engine advertises supportsDownloadToBrowser = false), and ranged downloads
 *   (downloadToFile with an offset throws RangeDownloadNotSupported — a known engine contract, not exercised here).
 *
 * @group integration
 * @group postproc
 * @group googledrive
 */
class GoogledriveTest extends AbstractPostprocTestCase
{
	/**
	 * Google Drive resumable-upload chunk size in bytes (1 MiB).
	 *
	 * Google requires every resumable-upload chunk except the last to be a multiple of 256 KiB. 1 MiB (= 4 x 256 KiB)
	 * satisfies that rule and keeps the multi-chunk lifecycle test fast while still forcing more than one chunk on the
	 * large file. The engine takes chunk_upload_size in whole MB, so this corresponds to chunk_upload_size = 1.
	 */
	private const CHUNK_SIZE = 1048576;

	/** @var string Drive sub-directory all test objects are stored under. */
	private const TEST_DIRECTORY = 'akeeba-engine-test';

	/** @var GoogleDriveConnector|null Independent connector used to read back stored object sizes and probe the API. */
	private $verificationConnector;

	/**
	 * ping() succeeds against a live account and reports whether the access token had to be refreshed.
	 */
	public function testPing(): void
	{
		$result = $this->getVerificationConnector()->ping();

		$this->assertIsArray($result, 'ping() should return an array.');
		$this->assertArrayHasKey('needs_refresh', $result, 'ping() should report whether a token refresh happened.');
	}

	/**
	 * A profile which has lost its access token but kept an unexpired expiry must refresh and recover.
	 *
	 * This is the live reproduction of the bug behind "Could not download file. The error was: Error 403: Method
	 * doesn't allow unregistered callers". ping() decided whether to refresh from the recorded expiry alone, so a
	 * profile in this state concluded its (absent) token was still good for the best part of an hour, sent an empty
	 * bearer token, and Google rejected the request as coming from nobody at all. The final getDriveInformation() call
	 * is the part that used to 403: asserting on ping()'s return value alone would not prove the new token works.
	 *
	 * Safe to run repeatedly: Google returns the same refresh token every time, so unlike Box or OneDrive this spends
	 * no credential.
	 */
	public function testABlankAccessTokenIsRefreshed(): void
	{
		$connector = new GoogleDriveConnector(
			'',
			$this->credential('GOOGLEDRIVE_REFRESH_TOKEN'),
			$this->credential('GOOGLEDRIVE_DLID'),
			''
		);

		// The state the bug needed: an hour of validity left, on a token we no longer hold.
		$connector->setTokenExpiration(time() + 3600);

		$result = $connector->ping();

		$this->assertTrue(
			$result['needs_refresh'],
			'A connector with no access token must refresh, whatever its recorded expiry claims.'
		);
		$this->assertNotEmpty($result['access_token'], 'The refresh should have returned a usable access token.');

		$about = $connector->getDriveInformation();

		$this->assertArrayHasKey(
			'user', $about,
			'After recovering the token the connector must be able to talk to Google Drive.'
		);
	}

	/**
	 * getDriveInformation() returns a populated "about" resource for the account.
	 */
	public function testGetDriveInformation(): void
	{
		$connector = $this->getVerificationConnector();
		$connector->ping();

		$info = $connector->getDriveInformation();

		$this->assertIsArray($info, 'getDriveInformation() should return an array.');
		$this->assertArrayHasKey('user', $info, 'The drive information should carry the user resource.');
		$this->assertArrayHasKey('storageQuota', $info, 'The drive information should carry the storage quota.');
	}

	/**
	 * getTeamDrives() returns an array (possibly empty) of Team/Shared Drive ID => name pairs without erroring. When a
	 * GOOGLEDRIVE_TEAM_DRIVE is configured, that drive must appear in the listing.
	 */
	public function testGetTeamDrives(): void
	{
		$connector = $this->getVerificationConnector();
		$connector->ping();

		try
		{
			$drives = $connector->getTeamDrives();
		}
		catch (\RuntimeException $e)
		{
			// Listing Shared (Team) Drives requires the drive.readonly/drive scope. A token issued without it makes
			// Google answer "Error 403: ...insufficient authentication scopes" (see Connector\GoogleDrive::fetch(),
			// which formats API errors as "Error {code}: {message}"). That is a token-capability limitation, not an
			// engine fault, so we skip rather than fail. Match the 403 + scope signature narrowly to avoid masking
			// genuine API failures.
			if (strpos($e->getMessage(), 'Error 403:') === 0
				&& stripos($e->getMessage(), 'insufficient authentication scopes') !== false)
			{
				$this->markTestSkipped(
					'getTeamDrives() requires the Shared Drive scope; the configured OAuth token lacks the Shared '
					. 'Drive scope, so Team Drives cannot be listed.'
				);
			}

			throw $e;
		}

		$this->assertIsArray($drives, 'getTeamDrives() should return an array of ID => name pairs.');

		$teamDrive = $this->credential('GOOGLEDRIVE_TEAM_DRIVE');

		if ($teamDrive !== '')
		{
			$this->assertArrayHasKey(
				$teamDrive, $drives,
				'The configured Team Drive ID should be present in the list of Team Drives.'
			);
		}
	}

	/**
	 * listContents() lists the test directory and reports an uploaded object with the correct name and size.
	 */
	public function testListContents(): void
	{
		[$remotePath, $localFile] = $this->uploadTemporaryObject();

		$connector = $this->getVerificationConnector();
		$connector->ping();

		$teamDriveID = $this->credential('GOOGLEDRIVE_TEAM_DRIVE');
		$folderId    = $connector->getIdForFolder(self::TEST_DIRECTORY, false, $teamDriveID);

		$this->assertNotNull($folderId, 'The test directory should resolve to a folder ID.');

		$listing  = $connector->listContents($folderId, null, 100, null, 'folder,name', $teamDriveID);
		$baseName = basename($remotePath);

		$this->assertArrayHasKey('files', $listing, 'listContents() should return a files array.');
		$this->assertArrayHasKey(
			$baseName, $listing['files'],
			'The uploaded object should appear in the directory listing keyed by its name.'
		);
		$this->assertSame(
			filesize($localFile), (int) $listing['files'][$baseName]['size'],
			'The listed size should match the uploaded file size.'
		);
	}

	/**
	 * getIdForFile() resolves the human-readable path of an uploaded object to a non-empty file ID, and returns null for
	 * a non-existent file in an existing folder.
	 */
	public function testGetIdForFile(): void
	{
		[$remotePath] = $this->uploadTemporaryObject();

		$connector = $this->getVerificationConnector();
		$connector->ping();

		$teamDriveID = $this->credential('GOOGLEDRIVE_TEAM_DRIVE');

		$fileId = $connector->getIdForFile($remotePath, false, $teamDriveID);

		$this->assertIsString($fileId, 'getIdForFile() should return the file ID as a string.');
		$this->assertNotEmpty($fileId, 'The uploaded object should resolve to a non-empty file ID.');

		// A name that does not exist inside the (existing) test directory must resolve to null, not throw.
		$missing = self::TEST_DIRECTORY . '/this-object-does-not-exist-' . uniqid() . '.bin';

		$this->assertNull(
			$connector->getIdForFile($missing, false, $teamDriveID),
			'A non-existent file in an existing folder should resolve to null.'
		);
	}

	/**
	 * createFolder() creates a folder and getIdForFolder() then resolves that folder's path to the same ID. The folder
	 * is removed on teardown via the engine's delete() (registered by uploading a child object into it would be heavy;
	 * instead we delete the folder ID directly here).
	 */
	public function testCreateFolderAndGetIdForFolder(): void
	{
		$connector = $this->getVerificationConnector();
		$connector->ping();

		$teamDriveID = $this->credential('GOOGLEDRIVE_TEAM_DRIVE');
		$parentId    = $connector->getIdForFolder(self::TEST_DIRECTORY, true, $teamDriveID);

		$this->assertNotNull($parentId, 'The test directory should resolve to (or be created as) a folder ID.');

		$folderName = 'mkdir-' . getmypid() . '-' . uniqid();
		$folderId   = $connector->createFolder($parentId, $folderName);

		$this->assertIsString($folderId, 'createFolder() should return the new folder ID.');
		$this->assertNotEmpty($folderId, 'createFolder() should return a non-empty folder ID.');

		try
		{
			// Google needs a moment before a freshly created folder is consistently resolvable by name.
			$resolved = $connector->getIdForFolder(self::TEST_DIRECTORY . '/' . $folderName, false, $teamDriveID);

			$this->assertSame(
				$folderId, $resolved,
				'getIdForFolder() should resolve the created folder path back to its ID.'
			);
		}
		finally
		{
			// Best-effort cleanup of the throw-away folder.
			try
			{
				$connector->delete($folderId, false);
			}
			catch (\Throwable $e)
			{
				// Ignore: leave no orphaned exception from cleanup.
			}
		}
	}

	/**
	 * simpleUpload() stores a small file (<= 5 MiB) and the round-tripped bytes match. This is the single-shot path the
	 * engine itself deliberately avoids (see the "Google Drive is broken" note in the engine), so it gets its own test.
	 */
	public function testSimpleUpload(): void
	{
		$connector = $this->getVerificationConnector();
		$connector->ping();

		$teamDriveID = $this->credential('GOOGLEDRIVE_TEAM_DRIVE');
		$folderId    = $connector->getIdForFolder(self::TEST_DIRECTORY, true, $teamDriveID);

		$localFile  = $this->createTestFile(self::SIZE_SMALL);
		$remoteName = $this->uniqueRemoteName(self::SIZE_SMALL);
		$remotePath = self::TEST_DIRECTORY . '/' . $remoteName;

		$result = $connector->simpleUpload($folderId, $localFile, $remoteName);

		$this->assertIsArray($result, 'simpleUpload() should return the file resource.');
		$this->assertArrayHasKey('id', $result, 'simpleUpload() should return the new file ID.');

		$this->registerRemoteObjectForCleanup($remotePath);

		$this->assertRoundTrip($connector, $remotePath, $localFile, $teamDriveID);
	}

	/**
	 * resumableUpload() stores a multi-chunk file and the round-tripped bytes match. This drives createUploadSession and
	 * uploadPart explicitly through the connector's own helper rather than through the engine's processPart loop.
	 */
	public function testResumableUpload(): void
	{
		$connector = $this->getVerificationConnector();
		$connector->ping();

		$teamDriveID = $this->credential('GOOGLEDRIVE_TEAM_DRIVE');

		$size       = 2 * self::CHUNK_SIZE + intdiv(self::CHUNK_SIZE, 2);
		$localFile  = $this->createTestFile($size);
		$remoteName = $this->uniqueRemoteName($size);
		$remotePath = self::TEST_DIRECTORY . '/' . $remoteName;

		$result = $connector->resumableUpload($remotePath, $localFile, self::CHUNK_SIZE, 'application/octet-stream', $teamDriveID);

		$this->assertIsArray($result, 'resumableUpload() should return the file resource.');
		$this->assertArrayHasKey('name', $result, 'A completed resumable upload should return the file resource name.');

		$this->registerRemoteObjectForCleanup($remotePath);

		$this->assertRoundTrip($connector, $remotePath, $localFile, $teamDriveID);
	}

	/**
	 * The high-level upload() helper auto-selects simple vs resumable uploading based on file size, and both paths
	 * round-trip byte-for-byte. We pass a part size of 5 MiB so a small file goes through simpleUpload and a >5 MiB file
	 * goes through resumableUpload.
	 */
	public function testUploadHelperAutoSelects(): void
	{
		$connector = $this->getVerificationConnector();
		$connector->ping();

		$teamDriveID = $this->credential('GOOGLEDRIVE_TEAM_DRIVE');
		$partSize    = 5242880;

		// Small file -> simple upload branch.
		$smallLocal  = $this->createTestFile(self::SIZE_SMALL);
		$smallName   = $this->uniqueRemoteName(self::SIZE_SMALL);
		$smallRemote = self::TEST_DIRECTORY . '/' . $smallName;
		$connector->upload($smallRemote, $smallLocal, $partSize, 'application/octet-stream', $teamDriveID);
		$this->registerRemoteObjectForCleanup($smallRemote);
		$this->assertRoundTrip($connector, $smallRemote, $smallLocal, $teamDriveID);

		// Larger-than-part-size file -> resumable upload branch.
		$bigSize     = $partSize + self::CHUNK_SIZE;
		$bigLocal    = $this->createTestFile($bigSize);
		$bigName     = $this->uniqueRemoteName($bigSize);
		$bigRemote   = self::TEST_DIRECTORY . '/' . $bigName;
		$connector->upload($bigRemote, $bigLocal, $partSize, 'application/octet-stream', $teamDriveID);
		$this->registerRemoteObjectForCleanup($bigRemote);
		$this->assertRoundTrip($connector, $bigRemote, $bigLocal, $teamDriveID);
	}

	/**
	 * setUploadToSharedWithMe() / isUploadToSharedWithMe() toggle and report the shared-with-me flag.
	 */
	public function testSharedWithMeToggle(): void
	{
		$connector = $this->getVerificationConnector();

		$this->assertFalse($connector->isUploadToSharedWithMe(), 'The shared-with-me flag should default to false.');

		$returned = $connector->setUploadToSharedWithMe(true);

		$this->assertSame($connector, $returned, 'setUploadToSharedWithMe() should return the connector for chaining.');
		$this->assertTrue($connector->isUploadToSharedWithMe(), 'The shared-with-me flag should now be true.');

		$connector->setUploadToSharedWithMe(false);

		$this->assertFalse($connector->isUploadToSharedWithMe(), 'The shared-with-me flag should toggle back to false.');
	}

	protected function getEngineSlug(): string
	{
		return 'googledrive';
	}

	protected function isProviderConfigured(): bool
	{
		return $this->credential('GOOGLEDRIVE_ACCESS_TOKEN') !== ''
			&& $this->credential('GOOGLEDRIVE_REFRESH_TOKEN') !== ''
			&& $this->credential('GOOGLEDRIVE_DLID') !== '';
	}

	protected function getSkipMessage(): string
	{
		return 'Google Drive is not configured. Set GOOGLEDRIVE_ACCESS_TOKEN, GOOGLEDRIVE_REFRESH_TOKEN and '
			. 'GOOGLEDRIVE_DLID to enable this integration test (see Test/.env.sample).';
	}

	protected function configureProvider(): void
	{
		// The engine sources the Download ID from the platform option `update_dlid`, not from a config key. Seed it on
		// the shared test platform; without it the engine's makeConnector() throws BadConfiguration.
		$this->platform()->setConfigurationOption('update_dlid', $this->credential('GOOGLEDRIVE_DLID'));

		$config = Factory::getConfiguration();

		$config->set('engine.postproc.googledrive.access_token', $this->credential('GOOGLEDRIVE_ACCESS_TOKEN'));
		$config->set('engine.postproc.googledrive.refresh_token', $this->credential('GOOGLEDRIVE_REFRESH_TOKEN'));
		$config->set('engine.postproc.googledrive.directory', self::TEST_DIRECTORY);
		$config->set('engine.postproc.googledrive.team_drive', $this->credential('GOOGLEDRIVE_TEAM_DRIVE'));
		$config->set('engine.postproc.googledrive.uploadtosharedwithme', 0);
		$config->set('engine.postproc.googledrive.oauth2_type', 'akeeba');
		// 1 MiB chunks (a 256 KiB multiple, as Google's resumable upload requires) keep the multi-chunk upload fast while
		// still exercising the resumable upload code path on the large file. chunk_upload_size is in whole MB.
		$config->set('engine.postproc.googledrive.chunk_upload_size', intdiv(self::CHUNK_SIZE, 1048576));
	}

	protected function getMinimumPartSize(): int
	{
		return self::CHUNK_SIZE;
	}

	protected function getRemoteSize(string $remotePath): ?int
	{
		$connector = $this->getVerificationConnector();
		$connector->ping();

		$teamDriveID = $this->credential('GOOGLEDRIVE_TEAM_DRIVE');

		try
		{
			$fileId = $connector->getIdForFile($remotePath, false, $teamDriveID);
		}
		catch (\Throwable $e)
		{
			// The enclosing folder does not exist (e.g. after the object and its directory were removed).
			return null;
		}

		if (empty($fileId))
		{
			return null;
		}

		// Read the size back via a folder listing (the connector exposes size through listContents, not by file ID).
		$parentPath = trim(dirname($remotePath), '/');
		$folderId   = $connector->getIdForFolder($parentPath, false, $teamDriveID);

		if (empty($folderId))
		{
			return null;
		}

		$baseName = basename($remotePath);
		$listing  = $connector->listContents($folderId, null, 100, null, 'folder,name', $teamDriveID);

		if (!isset($listing['files'][$baseName]['size']))
		{
			return null;
		}

		return (int) $listing['files'][$baseName]['size'];
	}

	/**
	 * Download an object through the connector by resolving its path to an ID, and assert it matches the local original
	 * byte-for-byte.
	 *
	 * @param   GoogleDriveConnector  $connector    The connector to use.
	 * @param   string                $remotePath   The remote path of the object.
	 * @param   string                $localFile    The local original to compare against.
	 * @param   string                $teamDriveID  The Team Drive ID (empty for the personal Drive).
	 *
	 * @return  void
	 */
	private function assertRoundTrip(GoogleDriveConnector $connector, string $remotePath, string $localFile, string $teamDriveID): void
	{
		$fileId = $connector->getIdForFile($remotePath, false, $teamDriveID);

		$this->assertNotEmpty($fileId, 'The uploaded object should resolve to a file ID for download.');

		$downloaded = $this->createTempFileName();
		$connector->download($fileId, $downloaded);

		$this->assertFilesEqual($localFile, $downloaded);
	}

	/**
	 * Get an independent connector instance used to probe the API and verify stored object sizes, separate from the
	 * engine's own connector. Mirrors how the engine builds its connector (see Googledrive::makeConnector).
	 *
	 * @return  GoogleDriveConnector
	 */
	private function getVerificationConnector(): GoogleDriveConnector
	{
		if (!$this->verificationConnector instanceof GoogleDriveConnector)
		{
			// An empty refresh URL makes the connector default to the Akeeba helper URL, matching oauth2_type = 'akeeba'.
			$this->verificationConnector = new GoogleDriveConnector(
				$this->credential('GOOGLEDRIVE_ACCESS_TOKEN'),
				$this->credential('GOOGLEDRIVE_REFRESH_TOKEN'),
				$this->credential('GOOGLEDRIVE_DLID'),
				''
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
