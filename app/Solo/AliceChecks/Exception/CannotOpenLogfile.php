<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Alice\Exception;

use Awf\Text\Text;
use RuntimeException;
use Throwable;

/**
 * ALICE Exception: cannot open log file
 */
class CannotOpenLogfile extends RuntimeException
{
	public function __construct($logFile, ?Throwable $previous = null)
	{
		$message = Text::sprintf('COM_AKEEBA_ALICE_ERR_CANNOT_OPEN_LOGFILE', $logFile);

		parent::__construct($message, 500, $previous);
	}
}