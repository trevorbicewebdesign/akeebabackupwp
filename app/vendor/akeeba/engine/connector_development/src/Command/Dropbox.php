<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engine\Development\Command;

use Akeeba\Engine\Postproc\Connector\Dropbox2 as DropboxConnector;

/**
 * Development helper for the Dropbox connector with team drives support
 */
class Dropbox extends AbstractCommand
{
	public function doExecute(): void
	{
		// Get the B2 connector
		$this->output->writeln("Getting the Dropbox connector");
		$connector = $this->getDropboxConnector();

		$account = $connector->getCurrentAccount();
		$rootNS  = $account['root_info']['root_namespace_id'];

		$connector->setNamespaceId($rootNS);

		$rootContents = $connector->listContents();

		var_dump($rootContents);
	}


	/**
	 * Get the Dropbox connector
	 *
	 * @return DropboxConnector
	 */
	private function getDropboxConnector()
	{
		$connector = new DropboxConnector(ENGINE_DEV_DROPBOX_ACCESS_TOKEN, ENGINE_DEV_DROPBOX_REFRESH_TOKEN, ENGINE_DEV_DLID);

		return $connector;
	}

}