<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\View\Fsfilters;


use Akeeba\Engine\Factory;
use Awf\Html\Select;
use Awf\Mvc\View;
use Awf\Text\Text;
use Awf\Utils\Template;
use Solo\Model\Fsfilters;
use Solo\View\ViewTraits\ProfileIdAndName;

class Html extends View
{
	use ProfileIdAndName;

	/**
	 * SELECT element for choosing a database root
	 *
	 * @var  string
	 */
	public $root_select = '';

	/**
	 * List of database roots
	 *
	 * @var  array
	 */
	public $roots = [];

	/**
	 * Prepare the view data for the main task
	 *
	 * @return  boolean
	 */
	public function onBeforeMain()
	{
		// Load additional Javascript
		Template::addJs('media://js/solo/fsfilters.js', $this->container->application);

		/** @var Fsfilters $model */
		$model = $this->getModel();
		$task  = $model->getState('browse_task', 'normal', 'cmd');

		$router = $this->container->router;

		// Add custom submenus
		$toolbar = $this->container->application->getDocument()->getToolbar();
		$toolbar->addSubmenuFromDefinition([
			'name'  => 'normal',
			'title' => Text::_('COM_AKEEBA_FILEFILTERS_LABEL_NORMALVIEW'),
			'url'   => $router->route('index.php?view=fsfilters&task=normal'),
		]);
		$toolbar->addSubmenuFromDefinition([
			'name'  => 'tabular',
			'title' => Text::_('COM_AKEEBA_FILEFILTERS_LABEL_TABULARVIEW'),
			'url'   => $router->route('index.php?view=fsfilters&task=tabular'),
		]);

		// Get a JSON representation of the available roots
		$filters   = Factory::getFilters();
		$root_info = $filters->getInclusions('dir');
		$roots     = [];
		$options   = [];

		if (!empty($root_info))
		{
			// Loop all dir definitions
			foreach ($root_info as $dir_definition)
			{
				if (is_null($dir_definition[1]))
				{
					// Site root definition has a null element 1. It is always pushed on top of the stack.
					array_unshift($roots, $dir_definition[0]);
				}
				else
				{
					$roots[] = $dir_definition[0];
				}

				$options[] = $this->getContainer()->html->select->option( $dir_definition[0], $dir_definition[0]);
			}
		}

		$siteRoot          = $roots[0];
		$attributes        = ['onchange' => "akeeba.Fsfilters.activeRootChanged();"];
		$this->root_select = $this->getContainer()->html->select->genericList(
			$options, 'root', $attributes, 'value', 'text', $siteRoot, 'active_root'
		);
		$this->roots       = $roots;
		$document          = $this->container->application->getDocument();

		// Add script options
		$document->addScriptOptions('akeeba.System.params.AjaxURL', $router->route('index.php?view=Fsfilters&task=ajax'));
		$document->addScriptOptions('akeeba.Fsfilters.loadingGif', Template::parsePath('media://image/loading.gif', false, $this->getContainer()->application));

		switch ($task)
		{
			case 'normal':
			default:
				$this->setLayout('default');

				// Get a JSON representation of the directory data
				$document->addScriptOptions('akeeba.FileFilters.guiData', $model->make_listing($siteRoot, [], ''));
				$document->addScriptOptions('akeeba.FileFilters.viewType', "list");

				break;

			case 'tabular':
				$this->setLayout('tabular');

				// Get a JSON representation of the tabular filter data
				$document->addScriptOptions('akeeba.FileFilters.guiData', [
					'list' => $model->get_filters($siteRoot)
				]);
				$document->addScriptOptions('akeeba.FileFilters.viewType', "tabular");

				break;
		}

		$doc = $this->container->application->getDocument();
		$doc->lang('COM_AKEEBA_FILEFILTERS_LABEL_UIROOT');
		$doc->lang('COM_AKEEBA_FILEFILTERS_LABEL_UIERRORFILTER');
		$doc->lang('COM_AKEEBA_FILEFILTERS_TYPE_DIRECTORIES');
		$doc->lang('COM_AKEEBA_FILEFILTERS_TYPE_SKIPFILES');
		$doc->lang('COM_AKEEBA_FILEFILTERS_TYPE_SKIPDIRS');
		$doc->lang('COM_AKEEBA_FILEFILTERS_TYPE_FILES');
		$doc->lang('COM_AKEEBA_FILEFILTERS_TYPE_DIRECTORIES_ALL');
		$doc->lang('COM_AKEEBA_FILEFILTERS_TYPE_SKIPFILES_ALL');
		$doc->lang('COM_AKEEBA_FILEFILTERS_TYPE_SKIPDIRS_ALL');
		$doc->lang('COM_AKEEBA_FILEFILTERS_TYPE_FILES_ALL');
		$doc->lang('COM_AKEEBA_FILEFILTERS_TYPE_APPLYTOALLDIRS');
		$doc->lang('COM_AKEEBA_FILEFILTERS_TYPE_APPLYTOALLFILES');

		$this->getProfileIdAndName();

		return true;
	}

	/**
	 * The normal task simply calls the method for the main task
	 *
	 * @return  boolean
	 */
	public function onBeforeNormal()
	{
		return $this->onBeforeMain();
	}

	/**
	 * The tabular task simply calls the method for the main task
	 *
	 * @return  boolean
	 */
	public function onBeforeTabular()
	{
		return $this->onBeforeMain();
	}
}
