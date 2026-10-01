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

namespace Akeeba\Engine\Test\Integration\Driver;

use Akeeba\Engine\Driver\Base;
use Akeeba\Engine\Driver\Mysqli;

/**
 * Integration tests for the MySQLi database driver.
 *
 * Run via run-integration-tests.sh, or manually with INTEGRATION_DB_HOST set:
 *   INTEGRATION_DB_HOST=127.0.0.1 INTEGRATION_DB_PORT=13306 \
 *   INTEGRATION_DB_USER=root INTEGRATION_DB_PASSWORD=secret \
 *   INTEGRATION_DB_NAME=akeebatest \
 *   vendor/bin/phpunit --configuration phpunit.integration.xml
 */
final class MysqliTest extends AbstractMysqlDriverTestCase
{
	protected static function createDriver(array $options)
	{
		if (!Mysqli::isSupported())
		{
			static::$skipReason = 'The mysqli PHP extension is not available.';

			return null;
		}

		return new Mysqli($options);
	}
}
