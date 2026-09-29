<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\View\Wizard;

use Awf\Mvc\View;
use Awf\Text\Text;
use Awf\Utils\Template;
use Solo\Helper\Escape;

/**
 * The view class for the Configuration view
 */
class Html extends View
{
	public $siteInfo;

	public function onBeforeMain()
	{
		$document = $this->container->application->getDocument();

		// Load the necessary Javascript
		Template::addJs('media://js/solo/showon.js', $this->container->application);
		Template::addJs('media://js/solo/configuration.js', $this->container->application);
		Template::addJs('media://js/solo/wizard.js', $this->container->application);

		// Append buttons to the toolbar
		$buttons = [
			[
				'title'   => 'SOLO_BTN_SUBMIT',
				'class'   => 'akeeba-btn--green',
				'onClick' => 'document.forms.adminForm.submit(); return false;',
				'icon'    => 'akion-checkmark-circled',
			],
		];


		$toolbar = $document->getToolbar();
		foreach ($buttons as $button)
		{
			$toolbar->addButtonFromDefinition($button);
		}

		// Get the site URL and root directory
		$this->siteInfo = $this->getModel()->guessSiteParams();

		// Add Javascript
		$doc = $this->container->application->getDocument();
		$doc->lang('COM_AKEEBA_CONFIG_UI_BROWSE');
		$doc->lang('SOLO_COMMON_LBL_ROOT');

		$document   = $this->container->application->getDocument();
		$router     = $this->getContainer()->router;
		$urlBrowser = Escape::escapeJS($router->route('index.php?view=browser&tmpl=component&processfolder=1&folder='));
		$urlAjax    = Escape::escapeJS($router->route('index.php?view=wizard&task=ajax'));

		$document->addScriptOptions('akeeba.Configuration.URLs', [
			'browser' => $urlBrowser,
		]);
		$document->addScriptOptions('akeeba.System.params.AjaxURL', $urlAjax);
		$document->addScriptOptions('akeeba.Wizard.AjaxURL', $urlAjax);

		// All done, show the page!
		return true;
	}

	public function onBeforeWizard()
	{
		// Load the necessary Javascript
		Template::addJs('media://js/solo/backup.js', $this->container->application);
		Template::addJs('media://js/solo/wizard.js', $this->container->application);

		$doc = $this->container->application->getDocument();
		$doc->lang('COM_AKEEBA_CONFWIZ_UI_MINEXECTRY');
		$doc->lang('COM_AKEEBA_CONFWIZ_UI_CANTDETERMINEMINEXEC');
		$doc->lang('COM_AKEEBA_CONFWIZ_UI_SAVEMINEXEC');
		$doc->lang('COM_AKEEBA_CONFWIZ_UI_CANTSAVEMINEXEC');
		$doc->lang('COM_AKEEBA_CONFWIZ_UI_CANTFIXDIRECTORIES');
		$doc->lang('COM_AKEEBA_CONFWIZ_UI_CANTDBOPT');
		$doc->lang('COM_AKEEBA_CONFWIZ_UI_EXECTOOLOW');
		$doc->lang('COM_AKEEBA_CONFWIZ_UI_MINEXECTRY');
		$doc->lang('COM_AKEEBA_CONFWIZ_UI_SAVINGMAXEXEC');
		$doc->lang('COM_AKEEBA_CONFWIZ_UI_CANTSAVEMAXEXEC');
		$doc->lang('COM_AKEEBA_CONFWIZ_UI_CANTDETERMINEPARTSIZE');
		$doc->lang('COM_AKEEBA_CONFWIZ_UI_PARTSIZE');
		$doc->lang('COM_AKEEBA_BACKUP_TEXT_LASTRESPONSE');

		$document = $this->container->application->getDocument();
		$router   = $this->getContainer()->router;
		$urlAjax  = Escape::escapeJS($router->route('index.php?view=wizard&task=ajax'));

		$document->addScriptOptions('akeeba.System.params.AjaxURL', $urlAjax);

		// All done, show the page!
		return true;
	}
}
