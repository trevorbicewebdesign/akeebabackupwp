<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Model;

use Akeeba\Engine\Factory;
use Akeeba\Engine\Platform;
use Awf\Container\Container;
use Awf\Mvc\Model;
use Awf\Pagination\Pagination;
use Awf\Text\Language;
use Awf\Text\Text;
use Exception;

class Manage extends Model
{
	/** @var   Pagination  The pagination model for this data */
	protected $pagination = null;

	public function __construct(?Container $container = null, ?Language $language = null)
	{
		parent::__construct($container, $language);

		$limit      = $this->getUserStateFromRequest('solo_limit', 'limit', 10, 'int');
		$limitStart = $this->getUserStateFromRequest('solo_manage_start', 'limitstart', 0, 'int');

		$this->setState('limit', $limit);
		$this->setState('limitStart', $limitStart);
	}

	/**
	 * Is this string a valid remote filename?
	 *
	 * We've had reports that some servers return a bogus, non-empty string for some remote_filename columns, causing
	 * the "Manage remote stored files" column to appear even for locally stored files. By applying more rigorous tests
	 * for the remote_filename column we can avoid this problem.
	 *
	 * @param   string|null  $filename
	 *
	 * @return  bool
	 *
	 * @since   7.6.4
	 */
	public function isRemoteFilename(?string $filename = null): bool
	{
		// A remote filename has to be a string which is does not consist solely of whitespace
		if (!is_string($filename) || trim($filename) === '')
		{
			return false;
		}

		// Let's remote whitespace just in case
		$filename = trim($filename);

		// A remote filename must be in the format engine://path
		if (strpos($filename, '://') === false)
		{
			return false;
		}

		// Get the engine and path
		[$engine, $path] = explode('://', $filename, 2);
		$engine = trim($engine);
		$path   = trim($path);

		// Both engine and path must be non-empty
		if (empty($engine) || empty($path))
		{
			return false;
		}

		// The engine must be known to the backup engine
		$classname = 'Akeeba\\Engine\\Postproc\\' . ucfirst($engine);

		return class_exists($classname);
	}

	/**
	 * Returns the same list as getStatisticsList(), but includes an extra field
	 * named 'meta' which categorises attempts based on their backup archive status
	 *
	 * @param   boolean  $overrideLimits  Should I override all list limits?
	 * @param   array    $filters         Filters to apply, see PlatformInterface::get_statistics_list
	 * @param   array    $order           Record ordering information (By and Ordering)
	 *
	 * @return  array  An array of backup attempt objects
	 */
	public function &getStatisticsListWithMeta($overrideLimits = false, $filters = null, $order = null)
	{
		$limitstart = $overrideLimits ? 0 : $this->getState('limitstart', 0);
		$limit      = $overrideLimits ? 0 : $this->getState('limit', 10);
		$filters    = $overrideLimits ? null : $filters;

		if (is_array($order) && isset($order['order']))
		{
			$order['order'] = strtoupper($order['order']) === 'ASC' ? 'asc' : 'desc';
		}

		$allStats = Platform::getInstance()->get_statistics_list([
			'limitstart' => $limitstart,
			'limit'      => $limit,
			'filters'    => $filters,
			'order'      => $order,
		]);

		$validRecords          = Platform::getInstance()->get_valid_backup_records() ?: [];
		$updateObsoleteRecords = [];
		$ret                   = array_map(function (array $stat) use (&$updateObsoleteRecords, $validRecords) {
			$hasRemoteFiles = false;

			// Translate backup status and the existence of a remote filename to the backup record's "meta" status.
			switch ($stat['status'])
			{
				case 'run':
					$stat['meta'] = 'pending';
					break;

				case 'fail':
					$stat['meta'] = 'fail';
					break;

				default:
					$hasRemoteFiles = $this->isRemoteFilename($stat['remote_filename']);
					$stat['meta']   = $hasRemoteFiles ? 'remote' : 'obsolete';
					break;
			}

			$stat['hasRemoteFiles'] = $hasRemoteFiles;

			// If the backup is reported to have files still stored on the server we need to investigate further
			if (in_array($stat['id'], $validRecords))
			{
				$archives      = Factory::getStatistics()->get_all_filenames($stat);
				$hasLocalFiles = (is_array($archives) ? count($archives) : 0) > 0;
				$stat['meta']  = $hasLocalFiles ? 'ok' : ($hasRemoteFiles ? 'remote' : 'obsolete');

				// The archives exist. Set $stat['size'] to the total size of the backup archives.
				if ($hasLocalFiles)
				{
					$stat['size'] = $stat['total_size']
						?: array_reduce(
							$archives,
							function ($carry, $filename) {
								return $carry += @filesize($filename) ?: 0;
							},
							0
						);

					return $stat;
				}

				// The archives do not exist or we can't find them. If the record says otherwise we need to update it.
				if ($stat['filesexist'])
				{
					$updateObsoleteRecords[] = $stat['id'];
				}

				// Does the backup record report a total size even though our files no longer exist?
				if ($stat['total_size'])
				{
					$stat['size'] = $stat['total_size'];
				}
			}

			return $stat;
		}, $allStats);

		// Update records which report that their files exist on the server but, in fact, they don't.
		Platform::getInstance()->invalidate_backup_records($updateObsoleteRecords);

		return $ret;
	}

