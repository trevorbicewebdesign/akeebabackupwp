<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Exception\Update;

use Exception;
use RuntimeException;

class PlatformError extends UpdateError
{
	public function __construct($message = "", $code = 0, ?Exception $previous = null)
	{
		if (empty($message))
		{
			$message = 'There is an update to Akeeba Backup but the minimum PHP or WordPress version is higher than what is available on this site.';
		}

		if (empty($code))
		{
			$code = 500;
		}

		parent::__construct($message, $code, $previous);
	}

}
