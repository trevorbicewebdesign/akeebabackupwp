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
use Solo\Helper\HashHelper;

/**
 * Checks if the user is trying to backup old backups
 */
class OldBackups extends Base
{
	public function __construct(Container $container, $logFile = null)
	{
		$this->priority         = 40;
		$this->checkLanguageKey = 'COM_AKEEBA_ALICE_ANALYZE_FILESYSTEM_OLD_BACKUPS';

		parent::__construct($container, $logFile);
	}

	public function check()
	{
		$bigfiles = [];

		$this->scanLines(function ($data) use (&$bigfiles) {
			// Only looking files with extensions like .jpa, .jps, .j01, .j02, ..., .j99, .j100, ..., .j99999, .z01, ...
			preg_match_all('#-- Adding.*? <root>/(.*?)(\.(?:jpa|jps|j\d{2,5}|z\d{2,5}))#i', $data, $tmp_matches);

			if (!isset($tmp_matches[1]) || !$tmp_matches[1])
			{
				return;
			}

			// Record valid matches only
			for ($i = 0; $i < count($tmp_matches[1]); $i++)
			{
				// Get flagged files only once
				$key = HashHelper::md5($tmp_matches[1][$i] . $tmp_matches[2][$i]);

				if (isset($bigfiles[$key]))
				{
					continue;
				}

				$filename = $tmp_matches[1][$i] . $tmp_matches[2][$i];
				$filePath = $this->container->basePath . '/' . $filename;
				$fileSize = 0;

				if (@file_exists($filePath) && @is_file($filePath))
				{
					$fileSize = @filesize($filePath);
				}

				if ($fileSize > 1048576)
				{
					$bigfiles[$key] = [
						'filename' => $filename,
					];
				}
			}
		});

		if (empty($bigfiles))
		{
			return;
		}

		$errorMsg = [];

		$this->setResult(-1);

		foreach ($bigfiles as $bad)
		{
			$errorMsg[] = 'File: ' . $bad['filename'];
		}

		$this->setErrorLanguageKey([
			'COM_AKEEBA_ALICE_ANALYZE_FILESYSTEM_OLD_BACKUPS_ERROR', implode("\n", $errorMsg),
		]);
	}

	public function getSolution()
	{
		return Text::_('COM_AKEEBA_ALICE_ANALYZE_FILESYSTEM_OLD_BACKUPS_SOLUTION');
	}
}
