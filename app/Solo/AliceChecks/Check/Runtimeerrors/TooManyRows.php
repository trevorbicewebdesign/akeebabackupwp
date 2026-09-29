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
 * Checks if the user is trying to backup tables with too many rows, causing the system to fail
 */
class TooManyRows extends Base
{
	public function __construct(Container $container, $logFile = null)
	{
		$this->priority         = 50;
		$this->checkLanguageKey = 'COM_AKEEBA_ALICE_ANALYZE_RUNTIME_ERRORS_TOOMANYROWS';

		parent::__construct($container, $logFile);
	}

	public function check()
	{
		$tables    = [];
		$row_limit = 1000000;

		$this->scanLines(function ($data) use (&$tables, &$row_limit) {
			// Let's save every scanned table
			preg_match_all('#Continuing dump of (.*?) from record \#(\d+)#i', $data, $matches);

			if (!isset($matches[1]) || empty($matches[1]))
			{
				return;
			}

			for ($i = 0; $i < count($matches[1]); $i++)
			{
				if ($matches[2][$i] >= $row_limit)
				{
					$table          = trim($matches[1][$i]);
					$tables[$table] = $matches[2][$i];
				}
			}
		});

		if (!count($tables))
		{
			return;
		}

		$errorMsg = [];

		foreach ($tables as $table => $rows)
		{
			$errorMsg[] = sprintf(
				"%s %d %s %s",
				Text::_('COM_AKEEBA_ALICE_ANALYZE_RUNTIME_ERRORS_TOOMANYROWS_TABLE'),
				$table,
				number_format((float) $rows),
				Text::_('COM_AKEEBA_ALICE_ANALYZE_RUNTIME_ERRORS_TOOMANYROWS_ROWS'
			));
		}

		// Let's raise only a warning, maybe the server is powerful enough to dump huge tables and the problem is somewhere else
		$this->setResult(0);
		$this->setErrorLanguageKey([
			'COM_AKEEBA_ALICE_ANALYZE_RUNTIME_ERRORS_TOOMANYROWS_ERROR', implode("\n", $errorMsg),
		]);
	}

	public function getSolution()
	{
		return Text::_('COM_AKEEBA_ALICE_ANALYZE_RUNTIME_ERRORS_TOOMANYROWS_SOLUTION');
	}
}
