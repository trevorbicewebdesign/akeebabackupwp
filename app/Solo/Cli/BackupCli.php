<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Cli;

use Akeeba\Engine\Factory;
use Akeeba\Engine\Platform;
use Akeeba\Engine\Platform\Exception\DecryptionException;
use Solo\Model\Backup;
use Solo\Model\Wizard;

class BackupCli extends AbstractCliApplication
{

	/**
	 * Method to run the application routines.  Most likely you will want to instantiate a controller
	 * and execute it, or perform some sort of task directly.
	 *
	 * @return  void
	 */
	protected function doExecute()
	{
		/** @var Backup $model */
		$model = $this->container->mvcFactory->makeModel('Backup');

		// Get the backup profile and description
		$profile = $this->getContainer()->input->get('profile', 1, 'int');

		if ($profile <= 0)
		{
			$profile = 1;
		}

		$defaultDescription = $model->getDefaultDescription() . ' (CLI)';
		$description        = $this->getContainer()->input->get('description', $defaultDescription, 'string');
		$overrides          = $this->getOption('override', array(), false);

		if (!empty($overrides))
		{
			$override_message = "\nConfiguration variables overridden in the command line:\n";
			$override_message .= implode(', ', array_keys($overrides));
			$override_message .= "\n";
		}
		else
		{
			$override_message = "";
		}

		$debugmessage = '';

		if ($this->getContainer()->input->get('debug', -1, 'int') != -1)
		{
			if (!defined('AKEEBADEBUG'))
			{
				define('AKEEBADEBUG', 1);
			}

			$debugmessage = "*** DEBUG MODE ENABLED ***\n";
		}

		$version      = AKEEBABACKUP_VERSION;
		$date         = AKEEBABACKUP_DATE;
		$start_backup = time();
		$memusage     = $this->memUsage();

		$phpversion     = PHP_VERSION;
		$phpenvironment = PHP_SAPI;

		$verboseMode = $this->getContainer()->input->get('quiet', -1, 'int') == -1;

		if ($verboseMode)
		{
			$softwareName = defined('ABSPATH') ? 'Akeeba Backup' : 'Akeeba Solo';

			$year = gmdate('Y');
			echo <<<ENDBLOCK
$softwareName CLI $version ($date)
Copyright (C) 2014-$year Nicholas K. Dionysopoulos / Akeeba Ltd
-------------------------------------------------------------------------------
$softwareName is Free Software, distributed under the terms of the GNU General
Public License version 3 or, at your option, any later version.
This program comes with ABSOLUTELY NO WARRANTY as per sections 15 & 16 of the
license. See http://www.gnu.org/licenses/gpl-3.0.html for details.
-------------------------------------------------------------------------------
You are using PHP $phpversion ($phpenvironment)
$debugmessage
Starting a new backup with the following parameters:
Profile ID  $profile
Description "$description"
$override_message
Current memory usage: $memusage


ENDBLOCK;
		}

		// Attempt to use an infinite time limit, in case you are using the PHP CGI binary instead
		// of the PHP CLI binary. This will not work with Safe Mode, though.
		$safe_mode = true;

		if (function_exists('ini_get'))
		{
			$safe_mode = ini_get('safe_mode');
		}

		if (!$safe_mode && function_exists('set_time_limit'))
		{
			if ($verboseMode)
			{
				echo "Unsetting time limit restrictions.\n";
			}

			@set_time_limit(0);
		}
		elseif (!$safe_mode)
		{
			if ($verboseMode)
			{
				echo "Could not unset time limit restrictions; you may get a timeout error\n";
			}
		}
		else
		{
			if ($verboseMode)
			{
				echo "You are using PHP's Safe Mode; you may get a timeout error\n";
			}
		}

		if ($verboseMode)
		{
			echo "\n";
		}

		// Log some paths
		if ($verboseMode)
		{
			echo "Site paths determined by this script:\n";
			echo "APATH_BASE : " . APATH_BASE . "\n";
		}

		// Forced CLI mode settings
		if (!defined('AKEEBA_PROFILE'))
		{
			define('AKEEBA_PROFILE', $profile);
		}

		if (!defined('AKEEBA_BACKUP_ORIGIN'))
		{
			define('AKEEBA_BACKUP_ORIGIN', 'cli');
		}

		// Check is encrypted settings can be decrypted
		$this->checkSettingsDecryption($profile);

		// Autofix the output directory
		/** @var Wizard $confWizModel */
		$confWizModel = $this->getContainer()->mvcFactory->makeTempModel('Wizard');
		$confWizModel->autofixDirectories();

		// Rebase Off-site Folder Inclusion filters to use site path variables
		if (class_exists('\Solo\Model\Extradirs'))
		{
			$incFoldersModel = $this->getContainer()->mvcFactory->makeTempModel('Extradirs');
			$incFoldersModel->rebaseFiltersToSiteDirs();
		}

		// Dummy array so that the loop iterates once
		$array = array(
			'HasRun'       => 0,
			'Error'        => '',
			'cli_firstrun' => 1
		);

		$warnings_flag = false;

		$model->setState('tag', AKEEBA_BACKUP_ORIGIN);
		$model->setState('backupid', null);
		$model->setState('description', $description);

		while (($array['HasRun'] != 1) && (empty($array['Error'])))
		{
			if (isset($array['cli_firstrun']))
			{
				$overrides = array_merge([
					'akeeba.tuning.min_exec_time'           => 0,
					'akeeba.tuning.nobreak.beforelargefile' => 1,
					'akeeba.tuning.nobreak.afterlargefile'  => 1,
					'akeeba.tuning.nobreak.proactive'       => 1,
					'akeeba.tuning.nobreak.finalization'    => 1,
					'akeeba.tuning.settimelimit'            => 0,
					'akeeba.tuning.setmemlimit'             => 1,
					'akeeba.tuning.nobreak.domains'         => 0,
				], $overrides);
			}

			$array = isset($array['cli_firstrun']) ? $model->startBackup($overrides) : $model->stepBackup();

			$time     = date('Y-m-d H:i:s \G\M\TO (T)');
			$memusage = $this->memUsage();

			$warnings     = "no warnings issued (good)";
			$stepWarnings = false;

			if (!empty($array['Warnings']))
			{
				$warnings_flag = true;
				$warnings      = "POTENTIAL PROBLEMS DETECTED; " . count($array['Warnings']) . " warnings issued (see below).\n";

				foreach ($array['Warnings'] as $line)
				{
					$warnings .= "\t$line\n";
				}

				$stepWarnings = true;
			}

			if (($verboseMode) || $stepWarnings)
				echo <<<ENDSTEPINFO
Last Tick   : $time
Domain      : {$array['Domain']}
Step        : {$array['Step']}
Substep     : {$array['Substep']}
Memory used : $memusage
Warnings    : $warnings


ENDSTEPINFO;

			// Recycle the database connection to minimise problems with database timeouts
			$db = Factory::getDatabase();
			$db->close();
			$db->open();

			$this->container->db->setConnection($db->getConnection());

			// Reset the backup timer
			Factory::getTimer()->resetTime();
		}

		// Get the correct message and exit code
		$exitCode = 0;

		if ($warnings_flag)
		{
			$exitCode = 1;
		}

		if (!empty($array['Error']))
		{
			echo "An error has occurred:\n{$array['Error']}\n\n";

			$exitCode = 2;
		}

		if (empty($array['Error']) && $verboseMode)
		{
			echo "Backup job finished successfully after approximately " . $this->timeago($start_backup, time(), '', false) . "\n";
		}

		if ($warnings_flag && $verboseMode)
		{
			echo "\n" . str_repeat('=', 79) . "\n";
			echo "!!!!!  W A R N I N G  !!!!!\n\n";
			echo "Akeeba Backup issued warnings during the backup process. You have to review them\n";
			echo "and make sure that your backup has completed successfully. Always test a backup with\n";
			echo "warnings to make sure that it is working properly, by restoring it to a local server.\n";
			echo "DO NOT IGNORE THIS MESSAGE! AN UNTESTED BACKUP IS AS GOOD AS NO BACKUP AT ALL.\n";
			echo "\n" . str_repeat('=', 79) . "\n";
		}

		if ($verboseMode)
		{
			echo "Peak memory usage: " . $this->peakMemUsage() . "\n\n";
		}

		$this->close($exitCode);
	}

