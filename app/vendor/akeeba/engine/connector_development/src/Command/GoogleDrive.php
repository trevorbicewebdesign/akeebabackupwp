<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engine\Development\Command;

use Akeeba\Engine\Postproc\Connector\GoogleDrive as GoogleDriveConnector;
use RuntimeException;

class GoogleDrive extends \Akeeba\Engine\Development\Command\AbstractCommand
{
	public function doExecute(): void
	{
		// Get the connector
		$this->output->writeln("Getting the Google Drive connector");
		$connector = $this->getConnector();

		$connector->ping();

		foreach ([64 * 1024, 256 * 1024, 1024 * 1024, 10 * 1024 * 1024] as $fileSize)
		{
			$this->output->writeln(sprintf('Testing with a %s file', $this->formatByteSize($fileSize)));
			[$remoteFile, $fileId] = $this->runUploadTest($connector, $fileSize);

			$this->output->writeln("Deleting uploaded file $remoteFile");
			$connector->delete($fileId, true);
		}
	}

	/**
	 * Uploads a test file of the given size
	 *
	 * @param   GoogleDriveConnector  $connector  The Google Storage connector we will use
	 * @param   int                   $fileSize   The desired file size
	 *
	 * @return  array
	 *
	 * @throws  \Exception
	 */
	private function runUploadTest(GoogleDriveConnector $connector, $fileSize): array
	{
		$localFile  = $this->getTestFile($fileSize);
		$remoteFile = '/' . ltrim(ENGINE_DEV_GDRIVE_FOLDER . '/' . basename($localFile), '/');

		$this->output->writeln("Uploading $localFile, size $fileSize");

		$result = $connector->upload($remoteFile, $localFile);

		if (!is_array($result) || empty($result))
		{
			throw new RuntimeException('Failed to upload');
		}

		$this->assertArrayContains(
			[
				'kind'     => 'drive#file',
				'name'     => basename($localFile),
				"mimeType" => "application/octet-stream",
			], $result
		);

		return [$remoteFile, $result['id']];
	}


	/**
	 * Get the Google Storage connector
	 *
	 * @return GoogleDriveConnector
	 */
	private function getConnector()
	{
		// Initialize the API connector
		return new GoogleDriveConnector(
			ENGINE_DEV_GDRIVE_ACCESS_TOKEN, ENGINE_DEV_GDRIVE_REFRESH_TOKEN, ENGINE_DEV_DLID
		);
	}
}
