<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Model\Mixin;

use Exception;
use Solo\Helper\ExceptionChain;
use Throwable;

trait GetErrorsFromExceptions
{
	/**
	 * Retrieve the messages from nested exceptions into an array. It will optionally add the trace as the last element
	 * of the array if debug mode (JDEBUG or AKEEBADEBUG) is enabled and $includeTraceInDebug is true.
	 *
	 * @param   Exception|Throwable  $exception            The Exception or Throwable to log
	 *
	 * @param   bool                 $includeTraceInDebug  Include the trace when debug mode is enabled
	 *
	 * @return  array
	 */
	public function getErrorsFromExceptions($exception, $includeTraceInDebug = true)
	{
		$ret = array_map(
			fn (Throwable $thisException) => $thisException->getMessage(),
			ExceptionChain::walk($exception)
		);

		if ($includeTraceInDebug && ((defined('JDEBUG') && JDEBUG) || (defined('AKEEBADEBUG') && AKEEBADEBUG)))
		{
			$ret[] = $exception->getTraceAsString();
		}

		return $ret;
	}

}