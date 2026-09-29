<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Helper;

use Akeeba\Engine\Platform;
use Awf\Container\Container;
use DirectoryIterator;
use Throwable;

class Leftovers
{
	/**
	 * Returns the detected leftover files and folders.
	 *
	 * @return  array
	 * @since   9.1.0
	 */
	public static function getLeftovers(): array
	{
		$root  = Platform::getInstance()->get_site_root();
		$files = [];

		try
		{
			$di = new DirectoryIterator($root);
		}
		catch (Throwable $e)
		{
			return [];
		}

		/** @var DirectoryIterator $file */
		foreach ($di as $file)
		{
			if ($file->isDot())
			{
				continue;
			}

			if ($file->isDir())
			{
				if (self::isInstallationFolder($file->getPathname()))
				{
					$files[] = $file->getBasename();
				}

				continue;
			}

			if (!$file->isFile())
			{
				continue;
			}

			$ext = $file->getExtension();

			$isArchive     = in_array($ext, ['zip', 'jpa', 'jps']);
			$isArchivePart = (str_starts_with($ext, 'z') || str_starts_with($ext, 'p'))
			                 && preg_match('/[pz][\d]]{2,}/', $ext);
			$isKickstart   = $file->getBasename() === 'kickstart.php'
			                 || ($ext === 'php' && str_contains($file->getBasename(), 'kickstart'));

			if ($isArchive || $isArchivePart || $isKickstart)
			{
				$files[] = $file->getBasename();
			}
		}

		return $files;
	}

	/**
	 * Delete leftover files and folders.
	 *
	 * @return  array Returns the number of successful and failed to delete files / folders.
	 * @since   9.1.0
	 */
	public static function deleteLeftovers(Container $container): array
	{
		$root          = Platform::getInstance()->get_site_root();
		$leftoverFiles = self::getLeftovers();
		$fails         = 0;
		$deleted       = 0;

		if (empty($leftoverFiles))
		{
			return [0, 0];
		}

		foreach ($leftoverFiles as $file)
		{
			$filePath = $root . '/' . $file;

			if (!file_exists($filePath))
			{
				continue;
			}

			if (is_dir($filePath))
			{
				if (!$container->fileSystem->rmdir($filePath))
				{
					$fails++;
				}
				else
				{
					$deleted++;
				}

				continue;
			}

			if (!$container->fileSystem->delete($filePath))
			{
				$fails++;
			}
			else
			{
				$deleted++;
			}
		}

		return [$deleted, $fails];
	}

	/**
	 * Does this look like a leftover installation folder?
	 *
	 * @param   string  $folder
	 *
	 * @return  bool
	 * @since   9.1.0
	 */
	private static function isInstallationFolder(string $folder): bool
	{
		/**
		 * Check for the existence of the following files and folders (covers ANGIE and BRS):
		 * index.php
		 * version.php
		 * src/Controller/AbstractSetup.php OR angie/controllers/base/main.php
		 */
		if (!@is_file($folder . '/index.php'))
		{
			return false;
		}

		if (!@is_file($folder . '/version.php'))
		{
			return false;
		}

		if (!@is_file($folder . '/src/Controller/AbstractSetup.php')
		    && !@is_file(
				$folder . '/angie/controllers/base/main.php'
			))
		{
			return false;
		}

		return true;
	}
}