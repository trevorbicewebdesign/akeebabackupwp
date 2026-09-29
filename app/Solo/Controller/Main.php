<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Controller;

use Akeeba\Engine\Factory;
use Akeeba\Engine\Platform;
use Akeeba\Engine\Util\RandomValue;
use AkeebaBackupWPUpdater;
use Awf\Text\Text;
use Exception;
use RuntimeException;
use Solo\Model\Update;
use Solo\View\Main\Html;

class Main extends ControllerDefault
{
	public function switchProfile()
	{
		$this->csrfProtection();

		// Switch the active profile
		$session          = $this->getContainer()->segment;
		$session->profile = $this->input->getInt('profile', 1);

		// Redirect
		$url = $this->container->router->route('index.php?view=main');

		$returnURL = $this->input->get('returnurl', '', 'raw');
		if (!empty($returnURL))
		{
			$url = base64_decode($returnURL);
		}

		$this->setRedirect($url);

		return true;
	}

	public function getUpdateInformation()
	{
		// Protect against direct access
		$this->csrfProtection();

		// Initialise
		$ret = [
			'hasUpdate'  => false,
			'version'    => '',
			'noticeHTML' => '',
		];

		// Am I running inside a CMS?
		$inCMS = $this->container->segment->get('insideCMS', false);

		/** @var Update $updateModel */
		$updateModel      = $this->container->mvcFactory->makeTempModel('Update');
		$ret['hasUpdate'] = $updateModel->getUpdateInformation()->get('hasUpdate', false);
		$ret['version']   = $updateModel->getUpdateInformation()->get('version', 'dev');

		if ($ret['hasUpdate'])
		{
			$router            = $this->container->router;
			$updateHeader      = Text::sprintf('SOLO_UPDATE_LBL_MAINNOTICE_TEXT', '<span class="label label-success">' . $ret['version'] . '</span>');
			$updateButton      = Text::_('SOLO_UPDATE_BTN_UPDATE_NOW');
			$updateLink        = $router->route('index.php?view=update');
			$ret['noticeHTML'] = <<< HTML
<div class="akeeba-block--warning">
	<h3>
		$updateHeader
	</h3>
	<p style="text-align: center">
		<a href="$updateLink" class="akeeba-btn--large--teal">
			<span class="akion-refresh"></span>
			$updateButton
		</a>
	</p>
</div>
HTML;
		}

		echo '#"\#\"#' . json_encode($ret) . '#"\#\"#';
		$this->container->application->close();
	}

	public function applyDownloadId()
	{
		// Protect against direct access
		$this->csrfProtection();

		$msg     = Text::_('COM_AKEEBA_CPANEL_ERR_INVALIDDOWNLOADID');
		$msgType = 'error';
		$dlid    = $this->input->getString('dlid', '');

		// If the Download ID seems legit let's apply it
		if (preg_match('/^([0-9]{1,}:)?[0-9a-f]{32}$/i', $dlid))
		{
			$msg     = null;
			$msgType = null;

			$config = $this->container->appConfig;
			$config->set('options.update_dlid', $dlid);
			$config->saveConfiguration();
		}

		// Akeeba Backup for WordPress: reset update information
		if (defined('WPINC'))
		{
			$transient = (object) [
				'response' => [],
			];
			AkeebaBackupWPUpdater::getUpdateInformation($transient);
		}

		// Redirect
		$url = $this->container->router->route('index.php?view=main');

		$returnURL = $this->input->get('returnurl', '', 'raw');
		if (!empty($returnURL))
		{
			$url = base64_decode($returnURL);
		}

		$this->setRedirect($url, $msg, $msgType);

		return true;
	}

	/**
	 * Reset the Secret Word for front-end and remote backup
	 *
	 * @return  bool
	 */
	public function resetSecretWord()
	{
		// CSRF prevention
		$this->csrfProtection();

		$session   = $this->container->segment;
		$newSecret = $session->get('newSecretWord', null);

		if (empty($newSecret))
		{
			$random    = new RandomValue();
			$newSecret = $random->generateString(32);
			$session->set('newSecretWord', $newSecret);
		}

		$config = $this->container->appConfig;
		$config->set('options.frontend_secret_word', $newSecret);
		$config->saveConfiguration();

		$msg = Text::sprintf('COM_AKEEBA_CPANEL_MSG_FESECRETWORD_RESET', $newSecret);

		$session->set('newSecretWord', null);

		$url = $this->container->router->route('index.php?view=Main');
		$this->setRedirect($url, $msg);

		return true;
	}

	/**
	 * Resets the "updatedb" flag and forces the database updates
	 */
	public function forceUpdateDb()
	{
		// Reset the flag so the updates could take place
		$this->container->appConfig->set('updatedb', null);
		$this->container->appConfig->saveConfiguration();

		/** @var \Solo\Model\Main $model */
		$model = $this->getModel();

		try
		{
			$model->checkAndFixDatabase();
		}
		catch (RuntimeException $e)
		{
			// This should never happen, since we reset the flag before execute the update, but you never know
		}

		$url = $this->container->router->route('index.php?view=Main');
		$this->setRedirect($url);
	}

