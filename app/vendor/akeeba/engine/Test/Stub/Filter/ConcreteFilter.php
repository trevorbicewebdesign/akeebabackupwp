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

namespace Akeeba\Engine\Test\Stub\Filter;

use Akeeba\Engine\Filter\Base;

/**
 * Minimal concrete subclass of Filter\Base for unit testing.
 *
 * Seeding filter data directly via seedFilterData() avoids any dependency on
 * Core\Filters or the Factory singleton.
 */
class ConcreteFilter extends Base
{
	/**
	 * Directly seed the internal filter data so tests don't need Core\Filters.
	 *
	 * @param   array  $data  Filter data keyed by root, e.g. ['[ROOT]' => ['/foo', '/bar']]
	 */
	public function seedFilterData(array $data)
	{
		$this->filter_data = $data;
	}
}
