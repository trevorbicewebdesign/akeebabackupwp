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
 * The controller for FTP browser
 */
class Ftpbrowser extends ControllerDefault
{
	private bool $noFlush = false;

	public function __construct(?Container $container = null, ?Language $language = null)
	{
		parent::__construct($container, $language);

		$this->noFlush = $this->container->appConfig->get('no_flush', 0);
	}

	public function execute($task)
	{
		// If we are running inside a CMS but there is no active user we have to throw a 403
		$inCMS = $this->container->segment->get('insideCMS', false);

		if ($inCMS && !$this->container->userManager->getUser()->getId())
		{
			return false;
		}

		return parent::execute($task);
	}


	public function main()
	{
		/** @var \Solo\Model\Ftpbrowser $model */
		$model = $this->getModel();

		// Grab the data and push them to the model
		$model->setState('host',		$this->input->getString('host', ''));
		$model->setState('port',		$this->input->getInt('port', 21));
		$model->setState('passive',		$this->input->getInt('passive', 1));
		$model->setState('ssl',			$this->input->getInt('ssl', 0));
		$model->setState('username',	$this->input->getRaw('username', ''));
		$model->setState('password',	$this->input->getRaw('password', ''));
		$model->setState('directory',	$this->input->getRaw('directory', ''));

		$ret = $model->doBrowse();

		@ob_end_clean();

		echo '#"\#\"#'.json_encode($ret).'#"\#\"#';

		if (!$this->noFlush)
		{
			flush();
		}

		$this->container->application->close();
	}
} 
