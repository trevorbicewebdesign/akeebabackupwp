<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Session;

use Awf\Session\SegmentInterface;
use Solo\Helper\HashHelper;

class Segment extends \Awf\Session\Segment implements SegmentInterface
{
	/**
	 * Forces a session start (or reactivation) and loads the segment data from WordPress' user meta storage.
	 *
	 * @return  void
	 *
	 */
	protected function load()
	{
		// CLI mode
		if (!defined('WPINC'))
		{
			return;
		}

		// is data already loaded?
		if ($this->data !== null)
		{
			// no need to re-load
			return;
		}

		// if the session is not started, start it
		if (!$this->session->isStarted())
		{
			$this->session->start();
		}

		// Intialize data
		$this->data = array();

		// Get the WordPress user meta key
		$metaKey    = $this->session->getName() . '_' . HashHelper::md5($this->getName());
		$userId     = get_current_user_id();
		$this->data = get_user_meta($userId, $metaKey, true);

		// Sometimes WordPress returns broken data
		if (!is_array($this->data))
		{
			$this->data = array();
		}
	}

	/**
	 * Commit the session data to WordPress' user meta storage.
	 *
	 * @return  void
	 */
	public function save()
	{
		// CLI mode
		if (!defined('WPINC'))
		{
			return;
		}

		$metaKey = $this->session->getName() . '_' . HashHelper::md5($this->getName());
		$userId  = get_current_user_id();

		update_user_meta($userId, $metaKey, $this->data);
	}

}
