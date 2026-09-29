<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engine\Development\Command;

use Akeeba\Engine\Postproc\Connector\AzureModern\Connector as AzureConnector;
use Akeeba\Engine\Postproc\Connector\AzureModern\Exception\ApiException;
use ReflectionException;
use RuntimeException;

/**
 * Development helper for the Azure connector
 */
class AzureModern extends AbstractCommand
{
	/**
	 * Run the development test loop
	 *
	 * @throws ApiException
	 */
	public function doExecute(): void
	{
		// Get the B2 connector
		$this->output->writeln("Getting the Azure connector");
		$connector     = $this->getAzureConnector();
		$containerName = ENGINE_DEV_AZURE_BLOB_CONTAINER;

		$fileSize      = 14 * 1024 * 1024;
		$localFile     = $this->getTestFile($fileSize);
		$remoteFile    = 'test/' . basename($localFile);
		$referenceSHA1 = hash_file('sha1', $localFile);

		$this->output->writeln(sprintf("Local file: %s", $localFile));

		$this->testMultipartUpload($connector, $containerName, $localFile, $remoteFile, $fileSize);
		$this->testDownloadToServer($connector, $containerName, $localFile, $remoteFile);

		$this->output->writeln('Signed URL:');
		$remoteURL = $connector->getSignedURL($containerName, $remoteFile, 600);
		$this->output->writeln($remoteURL);

		$context = stream_context_create([
			'http' => [
				'user_agent'       => "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:67.0) Gecko/20100101 Firefox/67.0",
				'follow_location'  => 1,
				'protocol_version' => '1.1',
				'timeout'          => 10.0,
			],
		]);

		$contents = @file_get_contents($remoteURL, false, $context);
		[, $httpCode,] = explode(" ", $http_response_header[0], 3);

		if ($httpCode != 200)
		{
			$reason = "UNKNOWN";

			foreach ($http_response_header as $line)
			{
				if (strpos($line, ':') == false)
				{
					continue;
				}

				[$key, $value] = explode(':', $line, 2);

				if ($key != 'x-ms-error-code')
				{
					continue;
				}

				$reason = trim($value);

				break;
			}

			throw new RuntimeException(sprintf('Invalid HTTP code %u. Reason: %s', $httpCode, $reason));
		}

		$sha1 = sha1($contents);
		unset($contents);

		if ($sha1 != $referenceSHA1)
		{
			throw new RuntimeException('Signed download URL content does not match uploaded file contents');
		}

		$this->output->writeln('Deleting BLOB');
		$connector->deleteBlob($containerName, $remoteFile);
	}

	/**
	 * Get the Azure connector
	 *
	 * @return AzureConnector
	 */
	private function getAzureConnector()
	{
		$account   = ENGINE_DEV_AZURE_BLOB_ACCOUNT;
		$key       = ENGINE_DEV_AZURE_BLOB_KEY;
		$connector = new AzureConnector($account, $key, false, true);

		return $connector;
	}

	/**
	 * @param   AzureConnector  $connector
	 * @param   string          $containerName
	 * @param   string          $localFile
	 * @param   string          $remoteFile
	 * @param   int             $fileSize
	 *
	 *
	 * @throws ApiException
	 * @throws ReflectionException
	 */
	private function testUpload(AzureConnector $connector, $containerName, $localFile, $remoteFile, $fileSize)
	{
		// Single file upload
		$this->output->writeln(sprintf("Uploading %s to %s", $localFile, $remoteFile));
		$this->output->writeln(sprintf("File size: %s bytes", $fileSize));

		$uploadInfo   = $connector->putBlob($containerName, $remoteFile, $localFile);
		$intermediate = $this->objectToArray($uploadInfo);
		$this->assertArrayContains([
			'container' => $containerName,
			'name'      => $remoteFile,
			'size'      => $fileSize,
		], $intermediate['_data']);
	}

	/**
	 * @param   AzureConnector  $connector
	 * @param   string          $containerName
	 * @param   string          $localFile
	 * @param   string          $remoteFile
	 * @param   int             $fileSize
	 *
	 *
	 * @throws ApiException
	 * @throws ReflectionException
	 */
	private function testMultipartUpload(AzureConnector $connector, $containerName, $localFile, $remoteFile, $fileSize)
	{
		// Single file upload
		$this->output->writeln(sprintf("Uploading %s to %s", $localFile, $remoteFile));
		$this->output->writeln(sprintf("File size: %s bytes", $fileSize));

		$chunkSize = $connector->getBestBlockSize($localFile);
		$fp        = fopen($localFile, 'r');
		$blockIds  = [];
		$sequence  = 0;

		do
		{
			$chunk      = fread($fp, $chunkSize);
			$blockIds[] = $connector->putBlock($containerName, $remoteFile, $chunk);
			$size       = mb_strlen($chunk, '8bit');

			$this->output->writeln(
				sprintf(
					'Uploading chunk #%d (size: %d — chunk max size: %d',
					++$sequence,
					$size,
					$chunkSize
				)
			);

			if (feof($fp))
			{
				$this->output->writeln('Input file: EOF (good!)');
				break;
			}
		} while (true);

		fclose($fp);

		$this->output->writeln('Finalising upload');
		$connector->putBlockList($containerName, $remoteFile, $blockIds);
		$this->output->writeln('All good');
	}

	/**
	 * @param   AzureConnector  $connector
	 * @param   string          $containerName
	 * @param   string          $localFile
	 * @param   string          $remoteFile
	 */
	private function testDownloadToServer($connector, $containerName, $localFile, $remoteFile)
	{
		$this->output->writeln('Downloading and verifying (to file)');
		$tempName = $this->getTempFileName();
		$connector->getBlob($containerName, $remoteFile, $tempName);

		$this->assertFilesEquals($localFile, $tempName);

		@unlink($localFile);
	}

}