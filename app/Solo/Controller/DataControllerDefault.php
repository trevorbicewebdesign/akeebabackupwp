<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Controller;

use Awf\Mvc\DataController;
use Solo\Application\AclChecks;

/**
 * Common controller superclass. Reserved for future use.
 */
abstract class DataControllerDefault extends DataController
{
	use AclChecks;

	public function execute($task)
	{
		$view = $this->input->getCmd('view', 'main');

		$this->aclCheck($view, $task);

		return parent::execute($task);
	}
}
