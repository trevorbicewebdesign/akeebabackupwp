<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\View\Backup;

use Akeeba\Engine\Factory;
use Akeeba\Engine\Platform;
use Awf\Date\Date;
use Awf\Mvc\Model;
use Awf\Mvc\View;
use Awf\Text\Text;
use Awf\Uri\Uri;
use Awf\Utils\Template;
use Solo\Helper\Status;
use Solo\Helper\Utils;
use Solo\Model\Backup;
use Solo\Model\Main;

/**
 * The view class for the Backup view
 */
class Html extends View
{
	public $defaultDescription;

	public $description;

	public $comment;

	public $returnURL;

	public $returnForm;

	public $profileId;

	public $profileName;

	public $hasANGIEPassword;

	public $autoStart;

	public $unwriteableOutput;

	public $hasErrors = false;

	/**
	 * Do we have warnings which may affect –but do not prevent– the backup from running?
	 *
	 * @var  bool
	 */
	public $hasWarnings = false;

	/**
	 * The HTML of the warnings cell
	 *
	 * @var  string
	 */
	public $warningsCell = '';

	public $hasCriticalErrors = false;

	public $subtitle;

	public $profileList;

	public $backupOnUpdate = false;

	/**
	 * Should I prompt the user to run the Configuration Wizard?
	 *
	 * @var  bool
	 */
	public $promptForConfigurationWizard = false;

