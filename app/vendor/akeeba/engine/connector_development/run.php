<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

define('AKEEBAENGINE', 1);

// Has the user run composer install already?
if (!is_file(__DIR__ . '/../vendor/autoload.php'))
{
	echo "\n\n\n";
	echo "* * *  E R R O R  * * *\n\n";
	echo "Please run composer install before running this script.\n";
	echo "\n\n\n";
}

if (!file_exists(__DIR__ . '/config.php'))
{
	echo "\n\n\n";
	echo "* * *  E R R O R  * * *\n\n";
	echo "Copy config.dist.php to config.php and modify accordingly before running this script.\n";
	echo "\n\n\n";
}

require_once __DIR__ . '/config.php';

// Detect the native operating system type.
$os = strtoupper(substr(PHP_OS, 0, 3));

if (!defined('IS_WIN'))
{
	define('IS_WIN', $os === 'WIN');
}

if (!defined('IS_UNIX'))
{
	define('IS_UNIX', IS_WIN === false);
}

// Load Composer's autoloader
/** @var \Composer\Autoload\ClassLoader $loader */
$loader = require_once __DIR__ . '/../vendor/autoload.php';

$loader->addPsr4('Akeeba\\Engine\\Development\\', __DIR__ . '/src');

$app = new Silly\Application('Akeeba Engine 2 Connector Development', '0.0.1');

// Register the commands
$di = new DirectoryIterator(__DIR__ . '/src/Command');

/** @var DirectoryIterator $file */
foreach ($di as $file)
{
	if (!$file->isFile() || $file->isDot() || $file->getExtension() !== 'php'
	    || strpos(
		       $file->getBasename(), 'Abstract'
	       ) !== false)
	{
		continue;
	}

	$basename  = $file->getBasename('.php');
	$className = '\\Akeeba\\Engine\\Development\\Command\\' . $basename;

	if (!class_exists($className))
	{
		continue;
	}

	/** @var \Akeeba\Engine\Development\Command\AbstractCommand $commandObject */
	$commandObject = new $className;
	$app->command(lcfirst($basename), $commandObject)
		->descriptions($commandObject->getDescription());
}

$app->run();