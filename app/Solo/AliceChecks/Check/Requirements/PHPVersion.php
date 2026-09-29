<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Alice\Check\Requirements;

use Awf\Container\Container;
use Solo\Alice\Check\Base;
use Solo\Alice\Exception\StopScanningEarly;
use Awf\Text\Text;

/**
 * Checks if the user is using a too old or too new PHP version
 */
class PHPVersion extends Base
{
	public function __construct(Container $container, $logFile = null)
	{
		$this->priority         = 10;
		$this->checkLanguageKey = 'COM_AKEEBA_ALICE_ANALYZE_REQUIREMENTS_PHP_VERSION';

		parent::__construct($container, $logFile);
	}

	public function check()
	{
		$this->scanLines(function ($line) {
			$pos = strpos($line, '|PHP Version');

			if ($pos === false)
			{
				return;
			}

			$version = trim(substr($line, strpos($line, ':', $pos) + 1));

			// PHP too old (well, this should never happen)
			if (version_compare($version, '5.6', 'lt'))
			{
				$this->setResult(-1);
				$this->setErrorLanguageKey([
					'COM_AKEEBA_ALICE_ANALYZE_REQUIREMENTS_PHP_VERSION_ERR_TOO_OLD',
				]);
			}

			throw new StopScanningEarly();
		});
	}

	public function getSolution()
	{
		return Text::_('COM_AKEEBA_ALICE_ANALYZE_REQUIREMENTS_PHP_VERSION_SOLUTION');
	}
}
