<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Controller;

use Awf\Mvc\DataController;
use Solo\Application\AclChecks;
use Solo\Helper\Utils;

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

		/**
		 * AWF's DataController redirects to the base64 encoded `returnurl` without validating it, and its
		 * cancel() task has no CSRF check, making it an open redirect. Replace the input with a validated
		 * value (or nothing, so AWF falls back to the routed default URL) before any task can read it.
		 */
		$rawReturnUrl = $this->input->get('returnurl', '', 'raw');

		if ($rawReturnUrl !== '' && $rawReturnUrl !== null)
		{
			$this->input->set('returnurl', Utils::sanitiseEncodedReturnUrl($rawReturnUrl));
		}

		return parent::execute($task);
	}
}
