<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Controller;

use Awf\Mvc\Controller;

class Leftovers extends Controller
{
	public function remove(): void
	{
		$this->csrfProtection();

		[$success, $fail] = \Solo\Helper\Leftovers::deleteLeftovers($this->container);

		$container    = $this->getContainer();
		$segment      = $container->segment;
		$lang         = $container->language;
		$messageQueue = $segment->getFlash('leftovers_message_queue', []) ?: [];

		if ($success > 0)
		{
			$messageQueue[] = (object) [
				'type'    => 'success',
				'message' => $lang->plural('COM_AKEEBA_LEFTOVERS_SUCCESS_DELETING_N_FILES', $success),
			];
		}

		if ($fail > 0)
		{
			$messageQueue[] = (object) [
				'type'    => 'error',
				'message' => $lang->plural('COM_AKEEBA_LEFTOVERS_ERR_DELETING_N_FILES', $fail),
			];
		}

		$segment->setFlash('leftovers_message_queue', $messageQueue);

		$container->application->redirect(admin_url());
	}
}