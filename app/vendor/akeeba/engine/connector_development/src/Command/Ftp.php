<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
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

namespace Akeeba\Engine\Development\Command;

use Akeeba\Engine\Util\Transfer\Ftp as FtpTransfer;

class Ftp extends AbstractCommand
{
	protected function doExecute(): void
	{
		$this->output->writeln("Getting the FTP connector");
		$ftp = $this->getFtpConnector();

		$fileSize = 64*1024;
		$this->output->writeln(sprintf('Testing with a %s file', $this->formatByteSize($fileSize)));

		$localFile  = $this->getTestFile($fileSize);
		$remoteFile = '/' . ltrim(ENGINE_DEV_FTP_DIRECTORY . '/' . basename($localFile), '/');

		$this->output->writeln("Uploading $localFile, size $fileSize");

		$ftp->upload($localFile, $remoteFile);

		$this->output->writeln("Downloading $remoteFile to $localFile");

		$ftp->download($remoteFile, $localFile);

		$this->output->writeln("Deleting $remoteFile");

		$ftp->delete($remoteFile);
	}

	private function getFtpConnector(): FtpTransfer
	{
		return new FtpTransfer(
			[
				'host'      => ENGINE_DEV_FTP_SERVER,
				'port'      => ENGINE_DEV_FTP_PORT,
				'username'  => ENGINE_DEV_FTP_USERNAME,
				'password'  => ENGINE_DEV_FTP_PASSWORD,
				'directory' => ENGINE_DEV_FTP_INITIAL,
				'ssl'       => ENGINE_DEV_FTP_SSL,
				'passive'   => ENGINE_DEV_FTP_PASV,
				'timeout'   => ENGINE_DEV_FTP_TIMEOUT,
			]
		);
	}
}