<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engine\Development\Command;

use Akeeba\Engine\Postproc\Connector\Ovh as OvhConnector;

class OVH extends \Akeeba\Engine\Development\Command\AbstractCommand
{
	public function doExecute(): void
	{
		// Get the B2 connector
		$this->output->writeln("Getting the OVH connector");
		$ovh = $this->getConnector();

		// Login
		$this->output->writeln('Login');
		$token = $ovh->getToken(true);

		$this->output->writeln(sprintf("Got token: %s", $token));

		// Test file, 256KB
		$localFile  = $this->getTestFile(256 * 1024);
		$remoteFile = ltrim(trim(ENGINE_DEV_OVH_DIRECTORY, '/') . '/' . basename($localFile), '/');
		$fileSize   = filesize($localFile);

		// Single file upload
		$this->output->writeln(sprintf("Uploading %s to %s", $localFile, $remoteFile));
		$this->output->writeln(sprintf("File size: %s bytes", $fileSize));

		$ovh->putObject(['file' => $localFile], $remoteFile);

		// Download the already uploaded file
		$tempFileName = $this->getTempFileName();
		$this->output->writeln(sprintf("Downloading %s to %s", $remoteFile, $tempFileName));
		$fp = fopen($tempFileName, 'w');

		$ovh->downloadObject($remoteFile, $fp);
		fclose($fp);

		$this->assertFilesEquals($localFile, $tempFileName);

		// Delete the uploaded file
		$this->output->writeln(sprintf('Deleting uploaded file %s', $remoteFile));
		$ovh->deleteObject($remoteFile);
	}

	/**
	 * @return OvhConnector
	 */
	private function getConnector()
	{
		// Initialize the API connector; try to use the stored API information to save some time
		$ovh = new OvhConnector(ENGINE_DEV_OVH_PROJECTID, ENGINE_DEV_OVH_USERNAME, ENGINE_DEV_OVH_PASSWORD);
		$ovh->setStorageEndpoint(ENGINE_DEV_OVH_CONTAINERURL);

		return $ovh;
	}

}