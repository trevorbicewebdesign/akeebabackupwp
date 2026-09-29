<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Model;

use Akeeba\Engine\Factory;
use Akeeba\Engine\Platform;
use AkeebaBackupWP;
use Awf\Mvc\Model;
use Awf\Text\Text;
use RuntimeException;

/**
 * Model for migrating backup archives and backup profiles in Akeeba Backup for WordPress version 8 and later.
 *
 * We are handling backup profiles whose output directory falls under one of the following categories:
 * - Default output directory (moved from wp-content/plugins/akeebabackupwp/app/backups to wp-content/backups).
 * - Default output directory, but NOT using the `[DEFAULT_OUTPUT]` variable.
 * - Under the plugin's root folder (wp-content/plugins/akeebabackupwp).
 *
 * The backup archives are moved respectively to:
 * - The new default output folder (wp-content/backups).
 * - The new default output folder (wp-content/backups).
 * - A folder under the default output folder (e.g. wp-content/plugins/akeebabackupwp/some/folder to
 *   wp-content/backups/some/folder).
 *
 * The backup profiles are updated respectively as follows:
 * - No change.
 * - Changed to `[DEFAULT_OUTPUT]`
 * - Output directory changed to something like `[DEFAULT_OUTPUT/some/folder]`.
 *
 * @since  8.1.0
 */
class Migreight extends Model
{
	/**
	 * Returns a list of profile IDs which need their output directories migrated.
	 *
	 * @return  array
	 * @since   8.1.0
	 */
	public function getAffectedProfiles(): array
	{
		// Get a list of backup profiles
		$db       = $this->container->db;
		$query    = $db->getQuery(true)->select(
			[
				$db->quoteName('id'),
				$db->quoteName('description'),
			]
		)->from($db->quoteName('#__ak_profiles'));
		$profiles = $db->setQuery($query)->loadAssocList('id', 'description');

		// Normally this should never happen as we're supposed to have at least profile #1
		if (empty($profiles))
		{
			return [];
		}

		// Temporarily store the backup profile
		$currentProfile = Platform::getInstance()->get_active_profile();

		$profiles = array_filter(
			$profiles, function (int $profileId): bool {
			Platform::getInstance()->load_configuration($profileId);
			$config             = Factory::getConfiguration();
			$rawDirectory       = $config->get('akeeba.basic.output_directory', '[DEFAULT_OUTPUT]', false);
			$processedDirectory = $config->get('akeeba.basic.output_directory', '[DEFAULT_OUTPUT]', true);

			/**
			 * The backup profile uses the `[DEFAULT_OUTPUT]` variable whose semantics changed in Akeeba Backup 8.
			 *
			 * We do not need to migrate the profile, but we _might_ have to migrate the backup archives.
			 */
			if (empty($rawDirectory) || $rawDirectory === '[DEFAULT_OUTPUT]')
			{
				return false;
			}

			// The translated absolute directory is under the plugin's root
			$relativeDirectory = $this->getFolderUnderPlugin($processedDirectory);

			return !empty($relativeDirectory);
		}, ARRAY_FILTER_USE_KEY
		);

		// Go back to the default profile
		Platform::getInstance()->load_configuration($currentProfile);

		return $profiles;
	}

	/**
	 * Get the backup archive IDs which need to be migrated, along with the from and two path, and the number of parts.
	 *
	 * @return  array
	 * @since   8.1.0
	 */
	public function getTranslatedArchives(): array
	{
		$folders = $this->getArchiveFolderMap();

		if (empty($folders))
		{
			return [];
		}

		// Get a list of backup archives which might need to be migrated
		$db       = $this->container->db;
		$query    = $db->getQuery(true)->select(
			[
				$db->quoteName('id'),
				$db->quoteName('absolute_path', 'from'),
				$db->quoteName('multipart'),
			]
		)->from($db->quoteName('#__ak_stats'))->where(
			[
				$db->quoteName('status') . ' = ' . $db->quote('complete'),
				$db->quoteName('filesexist') . ' = 1',
			]
		);
		$archives = $db->setQuery($query)->loadObjectList('id');

		// Get backup records falsely stating their files exist and update them (otherwise the migration is never done)
		$invalidRecordIDs = array_values(
			array_map(
				fn($x) => $x->id,
				array_filter(
					$archives, fn($arc) => in_array(dirname($arc->from), array_keys($folders)) && !file_exists($arc->from)
				)
			)
		);

		if (!empty($invalidRecordIDs))
		{
			$this->markInvalidRecords($invalidRecordIDs);
		}

		// Get the archives to migrate
		$archives = array_filter(
			$archives, fn($arc) => in_array(dirname($arc->from), array_keys($folders)) && @file_exists($arc->from)
		);

		return array_map(
			function (object $arc) use ($folders): object {
				$arc->multipart = max(1, (int) $arc->multipart);
				$arc->id        = (int) $arc->id;
				$arc->to        = $arc->from;

				foreach ($folders as $from => $to)
				{
					if (!str_starts_with($arc->from, $from))
					{
						continue;
					}

					$arc->to = dirname(
						rtrim(WP_CONTENT_DIR, '/\\') . '/backups/' . ltrim(
							substr($arc->from, strlen($from)) . (empty($to) ? '' : '/' . $to), '/\\'
						)
					);
					break;
				}

				return $arc;
			}, $archives
		);
	}

