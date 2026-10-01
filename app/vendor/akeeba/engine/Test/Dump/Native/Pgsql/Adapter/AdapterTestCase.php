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

namespace Akeeba\Engine\Test\Dump\Native\Pgsql\Adapter;

use Akeeba\Engine\Dump\Native\Pgsql\Adapter\AdapterInterface;
use PHPUnit\Framework\TestCase;

abstract class AdapterTestCase extends TestCase
{
	abstract protected function makeAdapter(): AdapterInterface;

	/**
	 * Returns a shell command that exits 0 and prints "hello" to stdout.
	 */
	protected function successCommand(): string
	{
		return PHP_BINARY . ' -r "echo \'hello\';"';
	}

	/**
	 * Returns a shell command that prints "fail" to stdout and exits with code 42.
	 */
	protected function failCommand(): string
	{
		return PHP_BINARY . ' -r "echo \'fail\'; exit(42);"';
	}

	public function testDiathesimoReturnsBool(): void
	{
		$this->assertIsBool($this->makeAdapter()->diathesimo());
	}

	public function testDiathesimoIsTrueOnThisPlatform(): void
	{
		$this->assertTrue(
			$this->makeAdapter()->diathesimo(),
			get_class($this->makeAdapter()) . '::diathesimo() must be true in this test environment'
		);
	}

	public function testEktelesiSuccessfulCommandReturnsZero(): void
	{
		$adapter = $this->makeAdapter();

		if (!$adapter->diathesimo())
		{
			$this->markTestSkipped('Adapter not available on this platform');
		}

		$output   = [];
		$exitCode = $adapter->ektelesi($this->successCommand(), $output);

		$this->assertSame(0, $exitCode);
	}

	public function testEktelesiSuccessfulCommandPopulatesOutput(): void
	{
		$adapter = $this->makeAdapter();

		if (!$adapter->diathesimo())
		{
			$this->markTestSkipped('Adapter not available on this platform');
		}

		$output = [];
		$adapter->ektelesi($this->successCommand(), $output);

		$this->assertIsArray($output);
		$this->assertNotEmpty($output);
	}

	public function testEktelesiSuccessfulCommandOutputContainsExpectedText(): void
	{
		$adapter = $this->makeAdapter();

		if (!$adapter->diathesimo())
		{
			$this->markTestSkipped('Adapter not available on this platform');
		}

		$output = [];
		$adapter->ektelesi($this->successCommand(), $output);

		$this->assertStringContainsString('hello', implode("\n", $output));
	}
}
