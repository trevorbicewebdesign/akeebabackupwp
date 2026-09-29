<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Exception\Update;

use Exception;
use RuntimeException;

class StabilityError extends UpdateError
{
	public function __construct($message = "", $code = 0, ?Exception $previous = null)
	{
		if (empty($message))
		{
			$message = 'There is an update to Akeeba Backup but its stability is lower than the minimum update stability specified in System Configuration.';
		}

		if (empty($code))
		{
			$code = 500;
		}

		parent::__construct($message, $code, $previous);
	}
}
