<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Dependencies\Cron;

interface FieldFactoryInterface
{
    public function getField(int $position): FieldInterface;
}
