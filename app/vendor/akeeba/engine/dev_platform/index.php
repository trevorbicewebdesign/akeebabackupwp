#!/usr/bin/env php
<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License version 3, or later
 *
 * This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, version 3.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program. If not, see
 * <https://www.gnu.org/licenses/>.
 */

use Akeeba\Engine\DevPlatform\Command;
use Akeeba\Engine\Platform;
use Composer\CaBundle\CaBundle;

require __DIR__ . '/../vendor/autoload.php';

define('AKEEBAENGINE', 1);
define('AKEEBADEBUG', 1);
define('AKEEBADEBUG_ERROR_DISPLAY', 1);
define('AKEEBA_CACERT_PEM', CaBundle::getBundledCaBundlePath());
define('AKEEBA_VERSION', 'dev');
define('AKEEBA_PRO', true);
define('AKEEBA_DATE', (new \DateTime())->format('Y-m-d'));

/**
 * Artificial per-chunk delay, in microseconds, while putting a file into the backup archive.
 *
 * This slows down the backup of large files on purpose, giving us a wide enough window to test the engine's handling of
 * files which grow, shrink, or disappear mid-backup (see dev_platform/makebigfile.php and the
 * Test/Integration/Backup/MisbehavingFileTest integration test).
 *
 * It is read from the AKEEBA_DEBUG_BIG_FILE_MULTIPART_DELAY environment variable so that the integration test (or you)
 * can enable it without editing this file. Set it to e.g. 100000 (100 ms) to enable. Leave it unset for normal use.
 */
$multipartDelay = getenv('AKEEBA_DEBUG_BIG_FILE_MULTIPART_DELAY');

if ($multipartDelay !== false && is_numeric($multipartDelay) && (int) $multipartDelay > 0)
{
	define('AKEEBA_DEBUG_BIG_FILE_MULTIPART_DELAY', (int) $multipartDelay);
}

error_reporting(E_ALL | E_NOTICE | E_DEPRECATED);
ini_set('display_errors', 1);

try
{
	// Load the dev platform
	Platform::addPlatform('Development', __DIR__ . '/Platform');
	$platform = Platform::getInstance();
	$platform->load_version_defines();

	// Run the CLI app
	$app = new Silly\Application();

	Command\Init::register($app);
	Command\TestPostgresql::register($app);
	Command\TestPgdump::register($app);
	Command\NukeBackups::register($app);
	Command\NukeProfiles::register($app);
	Command\NukeEverything::register($app);
	Command\ConfigList::register($app);
	Command\ConfigSet::register($app);
	Command\ConfigExport::register($app);
	Command\ConfigImport::register($app);
	Command\BackupTake::register($app);
	Command\BackupList::register($app);
	Command\BackupInfo::register($app);
	Command\BackupLogView::register($app);
	Command\BackupDelete::register($app);
	Command\BackupDeleteFiles::register($app);
	Command\BackupRemoteDownload::register($app);
	Command\BackupRemoteUpload::register($app);
	Command\BackupRemoteDelete::register($app);
	Command\BackupFreeze::register($app);
	Command\BackupUnfreeze::register($app);
	Command\ProfileList::register($app);
	Command\ProfileAdd::register($app);
	Command\ProfileRename::register($app);
	Command\ProfileDelete::register($app);
	Command\FilterList::register($app);
	Command\FilterAdd::register($app);
	Command\FilterRemove::register($app);
	Command\AuthTest::register($app);
	Command\AuthLogin::register($app);

	$app->run();
}
catch (Throwable $exception)
{
	echo <<< TEXT


==============================================================================
                                ERROR
==============================================================================

{$exception->getMessage()}

{$exception->getFile()}:{$exception->getLine()}

{$exception->getTraceAsString()}

TEXT;
}