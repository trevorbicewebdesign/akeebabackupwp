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
use Akeeba\Engine\Dump\Native\Pgsql\Adapter\EktelesiKelifos;

final class EktelesiKelifosTest extends AdapterTestCase
{
	protected function makeAdapter(): AdapterInterface
	{
		return new EktelesiKelifos();
	}

	/**
	 * Unlike the other adapters, the underlying function cannot recover an exit code.
	 * It always returns 0 when the command produces output, regardless of its exit code.
	 */
	public function testEktelesiAlwaysReturnsZeroWhenCommandProducesOutput(): void
	{
		$adapter = $this->makeAdapter();

		if (!$adapter->diathesimo())
		{
			$this->markTestSkipped('Adapter not available on this platform');
		}

		$output   = [];
		$exitCode = $adapter->ektelesi($this->failCommand(), $output);

		$this->assertSame(0, $exitCode);
	}

	/**
	 * When the underlying function returns null (command produces no output), the adapter
	 * must return -1 and leave $output as an empty array.
	 */
	public function testEktelesiReturnsNegativeOneWhenCommandProducesNoOutput(): void
	{
		$adapter = $this->makeAdapter();

		if (!$adapter->diathesimo())
		{
			$this->markTestSkipped('Adapter not available on this platform');
		}

		// A silent command with no stdout causes the underlying function to return null.
		$silentCommand = PHP_BINARY . ' -r "exit(1);"';
		$output        = [];
		$exitCode      = $adapter->ektelesi($silentCommand, $output);

		$this->assertSame(-1, $exitCode);
		$this->assertSame([], $output);
	}
}
