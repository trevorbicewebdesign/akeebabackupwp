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

/**
 * Integration test for the native (PHP ext/ssh2) SFTP post-processing engine (engine/Postproc/Sftp.php), run against the
 * local, ephemeral atmoz/sftp container managed by AbstractSftpTestCase.
 *
 * On top of the opt-in + Docker gating in the base class, this test additionally self-skips when the PHP `ssh2`
 * extension is not loaded, since both the engine under test and this test's independent verification use it.
 *
 * Connector coverage map (Akeeba\Engine\Util\Transfer\Sftp — the "connector" the native SFTP engine uses; scoped to what
 * the engine exercises plus a couple of independent checks):
 *   COVERED (through the engine's processPart/downloadToFile/delete and this test's own verification): connect()/login
 *           (ssh2_connect + ssh2_auth_password + ssh2_sftp), upload() (write via the ssh2.sftp stream wrapper),
 *           download() (read via the stream wrapper), delete() (ssh2_sftp_unlink), mkdir() (the configured initial
 *           directory is created on connect, exercising ssh2_sftp_mkdir), and isDir() (ssh2_sftp_stat) during those
 *           operations.
 *   NOT COVERED here: read()/write() of in-memory strings (archiver path), copy(), move()/rename(), chmod() (the engine
 *           never calls it), listFolders() (sub-directory browser), cwd(), getWrapperStringFor(), getRawList(),
 *           isFirewalled(), and public-key authentication (the container uses password auth). Engine paths not covered:
 *           ranged downloads — rejected by design, which testRangedDownloadIsRejected() pins — and downloadToBrowser(),
 *           which the SFTP engine does not support (testEngineDoesNotSupportDownloadToBrowser()).
 *
 * @group integration
 * @group postproc
 * @group sftp
 */
class SftpTest extends AbstractSftpTestCase
{
	protected function getEngineSlug(): string
	{
		return 'sftp';
	}

	protected static function requiredExtensionSkipReason(): ?string
	{
		if (!function_exists('ssh2_connect'))
		{
			return 'The PHP `ssh2` extension is not installed, so the native SFTP engine cannot be tested.';
		}

		return null;
	}

	protected static function serverAcceptsLogin(): bool
	{
		$connection = @ssh2_connect(self::HOST, self::$sshPort);

		if ($connection === false)
		{
			return false;
		}

		$ok = @ssh2_auth_password($connection, self::user(), self::password());

		// There is no ssh2_close(); ssh2_disconnect() exists only on newer ext/ssh2, so guard it. Otherwise the handle is
		// freed when $connection goes out of scope.
		if (function_exists('ssh2_disconnect'))
		{
			@ssh2_disconnect($connection);
		}

		return $ok;
	}

	protected function getRemoteSize(string $remotePath): ?int
	{
		$sftp = $this->openSftp();

		if ($sftp === null)
		{
			return null;
		}

		// ssh2_sftp_stat() returns false when the file does not exist.
		$stat = @ssh2_sftp_stat($sftp, $remotePath);

		if (!is_array($stat) || !isset($stat['size']))
		{
			return null;
		}

		return (int) $stat['size'];
	}

	protected function listRemoteDirectory(string $directory): array
	{
		$sftp = $this->openSftp();

		if ($sftp === null)
		{
			return [];
		}

		$entries = @scandir("ssh2.sftp://{$sftp}{$directory}");

		if (!is_array($entries))
		{
			return [];
		}

		return array_values(array_filter($entries, static function ($name) {
			return $name !== '.' && $name !== '..';
		}));
	}

	/**
	 * Open a fresh, authenticated SFTP subsystem handle to the test server, or null if it could not be established.
	 *
	 * @return  resource|null
	 */
	private function openSftp()
	{
		$connection = @ssh2_connect(self::HOST, self::$sshPort);

		if ($connection === false)
		{
			return null;
		}

		if (!@ssh2_auth_password($connection, self::user(), self::password()))
		{
			return null;
		}

		$sftp = @ssh2_sftp($connection);

		return $sftp ?: null;
	}
}
