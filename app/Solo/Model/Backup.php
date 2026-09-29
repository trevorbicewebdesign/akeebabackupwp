<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Model;

use Akeeba\Engine\Base\Part;
use Akeeba\Engine\Factory;
use Akeeba\Engine\Platform;
use Akeeba\Engine\Psr\Log\LogLevel;
use Akeeba\Engine\Util\PushMessages;
use Awf\Application\Application;
use Awf\Mvc\Model;
use Awf\Text\Text;
use Exception;

class Backup extends Model
{
	/**
	 * Starts or step a backup process. Set the state variable "ajax" to the task you want to execute OR call the
	 * relevant public method directly.
	 *
	 * @return  array  An Akeeba Engine return array
	 */
	public function runBackup()
	{
		$ret_array = [];

		$ajaxTask = $this->getState('ajax');

		switch ($ajaxTask)
		{
			// Start a new backup
			case 'start':
				$ret_array = $this->startBackup();
				break;

			// Step through a backup
			case 'step':
				$ret_array = $this->stepBackup();
				break;

			// Send a push notification for backup failure
			case 'pushFail':
				$this->pushFail();
				break;

			default:
				break;
		}

		return $ret_array;
	}

	/**
	 * Starts a new backup.
	 *
	 * State variables expected
	 * backupid     The ID of the backup. If none is set up we will create a new one in the form id123
	 * tag          The backup tag, e.g. "frontend". If none is set up we'll get it through the Platform.
	 * description  The description of the backup (optional)
	 * comment      The comment of the backup (optional)
	 * jpskey       JPS password
	 * angiekey     ANGIE password
	 *
	 * @param   array  $overrides  Configuration overrides
	 *
	 * @return  array  An Akeeba Engine return array
	 */
	public function startBackup(array $overrides = [])
	{
		/**
		 * Make sure the database schema is OK. Absolutely necessary in case the update is installed but the application
		 * has never been visited. Practical examples: WordPress automatic updates, user updating Solo/ABWP through FTP
		 * and in all cases only ever running scheduled and / or remove backups.
		 */
		try
		{
			/** @var Main $mainModel */
			$mainModel = $this->container->mvcFactory->makeModel('Main');
			$mainModel->checkAndFixDatabase();
		}
		catch (Exception $e)
		{
			// Hopefully the backup dies in an informative way, thank you very much.
		}

		// Get information from the session
		$tag         = $this->getState('tag', null, 'string');
		$description = $this->getState('description', '', 'string');
		$comment     = $this->getState('comment', '', 'html');
		$jpskey      = $this->getState('jpskey', null, 'raw');
		$angiekey    = $this->getState('angiekey', null, 'raw');
		$backupId    = $this->getBackupId();

		// Use the default description if none specified
		$description = $description ?: $this->getDefaultDescription();

		// Try resetting the engine
		try
		{
			Factory::resetState([
				'maxrun' => 0,
			]);
		}
		catch (Exception $e)
		{
			// This will fail if the output directory is unwriteable / unreadable / missing.
		}

		// Remove any stale memory files left over from the previous step
		if (empty($tag))
		{
			$tag = Platform::getInstance()->get_backup_origin();
		}

		$tempVarsTag = $tag;
		$tempVarsTag .= empty($backupId) ? '' : ('.' . $backupId);

		Factory::getFactoryStorage()->reset($tempVarsTag);
		Factory::nuke();
		Factory::getLog()->log(LogLevel::DEBUG, " -- Resetting Akeeba Engine factory ($tag.$backupId)");
		Platform::getInstance()->load_configuration();

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

		// Should I apply any configuration overrides?
		if (is_array($overrides) && !empty($overrides))
		{
			$config        = Factory::getConfiguration();
			$protectedKeys = $config->getProtectedKeys();
			$config->resetProtectedKeys();

			foreach ($overrides as $k => $v)
			{
				$config->set($k, $v);
			}

			$config->setProtectedKeys($protectedKeys);
		}

		Platform::getInstance()->apply_quirk_definitions();

		// Check if there are critical issues preventing the backup
		if (!Factory::getConfigurationChecks()->getShortStatus())
		{
			$configChecks = Factory::getConfigurationChecks()->getDetailedStatus();

			foreach ($configChecks as $checkItem)
			{
				if ($checkItem['severity'] != 'critical')
				{
					continue;
				}

				return [
					'HasRun'   => 0,
					'Domain'   => 'init',
					'Step'     => '',
					'Substep'  => '',
					'Error'    => 'Failed configuration check Q' . $checkItem['code'] . ': ' . $checkItem['description'] . '. Please refer to https://www.akeeba.com/documentation/warnings/q' . $checkItem['code'] . '.html for more information and troubleshooting instructions.',
					'Warnings' => [],
					'Progress' => 0,
				];
			}
		}

		// Set up Kettenrad
		$options = [
			'description' => $description,
			'comment'     => $comment,
			'jpskey'      => $jpskey,
			'angiekey'    => $angiekey,
		];

		if (is_null($jpskey))
		{
			unset ($options['jpskey']);
		}

		if (is_null($angiekey))
		{
			unset ($options['angiekey']);
		}

		$kettenrad = Factory::getKettenrad();
		$kettenrad->setBackupId($backupId);
		$kettenrad->setup($options);

		$this->setState('backupid', $backupId);

		/**
		 * Convert log files in the backup output directory
		 *
		 * This removes the obsolete, default log files (akeeba.(backend|frontend|cli|json).log and converts the old .log
		 * files into their .php counterparts.
		 *
		 * We are doing this when taking a new backup on top of the Control Panel page because some people might be
		 * installing updates and taking backups automatically, without visiting the Control Panel except in rare cases.
		 */
		/** @var Main $cpModel */
		$cpModel = $this->container->mvcFactory->makeTempModel('Main');
		$cpModel->convertLogFiles(3);


		// Run the first backup step. We need to run tick() twice
		/**
		 * We need to run tick() twice in the first backup step.
		 *
		 * The first tick() will reset the backup engine and start a new backup. However, no backup record is created
		 * at this point. This means that Factory::loadState() cannot find a backup record, therefore it cannot read
		 * the backup profile being used, therefore it will assume it's profile #1.
		 *
		 * The second tick() creates the backup record without doing much else, fixing this issue.
		 *
		 * However, if you have conservative settings where the min exec time is MORE than the max exec time the second
		 * tick would never run. Therefore we need to tell the first tick to ignore the time settings (since it only
		 * takes a few milliseconds to execute anyway) and then apply the time settings on the second tick (which also
		 * only takes a few milliseconds). This is why we have setIgnoreMinimumExecutionTime before and after the first
		 * tick. DO NOT REMOVE THESE.
		 *
		 * Furthermore, if the first tick reaches the end of backup or an error condition we MUST NOT run the second
		 * tick() since the engine state will be invalid. Hence the check for the state that performs a hard break. This
		 * could happen if you have a sufficiently high max execution time, no break between steps and we fail to
		 * execute any step, e.g. the installer image is missing, a database error occurred or we can not list the files
		 * and directories to back up.
		 *
		 * THEREFORE, DO NOT REMOVE THE LOOP OR THE if-BLOCK IN IT, THEY ARE THERE FOR A GOOD REASON!
		 */
		$kettenrad->setIgnoreMinimumExecutionTime(true);

		for ($i = 0; $i < 2; $i++)
		{
			$kettenrad->tick();

			if (in_array($kettenrad->getState(), [Part::STATE_FINISHED, Part::STATE_ERROR]))
			{
				break;
			}

			$kettenrad->setIgnoreMinimumExecutionTime(false);
		}

		$ret_array = $kettenrad->getStatusArray();

		try
		{
			Factory::saveState($tag, $backupId);
		}
		catch (\RuntimeException $e)
		{
			$ret_array['Error'] = $e->getMessage();
		}

		return $ret_array;
	}

