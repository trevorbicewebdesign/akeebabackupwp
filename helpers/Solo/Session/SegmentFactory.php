<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Session;

class SegmentFactory
{
	/**
	 *
	 * Creates a session segment object.
	 *
	 * @param Manager $manager
	 * @param string  $name
	 *
	 * @return Segment
	 */
	public function newInstance(Manager $manager, $name)
	{
		return new Segment($manager, $name);
	}
}
