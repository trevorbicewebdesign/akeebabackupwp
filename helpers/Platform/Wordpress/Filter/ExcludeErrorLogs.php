<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engine\Filter;

use Akeeba\Engine\Factory;
use Akeeba\Engine\Filter\Base as FilterBase;

// Protection against direct access
defined('AKEEBAENGINE') or die();

/**
 * Automatically exclude error_log files from the backup set
 */
class ExcludeErrorLogs extends FilterBase
{
	public function __construct()
	{
		$this->object      = 'file';
		$this->subtype     = 'all';
		$this->method      = 'regex';
		$this->filter_name = 'ExcludeErrorLogs';

		// Get the site's root
		$configuration = Factory::getConfiguration();

		if ($configuration->get('akeeba.platform.override_root', 0))
		{
			$root = $configuration->get('akeeba.platform.newroot', '[SITEROOT]');
		}
		else
		{
			$root = '[SITEROOT]';
		}

		if (stristr($root, '['))
		{
			$root = Factory::getFilesystemTools()->translateStockDirs($root);
		}

		// We take advantage of the filter class magic to inject our custom filters
		$this->filter_data[$root] = [
			'#^error_log$#',
			'#/error_log$#',
			'#/debug\.log$#',
		];

		parent::__construct();
	}

}