<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2024 Nicholas K. Dionysopoulos / Akeeba Ltd
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

namespace Akeeba\Engine\Development\Shim;

use Akeeba\Engine\Factory;
use Akeeba\Engine\Postproc\Webdav;
use Composer\CaBundle\CaBundle;

class WebDAVShim extends Webdav
{
	protected function getSettings()
	{
		if (!defined('AKEEBA_CACERT_PEM'))
		{
			define('AKEEBA_CACERT_PEM', CaBundle::getBundledCaBundlePath());
		}

		$this->directory = ENGINE_DEV_WEBDAV_DIRECTORY;

		// Fix the directory name, if required
		$this->directory = empty($this->directory) ? '' : $this->directory;
		$this->directory = trim($this->directory);
		$this->directory = ltrim(Factory::getFilesystemTools()->TranslateWinPath($this->directory), '/');
		$this->directory = Factory::getFilesystemTools()->replace_archive_name_variables($this->directory);

		return [
			'baseUri'  => ENGINE_DEV_WEBDAV_ENDPOINT,
			'userName' => ENGINE_DEV_WEBDAV_USERNAME,
			'password' => ENGINE_DEV_WEBDAV_PASSWORD,
		];
	}

}