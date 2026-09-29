<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Cli;

class CheckFailedUploadCli extends AbstractCliApplication
{

	/**
	 * Method to run the application routines.  Most likely you will want to instantiate a controller
	 * and execute it, or perform some sort of task directly.
	 *
	 * @return  void
	 */
	protected function doExecute()
	{
		$debugmessage = '';

		if ($this->getContainer()->input->get('debug', -1, 'int') != -1)
		{
			if (!defined('AKEEBADEBUG'))
			{
				define('AKEEBADEBUG', 1);
			}

			$debugmessage = "*** DEBUG MODE ENABLED ***\n";
		}

		$version		 = AKEEBABACKUP_VERSION;
		$date			 = AKEEBABACKUP_DATE;
		$start_backup	 = time();

		$phpversion		 = PHP_VERSION;
		$phpenvironment	 = PHP_SAPI;

		if ($this->getContainer()->input->get('quiet', -1, 'int') == -1)
		{
			$softwareName = defined('ABSPATH') ? 'Akeeba Backup' : 'Akeeba Solo';
			$year = gmdate('Y');
			echo <<<ENDBLOCK
$softwareName Check failed upload CLI $version ($date)
Copyright (C) 2014-$year Nicholas K. Dionysopoulos / Akeeba Ltd
-------------------------------------------------------------------------------
$softwareName is Free Software, distributed under the terms of the GNU General
Public License version 3 or, at your option, any later version.
This program comes with ABSOLUTELY NO WARRANTY as per sections 15 & 16 of the
license. See http://www.gnu.org/licenses/gpl-3.0.html for details.
-------------------------------------------------------------------------------
You are using PHP $phpversion ($phpenvironment)
$debugmessage
Checking for failed uploads
\n
ENDBLOCK;
		}

		// Forced CLI mode settings
		if (!defined('AKEEBA_BACKUP_ORIGIN'))
		{
			define('AKEEBA_BACKUP_ORIGIN', 'cli');
		}

		/** @var \Solo\Model\Main $cpanelModel */
		$cpanelModel = $this->container->mvcFactory->makeModel('Main');
		$result = $cpanelModel->notifyFailedUploads();

		echo implode("\n", $result['message']);

		$this->close();
	}

}
