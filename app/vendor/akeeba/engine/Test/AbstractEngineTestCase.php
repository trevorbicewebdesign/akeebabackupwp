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

namespace Akeeba\Engine\Test;

use Akeeba\Engine\Factory;
use Akeeba\Engine\Platform;
use Akeeba\Engine\Test\Stub\Platform\TestPlatform;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

abstract class AbstractEngineTestCase extends TestCase
{
	/** @var TestPlatform */
	private static $testPlatformInstance;

	protected function setUp(): void
	{
		parent::setUp();

		Factory::nuke();

		$this->resetPlatformStatics();
		$this->injectTestPlatform();
	}

	protected function tearDown(): void
	{
		Factory::nuke();
		$this->resetPlatformStatics();

		parent::tearDown();
	}

	protected function platform(): TestPlatform
	{
		return self::$testPlatformInstance;
	}

	private function resetPlatformStatics(): void
	{
		$rc = new ReflectionClass(Platform::class);

		$instanceProp = $rc->getProperty('instance');
		$instanceProp->setAccessible(true);
		$instanceProp->setValue(null, null);

		$connectorProp = $rc->getProperty('platformConnectorInstance');
		$connectorProp->setAccessible(true);
		$connectorProp->setValue(null, null);

		$directoriesProp = $rc->getProperty('knownPlatformsDirectories');
		$directoriesProp->setAccessible(true);
		$directoriesProp->setValue(null, []);
	}

	private function injectTestPlatform(): void
	{
		self::$testPlatformInstance = new TestPlatform();

		$rc = new ReflectionClass(Platform::class);

		$connectorProp = $rc->getProperty('platformConnectorInstance');
		$connectorProp->setAccessible(true);
		$connectorProp->setValue(null, self::$testPlatformInstance);

		// Create a Platform wrapper and inject it as the singleton instance
		$instanceProp = $rc->getProperty('instance');
		$instanceProp->setAccessible(true);

		// We need a Platform object without triggering auto-detection.
		// We use reflection to bypass the constructor.
		$platformInstance = $rc->newInstanceWithoutConstructor();
		$instanceProp->setValue(null, $platformInstance);
	}
}
