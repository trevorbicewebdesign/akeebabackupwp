<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\View\Main;

use Akeeba\Engine\Factory;
use Akeeba\Engine\Platform;
use Awf\Mvc\View;
use Awf\Utils\Template;
use RuntimeException;
use Solo\Helper\Status;
use Solo\Model\Main;
use Solo\Model\Migreight;
use Solo\Model\Stats;

class Html extends View
{
	/**
	 * Active backup profile ID
	 *
	 * @var   int
	 */
	public $profile;

	/**
	 * A list of Akeeba Engine backup profiles in a format suitable for use with Html\Select::genericList
	 *
	 * @var   array
	 */
	public $profileList;

	/**
	 * List of profiles to display as Quick Icons in the control panel page
	 *
	 * @var   array  Array of stdClass objects
	 */
	public $quickIconProfiles;

	/**
	 * Do I have to ask the user to provide a Download ID?
	 *
	 * @var   bool
	 */
	public $needsDownloadId;

	/**
	 * Did a Core edition user provide a Download ID instead of installing Akeeba Backup Professional?
	 *
	 * @var   bool
	 */
	public $warnCoreDownloadId;

	/**
	 * If front-end backup is enabled and the secret word has an issue (too insecure) we populate this variable
	 *
	 * @var  string
	 */
	public $frontEndSecretWordIssue;

	/**
	 * In case the existing Secret Word is insecure we generate a new one. This variable contains the new Secret Word.
	 *
	 * @var  string
	 */
	public $newSecretWord;

	/**
	 * Should I have the browser ask for desktop notification permissions?
	 *
	 * @var   bool
	 */
	public $desktop_notifications;

	/**
	 * If anonymous statistics collection is enabled and we have to collect statistics this will include the HTML for
	 * the IFRAME that performs the anonymous stats collection.
	 *
	 * @var   string
	 */
	public $statsIframe;

	/**
	 * Is the mbstring extension installed and enabled? This is required by Joomla and Akeeba Backup to correctly work
	 *
	 * @var  bool
	 */
	public $checkMbstring = true;

	/**
	 * ACL checks. This is set to the View by the Controller.
	 *
	 * @see  \Solo\Controller\Main::onBeforeDefault()
	 *
	 * @var  array
	 */
	public $aclChecks = [];

	/**
	 * The HTML for the backup status cell
	 *
	 * @var   string
	 */
	public $statusCell = '';

	/**
	 * HTML for the warnings (status details)
	 *
	 * @var   string
	 */
	public $detailsCell = '';

	/**
	 * Details of the latest backup as HTML
	 *
	 * @var   string
	 */
	public $latestBackupCell = '';

	/**
	 * Do I have stuck updates pending?
	 *
	 * @var  bool
	 */
	public $stuckUpdates = false;

	/**
	 * Should I prompt the user ot run the configuration wizard?
	 *
	 * @var  bool
	 */
	public $promptForConfigurationWizard = false;

	/**
	 * How many warnings do I have to display?
	 *
	 * @var  int
	 */
	public $countWarnings = 0;

	/**
	 * The fancy formatted changelog of the component
	 *
	 * @var  string
	 */
	public $formattedChangelog = '';

	/**
	 * Timestamp when the Core user last dismissed the upsell to Pro
	 *
	 * @var   int
	 * @since 7.0.0
	 */
	public $lastUpsellDismiss = 0;

	/**
	 * Is the output directory under the site's root?
	 *
	 * @var   bool
	 * @since 7.0.3
	 */
	public $isOutputDirectoryUnderSiteRoot = false;

	/**
	 * Does the output directory have the expected security files?
	 *
	 * @var   bool
	 * @since 7.0.3
	 */
	public $hasOutputDirectorySecurityFiles = false;

	/**
	 * Do I need to migrate backup profiles and archives? (Only under WordPress)
	 *
	 * @var   bool
	 * @since 8.1.0
	 */
	public $needsMigreight = false;

