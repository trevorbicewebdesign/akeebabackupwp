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

namespace Akeeba\Engine\Test\Stub\Util\Transfer;

defined('AKEEBAENGINE') || die();

/**
 * An in-memory stand-in for the FTP/SFTP transfer objects used by the Direct* archivers.
 *
 * It implements just enough of the transfer surface that Directftp::upload() (and the helpers it calls) need:
 * getPath(), isDir(), mkdir(), upload() and chmod(). Every mutating call is recorded so tests can make assertions
 * about it.
 *
 * Crucially, it models POSIX directory permissions the way a real FTP server does: a directory whose mode lacks the
 * owner-execute bit (e.g. 0644) cannot have new entries created inside it. This is what makes it a faithful
 * reproduction of the "DirectFTP chmods the directory instead of the file" bug — uploading a file into a directory
 * that was chmod-ed to 0644 fails, exactly as it does against a live server.
 */
class RecordingFtpTransfer
{
	/** @var string The configured initial directory, mirrored from the real transfer classes. */
	public $initialDir = '/';

	/** @var array<string, int> Map of existing remote directory path => octal permissions. */
	public $directories = ['/' => 0755];

	/** @var string[] Remote paths of every file successfully "uploaded". */
	public $uploadedFiles = [];

	/** @var array<int, array{path: string, permissions: int}> Every chmod() call, in order. */
	public $chmodCalls = [];

	/** @var string[] Every directory path passed to mkdir(), in order. */
	public $mkdirCalls = [];

	/**
	 * Collapse duplicate slashes and strip a trailing slash so the in-memory bookkeeping matches regardless of how
	 * callers spell a path (e.g. "//installation" and "/installation" are the same directory to an FTP server).
	 */
	private function normalize($path)
	{
		$path = str_replace('\\', '/', (string) $path);
		$path = preg_replace('#/+#', '/', $path);

		if ($path !== '/')
		{
			$path = rtrim($path, '/');
		}

		return $path === '' ? '/' : $path;
	}

	/**
	 * Resolve a (possibly relative) path against the initial directory. Mirrors Ftp::getPath().
	 */
	public function getPath($fileName)
	{
		$fileName = str_replace('\\', '/', $fileName);

		if (strpos($fileName, $this->initialDir) === 0)
		{
			return $fileName;
		}

		$fileName = trim($fileName, '/');

		return rtrim($this->initialDir, '/') . '/' . $fileName;
	}

	/**
	 * A directory exists if we have created it (or it is the initial directory).
	 */
	public function isDir($path)
	{
		return isset($this->directories[$this->normalize($path)]);
	}

	/**
	 * Create a directory, recording the call and its mode.
	 */
	public function mkdir($dirName, $permissions = 0755)
	{
		$normalized                      = $this->normalize($dirName);
		$this->mkdirCalls[]              = $normalized;
		$this->directories[$normalized] = $permissions;

		return true;
	}

	/**
	 * "Upload" a file. Fails if the parent directory is not traversable (no owner-execute bit), just like a real
	 * server would refuse to create an entry inside a 0644 directory.
	 */
	public function upload($localFilename, $remoteFilename, $useExceptions = true)
	{
		$remote = $this->normalize($remoteFilename);
		$parent = $this->normalize(dirname($remote));
		$mode   = $this->directories[$parent] ?? 0755;

		// Owner-execute (0100) is required to create entries inside a directory.
		if (($mode & 0100) === 0)
		{
			return false;
		}

		$this->uploadedFiles[] = $remote;

		return true;
	}

	/**
	 * Change permissions. If the target happens to be a known directory, the new mode is applied to it — which is how
	 * the old bug bricked the directory by setting it to 0644.
	 */
	public function chmod($fileName, $permissions)
	{
		$normalized         = $this->normalize($fileName);
		$this->chmodCalls[] = ['path' => $normalized, 'permissions' => $permissions];

		if (isset($this->directories[$normalized]))
		{
			$this->directories[$normalized] = $permissions;
		}

		return true;
	}
}
