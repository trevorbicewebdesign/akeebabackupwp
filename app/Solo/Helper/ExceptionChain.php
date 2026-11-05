<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Helper;

use Throwable;

class ExceptionChain
{
	/**
	 * Walks a Throwable's chain of previous exceptions, from $e itself to the root cause.
	 *
	 * A hand-rolled loop or recursion that advances from the outer exception instead of the current node is a
	 * classic infinite-loop bug once a previous exception exists. This also guards against a pathological chain
	 * that somehow cycles back on itself, which would otherwise loop (or recurse) forever even with the advance
	 * fixed.
	 *
	 * @param   Throwable  $e  The exception to start walking from.
	 *
	 * @return  Throwable[]  The chain, starting with $e, in outer-to-innermost order.
	 */
	public static function walk(Throwable $e): array
	{
		$chain = [];
		$seen  = [];

		while ($e !== null)
		{
			$id = spl_object_id($e);

			if (isset($seen[$id]))
			{
				break;
			}

			$seen[$id] = true;
			$chain[]   = $e;
			$e         = $e->getPrevious();
		}

		return $chain;
	}
}