	/**
	 * Delete the stats record whose ID is set in the model
	 *
	 * @return  boolean  True on success
	 *
	 * @throws  \RuntimeException
	 */
	public function delete()
	{
		$id = $this->getState('id', 0);

		if ((!is_numeric($id)) || ($id <= 0))
		{
			throw new \RuntimeException(Text::_('COM_AKEEBA_BUADMIN_ERROR_INVALIDID'), 500);
		}

		// Try to delete files
		$this->deleteFile();

		Platform::getInstance()->delete_statistics($id);

		return true;
	}

	/**
	 * Delete the backup file of the stats record whose ID is set in the model
	 *
	 * @return  boolean  True on success
	 *
	 * @throws  \RuntimeException
	 */
	public function deleteFile()
	{
		$id = $this->getState('id', 0);

		if ((!is_numeric($id)) || ($id <= 0))
		{
			throw new \RuntimeException(Text::_('COM_AKEEBA_BUADMIN_ERROR_INVALIDID'), 500);
		}

		$stat     = Platform::getInstance()->get_statistics($id);
		$allFiles = Factory::getStatistics()->get_all_filenames($stat, false);

		// Remove the custom log file if necessary
		if (!is_null($stat))
		{
			$this->_deleteLogs($stat);
		}

		// Make sure we have some files
		if (empty($allFiles))
		{
			return true;
		}

		// Get a reference to the filesystem abstraction
		$fs = $this->container->fileSystem;

		// Set the default status
		$status = true;

		// Delete all archive files
		foreach ($allFiles as $filename)
		{
			try
			{
				$fs->delete($filename);
			}
			catch (\Exception $e)
			{
				// Ignore file deletion failure
				$status = false;
			}
		}

		return $status;
	}

	/**
	 * Get a pagination object
	 *
	 * @param   array  $filters  Any filters to use
	 *
	 * @return  Pagination
	 */
	public function &getPagination($filters = null)
	{
		if (!is_object($this->pagination))
		{
			// Prepare pagination values
			$total      = Platform::getInstance()->get_statistics_count($filters);
			$limitStart = $this->getState('limitStart');
			$limit      = $this->getState('limit');

			// Create the pagination object
			$this->pagination = new Pagination($total, $limitStart, $limit, 10, $this->container);
		}

		return $this->pagination;
	}

	/**
	 * Gets the post-processing engine for each backup profile
	 *
	 * @return  array  Key/value where key=profile ID, value=post-processing engine
	 */
	public function getPostProcessingEnginePerProfile()
	{
		// Cache the current profile
		$currentProfileID = Platform::getInstance()->get_active_profile();

		$db = $this->container->db;

		$query = $db->getQuery(true)
			->select($db->qn('id'))
			->from($db->qn('#__ak_profiles'));
		$db->setQuery($query);
		$profiles = $db->loadColumn();

		$engines = [];

		foreach ($profiles as $profileID)
		{
			Platform::getInstance()->load_configuration($profileID);
			$pConf               = Factory::getConfiguration();
			$engines[$profileID] = $pConf->get('akeeba.advanced.postproc_engine');
		}

		Platform::getInstance()->load_configuration($currentProfileID);

		return $engines;
	}

	public function hideRestorationInstructionsModal()
	{
		$config = $this->container->appConfig;
		$config->set('options.show_howtorestoremodal', 0);
		$config->saveConfiguration();
	}

	/**
	 * Freeze or melt a backup report
	 *
	 * @param array $ids        Array of backup IDs that should be updated
	 * @param int   $freeze     1= freeze, 0= melt
	 *
	 * @throws Exception
	 */
	public function freezeUnfreezeRecords(array $ids, $freeze)
	{
		if (!$ids)
		{
			return;
		}

		$freeze = (int) $freeze;

		foreach ($ids as $id)
		{
			// If anything wrong happens, let the exception bubble up, so it will be reported
			Platform::getInstance()->set_or_update_statistics($id, ['frozen' => $freeze]);
		}
	}

	/**
	 * Deletes the backup-specific log files of a stats record
	 *
	 * @param   array  $stat  The array holding the backup stats record
	 *
	 * @return  void
	 */
	protected function _deleteLogs(array $stat)
	{
		// We can't delete logs if there is no backup ID in the record
		if (!isset($stat['backupid']) || empty($stat['backupid']))
		{
			return;
		}

		$fs           = $this->container->fileSystem;
		$logFileNames = [
			'akeeba.' . $stat['tag'] . '.' . $stat['backupid'] . '.log',
			'akeeba.' . $stat['tag'] . '.' . $stat['backupid'] . '.log.php',
		];

		foreach ($logFileNames as $logFileName)
		{
			$logPath = dirname($stat['absolute_path']) . '/' . $logFileName;

			try
			{
				$fs->delete($logPath);
			}
			catch (\Exception $e)
			{
				// Ignore file deletion failure
			}
		}
	}
}
