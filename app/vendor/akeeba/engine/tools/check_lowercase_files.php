<?php

function scanFolderRecursive($folder)
{
	$dh = new DirectoryIterator($folder);

	/** @var DirectoryIterator $entry */
	foreach ($dh as $entry)
	{
		if ($entry->isDot())
		{
			continue;
		}

		$fileName  = $entry->getFilename();
		$filePath  = $entry->getPathname();
		$extension = $entry->getExtension();

		if (in_array($fileName, array('cacert.pem', 'secretkey.php', 'serverkey.php')))
		{
			continue;
		}

		$wrongCase = strtolower($fileName) == $fileName;

		switch ($extension)
		{
			// Obsolete engine configuration INI files
			case 'ini':
			// New engine configuration JSON files
			case 'json':
			// web.config
			case 'config':
			// .htaccess
			case 'htaccess':
				$wrongCase = !$wrongCase;
				break;
		}

		if ($wrongCase)
		{
			echo "$filePath";

			if ($entry->isDir())
			{
				echo "/";
			}

			echo "\n";
		}

		if ($entry->isDir())
		{
			scanFolderRecursive($filePath);
		}
	}
}

echo "Files and folders with wrong case names\n\n";

scanFolderRecursive(__DIR__ . '/../engine');

echo "\n";