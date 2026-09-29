<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engine\Platform;

use Akeeba\Engine\Platform\Base;

class Dummy extends Base
{
	private $flashVariables = [];

	public function set_flash_variable($name, $value)
	{
		$this->flashVariables[$name] = $value;
	}

	public function get_flash_variable($name, $default = null)
	{
		if (!isset($this->flashVariables[$name]))
		{
			return $default;
		}

		$value = $this->flashVariables[$name] ?? $default;

		unset($this->flashVariables[$name]);

		return $value;
	}

	public function redirect($url)
	{
		// Does nothing.
	}

	public function get_backup_origin()
	{
		return 'cli';
	}

	public function get_host()
	{
		return 'nothing.invalid';
	}

	public function get_platform_configuration_option($key, $default)
	{
		return $default;
	}
}