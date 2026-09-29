<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engine\Development\Command;

use Akeeba\Engine\Postproc\Connector\GoogleStorage as GoogleStorageConnector;
use Exception;
use RuntimeException;

class GoogleStorage extends \Akeeba\Engine\Development\Command\AbstractCommand
{
	public function doExecute(): void
	{
		// Get the connector
		$this->output->writeln("Getting the Google Storage connector");
		$connector = $this->getConnector();

		foreach ([256 * 1024, 1024 * 1024, 10 * 1024 * 1024] as $fileSize)
		{
			$this->output->writeln(sprintf('Testing with a %s file', $this->formatByteSize($fileSize)));
			$remotePath = $this->runUploadTest($connector, $fileSize);

			$this->output->writeln("Deleting uploaded file $remotePath");
			$connector->delete(ENGINE_DEV_GOOGLE_STORAGE_BUCKET, $remotePath, true);
		}
	}

	/**
	 * Uploads a test file of the given size
	 *
	 * @param   GoogleStorageConnector  $connector  The Google Storage connector we will use
	 * @param   int            $fileSize   The desired file size
	 *
	 * @return  string  The path to the uploaded file
	 *
	 * @throws  Exception
	 */
	private function runUploadTest(GoogleStorageConnector $connector, $fileSize)
	{
		$localFile  = $this->getTestFile($fileSize);
		$remoteFile = '/' . ltrim(ENGINE_DEV_GOOGLE_STORAGE_PATH . '/' . basename($localFile), '/');

		$this->output->writeln("Uploading $localFile, size $fileSize");

		$result = $connector->upload(ENGINE_DEV_GOOGLE_STORAGE_BUCKET, $remoteFile, $localFile);
		//$result = json_decode($result, true);

		if (!is_array($result) || empty($result))
		{
			throw new RuntimeException('Failed to upload');
		}

		$this->assertArrayContains([
			'kind'         => 'storage#object',
			'name'         => basename($localFile),
			'bucket'       => ENGINE_DEV_GOOGLE_STORAGE_BUCKET,
			"contentType"  => "application/octet-stream",
			"storageClass" => "STANDARD",
			'size'         => $fileSize,
			'md5Hash'      => base64_encode(hash_file('md5', $localFile, true)),
		], $result);

		// var_dump($result);

		return $remoteFile;
	}


	/**
	 * Get the Google Storage connector
	 *
	 * @return GoogleStorageConnector
	 */
	private function getConnector()
	{
		$jsonFile = __DIR__ . '/googlestorage.json';

		if (!file_exists($jsonFile))
		{
			throw new RuntimeException('You need to place googlestorage.json in the same folder as this script');
		}

		$json   = file_get_contents($jsonFile);
		$config = json_decode($json, true);

		if (empty($config))
		{
			throw new RuntimeException('Invalid googlestorage.json contents');
		}

		// Initialize the API connector
		return new GoogleStorageConnector($config['client_email'], $config['private_key']);
	}
}