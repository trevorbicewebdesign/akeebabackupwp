<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engine\Development\Command;

use Akeeba\Engine\Development\Shim\WebDAVShim;
use Akeeba\Engine\Postproc\Connector\Davclient;

class WebDAV extends \Akeeba\Engine\Development\Command\AbstractCommand
{
	public function doExecute(): void
	{
		$this->output->writeln("Getting the WebDAV connector");
		$dav = $this->getConnector();

		// Test file, 256KB
		$localFile  = $this->getTestFile(256 * 1024);
		$remoteFile = ltrim(trim(ENGINE_DEV_WEBDAV_DIRECTORY, '/') . '/' . basename($localFile), '/');
		$fileSize   = filesize($localFile);

		// Single file upload
		$this->output->writeln(sprintf("Uploading %s to %s", $localFile, $remoteFile));
		$this->output->writeln(sprintf("File size: %s bytes", $fileSize));

		$refClass = new \ReflectionClass($dav);
		$method   = $refClass->getMethod('putFile');

		if (version_compare(PHP_VERSION, '8.1.0', 'lt'))
		{
			$method->setAccessible(true);
		}

		$method->invokeArgs($dav, [$localFile, basename($remoteFile)]);

		// Download the already uploaded file
		$tempFileName = $this->getTempFileName();
		$this->output->writeln(sprintf("Downloading %s to %s", $remoteFile, $tempFileName));
		$dav->downloadToFile($remoteFile, $tempFileName);

		$this->assertFilesEquals($localFile, $tempFileName);

		// Delete the uploaded file
		$this->output->writeln(sprintf('Deleting uploaded file %s', $remoteFile));
		$dav->delete($remoteFile);
	}

	/**
	 * @return WebDAVShim
	 */
	private function getConnector(): WebDAVShim
	{
		return new WebDAVShim();
	}

}