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
 * Exclude the subdirectories of the output directory
 */
class OutputSkipSubdirectories extends FilterBase
{
	public function __construct()
	{
		$this->object      = 'dir';
		$this->subtype     = 'children';
		$this->method      = 'direct';
		$this->filter_name = 'OutputSkipSubdirectories';

		// We take advantage of the filter class magic to inject our custom filters
		$configuration = Factory::getConfiguration();

		// Get the site's root
		if ($configuration->get('akeeba.platform.override_root', 0))
		{
			$root = $configuration->get('akeeba.platform.newroot', '[SITEROOT]');
		}
		else
		{
			$root = '[SITEROOT]';
		}

		$this->filter_data[$root] = [
			// Output & temp directory of the application
			$this->treatDirectory($configuration->get('akeeba.basic.output_directory')),
			// Default backup output directory
			$this->treatDirectory(APATH_BASE . '/backups'),
		];

		parent::__construct();
	}
}