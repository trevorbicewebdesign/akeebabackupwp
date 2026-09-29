<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Controller;


class Errortest extends ControllerDefault
{
	public function main()
	{
		throw new \RuntimeException('I am a runtime exception with error code 500', 500);
	}

	public function notfound()
	{
		throw new \RuntimeException('I am a runtime exception with error code 404 Not Found', 404);
	}

	public function fatal()
	{
		kalimera();
	}
}