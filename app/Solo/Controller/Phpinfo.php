<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Controller;

/**
 * The Controller for the Phpinfo view
 */
class Phpinfo extends ControllerDefault
{
	public function phpinfo()
	{
		@ob_end_clean();
		phpinfo();
		$this->container->application->close(200);
	}
}
