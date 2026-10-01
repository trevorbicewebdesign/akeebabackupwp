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

namespace Akeeba\Engine\Test\Util;

use Akeeba\Engine\Test\AbstractEngineTestCase;
use Akeeba\Engine\Util\ConfigurationCheck;
use ReflectionClass;

final class ConfigurationCheckTest extends AbstractEngineTestCase
{
	/**
	 * Invokes the private getExpandedOutputDirectory() helper via reflection.
	 */
	private function invokeGetExpandedOutputDirectory(ConfigurationCheck $check): string
	{
		$rc     = new ReflectionClass(ConfigurationCheck::class);
		$method = $rc->getMethod('getExpandedOutputDirectory');

		return $method->invoke($check);
	}

	/**
	 * Installs a deprecation collector and returns a closure that asserts none were raised.
	 */
	private function assertNoDeprecations(): callable
	{
		$deprecations = [];

		set_error_handler(
			function (int $errno, string $errstr) use (&$deprecations) {
				if ($errno === E_DEPRECATED)
				{
					$deprecations[] = $errstr;
				}

				return true;
			}
		);

		return function () use (&$deprecations) {
			restore_error_handler();

			$this->assertSame([], $deprecations, 'Unexpected PHP deprecation notice(s) raised: ' . implode('; ', $deprecations));
		};
	}

	/**
	 * Reproduces the bug reported against Akeeba Backup for Joomla ticket #43211: when
	 * 'akeeba.basic.output_directory' is null (e.g. imported from a configuration set that never
	 * defined it), expanding the platform's stock directory tokens into that null value used to pass
	 * null to str_replace(), which is deprecated since PHP 8.4.
	 */
	public function testGetExpandedOutputDirectoryWithNullConfigValueDoesNotDeprecate(): void
	{
		// Store an explicit null (bypassing token processing so it is stored verbatim)
		$this->conf()->set('akeeba.basic.output_directory', null, false);

		$check = new ConfigurationCheck();

		$assert = $this->assertNoDeprecations();

		$result = $this->invokeGetExpandedOutputDirectory($check);

		$assert();

		$this->assertSame('', $result);
	}

	public function testGetExpandedOutputDirectoryExpandsStockDirectoryTokens(): void
	{
		// TestPlatform maps [SITEROOT] => sys_get_temp_dir()
		$this->conf()->set('akeeba.basic.output_directory', '[SITEROOT]/backups', false);

		$check  = new ConfigurationCheck();
		$result = $this->invokeGetExpandedOutputDirectory($check);

		$this->assertSame(sys_get_temp_dir() . '/backups', $result);
	}

	private function conf()
	{
		return \Akeeba\Engine\Factory::getConfiguration();
	}
}
