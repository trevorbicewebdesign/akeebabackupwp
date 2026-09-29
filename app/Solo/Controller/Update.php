<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Controller;

use Awf\Text\Text;

class Update extends ControllerDefault
{
	public function main()
	{
		$force = $this->input->getInt('force', 0) == 1;

		/** @var \Solo\Model\Update $model */
		$model = $this->getModel();
		$model->load($force);

		parent::main();
	}

	public function download()
	{
		/** @var \Solo\Model\Update $model */
		$model = $this->getModel();
		$model->prepareDownload();

		$this->layout = 'download';

		$this->display();
	}

	public function downloader()
	{
		$json = $this->input->get('json', '', 'raw');
		$params = json_decode($json, true);

		/** @var \Solo\Model\Update $model */
		$model = $this->getModel();

		if (is_array($params) && !empty($params))
		{
			foreach ($params as $k => $v)
			{
				$model->setState($k, $v);
			}
		}

		// Set a very long timeout and a very big memory limit
		if (function_exists('ini_set'))
		{
			@ini_set('max_execution_time', 3600);
			@ini_set('memory_limit', '1024M');
		}

		$ret = $model->stepDownload(false);

		echo '#"\#\"#' . json_encode($ret) . '#"\#\"#';
	}

	public function extract()
	{
		$this->csrfProtection();

		$this->layout = 'extract';

		/** @var \Solo\Model\Update $model */
		$model = $this->getModel();
		$model->createRestorationINI();

		$this->display();
	}

	public function finalise()
	{
		// Do not add CSRF protection in this view; it called after the
		// installation of the update. At this point the session MAY have
		// already expired.

		/** @var \Solo\Model\Update $model */
		$model = $this->getModel();
		$model->finalise();

		$router = $this->container->router;

		$this->setRedirect($router->route('index.php?view=update&force=1'), Text::_('SOLO_UPDATE_COMPLETE_OK'), 'success');
	}
} 
