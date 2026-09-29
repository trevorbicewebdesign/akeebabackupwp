<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Controller;

use Awf\Mvc\Controller;
use Awf\Text\Text;
use RuntimeException;
use Throwable;

class Migreight extends Controller
{
	private const MAX_TIME = 2;

	/**
	 * Starts the migration
	 *
	 * @return  void
	 */
	public function start()
	{
		// Store the necessary information to the session
		/** @var \Solo\Model\Migreight $model */
		$model   = $this->getModel();

		try
		{
			// Migrate the profiles
			$model->migrateProfiles();

			// Migrate the archives
			$model->migrateArchives();

			$this->setRedirect(
				$this->container->router->route('index.php?view=migreight'),
				Text::_('COM_AKEEBA_MIGREIGHT_COMPLETE')
			);
		}
		catch (RuntimeException $e)
		{
			if ($e->getCode() != 8087)
			{
				throw $e;
			}

			$this->setRedirect(
				$this->container->router->route('index.php?view=migreight'),
				Text::_('COM_AKEEBA_MIGREIGHT_INCOMPLETE')
			);
		}
	}
}