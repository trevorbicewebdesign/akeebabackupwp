<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Alice\Check\Filesystem;

use Awf\Container\Container;
use Solo\Alice\Check\Base;
use Awf\Text\Text;

/**
 * Checks if the user is trying to backup multiple Joomla! installations with a single backup
 */
class MultipleSites extends Base
{
	public function __construct(Container $container, $logFile = null)
	{
		$this->priority         = 10;
		$this->checkLanguageKey = 'COM_AKEEBA_ALICE_ANALYZE_FILESYSTEM_MULTIPLE_SITES';

		parent::__construct($container, $logFile);
	}

	public function check()
	{
		$subfolders = [];
		$this->scanLines(function ($data) use (&$subfolders) {
			preg_match_all('#Adding\s(.*?)/administrator/index\.php to archive#i', $data, $matches);

			if (!$matches[1])
			{
				return;
			}

			$subfolders = array_merge($subfolders, $matches[1]);
		});

		if (empty($subfolders))
		{
			return;
		}

		$this->setResult(0);

		$this->setErrorLanguageKey([
			'COM_AKEEBA_ALICE_ANALYZE_FILESYSTEM_MULTIPLE_SITES_ERROR', implode("\n", $subfolders),
		]);
	}

	public function getSolution()
	{
		return Text::_('COM_AKEEBA_ALICE_ANALYZE_FILESYSTEM_MULTIPLE_SITES_SOLUTION');
	}
}