	public function onBeforeMain()
	{
		/** @var Main $model */
		$model        = $this->getModel();
		$statusHelper = Status::getInstance($this->container);
		$session      = $this->container->segment;

		$this->profile                         = Platform::getInstance()->get_active_profile();
		$this->profileList                     = $model->getProfileList();
		$this->quickIconProfiles               = $model->getQuickIconProfiles();
		$this->statusCell                      = $statusHelper->getStatusCell();
		$this->detailsCell                     = $statusHelper->getQuirksCell();
		$this->latestBackupCell                = $statusHelper->getLatestBackupDetails();
		$this->needsDownloadId                 = $model->needsDownloadID();
		$this->warnCoreDownloadId              = $model->mustWarnAboutDownloadIdInCore();
		$this->checkMbstring                   = $model->checkMbstring();
		$this->frontEndSecretWordIssue         = $model->getFrontendSecretWordError();
		$this->newSecretWord                   = $session->get('newSecretWord', null);
		$this->stuckUpdates                    = ($this->container->appConfig->get('updatedb', 0) == 1);
		$this->promptForConfigurationWizard    = Factory::getConfiguration()->get('akeeba.flag.confwiz', 0) == 0;
		$this->countWarnings                   = count(Factory::getConfigurationChecks()->getDetailedStatus());
		$this->desktop_notifications           = Platform::getInstance()->get_platform_configuration_option('desktop_notifications', '0') ? 1 : 0;
		$this->formattedChangelog              = $this->formatChangelog();
		$this->lastUpsellDismiss               = $this->container->appConfig->get('lastUpsellDismiss', 0);
		$this->hasOutputDirectorySecurityFiles = $model->hasOutputDirectorySecurityFiles();
		$this->isOutputDirectoryUnderSiteRoot  = $model->isOutputDirectoryUnderSiteRoot();

		// Solo: the output directory may be under Solo's root, not the site's root. It's equally important to me.
		if (!$this->container->segment->get('insideCMS', false))
		{
			$this->isOutputDirectoryUnderSiteRoot |= $model->isOutputDirectoryUnderSiteRoot(null, true);
		}

		/** @var Stats $statsModel */
		$statsModel        = $this->container->mvcFactory->makeTempModel('Stats');
		$this->statsIframe = $statsModel->collectStatistics(true);

		// Load the Javascript for this page
		Template::addJs('media://js/solo/main.js', $this->container->application);


		$router   = $this->container->router;
		$document = $this->container->application->getDocument();

		$document->addScriptOptions('akeeba.System.notification.hasDesktopNotification', $this->desktop_notifications);
		$document->addScriptOptions('akeeba.ControlPanel.checkOutDirUrl', $router->route('index.php?view=main&format=raw&task=checkOutputDirectory'));
		$document->addScriptOptions('akeeba.ControlPanel.outputDirUnderSiteRoot', (bool) $this->isOutputDirectoryUnderSiteRoot);
		$document->addScriptOptions('akeeba.ControlPanel.hasSecurityFiles', (bool) $this->hasOutputDirectorySecurityFiles);
		$document->addScriptOptions('akeeba.ControlPanel.cloudFlareURN', 'CLOUDFLARE::' . Template::parsePath('media://js/solo/system.js', false, $this->getContainer()->application));
		$document->addScriptOptions('akeeba.ControlPanel.updateInfoURL', $router->route('index.php?view=main&format=raw&task=getUpdateInformation&' . $this->getContainer()->session->getCsrfToken()->getValue() . '=1'));

		if ($this->container->segment->get('insideCMS', false))
		{
			/** @var Migreight $migreightModel */
			$migreightModel = $this->getModel('Migreight');
			$this->needsMigreight = count($migreightModel->getAffectedProfiles())
				|| count($migreightModel->getArchiveFolderMap());

		}

		return true;
	}

	/**
	 * Performs automatic access control checks
	 *
	 * @param   string  $view  The view being considered
	 * @param   string  $task  The task being considered
	 *
	 * @return  bool  True if access is allowed
	 *
	 * @throws RuntimeException
	 */
	public function canAccess($view, $task)
	{
		$view = strtolower($view);
		$task = strtolower($task);

		if (!isset($this->aclChecks[$view]))
		{
			return true;
		}

		if (!isset($this->aclChecks[$view][$task]))
		{
			if (!isset($this->aclChecks[$view]['*']))
			{
				return true;
			}

			$requiredPrivileges = $this->aclChecks[$view]['*'];
		}
		else
		{
			$requiredPrivileges = $this->aclChecks[$view][$task];
		}

		$user = $this->container->userManager->getUser();

		foreach ($requiredPrivileges as $privilege)
		{
			if (!$user->getPrivilege('akeeba.' . $privilege))
			{
				return false;
			}
		}

		return true;
	}

	protected function formatChangelog($onlyLast = false)
	{
		$container = $this->getContainer();
		$filePath  = isset($container['changelogPath']) ? $container['changelogPath'] : null;

		if (empty($filePath))
		{
			$filePath = APATH_BASE . '/CHANGELOG.php';
		}

		$ret   = '';
		$file  = $filePath;
		$lines = @file($file);

		if (empty($lines))
		{
			return $ret;
		}

		array_shift($lines);

		foreach ($lines as $line)
		{
			$line = trim($line);

			if (empty($line))
			{
				continue;
			}

			$type = substr($line, 0, 1);

			switch ($type)
			{
				case '=':
					continue 2;
					break;

				case '+':
					$ret .= "\t" . '<li><span class="akeeba-label--green">Added</span> ' . htmlentities(trim(substr($line, 2))) . "</li>\n";
					break;

				case '-':
					$ret .= "\t" . '<li><span class="akeeba-label--grey">Removed</span> ' . htmlentities(trim(substr($line, 2))) . "</li>\n";
					break;

				case '~':
				case '^':
					$ret .= "\t" . '<li><span class="akeeba-label--grey">Changed</span> ' . htmlentities(trim(substr($line, 2))) . "</li>\n";
					break;

				case '*':
					$ret .= "\t" . '<li><span class="akeeba-label--red">Security</span> ' . htmlentities(trim(substr($line, 2))) . "</li>\n";
					break;

				case '!':
					$ret .= "\t" . '<li><span class="akeeba-label--orange">Important</span> ' . htmlentities(trim(substr($line, 2))) . "</li>\n";
					break;

				case '#':
					$ret .= "\t" . '<li><span class="akeeba-label--teal">Fixed</span> ' . htmlentities(trim(substr($line, 2))) . "</li>\n";
					break;

				default:
					if (!empty($ret))
					{
						$ret .= "</ul>";
						if ($onlyLast)
						{
							return $ret;
						}
					}

					if (!$onlyLast)
					{
						$ret .= "<h4>$line</h4>\n";
					}
					$ret .= "<ul class=\"akeeba-changelog\">\n";

					break;
			}
		}

		return $ret;
	}
}
