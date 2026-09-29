<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Alice\Check\Runtimeerrors;

use Awf\Container\Container;
use Solo\Alice\Check\Base;
use Awf\Text\Text;

/**
 * Checks if error logs are included inside the backup. Since their size grows while we're trying to backup them,
 * this could led to corrupted archives.
 */
class ErrorLogsInArchive extends Base
{
	public function __construct(Container $container, $logFile = null)
	{
		$this->priority         = 80;
		$this->checkLanguageKey = 'COM_AKEEBA_ALICE_ANALYZE_RUNTIME_ERRORS_ERRORFILES';

		parent::__construct($container, $logFile);
	}

	public function check()
	{
		$error_files = [];

		$this->scanLines(function ($data) use (&$error_files) {
			preg_match_all('#Adding(.*?(/php_error_cpanel\.|php_error_cpanel\.|/error_)log)#', $data, $tmp_matches);

			if (isset($tmp_matches[1]))
			{
				$error_files = array_merge($error_files, $tmp_matches[1]);
			}
		});

		if (empty($error_files))
		{
			return;
		}

		$this->setResult(-1);
		$this->setErrorLanguageKey([
			'COM_AKEEBA_ALICE_ANALYZE_RUNTIME_ERRORS_ERRORFILES_FOUND', implode("\n", $error_files),
		]);
	}

	public function getSolution()
	{
		return Text::_('COM_AKEEBA_ALICE_ANALYZE_RUNTIME_ERRORS_ERRORFILES_SOLUTION');
	}
}
