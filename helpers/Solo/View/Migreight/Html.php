<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\View\Migreight;

use Awf\Text\Text;
use Solo\Model\Migreight;

class Html extends \Awf\Mvc\DataView\Html
{
	protected array $affectedProfiles;

	protected array $migratedFolders;

	public function __construct(?\Awf\Container\Container $container = null)
	{
		parent::__construct($container);

		$this->setTemplatePath(realpath(__DIR__ . '/../../ViewTemplates/Migreight'));
	}

	protected function onBeforeMain(): bool
	{
		$this->container->application->getDocument()->getToolbar()->setTitle(Text::_('COM_AKEEBA_MIGREIGHT_TITLE'));

		/** @var Migreight $model */
		$model = $this->getModel();

		$this->affectedProfiles = $model->getAffectedProfiles();
		$this->migratedFolders  = $model->getArchiveFolderMap();

		return true;
	}
}