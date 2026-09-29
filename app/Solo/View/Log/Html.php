<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\View\Log;

use Akeeba\Engine\Factory;
use Awf\Mvc\View;
use Awf\Utils\Template;
use Solo\Model\Log;
use Solo\View\ViewTraits\ProfileIdAndName;

class Html extends View
{
	use ProfileIdAndName;

	/**
	 * Big log file threshold: 2Mb
	 */
	const bigLogSize = 2097152;
	/**
	 * List of available log files
	 *
	 * @var  array
	 */
	public $logs = [];
	/**
	 * Currently selected log file tag
	 *
	 * @var  string
	 */
	public $tag;
	/**
	 * Is the select log too big for being
	 *
	 * @var bool
	 */
	public $logTooBig = false;
	/**
	 * Size of the log file
	 *
	 * @var int
	 */
	public $logSize = 0;

	/**
	 * Setup the main log page
	 *
	 * @return  boolean
	 */
	public function onBeforeMain()
	{
		/** @var Log $model */
		$model      = $this->getModel();
		$this->logs = $model->getLogList();

		$tag = $model->getState('tag', '', 'string');

		if (empty($tag))
		{
			$tag = null;
		}

		$this->tag = $tag;

		// Let's check if the file is too big to display
		if ($this->tag)
		{
			$logFile = Factory::getLog()->getLogFilename($this->tag);

			if (!@is_file($logFile) && @file_exists(substr($logFile, 0, -4)))
			{
				/**
				 * Bad host: the log file akeeba.tag.log.php may not exist but the akeeba.tag.log does.
				 */
				$logFile = substr($logFile, 0, -4);
			}

			if (@file_exists($logFile))
			{
				$this->logSize   = filesize($logFile);
				$this->logTooBig = ($this->logSize >= self::bigLogSize);
			}
		}

		// Load the Javascript
		Template::addJs('media://js/solo/log.min.js', $this->container->application);

		$document = $this->container->application->getDocument();
		$src      = $this->container->router->route('index.php?view=Log&task=iframe&format=raw&tag=' . urlencode($this->tag));

		$document->addScriptOptions('akeeba.Log.iFrameSrc', $src);

		$this->getProfileIdAndName();

		return true;
	}

	/**
	 * Setup the iframe display
	 *
	 * @return  boolean
	 */
	public function onBeforeIframe()
	{
		/** @var Log $model */
		$model = $this->getModel();
		$tag   = $model->getState('tag', '', 'string');

		if (empty($tag))
		{
			$tag = null;
		}

		$this->tag = $tag;

		$this->setLayout('raw');

		$this->container->application->getDocument()->setMimeType('text/html');

		return true;
	}
}
