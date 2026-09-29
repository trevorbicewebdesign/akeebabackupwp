<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Alice\Check\Runtimeerrors;

use Awf\Container\Container;
use Solo\Alice\Check\Base;
use Solo\Alice\Exception\StopScanningEarly;
use Awf\Text\Text;

/**
 * Checks if a fatal error occurred during the backup process
 */
class FatalError extends Base
{
	public function __construct(Container $container, $logFile = null)
	{
		$this->priority         = 110;
		$this->checkLanguageKey = 'COM_AKEEBA_ALICE_ANALYZE_RUNTIME_ERRORS_FATALERROR';

		parent::__construct($container, $logFile);
	}

	public function check()
	{
		$this->scanLines(function ($data) {
			preg_match('#ERROR   \|.*?\|(.*)#', $data, $tmp_matches);

			if (!isset($tmp_matches[1]))
			{
				return;
			}

			$error = $tmp_matches[1];

			if (empty($error))
			{
				return;
			}

			$this->setResult(-1);
			$this->setErrorLanguageKey(['COM_AKEEBA_ALICE_ANALYZE_RUNTIME_ERRORS_FATALERROR_ERROR', $error]);

			throw new StopScanningEarly();
		});
	}

	public function getSolution()
	{
		return Text::_('COM_AKEEBA_ALICE_ANALYZE_RUNTIME_ERRORS_FATALERROR_SOLUTION');
	}
}
