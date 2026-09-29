<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engine\Development\Command;

use Akeeba\Engine\Postproc\Connector\Backblaze as BackBlazeConnector;

/**
 * Development helper for the BackBlaze B2 connector
 */
class BackBlaze extends \Akeeba\Engine\Development\Command\AbstractCommand
{
	public function doExecute(): void
	{
		// Get the B2 connector
		$this->output->writeln("Getting the B2 connector");
		$b2 = $this->getB2Connector();

		// Test file, 256KB
		$localFile  = $this->getTestFile(256 * 1024);
		$remoteFile = 'test/' . basename($localFile);
		$fileSize   = filesize($localFile);

		// Single file upload
		$this->output->writeln(sprintf("Uploading %s to %s", $localFile, $remoteFile));
		$this->output->writeln(sprintf("File size: %s bytes", $fileSize));

		$bucketId   = $b2->getBucketId(ENGINE_DEV_BACKBLAZE_BUCKET);
		$uploadInfo = $b2->uploadFile($bucketId, $remoteFile, $localFile);

		$this->assertObjectContains([
			'fileName'      => $remoteFile,
			'bucketId'      => $bucketId,
			'contentLength' => $fileSize,
			'contentSha1'   => hash_file('sha1', $localFile),
			'contentType'   => "application/octet-stream",
		], $uploadInfo);

		// Download the already uploaded file
		$this->output->writeln(sprintf("Downloading %s to %s", $remoteFile, $localFile));
		$tempFileName = $this->getTempFileName();
		$b2->downloadFile(ENGINE_DEV_BACKBLAZE_BUCKET, $remoteFile, $tempFileName);
		$this->assertFilesEquals($localFile, $tempFileName);

		// Delete the uploaded file
		$this->output->writeln(sprintf('Deleting uploaded file %s', $remoteFile));
		$b2->deleteByFileName($bucketId, $remoteFile);
	}

	/**
	 * @return BackBlazeConnector
	 */
	private function getB2Connector(): BackBlazeConnector
	{
		// Initialize the API connector; try to use the stored API information to save some time
		$b2         = new BackBlazeConnector(ENGINE_DEV_BACKBLAZE_ID, ENGINE_DEV_BACKBLAZE_KEY);
		$serialized = file_get_contents(__DIR__ . '/backblaze_info.dat');

		if ($serialized)
		{
			$b2->setAccountInformation(unserialize($serialized));
		}

		$accountInformation = $b2->getAccountInformation();
		file_put_contents(__DIR__ . '/backblaze_info.dat', serialize($accountInformation));

		return $b2;
	}

}