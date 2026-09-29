<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\View\Dbfilters;

use Awf\Html\Select;
use Awf\Mvc\View;
use Awf\Text\Text;
use Awf\Uri\Uri;
use Awf\Utils\Template;
use Solo\Helper\Escape;
use Solo\Model\Dbfilters;
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
		Template::addJs('media://js/solo/fsfilters.js', $this->container->application);
		Template::addJs('media://js/solo/dbfilters.js', $this->container->application);

		/** @var Dbfilters $model */
		$model = $this->getModel();
		$task  = $model->getState('browse_task', 'normal', 'cmd');

		$router = $this->container->router;

		// Add custom submenus
		$toolbar = $this->container->application->getDocument()->getToolbar();
		$toolbar->addSubmenuFromDefinition([
			'name'  => 'normal',
			'title' => Text::_('COM_AKEEBA_FILEFILTERS_LABEL_NORMALVIEW'),
			'url'   => $router->route('index.php?view=dbfilters&task=normal'),
		]);
		$toolbar->addSubmenuFromDefinition([
			'name'  => 'tabular',
			'title' => Text::_('COM_AKEEBA_FILEFILTERS_LABEL_TABULARVIEW'),
			'url'   => $router->route('index.php?view=dbfilters&task=tabular'),
		]);

		// Get a JSON representation of the available roots
		$root_info = $model->get_roots();
		$roots     = [];
		$options   = [];

		if (!empty($root_info))
		{
			// Loop all db definitions
			foreach ($root_info as $def)
			{
				$roots[]   = $def->value;
				$options[] = $this->getContainer()->html->select->option( $def->value, $def->text);
			}
		}

		$siteRoot          = $roots[0];
		$selectOptions     = [
			'list.select' => $siteRoot,
			'id'          => 'active_root',
		];
		$this->root_select = $this->getContainer()->html->select->genericList($options, 'root', $selectOptions);
		$this->roots       = $roots;
		$document          = $this->container->application->getDocument();

		$document->addScriptOptions('akeeba.System.params.AjaxURL', $router->route('index.php?view=Dbfilters&task=ajax'));

		switch ($task)
		{
			case 'normal':
			default:
				$this->setLayout('default');

				// Get a JSON representation of the directory data
				$document->addScriptOptions('akeeba.DatabaseFilters.guiData', $model->make_listing($siteRoot));
				$document->addScriptOptions('akeeba.DatabaseFilters.viewType', 'list');
				break;

			case 'tabular':
				$this->setLayout('tabular');

				// Get a JSON representation of the tabular filter data
				$document->addScriptOptions('akeeba.DatabaseFilters.guiData', [
					'list' => $model->get_filters($siteRoot)
				]);
				$document->addScriptOptions('akeeba.DatabaseFilters.viewType', 'tabular');

				break;
		}

		// Load the Javascript language strings
		$doc = $this->container->application->getDocument();
		$doc->lang('COM_AKEEBA_FILEFILTERS_LABEL_UIROOT');
		$doc->lang('COM_AKEEBA_FILEFILTERS_LABEL_UIERRORFILTER');
		$doc->lang('COM_AKEEBA_FILEFILTERS_LABEL_UIERRORFILTER');
		$doc->lang('COM_AKEEBA_DBFILTER_TYPE_TABLES');
		$doc->lang('COM_AKEEBA_DBFILTER_TYPE_TABLEDATA');
		$doc->lang('COM_AKEEBA_DBFILTER_TABLE_MISC');
		$doc->lang('COM_AKEEBA_DBFILTER_TABLE_TABLE');
		$doc->lang('COM_AKEEBA_DBFILTER_TABLE_VIEW');
		$doc->lang('COM_AKEEBA_DBFILTER_TABLE_PROCEDURE');
		$doc->lang('COM_AKEEBA_DBFILTER_TABLE_FUNCTION');
		$doc->lang('COM_AKEEBA_DBFILTER_TABLE_TRIGGER');
		$doc->lang('COM_AKEEBA_DBFILTER_TABLE_META_ROWCOUNT');

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
