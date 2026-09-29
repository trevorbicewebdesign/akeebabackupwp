<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */
namespace Akeeba\Engine\Development\Command;

use Akeeba\Engine\Postproc\Connector\OneDriveBusiness as OneDriveBusinessConnector;
use ReflectionException;
use RuntimeException;

class OneDriveBusiness extends AbstractCommand
{
	public function doExecute(): void
	{
		$this->output->writeln("Getting the OneDrive connector");
		$connector = $this->getOneDriveConnector();

		$this->output->writeln("Ping...", false);
		$connector->ping();
		$this->output->writeln(" pong!", true);

		if (!defined('ENGINE_DEV_ONEDRIVE_BUSINESS_DRIVEID') || empty(ENGINE_DEV_ONEDRIVE_BUSINESS_DRIVEID))
		{
			$allDrives = $connector->getDrives();
			$allDriveIds = array_keys($allDrives);
			$driveId   = array_shift($allDriveIds);

			$this->output->writeln(sprintf('Using drive ID %s: %s', $driveId, $allDrives[$driveId]));
			$connector->setDriveId($driveId);
		}
		else
		{
			$connector->setDriveId(ENGINE_DEV_ONEDRIVE_BUSINESS_DRIVEID);
		}

		$fileSize      = 25 * 1024 * 1024;
		$localFile     = $this->getTestFile($fileSize);
		@clearstatcache($localFile);
		$fileSize      = @filesize($localFile);
		$remoteFile    = '/webbackup/akeeba_test/' . basename($localFile);
		$referenceSHA1 = hash_file('sha1', $localFile);

		$this->output->writeln(sprintf("Local file: %s", $localFile));

		$this->testUpload($connector, $localFile, $remoteFile, $fileSize);
		$this->testDownloadToServer($connector, $localFile, $remoteFile);

		$this->output->writeln(sprintf('Listing contents of %s', dirname($remoteFile)));
		$results = $connector->listContents(dirname($remoteFile));
		var_dump($results);

		$this->testSignedURL($connector, $remoteFile, $referenceSHA1);

		$this->output->writeln('Deleting remote file');
		$connector->delete($remoteFile);
	}

	/**
	 * Get the Azure connector
	 *
	 * @return OneDriveBusinessConnector
	 */
	private function getOneDriveConnector()
	{
		return new OneDriveBusinessConnector(ENGINE_DEV_ONEDRIVE_BUSINESS_TOKEN, ENGINE_DEV_ONEDRIVE_BUSINESS_REFRESH, ENGINE_DEV_DLID);
	}

	/**
	 * @param   OneDriveBusinessConnector  $connector
	 * @param   string                     $containerName
	 * @param   string                     $localFile
	 * @param   string                     $remoteFile
	 * @param   int                        $fileSize
	 *
	 * @throws ReflectionException
	 */
	private function testUpload(OneDriveBusinessConnector $connector, $localFile, $remoteFile, $fileSize)
	{
		// Single file upload
		$this->output->writeln(sprintf("Uploading %s to %s", $localFile, $remoteFile));
		$this->output->writeln(sprintf("File size: %s bytes", $fileSize));

		$uploadInfo = $connector->upload($remoteFile, $localFile);
		$this->assertArrayContains([
			'name' => basename($remoteFile),
			'size' => $fileSize,
		], $uploadInfo);
	}

	/**
	 * @param   OneDriveBusinessConnector  $connector
	 * @param   string                     $localFile
	 * @param   string                     $remoteFile
	 */
	private function testDownloadToServer(OneDriveBusinessConnector $connector, $localFile, $remoteFile)
	{
		$this->output->writeln('Downloading and verifying');
		$tempName = $this->getTempFileName();
		$connector->download($remoteFile, $tempName);

		$this->assertFilesEquals($localFile, $tempName);

		@unlink($localFile);
	}

	/**
	 * @param   OneDriveBusinessConnector  $connector
	 * @param   string                     $remoteFile
	 * @param                              $http_response_header
	 * @param                              $referenceSHA1
	 *
	 * @return void
	 */
	private function testSignedURL(OneDriveBusinessConnector $connector, string $remoteFile, $referenceSHA1): void
	{
		$this->output->writeln('Signed URL:');
		$remoteURL = $connector->getSignedURL($remoteFile);
		$this->output->writeln($remoteURL);
		$this->output->writeln('Downloading from signed URL');

		$context = stream_context_create(
			[
				'http' => [
					'user_agent'       => "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:67.0) Gecko/20100101 Firefox/67.0",
					'follow_location'  => 1,
					'protocol_version' => '1.1',
					'timeout'          => 10.0,
				],
			]
		);

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
	}
}