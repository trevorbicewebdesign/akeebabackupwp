<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Controller;


use Awf\Container\Container;
use Awf\Text\Language;

class Dbfilters extends ControllerDefault
{
	private bool $noFlush = false;

	public function __construct(?Container $container = null, ?Language $language = null)
	{
		parent::__construct($container, $language);

		// Register the two additional tasks
		$this->registerTask('normal', 'main');
		$this->registerTask('tabular', 'main');

		$this->noFlush = $this->container->appConfig->get('no_flush', 0);
	}

	/**
	 * Default task
	 *
	 * @return  void
	 */
	public function main()
	{
		$task = $this->input->getCmd('task', 'normal');

		if ($task == 'main')
		{
			$task = 'normal';
		}

		$this->getModel()->setState('browse_task', $task);

		$this->display();
	}

	/**
	 * AJAX proxy method
	 *
	 * @return  void
	 */
	public function ajax()
	{
		// Parse the JSON data and reset the action query param to the resulting array
		$action_json = $this->input->get('akaction', '', 'raw');
		$action = json_decode($action_json);

		/** @var \Solo\Model\Dbfilters $model */
		$model = $this->getModel();
		$model->setState('action', $action);

		$ret = $model->doAjax();

		@ob_end_clean();
		echo '#"\#\"#' . json_encode($ret) . '#"\#\"#';

		if (!$this->noFlush)
		{
			flush();
		}

		$this->container->application->close();
	}
} 
