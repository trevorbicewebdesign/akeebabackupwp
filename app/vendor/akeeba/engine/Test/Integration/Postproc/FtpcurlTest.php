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
 * Integration test for the cURL-based FTP post-processing engine (engine/Postproc/Ftpcurl.php), run against the local,
 * ephemeral pure-ftpd container managed by AbstractFtpTestCase.
 *
 * On top of the opt-in + Docker gating in the base class, this test additionally self-skips when the PHP cURL extension
 * is not installed OR is built without FTP protocol support, since both the engine under test and this test's independent
 * verification need cURL to speak FTP.
 *
 * Connector coverage map (Akeeba\Engine\Util\Transfer\FtpCurl — the "connector" the cURL FTP engine uses; scoped to what
 * the engine exercises plus a couple of independent checks):
 *   COVERED (through the engine's processPart/downloadToFile/delete and this test's own verification): connect() (the
 *           cURL handle / login), upload() (STOR), download() (RETR), delete() (DELE via QUOTE), mkdir() (the configured
 *           sub-directory is created on the first upload via CURLOPT_FTP_CREATE_MISSING_DIRS), isDir(), and the engine's
 *           downloadToBrowser() URL builder.
 *   NOT COVERED here: write()/read() (in-memory string transfer — archiver path), copy(), move()/rename(), chmod() as a
 *           positive assertion (the server runs with CHMOD disabled), listFolders(), cwd(), the passive-IP/active-mode
 *           and verbose toggles, the ProxyAware proxy path, and FTPS/TLS (the container speaks plain FTP). Engine path
 *           not covered: ranged downloads — rejected by design, which testRangedDownloadIsRejected() pins down.
 *
 * @group integration
 * @group postproc
 * @group ftp
 */
class FtpcurlTest extends AbstractFtpTestCase
{
	protected function getEngineSlug(): string
	{
		return 'ftpcurl';
	}

	protected static function requiredExtensionSkipReason(): ?string
	{
		if (!function_exists('curl_version'))
		{
			return 'The PHP cURL extension is not installed, so the cURL FTP engine cannot be tested.';
		}

		$info = curl_version();

		if (!isset($info['protocols']) || !in_array('ftp', $info['protocols'], true))
		{
			return 'The PHP cURL extension was built without FTP protocol support, so the cURL FTP engine cannot be '
				. 'tested.';
		}

		return null;
	}

	protected static function serverAcceptsLogin(): bool
	{
		$ch = self::curlHandle('ftp://' . self::HOST . ':' . self::$controlPort . '/');
		curl_setopt($ch, CURLOPT_NOBODY, true);

		curl_exec($ch);
		$errNo = curl_errno($ch);
		self::closeCurl($ch);

		return $errNo === 0;
	}

	protected function getRemoteSize(string $remotePath): ?int
	{
		// A single leading slash makes the path relative to the login (chroot) directory, which is the FTP root here.
		$ch = self::curlHandle('ftp://' . self::HOST . ':' . self::$controlPort . $remotePath);
		curl_setopt($ch, CURLOPT_NOBODY, true);

		curl_exec($ch);
		$errNo = curl_errno($ch);
		$size  = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
		self::closeCurl($ch);

		if ($errNo !== 0 || $size < 0)
		{
			// errno 78 (CURLE_REMOTE_FILE_NOT_FOUND) is the expected result once the object has been deleted.
			return null;
		}

		return (int) $size;
	}

	protected function listRemoteDirectory(string $directory): array
	{
		$ch = self::curlHandle('ftp://' . self::HOST . ':' . self::$controlPort . rtrim($directory, '/') . '/');
		curl_setopt($ch, CURLOPT_DIRLISTONLY, true);

		$listing = curl_exec($ch);
		$errNo   = curl_errno($ch);
		self::closeCurl($ch);

		if ($errNo !== 0 || !is_string($listing))
		{
			return [];
		}

		$names = array_map('trim', preg_split('/\r\n|\r|\n/', $listing));

		return array_values(array_filter($names, static function ($name) {
			return $name !== '';
		}));
	}

	/**
	 * Build a cURL handle for an FTP URL, configured like the engine's own connector (credentials, port, passive-IP
	 * workaround) and returning the transfer body as a string.
	 *
	 * @param   string  $url  The ftp:// URL to operate on.
	 *
	 * @return  \CurlHandle|resource
	 */
	private static function curlHandle(string $url)
	{
		$ch = curl_init();

		curl_setopt_array($ch, [
			CURLOPT_URL            => $url,
			CURLOPT_USERPWD        => self::user() . ':' . self::password(),
			CURLOPT_PORT           => self::$controlPort,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER         => false,
			// Mirror the engine's passive-mode workaround: ignore the IP the server hands back for passive transfers.
			CURLOPT_FTP_SKIP_PASV_IP => true,
			CURLOPT_CONNECTTIMEOUT => 5,
			CURLOPT_TIMEOUT        => 30,
		]);

		return $ch;
	}

	/**
	 * Close a cURL handle, mirroring the engine's own guard: curl_close() is a deprecated no-op from PHP 8.0 onwards (and
	 * actively raises a deprecation notice on 8.5+), but it is still needed to free the handle on older PHP versions.
	 *
	 * @param   \CurlHandle|resource  $ch  The handle to close.
	 *
	 * @return  void
	 */
	private static function closeCurl($ch): void
	{
		if (version_compare(PHP_VERSION, '8.5.0', 'lt'))
		{
			curl_close($ch);
		}
	}
}
