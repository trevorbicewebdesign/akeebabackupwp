<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Exception\Update;

use Exception;
use RuntimeException;

class UpdateError extends RuntimeException
{
	public function __construct($message = "", $code = 0, ?Exception $previous = null)
	{
		if (empty($message))
		{
			$message = 'There was an error fetching the Akeeba Backup update information';
		}

		if (empty($code))
		{
			$code = 500;
		}

		parent::__construct($message, $code, $previous);
	}

}
