<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License version 3, or later
 *
 * This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, version 3.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program. If not, see
 * <https://www.gnu.org/licenses/>.
 */

namespace Akeeba\Engine\Test\Stub\Platform;

use Akeeba\Engine\Platform\Base;

class TestPlatform extends Base
{
	public $platformName = 'test';

	public $priority = 1;

	/**
	 * Platform configuration options returned by get_platform_configuration_option().
	 *
	 * Tests can seed these (e.g. the Akeeba Download ID some post-processing engines require) via
	 * setConfigurationOption().
	 *
	 * @var array<string,mixed>
	 */
	private $configurationOptions = [];

	public function __construct()
	{
		$this->platformName = 'test';
		$this->priority     = 1;
	}

	/**
	 * Seed a platform configuration option for the duration of a test.
	 *
	 * @param   string  $key    The option name (e.g. 'update_dlid').
	 * @param   mixed   $value  The value to return from get_platform_configuration_option().
	 *
	 * @return  void
	 */
	public function setConfigurationOption($key, $value)
	{
		$this->configurationOptions[$key] = $value;
	}

	public function isThisPlatform()
	{
		return true;
	}

	public function get_stock_directories()
	{
		return [
			'[SITEROOT]'   => sys_get_temp_dir(),
			'[ROOTPARENT]' => sys_get_temp_dir(),
		];
	}

	public function get_site_root()
	{
		return sys_get_temp_dir();
	}

	public function getPlatformDirectories()
	{
		return [];
	}

	public function get_platform_configuration_option($key, $default)
	{
		return $this->configurationOptions[$key] ?? $default;
	}

	public function translate($key)
	{
		return $key;
	}

	public function get_active_profile()
	{
		return 1;
	}

	public function get_backup_origin()
	{
		return 'cli';
	}

	public function load_configuration($profile_id = null, $reset = true)
	{
		return true;
	}

	public function save_configuration($profile_id = null)
	{
		return true;
	}

	public function &load_filters()
	{
		$result = [];

		return $result;
	}

	public function save_filters(&$filter_data)
	{
		return true;
	}

	public function set_or_update_statistics($id = null, $data = [])
	{
		return null;
	}

	public function get_statistics($id)
	{
		return [];
	}

	public function &get_statistics_list($config = [])
	{
		$result = [];

		return $result;
	}

	public function get_statistics_count($filters = null)
	{
		return 0;
	}

	public function &get_valid_backup_records($useprofile = false, $tagFilters = [], $ordering = 'DESC')
	{
		$result = [];

		return $result;
	}

	public function get_running_backups($tag = null)
	{
		return [];
	}

	public function delete_statistics($id)
	{
		return true;
	}

	public function invalidate_backup_records($ids)
	{
		return true;
	}

	public function set_flash_variable($name, $value)
	{
	}

	public function get_flash_variable($name, $default = null)
	{
		return $default;
	}

	public function redirect($url)
	{
	}
}