	/**
	 * Get the mapping of folders used in the stats table in need of migration to their folder relative to default
	 * output
	 *
	 * @return  array
	 * @@since  8.1.0
	 */
	public function getArchiveFolderMap(): array
	{
		// Get a list of backup archives which might need to be migrated
		$db       = $this->container->db;
		$query    = $db->getQuery(true)->select(
			[
				$db->quoteName('id'),
				$db->quoteName('absolute_path'),
			]
		)->from($db->quoteName('#__ak_stats'))->where(
			[
				$db->quoteName('status') . ' = ' . $db->quote('complete'),
				$db->quoteName('filesexist') . ' = 1',
			]
		);
		$archives = $db->setQuery($query)->loadAssocList('id', 'absolute_path');

		$folders  = array_map(fn($x) => dirname($x), $archives);
		$folders  = array_unique($folders);
		$mappedTo = array_map([$this, 'getFolderUnderPlugin'], $folders);
		$folders  = array_combine($folders, $mappedTo);
		$folders  = array_map(
		// Catch legacy default output
			fn($to) => ($to === 'app/backups') ? '' : $to, array_filter($folders, fn($x) => $x !== null)
		);

		arsort($folders);

		return $folders;
	}

	/**
	 * Backup profile migration.
	 *
	 * Changes the backup output directory of the affected backup profiles.
	 *
	 * @return  void
	 * @since   8.1.0
	 */
	public function migrateProfiles()
	{
		$profiles = $this->getAffectedProfiles();

		// Temporarily store the backup profile
		$currentProfile = Platform::getInstance()->get_active_profile();

		foreach ($profiles as $profileId => $relativeFolder)
		{
			Platform::getInstance()->load_configuration($profileId);
			$config = Factory::getConfiguration();

			$config->set(
				'akeeba.basic.output_directory',
				'[DEFAULT_OUTPUT]' . (empty($relativeFolder) ? '' : '/' . $relativeFolder)
			);

			Platform::getInstance()->save_configuration($profileId);
		}

		// Go back to the default profile
		Platform::getInstance()->load_configuration($currentProfile);
	}

	/**
	 * Backup archive migration.
	 *
	 * Moves backup archive files from one directory to another
	 *
	 * @return  void
	 * @throws  RuntimeException
	 * @since   8.1.0
	 */
	public function migrateArchives()
	{
		// Get the archives to migrate, and initialise internal variables
		$archives      = $this->getTranslatedArchives();
		$failedFolders = [];
		$updateStats   = [];

		// Go through all archive definitions
		foreach ($archives as $definition)
		{
			// Collect basic information
			$statId     = $definition->id;
			$sourceFile = $definition->from;
			$destFolder = $definition->to;
			$partCount  = $definition->multipart;

			// If we already know the destination folder cannot be created, skip over this
			if (in_array($destFolder, $failedFolders))
			{
				continue;
			}

			// Create a list of files to migrate, based on the record's multipart status
			$files = [
				$sourceFile,
			];

			if ($partCount > 1)
			{
				for ($i = 1; $i < $partCount; $i++)
				{
					$files[] = substr($sourceFile, 0, -2) . sprintf('%02u', $i);
				}
			}

			// Try to create the new output folder if needed
			if (!@is_dir($destFolder))
			{
				$created = @mkdir($destFolder, 0755, true);

				// We could not create the folder. Enqueue a warning and skip over this record.
				if (!$created)
				{
					$failedFolders[] = $destFolder;

					$this->container->application->enqueueMessage(
						Text::sprintf('COM_AKEEBA_MIGREIGHT_CANNOT_CREATE_FOLDER', htmlentities($destFolder)),
						'warning'
					);

					continue;
				}
			}

			// Move each and every file, if it exists
			foreach ($files as $fromPath)
			{
				if (!is_file($fromPath))
				{
					continue;
				}

				$toPath = rtrim($destFolder, '/\\') . '/' . basename($fromPath);

				if (is_file($toPath) && @filesize($toPath) !== @filesize($fromPath))
				{
					@unlink($toPath);
				}

				@rename($fromPath, $toPath);
			}

			// Finally, make a note that the stat record needs to be updated.
			$updateStats[$statId] = rtrim($destFolder, '/\\') . '/' . basename($fromPath);
		}

		// Start a transaction to make record updates faster.
		$db = $this->container->db;

		if (count($updateStats))
		{
			$db->transactionStart();
		}

		// Go through each record we have to update and issue the SQL command to do so.
		foreach ($updateStats as $statId => $newAbsolutePath)
		{
			$query = $db->getQuery(true)
				->update($db->quoteName('#__ak_stats'))
				->set($db->quoteName('absolute_path') . ' = ' . $db->quote($newAbsolutePath))
				->where($db->quoteName('id') . ' = ' . (int) $statId);
			try
			{
				$db->setQuery($query)->execute();
			}
			catch (\Exception $e)
			{
				// Failure is always an option
			}
		}

		// Finally, commit the transaction.
		if (count($updateStats))
		{
			$db->transactionCommit();
		}

		// If we had failed to create any folder, communicate that to the Controller
		if (!empty($failedFolders))
		{
			throw new RuntimeException('', 8087);
		}
	}