	public function onBeforeMain()
	{
		// Load the necessary Javascript
		Template::addJs('media://js/solo/backup.js', $this->container->application);

		/** @var \Solo\Model\Backup $model */
		$model = $this->getModel();

		// Load the Status Helper
		$helper = Status::getInstance($this->container);

		// Determine default description
		$default_description = $this->getDefaultDescription();

		// Load data from the model state
		$backup_description = $model->getState('description', $default_description, 'string');
		$comment            = $model->getState('comment', '', 'html');
		$returnurl          = Utils::safeDecodeReturnUrl($model->getState('returnurl', ''));

		// Get the maximum execution time and bias
		$engineConfiguration = Factory::getConfiguration();
		$maxExecutionTime    = $engineConfiguration->get('akeeba.tuning.max_exec_time', 14) * 1000;
		$runtimeBias         = $engineConfiguration->get('akeeba.tuning.run_time_bias', 75);

		// Check if the output directory is writable
		$warnings         = Factory::getConfigurationChecks()->getDetailedStatus();
		$unwritableOutput = array_key_exists('001', $warnings);

		$this->hasErrors                    = !$helper->status;
		$this->hasWarnings                  = $helper->hasQuirks();
		$this->warningsCell                 = $helper->getQuirksCell(!$helper->status);
		$this->defaultDescription           = $default_description;
		$this->description                  = $backup_description;
		$this->comment                      = $comment;
		$this->returnURL                    = $returnurl;
		$this->unwriteableOutput            = $unwritableOutput;
		$this->autoStart                    = $model->getState('autostart', 0, 'boolean');
		$this->promptForConfigurationWizard = $engineConfiguration->get('akeeba.flag.confwiz', 0) == 0;
		$this->hasANGIEPassword = !empty(trim($engineConfiguration->get('engine.installer.angie.key', '')));

		// Push the return URL for POST redirects
		$this->returnForm = $model->getState('returnform', '');

		// Push the profile ID and name
		$this->profileId   = Platform::getInstance()->get_active_profile();
		$this->profileName = $this->escape(Platform::getInstance()->get_profile_name($this->profileId));

		// Should we display the notice about backup on update?
		$inCMS          = $this->container->segment->get('insideCMS', false);
		$backupOnUpdate = $this->input->getInt('backuponupdate', 0);

		if ($inCMS && $backupOnUpdate)
		{
			$this->backupOnUpdate = true;
		}

		// Set the toolbar title
		$this->subtitle = Text::_('COM_AKEEBA_BACKUP');

		// Push the list of profiles
		/** @var Main $cpanelModel */
		$cpanelModel       = $this->container->mvcFactory->makeModel('Main');
		$this->profileList = $cpanelModel->getProfileList();

		if (!$this->hasCriticalErrors)
		{
			$this->container->application->getDocument()->getMenu()->disableMenu('main');
		}

		// Push language strings to Javascript
		$doc = $this->container->application->getDocument();
		$doc->lang('COM_AKEEBA_BACKUP_TEXT_LASTRESPONSE');
		$doc->lang('COM_AKEEBA_BACKUP_TEXT_BACKUPSTARTED');
		$doc->lang('COM_AKEEBA_BACKUP_TEXT_BACKUPFINISHED');
		$doc->lang('COM_AKEEBA_BACKUP_TEXT_BACKUPHALT');
		$doc->lang('COM_AKEEBA_BACKUP_TEXT_BACKUPRESUME');
		$doc->lang('COM_AKEEBA_BACKUP_TEXT_BACKUPHALT_DESC');
		$doc->lang('COM_AKEEBA_BACKUP_TEXT_BACKUPFAILED');
		$doc->lang('COM_AKEEBA_BACKUP_TEXT_BACKUPWARNING');
		$doc->lang('COM_AKEEBA_BACKUP_TEXT_AVGWARNING');

		$document = $this->container->application->getDocument();
		$router   = $this->getContainer()->router;

		$hasDesktopNotifications = (bool) Platform::getInstance()->get_platform_configuration_option('desktop_notifications', '0');
		$autoResume              = $engineConfiguration->get('akeeba.advanced.autoresume', 1);
		$autoResumeTimeout       = $engineConfiguration->get('akeeba.advanced.autoresume_timeout', 10);
		$autoResumeRetries       = $engineConfiguration->get('akeeba.advanced.autoresume_maxretries', 3);
		
		$document->addScriptOptions('akeeba.Backup.defaultDescription', $this->defaultDescription);
		$document->addScriptOptions('akeeba.Backup.currentDescription', empty($this->description) ? $this->defaultDescription : $this->description);
		$document->addScriptOptions('akeeba.Backup.currentComment', $this->comment);
		$document->addScriptOptions('akeeba.Backup.hasAngieKey', $this->hasANGIEPassword);
		$document->addScriptOptions('akeeba.Backup.resume.enabled', (bool) $autoResume);
		$document->addScriptOptions('akeeba.Backup.resume.timeout', (int) $autoResumeTimeout);
		$document->addScriptOptions('akeeba.Backup.resume.maxRetries', (int) $autoResumeRetries);
		$document->addScriptOptions('akeeba.Backup.returnUrl', $this->returnURL);
		$document->addScriptOptions('akeeba.Backup.maxExecutionTime', (int) $maxExecutionTime);
		$document->addScriptOptions('akeeba.Backup.runtimeBias', (int) $runtimeBias);
		$document->addScriptOptions('akeeba.System.notification.iconURL', Uri::base(false, $this->getContainer()) . '/media/logo/' . $this->getContainer()->iconBaseName . '-96.png');
		$document->addScriptOptions('akeeba.Backup.domains', $this->getDomains());
		$document->addScriptOptions('akeeba.System.params.AjaxURL', $router->route('index.php?view=backup&task=ajax'));
		$document->addScriptOptions('akeeba.Backup.returnForm', (bool) $this->returnForm);
		$document->addScriptOptions('akeeba.Backup.URLs.LogURL', $router->route('index.php?view=log'));
		$document->addScriptOptions('akeeba.Backup.URLs.AliceURL', $router->route('index.php?view=alices'));
		$document->addScriptOptions('akeeba.System.notification.hasDesktopNotification', (bool) $hasDesktopNotifications);
		$document->addScriptOptions('akeeba.Backup.autoStart', !$this->unwriteableOutput && $this->autoStart);

		// All done, show the page!
		return true;
	}

	/**
	 * Get the default description for this backup attempt
	 *
	 * @return  string
	 */
	private function getDefaultDescription()
	{
		/** @var Backup $model */
		$model = $this->getModel();

		return $model->getDefaultDescription();
	}

	/**
	 * Get a list of backup domain keys and titles
	 *
	 * @return  array
	 */
	private function getDomains()
	{
		$engineConfiguration = Factory::getConfiguration();
		$script              = $engineConfiguration->get('akeeba.basic.backup_type', 'full');
		$scripting           = Factory::getEngineParamsProvider()->loadScripting();
		$domains             = [];

		if (empty($scripting))
		{
			return $domains;
		}

		foreach ($scripting['scripts'][$script]['chain'] as $domain)
		{
			$description = Text::_($scripting['domains'][$domain]['text']);
			$domain_key  = $scripting['domains'][$domain]['domain'];
			$domains[]   = [$domain_key, $description];
		}

		return $domains;
	}
}
