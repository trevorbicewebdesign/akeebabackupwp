<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\View\ViewTraits;

// Protect from unauthorized access
defined('_AKEEBA') or die();

use Akeeba\Engine\Platform;
use Solo\Model\Profiles;

trait ProfileIdAndName
{
	/**
	 * Active profile ID
	 *
	 * @var  int
	 */
	public $profileid = 0;

	/**
	 * Active profile's description
	 *
	 * @var  string
	 */
	public $profilename = '';

	/**
	 * Is this profile available as an One Click Backup icon? 0/1
	 *
	 * @var  int
	 */
	public $quickIcon = 0;

	/**
	 * Find the currently active profile ID and name and put them in properties accessible by the view template
	 */
	protected function getProfileIdAndName()
	{
		/** @var Profiles $profilesModel */
		$profilesModel = $this->getModel('Profiles')->getClone()->setIgnoreRequest(1);
		$profileId     = Platform::getInstance()->get_active_profile();

		try
		{
			$this->profilename = $profilesModel->findOrFail($profileId)->description;
			$this->profileid = $profileId;
			$this->quickIcon = $profilesModel->quickicon;
		}
		catch (\Exception $e)
		{
			$this->container->segment->set('profile', 1);

			$this->profileid   = 1;
			$this->profilename = $profilesModel->findOrFail(1)->description;
		}
	}
}