	/**
	 * Dismisses the Core to Pro upsell for 15 days
	 *
	 * @return  void
	 */
	public function dismissUpsell()
	{
		// Reset the flag so the updates could take place
		$this->container->appConfig->set('lastUpsellDismiss', time());
		$this->container->appConfig->saveConfiguration();

		$url = $this->container->router->route('index.php?view=Main');
		$this->setRedirect($url);
	}

	/**
	 * Check the security of the backup output directory and return the results for consumption through AJAX
	 *
	 * @return  void
	 *
	 * @throws  Exception
	 *
	 * @since   7.0.3
	 */
	public function checkOutputDirectory()
	{
		/** @var \Solo\Model\Main $model */
		$model  = $this->getModel();
		$outDir = $model->getOutputDirectory();
		$inCMS  = $this->container->segment->get('insideCMS', false);

		try
		{
			$result = $model->getOutputDirectoryWebAccessibleState($outDir);

			if (!$inCMS)
			{
				$altResult = $model->getOutputDirectoryWebAccessibleState($outDir, true);

				foreach ($altResult as $k => $v)
				{
					$result[$k] = $result[$k] || $altResult[$k];
				}
			}
		}
		catch (RuntimeException $e)
		{
			$result = [
				'readFile'   => false,
				'listFolder' => false,
				'isSystem'   => $model->isOutputDirectoryInSystemFolder(),
				'hasRandom'  => $model->backupFilenameHasRandom(),
			];
		}

		@ob_end_clean();

		echo '#"\#\"#' . json_encode($result) . '#"\#\"#';

		$this->container->application->close();
	}

	/**
	 * Add security files to the output directory of the currently configured backup profile
	 *
	 * @return  void
	 *
	 * @throws  Exception
	 *
	 * @since   7.0.3
	 */
	public function fixOutputDirectory()
	{
		// CSRF prevention
		$this->csrfProtection();

		/** @var \Solo\Model\Main $model */
		$model  = $this->getModel();
		$outDir = $model->getOutputDirectory();

		$fsUtils = Factory::getFilesystemTools();
		$fsUtils->ensureNoAccess($outDir, true);

		$this->setRedirect($this->container->router->route('index.php'));
	}

	/**
	 * Adds the [RANDOM] variable to the backup output filename, save the configuration and reload the Control Panel.
	 *
	 * @return  void
	 *
	 * @throws  Exception
	 *
	 * @since   7.0.3
	 */
	public function addRandomToFilename()
	{
		// CSRF prevention
		$this->csrfProtection();
		$registry     = Factory::getConfiguration();
		$templateName = $registry->get('akeeba.basic.archive_name');

		if (strpos($templateName, '[RANDOM]') === false)
		{
			$templateName .= '-[RANDOM]';
			$registry->set('akeeba.basic.archive_name', $templateName);
			Platform::getInstance()->save_configuration();
		}

		$this->setRedirect($this->container->router->route('index.php'));
	}

	protected function onBeforeDefault()
	{
		// If we are running inside a CMS but there is no active user we have to throw a 403
		$inCMS = $this->container->segment->get('insideCMS', false);

		if ($inCMS && !$this->container->userManager->getUser()->getId())
		{
			return false;
		}

		/** @var \Solo\Model\Main $model */
		$model = $this->getModel();

		try
		{
			$model->checkAndFixDatabase();
		}
		catch (RuntimeException $e)
		{
			// The update is stuck. We will display a warning in the Control Panel
		}

		try
		{
			if ($inCMS)
			{
				$model->updateAutomationConfiguration();
			}
		}
		catch (RuntimeException $e)
		{
			// Oh, well.
		}

		// Run the update scripts, if necessary
		if ($model->postUpgradeActions(false))
		{
			$url = $this->container->router->route('index.php?view=main');
			$this->container->application->redirect($url);
		}

		// Let's make sure the temporary and output directories are set correctly and writable...
		/** @var \Solo\Model\Wizard $wizmodel */
		$wizmodel = $this->getContainer()->mvcFactory->makeTempModel('Wizard');
		$wizmodel->autofixDirectories();

		// Rebase Off-site Folder Inclusion filters to use site path variables
		if (class_exists('\Solo\Model\Extradirs'))
		{

			$incFoldersModel = $this->getContainer()->mvcFactory->makeTempModel('Extradirs');
			$incFoldersModel->rebaseFiltersToSiteDirs();
		}

		// Apply settings encryption preferences
		$model->checkEngineSettingsEncryption();

		// Convert existing log files to the new .log.php format
		$model->convertLogFiles();

		// Update magic configuration parameters
		$model->updateMagicParameters();

		// Flag stuck backups
		$model->flagStuckBackups();

		// Reload the quirks definitions, since flagging stuck backups will reset the factory state,
		// deleting temp objects and their settings
		Platform::getInstance()->apply_quirk_definitions();

		// Copy the ACL checks to the view. We'll use that information to show or hide icons
		/** @var Html $view */
		$view            = $this->getView();
		$view->aclChecks = $this->aclChecks;

		return true;
	}
}