	/**
	 * Get the relative folder under the plugin's root folder for the given output directory.
	 *
	 * @param   string  $outputDirectory
	 *
	 * @return  string|null|false  NULL if it's not under the root folder, false if it collapses to `[DEFAULT_OUTPUT]`,
	 *                            relative folder otherwise.
	 *
	 * @since   8.1.0
	 */
	private function getFolderUnderPlugin(string $outputDirectory): ?string
	{
		// The output directory is empty, or [DEFAULT_OUTPUT]
		if (empty($outputDirectory) || $outputDirectory === '[DEFAULT_OUTPUT]')
		{
			return false;
		}

		$realOutputDirectory = @realpath($outputDirectory);

		// The output directory does not resolve, therefore it collapses to `[DEFAULT_OUTPUT]`
		if ($realOutputDirectory === false)
		{
			return false;
		}

		// Get the plugin directory, calculated and real path
		$abwpDir     = $this->getABWPDir();
		$abwpDirReal = @realpath($abwpDir);

		// If we cannot resolve the plugin directory (WTF?) just bail out
		if ($abwpDirReal === false)
		{
			return null;
		}

		// Check for legacy default output directory, expressed as a full path -- NO, DO NOT DO THAT
//		$legacyOutput     = $abwpDir . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'backups';
//		$legacyOutputReal = $abwpDirReal . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'backups';
//
//		if (rtrim($realOutputDirectory, '/\\') === $legacyOutput)
//		{
//			return false;
//		}
//
//		if (rtrim($realOutputDirectory, '/\\') === $legacyOutputReal)
//		{
//			return false;
//		}
//
//		if (rtrim($outputDirectory, '/\\') === $legacyOutput)
//		{
//			return false;
//		}
//
//		if (rtrim($outputDirectory, '/\\') === $legacyOutputReal)
//		{
//			return false;
//		}

		// Try to find the relative directory
		$relDir = null;

		if (strpos($realOutputDirectory, $abwpDirReal) === 0)
		{
			$relDir = trim(substr($realOutputDirectory, strlen($abwpDirReal)), '/\\');
		}
		elseif (strpos($realOutputDirectory, $abwpDir) === 0)
		{
			$relDir = trim(substr($realOutputDirectory, strlen($abwpDir)), '/\\');
		}
		elseif (strpos($outputDirectory, $abwpDirReal) === 0)
		{
			$relDir = trim(substr($outputDirectory, strlen($abwpDirReal)), '/\\');
		}
		elseif (strpos($outputDirectory, $abwpDir) === 0)
		{
			$relDir = trim(substr($outputDirectory, strlen($abwpDir)), '/\\');
		}

		// There is no relative directory. Bye!
		if (is_null($relDir))
		{
			return null;
		}

		// Remove multiple slashes
		$relDir = str_replace('\\', '/', $relDir);

		return preg_replace('#/{2,}#', '/', $relDir);
	}

	/**
	 * Get the Akeeba Backup for WordPress plugin directory (absolute path)
	 *
	 * @return  string
	 * @since   8.1.0
	 */
	private function getABWPDir(): string
	{
		$contentFolder = rtrim(
			(defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : (rtrim(ABSPATH, '/') . '/wp-content')), '/'
		);

		$pluginsDir = rtrim(
			defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : ($contentFolder . '/plugins'), '/'
		);

		$pluginSlug = (class_exists('AkeebaBackupWP') && !empty(AkeebaBackupWP::$pluginBaseName))
			? AkeebaBackupWP::$pluginBaseName : 'akeebabackupwp/akeebabackupwp.php';

		return dirname($pluginsDir . '/' . $pluginSlug);
	}

	/**
	 * Mark backup records with non-existent backup archives as such.
	 *
	 * @param   array  $invalidRecordIDs  List of backup archive record IDs.
	 *
	 * @return  void
	 * @since   8.1.0
	 */
	private function markInvalidRecords(array $invalidRecordIDs): void
	{
		if (empty($invalidRecordIDs))
		{
			return;
		}

		$db = $this->container->db;
		$db->transactionStart();

		for ($i = 0; $i < (count($invalidRecordIDs) % 100); $i++)
		{
			$chunk = array_slice($invalidRecordIDs, 100 * $i, 100);

			$query = $db->getQuery(true)
				->update($db->quoteName('#__ak_stats'))
				->set($db->quoteName('filesexist') . ' = 0')
				->where($db->quoteName('id') . ' IN (' . implode(',', $chunk) . ')');
			try
			{
				$db->setQuery($query)->execute();
			}
			catch (\Exception $e)
			{
				// Failure is an option, if we overshoot the chunk size
			}
		}

		$db->transactionCommit();
	}


}