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
use Akeeba\Engine\Driver\Pdomysql;

/**
 * Integration tests for the PDO MySQL database driver.
 *
 * Run via run-integration-tests.sh, or manually with INTEGRATION_DB_HOST set:
 *   INTEGRATION_DB_HOST=127.0.0.1 INTEGRATION_DB_PORT=13306 \
 *   INTEGRATION_DB_USER=root INTEGRATION_DB_PASSWORD=secret \
 *   INTEGRATION_DB_NAME=akeebatest \
 *   vendor/bin/phpunit --configuration phpunit.integration.xml
 */
final class PdomysqlTest extends AbstractMysqlDriverTestCase
{
	protected static function createDriver(array $options)
	{
		if (!Pdomysql::isSupported())
		{
			static::$skipReason = 'PDO with the MySQL driver is not available.';

			return null;
		}

		return new Pdomysql($options);
	}
}
