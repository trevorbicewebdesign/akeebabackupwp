<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Controller;

use Awf\Container\Container;
use Awf\Text\Language;

/**
 * The Configuration Wizard controller
 */
class Wizard extends ControllerDefault
{
	private bool $noFlush = false;

	public function __construct(?Container $container = null, ?Language $language = null)
	{
		parent::__construct($container, $language);

		$this->noFlush = $this->container->appConfig->get('no_flush', 0);
	}

	/**
	 * Executes a given controller task. The onBefore<task> and onAfter<task>
	 * methods are called automatically if they exist.
	 *
	 * @param   string  $task The task to execute, e.g. "browse"
	 *
	 * @return  null|bool  False on execution failure
	 *
	 * @throws  \Exception  When the task is not found
	 */
	public function execute($task)
	{
		// If we are running inside another CMS skip the first page
		$inCMS = $this->container->segment->get('insideCMS', false);

		if ($inCMS && !in_array($task, array('wizard', 'ajax')))
		{
			$task = 'wizard';
		}

		return parent::execute($task);
	}

	/**
	 * Tests and saves the site configuration settings, then redirects to the wizard task
	 */
	public function applySiteSettings()
	{
		$this->csrfProtection();

		$siteParams = $this->input->get('var', array(), 'array');

		try
		{
			/** @var \Solo\Model\Wizard $model */
			$model = $this->getModel();
			$model->testSiteParams($siteParams);
			$model->saveSiteParams($siteParams);
		}
		catch (\Exception $e)
		{
			$url = $this->container->router->route('index.php?view=wizard');
			$this->setRedirect($url, $e->getMessage(), 'error');

			return;
		}

		$url = $this->container->router->route('index.php?view=wizard&task=wizard');
		$this->setRedirect($url);
	}

	/**
	 * Show the main page of the wizard
	 *
	 * @return  void
	 */
	public function wizard()
	{
		$this->getView()->setLayout('wizard');

		$this->display();
	}

	public function ajax()
	{
		$act = $this->input->getCmd('akact', '');

		/** @var \Solo\Model\Wizard $model */
		$model = $this->getModel();
		$model->setState('act', $act);
		$ret = $model->runAjax();

		@ob_end_clean();
		echo '#"\#\"#' . json_encode( $ret ) . '#"\#\"#';

		if (!$this->noFlush)
		{
			flush();
		}

		$this->container->application->close();
	}
}
