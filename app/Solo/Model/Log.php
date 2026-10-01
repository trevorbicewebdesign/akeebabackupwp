<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Model;


use Akeeba\Engine\Factory;
use Awf\Mvc\Model;
use Awf\Text\Text;
use Solo\Helper\Utils;

class Log extends Model
{
	/**
	 * Finds the available log files in this backup profile's log directory
	 *
	 * @param   bool  $onlyFailed  Should I only return the log files of backups marked as failed?
	 *
	 * @return  array
	 */
	function getLogFiles($onlyFailed = false)
	{
		$configuration   = Factory::getConfiguration();
		$outputDirectory = $configuration->get('akeeba.basic.output_directory');

		$files = Factory::getFileLister()->getFiles($outputDirectory);
		$ret   = [];

		if (!empty($files) && is_array($files))
		{
			foreach ($files as $filename)
			{
				$baseName         = basename($filename);
				$startsWithAkeeba = substr($baseName, 0, 7) == 'akeeba.';
				$endsWithPhpLog   = substr($baseName, -8) == '.log.php';
				$endsWithLog      = !$endsWithPhpLog && substr($baseName, -4) == '.log';
				$endsWithPhp      = !$endsWithPhpLog && substr($baseName, -4) == '.php';
				$isDefaultLog     = in_array($baseName, ['akeeba.log', 'akeeba.log.php', 'akeeba.php']);

				if ($startsWithAkeeba && ($endsWithLog || $endsWithPhpLog || $endsWithPhp) && !$isDefaultLog)
				{
					/**
					 * Extract the tag from the filename (akeeba.tag.log, akeeba.tag.log.php or akeeba.tag.php)
					 *
					 * We ignore the first seven characters ("akeeba.") and the last X characters, where X is 8 if the
					 * log file name ends with .log.php or 4 if the log name ends with .log or .php.
					 */
					$tag = substr($baseName, 7, -($endsWithPhpLog ? 8 : 4));

					if (empty($tag))
					{
						continue;
					}

					$parts = explode('.', $tag);
					$key   = array_pop($parts);
					$key   = str_replace('id', '', $key);
					$key   = is_numeric($key) ? sprintf('%015u', $key) : $key;

					if (empty($parts))
					{
						$key = str_repeat('0', 15) . '.' . $key;
					}
					else
					{
						$key .= '.' . implode('.', $parts);
					}

					$ret[$key] = $tag;
				}
			}
		}

		if ($onlyFailed)
		{
			$ret = $this->keepOnlyFailedLogs($ret);
		}

		krsort($ret);

		return $ret;
	}

	/**
	 * Returns the options for the backup origin dropdown box in the log file display page
	 *
	 * @param   bool  $onlyFailed  Should I only return the log files of backups marked as failed?
	 *
	 * @return  array
	 */
	function getLogList($onlyFailed = false)
	{
		$options = [];

		$list = $this->getLogFiles($onlyFailed);

		if (!empty($list))
		{
			$options[] = $this->getContainer()->html->select->option( '', Text::_('COM_AKEEBA_LOG_CHOOSE_FILE_VALUE'));

			foreach ($list as $item)
			{
				$text = Text::_('COM_AKEEBA_BUADMIN_LABEL_ORIGIN_' . strtoupper($item));

				if (strstr($item, '.') !== false)
				{
					[$origin, $backupId] = explode('.', $item, 2);

					$text = Text::_('COM_AKEEBA_BUADMIN_LABEL_ORIGIN_' . strtoupper($origin)) . ' (' . $backupId . ')';
				}

				$options[] = $this->getContainer()->html->select->option( $item, $text);
			}
		}

		return $options;
	}

	/**
	 * Output the raw text log file to the standard output without the PHP die header
	 *
	 * @param   bool  $withHeader  Should I include a header telling the user how to submit this file?
	 *
	 * @return  void
	 */
	public function echoRawLog($withHeader = true)
	{
		$tag     = $this->getState('tag', '');
		$logFile = Utils::getLogFilePath($tag);

		if ($withHeader)
		{
			echo "WARNING: Do not copy and paste lines from this file!\r\n";
			echo "You are supposed to ZIP and attach it in your support forum post.\r\n";
			echo "If you fail to do so, we will be unable to provide efficient support.\r\n";
			echo "\r\n";
			echo "--- START OF RAW LOG --\r\n";
		}

		// The at sign (silence operator) is necessary to prevent PHP showing a warning if the file doesn't exist or
		// isn't readable for any reason.
		$fp = is_null($logFile) ? false : @fopen($logFile, 'r');

		if ($fp === false)
		{
			if ($withHeader)
			{
				echo "--- END OF RAW LOG ---\r\n";
			}

			return;
		}

		$firstLine = @fgets($fp);
		if (substr($firstLine, 0, 5) != ('<' . '?' . 'php'))
		{
			@fclose($fp);
			@readfile($logFile);
		}
		else
		{
			while (!feof($fp))
			{
				echo rtrim(fgets($fp)) . "\r\n";
			}

			@fclose($fp);
		}

		if ($withHeader)
		{
			echo "--- END OF RAW LOG ---\r\n";
		}
	}

	protected function keepOnlyFailedLogs($logs)
	{
		$db            = $this->container->db;
		$query         = $db->getQuery(true)
			->select([
				$db->quoteName('tag'),
				$db->quoteName('backupid'),
			])
			->from($db->quoteName('#__ak_stats'))
			->where($db->quoteName('status') . ' = ' . $db->quote('fail'));
		$failedBackups = $db->setQuery($query)->loadObjectList() ?: [];

		if (empty($failedBackups))
		{
			return [];
		}

		$failedBackups = array_map(function ($o) {
			$tag      = $o->tag ?? '';
			$backupId = $o->backupid ?? '';

			// Rebuild the on-disk log tag exactly as Kettenrad::getLogTag() composes it: origin, then
			// ".backupid" only when a backup ID is present. This must match the tags getLogFiles()
			// extracts from the akeeba.<tag>.log filenames, or the intersection below is always empty.
			return $tag . (empty($backupId) ? '' : '.' . $backupId);
		}, $failedBackups);

		return array_intersect($logs, $failedBackups);
	}

} 
