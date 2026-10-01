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

use Akeeba\Engine\Platform\Base as PlatformBase;

/**
 * Minimal platform stub for FileSystem tests.
 *
 * Provides a controlled set of stock directories for testing translateStockDirs
 * and rebaseFolderToStockDirs without hitting real filesystem paths.
 */
class FileSystemTestPlatform extends PlatformBase
{
	/** @var array The stock directories to return */
	private $stockDirectories = [];

	public function __construct(array $stockDirectories = [])
	{
		$this->platformName   = 'test';
		$this->priority       = 99;
		$this->stockDirectories = $stockDirectories;
	}

	public function get_stock_directories()
	{
		return $this->stockDirectories;
	}

	public function isThisPlatform()
	{
		return false;
	}

	public function save_configuration($profile_id = null)
	{
		return true;
	}

	public function load_configuration($profile_id = null, $reset = true)
	{
		return true;
	}

	public function get_site_root()
	{
		return '/var/www/html';
	}

	public function get_installer_images_path()
	{
		return '';
	}

	public function get_profile_name($id = null)
	{
		return 'Default';
	}

	public function get_backup_origin()
	{
		return 'backend';
	}

	public function get_timestamp_database($date = 'now')
	{
		return '2026-01-01 00:00:00';
	}

	public function get_local_timestamp($format)
	{
		return date($format);
	}

	public function get_host()
	{
		return 'localhost';
	}

	public function get_site_name()
	{
		return 'Test Site';
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

	public function get_running_backups($tag = null)
	{
		return [];
	}

	public function &get_valid_backup_records($useprofile = false, $tagFilters = [], $ordering = 'DESC')
	{
		$result = [];
		return $result;
	}

	public function remove_duplicate_backup_records($archivename)
	{
	}

	public function invalidate_backup_records($ids)
	{
	}

	public function get_valid_remote_records($profile = null, $engine = null)
	{
		return [];
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

	public function get_default_database_driver($use_platform = true)
	{
		return 'mysqli';
	}

	public function get_platform_database_options()
	{
		return [];
	}

	public function translate($key)
	{
		return $key;
	}

	public function load_version_defines()
	{
	}

	public function log_platform_special_directories()
	{
		return '';
	}

	public function get_platform_configuration_option($key, $default)
	{
		return $default;
	}

	public function get_administrator_emails()
	{
		return [];
	}

	public function send_email($to, $subject, $body, $attachFile = null)
	{
		return true;
	}

	public function unlink($file)
	{
		return true;
	}

	public function move($from, $to)
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

	public function delete_statistics($id)
	{
		return true;
	}

	public function getPlatformVersion()
	{
		return ['name' => 'Test', 'version' => '1.0'];
	}
}
