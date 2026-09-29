<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Exception\Update;

use Exception;
use RuntimeException;

class ConnectionError extends UpdateError
{
	public function __construct($message = "", $code = 0, ?Exception $previous = null)
	{
		if (empty($message))
		{
			$message = 'Cannot connect to Akeeba Backup\'s update server';
		}

		if (empty($code))
		{
			$code = 500;
		}

		parent::__construct($message, $code, $previous);
	}

}
