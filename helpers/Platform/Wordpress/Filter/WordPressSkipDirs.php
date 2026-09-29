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
 * WordPress-specific Filter: Skip Directories
 *
 * Exclude subdirectories of special directories
 */
class WordPressSkipDirs extends FilterBase
{	
	public function __construct()
	{
		$this->object	= 'dir';
		$this->subtype	= 'children';
		$this->method	= 'direct';
		$this->filter_name = 'WordPressSkipDirs';

		$configuration = Factory::getConfiguration();

		$root = '[SITEROOT]';

		if ($configuration->get('akeeba.platform.override_root', 0))
		{
			$root = $configuration->get('akeeba.platform.newroot', '[SITEROOT]');
		}

		$this->filter_data[$root] = [
			/**
			 * Cache directory.
			 *
			 * In theory, you can use whichever directory you want as cache in WordPress. You just enable WP_CACHE which
			 * tells WordPress to look for a specific file which will be handling caching. In practice, most of the time
			 * the cache folder will default to wp-content/cache. If the user chose a different directory it is up to
			 * them to exclude the cache directory.
			 */
			'wp-content/cache',
			/**
			 * Divi-specific cache folder.
			 *
			 * If this folder is included in the backup the frontend appears broken until you regenerate the Divi
			 * template. Excluding this folder forces Divi to regenerate the template, working around this issue.
			 */
			'wp-content/et-cache',
		];

		parent::__construct();
	}
}