	/**
	 * Steps through a backup.
	 *
	 * State variables expected (MUST be set):
	 * backupid        The ID of the backup.
	 * tag            The backup tag, e.g. "frontend".
	 * profile      (optional) The profile ID of the backup.
	 *
	 * @param   bool  $requireBackupId  Should the backup ID be required?
	 *
	 * @return  array  An Akeeba Engine return array
	 */
	public function stepBackup($requireBackupId = true)
	{
		// Get information from the model state
		$tag      = $this->getState('tag', defined('AKEEBA_BACKUP_ORIGIN') ? AKEEBA_BACKUP_ORIGIN : null, 'string');
		$backupId = $this->getState('backupid', null, 'string');

		// Get the profile from the session, the AKEEBA_PROFILE constant or the model state – in this order
		$profile = max(0, (int) $this->getState('profile', 0)) ?: $this->getLastBackupProfile($tag, $backupId);

		// Set the active profile
		$session = $this->getContainer()->segment;
		$session->set('profile', $profile);

		if (!defined('AKEEBA_PROFILE'))
		{
			define('AKEEBA_PROFILE', $profile);
		}

		// Run a backup step
		$ret_array = [
			'HasRun'   => 0,
			'Domain'   => 'init',
			'Step'     => '',
			'Substep'  => '',
			'Error'    => '',
			'Warnings' => [],
			'Progress' => 0,
		];

		try
		{
			// Reload the configuration
			Platform::getInstance()->load_configuration($profile);

			// Load the engine from storage
			Factory::loadState($tag, $backupId, $requireBackupId);

			// Set the backup ID and run a backup step
			$kettenrad = Factory::getKettenrad();
			$kettenrad->tick();
			$ret_array = $kettenrad->getStatusArray();
		}
		catch (\Exception $e)
		{
			$ret_array['Error'] = $e->getMessage();
		}

		try
		{
			if (empty($ret_array['Error']) && ($ret_array['HasRun'] != 1))
			{
				Factory::saveState($tag, $backupId);
			}
		}
		catch (\RuntimeException $e)
		{
			$ret_array['Error'] = $e->getMessage();
		}

		if (!empty($ret_array['Error']) || ($ret_array['HasRun'] == 1))
		{
			/**
			 * Do not nuke the Factory if we're trying to resume after an error.
			 *
			 * When the resume after error (retry) feature is enabled AND we are performing a backend backup we MUST
			 * leave the factory storage intact so we can actually resume the backup. If we were to nuke the Factory
			 * the resume would report that it cannot load the saved factory and lead to a failed backup.
			 */
			$config = Factory::getConfiguration();
			$origin = Platform::getInstance()->get_backup_origin();

			if (($origin == 'backend') && $config->get('akeeba.advanced.autoresume', 1))
			{
				// We are about to resume; abort.
				return $ret_array;
			}

			// Clean up
			Factory::nuke();

			$tempVarsTag = $tag;
			$tempVarsTag .= empty($backupId) ? '' : ('.' . $backupId);

			Factory::getFactoryStorage()->reset($tempVarsTag);
		}

		return $ret_array;
	}

