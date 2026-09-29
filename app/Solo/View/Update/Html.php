<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\View\Update;

use Awf\Mvc\View;
use Awf\Registry\Registry;
use Awf\Text\Text;
use Awf\Utils\Template;
use Solo\Model\Main;
use Solo\Model\Update;

class Html extends View
{
	/**
	 * The update information registry
	 *
	 * @var   Registry
	 */
	public $updateInfo;
	public $needsDownloadId;

	public function display($tpl = null)
	{
		Template::addJs('media://js/solo/update.js', $this->container->application);

		return parent::display($tpl);
	}

	public function onBeforeMain()
	{
		/** @var Update $model */
		$model = $this->getModel();

		/** @var Main $modelMain */
		$modelMain = $this->getModel('Main');

		$this->updateInfo      = $model->getUpdateInformation();
		$this->needsDownloadId = $modelMain->needsDownloadID();

		if ($this->updateInfo->get('stuck', 0))
		{
			$this->layout = 'stuck';
		}

		return true;
	}

	public function onBeforeDownload()
	{
		$doc = $this->container->application->getDocument();
		$doc->lang('SOLO_UPDATE_ERR_INVALIDDOWNLOADID');

		$token       = $this->getContainer()->session->getCsrfToken()->getValue();
		$router      = $this->getContainer()->router;
		$ajaxUrl     = $router->route('index.php?view=update&task=downloader&format=raw');
		$nextStepUrl = $router->route('index.php?view=update&task=extract&token=' . $token);
		$document    = $this->getContainer()->application->getDocument();

		$document->addScriptOptions('akeeba.System.params.AjaxURL', $ajaxUrl);
		$document->addScriptOptions('akeeba.Update.nextStepUrl', $nextStepUrl);
		$document->addScriptOptions('akeeba.Update.autoAction', 'startDownload');

		return true;
	}

	public function onBeforeExtract()
	{
		$router      = $this->getContainer()->router;
		$ajaxUrl     = \Awf\Uri\Uri::base(false, $this->container) . 'restore.php';
		$finalizeUrl = $router->route('index.php?view=update&task=finalise');
		$password    = $this->getModel()->getState('update_password', '');
		$document    = $this->getContainer()->application->getDocument();

		$document->addScriptOptions('akeeba.System.params.AjaxURL', $ajaxUrl);
		$document->addScriptOptions('akeeba.Update.finaliseUrl', $finalizeUrl);
		$document->addScriptOptions('akeeba.System.params.password', $password);
		$document->addScriptOptions('akeeba.Update.autoAction', 'pingExtract');

		return true;
	}
}
