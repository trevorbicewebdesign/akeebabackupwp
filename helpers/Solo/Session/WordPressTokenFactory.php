<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Session;


use Awf\Session\CsrfToken;
use Awf\Session\CsrfTokenFactory;
use Awf\Session\Manager;

class WordPressTokenFactory extends CsrfTokenFactory
{
	/**
	 * Creates a CsrfToken object.
	 *
	 * @param   Manager  $manager  The session manager.
	 *
	 * @return CsrfToken
	 *
	 */
	public function newInstance(Manager $manager)
	{
		$segment = $manager->newSegment(__NAMESPACE__ . '\WordPressToken');

		return new WordPressToken($segment);
	}

}