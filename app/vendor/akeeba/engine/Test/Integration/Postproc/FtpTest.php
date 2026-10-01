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
 * Integration test for the native (PHP ext/ftp) FTP post-processing engine (engine/Postproc/Ftp.php), run against the
 * local, ephemeral pure-ftpd container managed by AbstractFtpTestCase.
 *
 * On top of the opt-in + Docker gating in the base class, this test additionally self-skips when the PHP `ftp` extension
 * is not loaded, since both the engine under test and this test's independent verification use it.
 *
 * Connector coverage map (Akeeba\Engine\Util\Transfer\Ftp — the "connector" the native FTP engine uses; scoped to what
 * the engine exercises plus a couple of independent checks):
 *   COVERED (through the engine's processPart/downloadToFile/delete and this test's own verification): connect()/login,
 *           upload() (STOR), download() (RETR), delete() (DELE), mkdir() (the configured sub-directory is created on the
 *           first upload), isDir()/chdir during those operations, and the engine's downloadToBrowser() URL builder.
 *   NOT COVERED here: write()/read() (string in-memory transfer — used by the archiver, not the post-processing engine),
 *           copy(), move()/rename(), chmod() as a positive assertion (the server runs with CHMOD disabled), listFolders()
 *           (sub-directory browser), getWrapperStringFor(), isFirewalled(), and FTPS/TLS (the container speaks plain FTP).
 *           Engine path not covered: ranged downloads — rejected by design, which testRangedDownloadIsRejected() pins.
 *
 * @group integration
 * @group postproc
 * @group ftp
 */
class FtpTest extends AbstractFtpTestCase
{
	protected function getEngineSlug(): string
	{
		return 'ftp';
	}

	protected static function requiredExtensionSkipReason(): ?string
	{
		if (!function_exists('ftp_connect'))
		{
			return 'The PHP `ftp` extension is not installed, so the native FTP engine cannot be tested.';
		}

		return null;
	}

	protected static function serverAcceptsLogin(): bool
	{
		$connection = @ftp_connect(self::HOST, self::$controlPort, 5);

		if ($connection === false)
		{
			return false;
		}

		$ok = @ftp_login($connection, self::user(), self::password());

		@ftp_close($connection);

		return $ok;
	}

	protected function getRemoteSize(string $remotePath): ?int
	{
		$connection = $this->openConnection();

		if ($connection === null)
		{
			return null;
		}

		try
		{
			$size = @ftp_size($connection, $remotePath);
		}
		finally
		{
			@ftp_close($connection);
		}

		// ftp_size() returns -1 when the file does not exist (or the server cannot report a size).
		return $size < 0 ? null : $size;
	}

	protected function listRemoteDirectory(string $directory): array
	{
		$connection = $this->openConnection();

		if ($connection === null)
		{
			return [];
		}

		try
		{
			@ftp_pasv($connection, true);

			$list = @ftp_nlist($connection, $directory);
		}
		finally
		{
			@ftp_close($connection);
		}

		if (!is_array($list))
		{
			return [];
		}

		return array_map('basename', $list);
	}

	/**
	 * Open a fresh, authenticated ext/ftp connection to the test server, or null if it could not be established.
	 *
	 * @return  resource|null
	 */
	private function openConnection()
	{
		$connection = @ftp_connect(self::HOST, self::$controlPort, 10);

		if ($connection === false)
		{
			return null;
		}

		if (!@ftp_login($connection, self::user(), self::password()))
		{
			@ftp_close($connection);

			return null;
		}

		return $connection;
	}
}
