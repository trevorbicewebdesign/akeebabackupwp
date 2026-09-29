<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Application;

use Awf\Container\Container;
use Awf\User\Manager;

class UserManager extends Manager
{
	public function __construct(?Container $container = null)
	{
		parent::__construct($container);

		$this->user_table = '#__ak_users';
	}

}