	/**
	 * Send a push notification for a failed backup
	 *
	 * State variables expected (MUST be set):
	 * errorMessage  The error message
	 *
	 * @return  void
	 */
	public function pushFail()
	{
		$errorMessage = $this->getState('errorMessage');

		$platform = Platform::getInstance();
		$key      = 'COM_AKEEBA_PUSH_ENDBACKUP_FAIL_BODY_WITH_MESSAGE';

		if (empty($errorMessage))
		{
			$key = 'COM_AKEEBA_PUSH_ENDBACKUP_FAIL_BODY';
		}

		$pushSubject = sprintf(
			$platform->translate('COM_AKEEBA_PUSH_ENDBACKUP_FAIL_SUBJECT'),
			$platform->get_site_name(),
			$platform->get_host()
		);
		$pushDetails = sprintf(
			$platform->translate($key),
			$platform->get_site_name(),
			$platform->get_host(),
			$errorMessage
		);

		$push = new PushMessages();
		$push->message($pushSubject, $pushDetails);
	}

	public function getDefaultDescription()
	{
		return Text::_('COM_AKEEBA_BACKUP_DEFAULT_DESCRIPTION') . ' ' .
			Platform::getInstance()->get_local_timestamp(Text::_('DATE_FORMAT_LC2') . ' T');
	}

	/**
	 * Get the profile used to take the last backup for the specified tag
	 *
	 * @param   string  $tag       The backup tag a.k.a. backup origin (backend, frontend, json, ...)
	 * @param   string  $backupId  (optional) The Backup ID
	 *
	 * @return  int  The profile ID of the latest backup taken with the specified tag / backup ID
	 */
	public function getLastBackupProfile($tag, $backupId = null)
	{
		$filters = [
			['field' => 'tag', 'value' => $tag],
		];

		if (!empty($backupId))
		{
			$filters[] = ['field' => 'backupid', 'value' => $backupId];
		}

		$statList = Platform::getInstance()->get_statistics_list([
				'filters' => $filters,
				'order'   => [
					'by' => 'id', 'order' => 'DESC',
				],
			]
		);

		if (is_array($statList))
		{
			$stat = array_pop($statList);

			return (int) $stat['profile_id'];
		}

		// Backup entry not found. If backupId was specified, try without a backup ID
		if (!empty($backupId))
		{
			return $this->getLastBackupProfile($tag);
		}

		// Else, return the default backup profile
		return 1;
	}

	/**
	 * Get a new backup ID string.
	 *
	 * In the past we were trying to get the next backup record ID using two methods:
	 * - Querying the information_schema.tables metadata table. In many cases we saw this returning the wrong value,
	 *   even though the MySQL documentation said this should return the next autonumber (WTF?)
	 * - Doing a MAX(id) on the table and adding 1. This didn't work correctly if the latest records were deleted by the
	 *   user.
	 *
	 * However, the backup ID does not need to be the same as the backup record ID. It only needs to be *unique*. So
	 * this time around we are using a simple, unique ID based on the current GMT date and time.
	 *
	 * @return  string
	 */
	private function getBackupId(): string
	{
		$microtime    = explode(' ', microtime(false));
		$microseconds = (int) ($microtime[0] * 1000000);

		return 'id-' . gmdate('Ymd-His') . '-' . $microseconds;
	}
} 