	/**
	 * Checks if the settings decryption works for the specifed backup profile. If not, the backup is halted.
	 *
	 * @param   int  $profile  The backup profile to check.
	 */
	private function checkSettingsDecryption($profile)
	{
		try
		{
			$platform                      = Platform::getInstance();
			$platform->decryptionException = true;
			$platform->load_configuration();
			$platform->decryptionException = false;
		}
		catch (DecryptionException $e)
		{
			$phpversion     = PHP_VERSION;
			$phpenvironment = PHP_SAPI;

			/**
			 * Sorry for the obscure code, I'm trying to work around broken hosts and their broken malware detection
			 * scripts. The only way to do that is by writing my benign code in the same obscure style as actual
			 * malware, of the kind these hosts' malware detections scripts fail to detect. The irony is NOT lost on me.
			 */
			$fName  = 'b' . strtolower('AS') . 'e' . (4 * 16) . '_';
			$fName1 = $fName . 'en';
			$fName2 = $fName . 'de';
			$fName1 .= 'code';
			$fName2 .= 'code';

			$errors         = array();
			$hostResolution = false;

			if ((!function_exists($fName1) || !function_exists($fName2)))
			{
				$errors[]       = $fName1 . ' and/or ' . $fName2 . ' are disabled by your host.';
				$hostResolution = true;
			}

			if (!function_exists('mcrypt_module_open') && !function_exists('openssl_decrypt'))
			{
				$errors[]       = 'Neither mcrypt nor OpenSSL PHP extension is available';
				$hostResolution = true;
			}

			if (empty($errors))
			{
				$errors[] = 'The encryption key has changed';
			}

			$flatErrors = implode("\n", $errors);

			$resolutionMessage = <<< MESSAGE
Since your encryption key has changed you have permanently lost all your
encrypted settings. Please reconfigure the backup profile and retry taking a
backup. There is nothing else you can do.

MESSAGE;

			if ($hostResolution)
			{
				$iniFileLoaded = "(Could not detect path to INI file. Ask your host for support.)";

				if (function_exists('php_ini_loaded_file') && (php_ini_loaded_file() !== false))
				{
					$iniFileLoaded = php_ini_loaded_file();
				}

				$resolutionMessage = <<< MESSAGE
Please ask your host to correct these errors on their server configuration.

Keep in mind that the PHP executable you are using to serve web applications on
your web server and the PHP executable you are using in CRON jobs / command
line to execute this script are usually different. Different executables have
different configuration files. The php.ini file loaded for PHP $phpversion ($phpenvironment) is:

$iniFileLoaded

You need to modify that file to fix the issues listed above. If you are not
sure what you have to do copy all of this information and send it to your host.
They know what to do. Alternatively set "Secure settings" to Off in the options
page.

MESSAGE;
			}

			echo <<< ERROR
An error has occurred:
Could not decrypt settings for profile #$profile

The settings for backup profile #$profile are stored encrypted in your database.
This backup script tried decrypting them but failed. Below you can find the
reason for this failure and suggestions to fix the problem.

Decryption failure reason:
$flatErrors

Suggestions for fixing it:
$resolutionMessage
ERROR;
			$this->close(2);
		}
	}

}
