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

namespace Akeeba\Engine\Test\Stub\Util;

use Akeeba\Engine\Util\HashTrait;

class HashTraitStub
{
	use HashTrait;

	public static function publicMd5($string, $binary = false)
	{
		return self::md5($string, $binary);
	}

	public static function publicSha1($string, $binary = false)
	{
		return self::sha1($string, $binary);
	}

	public static function publicMd5File($filename, $binary = false)
	{
		return self::md5_file($filename, $binary);
	}

	public static function publicSha1File($filename, $binary = false)
	{
		return self::sha1_file($filename, $binary);
	}
}
