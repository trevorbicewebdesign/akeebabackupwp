<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo;

use Akeeba\Engine\Factory;
use Akeeba\Engine\Platform;
use Awf\Database\Installer;

class PostUpgradeScript
{
	/** @var \Awf\Container\Container|null The container of the application we are running in */
	protected $container = null;

	/**
	 * @var array Files to remove from all versions
	 */
	protected $removeFilesAllVersions = [
		'media/css/bootstrap-namespaced.css',
		'media/css/bootstrap-switch.css',
		'media/css/datepicker.css',
		'media/css/theme.css',
		'media/js/bootstrap-switch.js',
		'media/js/piecon.js',
		'media/js/solo/alice.js',
		'media/js/solo/backup.js',
		'media/js/solo/configuration.js',
		'media/js/solo/dbfilters.js',
		'media/js/solo/encryption.js',
		'media/js/solo/extradirs.js',
		'media/js/solo/fsfilters.js',
		'media/js/solo/gui-helpers.js',
		'media/js/solo/multidb.js',
		'media/js/solo/regexdbfilters.js',
		'media/js/solo/regexfsfilters.js',
		'media/js/solo/restore.js',
		'media/js/solo/setup.js',
		'media/js/solo/stepper.js',
		'media/js/solo/system.js',
		'media/js/solo/update.js',
		'media/js/solo/wizard.js',
		// Obsolete Mautic integration
		'Solo/assets/installers/angie-mautic.ini',
		'Solo/assets/installers/angie-mautic.jpa',
		'Solo/Platform/Solo/Filter/MauticSkipDirs.php',
		'Solo/Platform/Solo/Filter/MauticSkipFiles.php',
		'Solo/Pythia/Oracle/Mautic.php',
		// Obsolete AES-128 CTR implementation in Javascript
		'media/js/solo/encryption.min.js',
		'media/js/solo/encryption.min.map',
		// Bootstrap-based theme
		'media/css/bootstrap.css.map',
		'media/css/bootstrap.min.css',
		'media/css/bootstrap-joomla.min.css',
		'media/css/bootstrap-namespaced.min.css',
		'media/css/bootstrap-prestashop.min.css',
		'media/css/bootstrap-switch.min.css',
		'media/css/bootstrap-wordpress.min.css',
		'media/css/font-awesome.min.css',
		'media/fonts/FontAwesome.ttf',
		'media/fonts/akeeba-backup-origin.eot',
		'media/fonts/akeeba-backup-origin.svg',
		'media/fonts/akeeba-backup-origin.ttf',
		'media/fonts/akeeba-backup-origin.woff',
		'media/fonts/fontawesome-webfont.eot',
		'media/fonts/fontawesome-webfont.svg',
		'media/fonts/fontawesome-webfont.ttf',
		'media/fonts/fontawesome-webfont.woff',
		'media/fonts/glyphicons-halflings-regular.eot',
		'media/fonts/glyphicons-halflings-regular.svg',
		'media/fonts/glyphicons-halflings-regular.ttf',
		'media/fonts/glyphicons-halflings-regular.woff',
		'media/image/akeeba-ui-32.png',
		'media/image/quickicon-ok-48.png',
		'media/image/quickicon-warning-48.png',
		'media/js/bootstrap.min.js',
		'media/js/bootstrap-switch.min.js',
		'media/js/html5shiv.min.js',
		'media/js/respond.min.js',
		'media/js/selectize.min.js',

		// Removed platforms
		'Solo/Pythia/Oracle/Drupal7.php',
		'Solo/Pythia/Oracle/Drupal8.php',
		'Solo/Pythia/Oracle/Grav.php',
		'Solo/Pythia/Oracle/Magento.php',
		'Solo/Pythia/Oracle/Magento2.php',
		'Solo/Pythia/Oracle/Moodle.php',
		'Solo/Pythia/Oracle/Octobercms.php',
		'Solo/Pythia/Oracle/Pagekit.php',
		'Solo/Pythia/Oracle/Phpbb.php',
		'Solo/Platform/Solo/Filter/Drupal7TableData.php',
		'Solo/Platform/Solo/Filter/Drupal8TableData.php',
		'Solo/Platform/Solo/Filter/GravSkipDirs.php',
		'Solo/Platform/Solo/Filter/GravSkipFiles.php',
		'Solo/Platform/Solo/Filter/MagentoSkipDirs.php',
		'Solo/Platform/Solo/Filter/MagentoSkipFiles.php',
		'Solo/Platform/Solo/Filter/OctobercmsSkipDirs.php',
		'Solo/Platform/Solo/Filter/OctobercmsSkipFiles.php',
		'Solo/Platform/Solo/Filter/OctobercmsTableData.php',
		'Solo/Platform/Solo/Filter/PagekitSkipDirs.php',
		'Solo/Platform/Solo/Filter/PagekitSkipFiles.php',
		'Solo/Platform/Solo/Filter/PagekitTableData.php',
		'Solo/Platform/Solo/Filter/PrestashopSkipDirs.php',
		'Solo/Platform/Solo/Filter/PrestashopSkipFiles.php',
		'Solo/Platform/Solo/Filter/PrestashopTableData.php',

		// Migration of Akeeba Engine to JSON format
		"Solo/Platform/Solo/Config/04.quota.ini",
		"Solo/Platform/Solo/Config/02.advanced.ini",
		"Solo/Platform/Solo/Config/Pro/04.quota.ini",
		"Solo/Platform/Solo/Config/Pro/02.advanced.ini",
		"Solo/Platform/Solo/Config/Pro/01.basic.ini",
		"Solo/Platform/Solo/Config/Pro/02.platform.ini",
		"Solo/Platform/Solo/Config/Pro/03.filters.ini",
		"Solo/Platform/Solo/Config/Pro/05.tuning.ini",
		"Solo/Platform/Solo/Config/01.basic.ini",
		"Solo/Platform/Solo/Config/05.tuning.ini",
		"Solo/Platform/Solo/Filter/Stack/myjoomla.ini",
		"Solo/Platform/Solo/Filter/Stack/actionlogs.ini",

		// Removed PostgreSQL and MS SQL Server support
		'Solo/assets/sql/xml/postgresql.xml',
		'Solo/assets/sql/xml/sqlsrv.xml',

		// ALICE refactoring
		"media/js/solo/alice.min.js",
		"media/js/solo/alice.min.map",
		'media/js/solo/stepper.min.js',
		'media/js/solo/stepper.min.map',

		// Version 7 -- remove non-RAW JSON API encapsulation
		"Solo/Model/Json/Encapsulation/AesCbc128.php",
		"Solo/Model/Json/Encapsulation/AesCbc256.php",
		"Solo/Model/Json/Encapsulation/AesCtr128.php",
		"Solo/Model/Json/Encapsulation/AesCtr256.php",

		// Obsolete base views
		"Solo/View/DataHtml.php",
		"Solo/View/Html.php",

		// Obsolete loadScripts
		"media/js/solo/loadscripts.min.js",
		"media/js/solo/loadscripts.min.map",

		// Obsolete scripts
		"Solo/ViewTemplates/Backup/script.blade.php",

		// Changelog PNG images
		'media/image/changelog.png',

		// Remove piecon
		'media/js/piecon.min.js',

		// Remove legacy filters
		"Solo/Platform/Solo/Filter/Stack/myjoomla.json",
		"Solo/Platform/Solo/Filter/Stack/StackMyjoomla.php",

		// HHVM is no longer PHP compatible, why do we even have this?
		"hhvm.php",

		// Leftover vendor files in 8.0.0
		"vendor/akeeba/engine/.gitattributes",
		"vendor/akeeba/engine/.gitignore",
		"vendor/akeeba/engine/icon.png",
		"vendor/akeeba/engine/rector.php",
		"vendor/akeeba/s3/.gitignore",

		// Obsolete ANGIE installers
		'Solo/assets/installers/angie.jpa',
		'Solo/assets/installers/angie.json',
		'Solo/assets/installers/angie-generic.jpa',
		'Solo/assets/installers/angie-generic.json',
		'Solo/assets/installers/angie-joomla.jpa',
		'Solo/assets/installers/angie-joomla.json',
		'Solo/assets/installers/angie-wordpress.jpa',
		'Solo/assets/installers/angie-wordpress.json',

		// Obsolete JSON tasks
		'Solo/Model/Json/Task/Browse.php',
		'Solo/Model/Json/Task/DeleteProfile.php',
		'Solo/Model/Json/Task/GetDBEntities.php',
		'Solo/Model/Json/Task/GetDBFilters.php',
		'Solo/Model/Json/Task/GetDBRoots.php',
		'Solo/Model/Json/Task/GetFSEntities.php',
		'Solo/Model/Json/Task/GetFSFilters.php',
		'Solo/Model/Json/Task/GetFSRoots.php',
		'Solo/Model/Json/Task/GetGUIConfiguration.php',
		'Solo/Model/Json/Task/GetIncludedDBs.php',
		'Solo/Model/Json/Task/GetIncludedDirectories.php',
		'Solo/Model/Json/Task/GetRegexDBFilters.php',
		'Solo/Model/Json/Task/GetRegexFSFilters.php',
		'Solo/Model/Json/Task/Log.php',
		'Solo/Model/Json/Task/RemoveIncludedDB.php',
		'Solo/Model/Json/Task/RemoveIncludedDirectory.php',
		'Solo/Model/Json/Task/SaveConfiguration.php',
		'Solo/Model/Json/Task/SaveProfile.php',
		'Solo/Model/Json/Task/SetDBFilter.php',
		'Solo/Model/Json/Task/SetFSFilter.php',
		'Solo/Model/Json/Task/SetIncludedDB.php',
		'Solo/Model/Json/Task/SetIncludedDirectory.php',
		'Solo/Model/Json/Task/SetRegexDBFilter.php',
		'Solo/Model/Json/Task/SetRegexFSFilter.php',
		'Solo/Model/Json/Task/TestDBConnection.php',
		'Solo/Model/Json/Task/UpdateDownload.php',
		'Solo/Model/Json/Task/UpdateExtract.php',
		'Solo/Model/Json/Task/UpdateGetInformation.php',
		'Solo/Model/Json/Task/UpdateInstall.php',
	];

	/**
	 * @var array Files to remove from Pro
	 */
	protected $removeFilesPro = [

	];

	/**
	 * @var array Folders to remove from all versions
	 */
	protected $removeFoldersAllVersions = [
		// Bootstrap-based theme
		'media/css/selectize',
		'media/less',

		// ALICE refactoring
		"Solo/alice",

		// Conversion to Blade
		'Solo/View/Alice/tmpl',
		'Solo/View/Backup/tmpl',
		'Solo/View/Browser/tmpl',
		'Solo/View/Common',
		'Solo/View/Configuration/tmpl',
		'Solo/View/Dbfilters/tmpl',
		'Solo/View/Discover/tmpl',
		'Solo/View/Extradirs/tmpl',
		'Solo/View/Fsfilters/tmpl',
		'Solo/View/Log/tmpl',
		'Solo/View/Login/tmpl',
		'Solo/View/Main/tmpl',
		'Solo/View/Manage/tmpl',
		'Solo/View/Multidb/tmpl',
		'Solo/View/Phpinfo/tmpl',
		'Solo/View/Profiles/tmpl',
		'Solo/View/Regexdbfilters/tmpl',
		'Solo/View/Regexfsfilters/tmpl',
		'Solo/View/Remotefiles/tmpl',
		'Solo/View/Restore/tmpl',
		'Solo/View/S3import/tmpl',
		'Solo/View/Schedule/tmpl',
		'Solo/View/Setup/tmpl',
		'Solo/View/Sysconfig/tmpl',
		'Solo/View/Transfer/tmpl',
		'Solo/View/Update/tmpl',
		'Solo/View/Upload/tmpl',
		'Solo/View/Users/tmpl',
		'Solo/View/Wizard/tmpl',

		// Precompiled templates
		'Solo/PrecompiledTemplates',
		'tmp/compiled_templates',

		// Obsolete jQuery stuff
		'media/js/datepicker',
		'media/js/dist',

		// Removed the “Archive integrity check” feature.
		'Solo/Platform/Solo/Finalization',

		// AWF is installed via Composer now
		'Awf',
		'awf',

		// Akeeba Engine is installed via Composer now
		'Solo/engine',

		// Leftover vendor folders in 8.0.0
		"vendor/akeeba/engine/.idea",
		"vendor/akeeba/engine/binned_ideas",
		"vendor/akeeba/engine/connector_development",
		"vendor/akeeba/engine/Test",
		"vendor/akeeba/engine/tools",
		"vendor/akeeba/s3/minitest",

		// Old stats collector
		'Solo/assets/stats',
	];

	/**
	 * @var array Folders to remove from Core
	 */
	protected $removeFoldersCore = [
		// CLI scripts
		'cli',
		// Pro engine features
		'vendor/akeeba/engine/engine/plugins',
		'vendor/akeeba/engine/engine/Postproc/Connector',
		'Solo/Platform/Solo/Config/Pro',

		// Pro application features
		'Solo/AliceChecks',

		'Solo/Model/Json',

		'Solo/View/Alice',
		'Solo/View/Crons',
		'Solo/View/Discover',
		'Solo/View/Extradirs',
		'Solo/View/Multidb',
		'Solo/View/Regexdbfilters',
		'Solo/View/Regexfsfilters',
		'Solo/View/Remotefiles',
		'Solo/View/Restore',
		'Solo/View/S3import',
		'Solo/View/Schedule',
		'Solo/View/Transfer',
		'Solo/View/Upload',

		'Solo/ViewTemplates/Alice',
		'Solo/ViewTemplates/Crons',
		'Solo/ViewTemplates/Discover',
		'Solo/ViewTemplates/Extradirs',
		'Solo/ViewTemplates/Multidb',
		'Solo/ViewTemplates/Regexdbfilters',
		'Solo/ViewTemplates/Regexfsfilters',
		'Solo/ViewTemplates/Remotefiles',
		'Solo/ViewTemplates/Restore',
		'Solo/ViewTemplates/S3import',
		'Solo/ViewTemplates/Schedule',
		'Solo/ViewTemplates/Transfer',
		'Solo/ViewTemplates/Upload',

		// Version 7 -- JSON and legacy API
		'Solo/Model/Json',

		// Obsolete language folder
		'languages/akeeba',
	];

	/**
	 * @var array Files to remove from Core
	 */
	protected $removeFilesCore = [
		// Pro engine features
		// -- Archivers
		'vendor/akeeba/engine/engine/Archiver/directftp.ini',
		'vendor/akeeba/engine/engine/Archiver/directftp.json',
		'vendor/akeeba/engine/engine/Archiver/Directftp.php',
		'vendor/akeeba/engine/engine/Archiver/directftpcurl.ini',
		'vendor/akeeba/engine/engine/Archiver/directftpcurl.json',
		'vendor/akeeba/engine/engine/Archiver/Directftpcurl.php',
		'vendor/akeeba/engine/engine/Archiver/directsftp.ini',
		'vendor/akeeba/engine/engine/Archiver/directsftp.json',
		'vendor/akeeba/engine/engine/Archiver/Directsftp.php',
		'vendor/akeeba/engine/engine/Archiver/directsftpcurl.ini',
		'vendor/akeeba/engine/engine/Archiver/directsftpcurl.json',
		'vendor/akeeba/engine/engine/Archiver/Directsftpcurl.php',
		'vendor/akeeba/engine/engine/Archiver/jps.ini',
		'vendor/akeeba/engine/engine/Archiver/jps.json',
		'vendor/akeeba/engine/engine/Archiver/Jps.php',
		'vendor/akeeba/engine/engine/Archiver/zipnative.ini',
		'vendor/akeeba/engine/engine/Archiver/zipnative.json',
		'vendor/akeeba/engine/engine/Archiver/Zipnative.php',
		// -- Filters
		'vendor/akeeba/engine/engine/Filter/Extradirs.php',
		'vendor/akeeba/engine/engine/Filter/Multidb.php',
		'vendor/akeeba/engine/engine/Filter/Regexdirectories.php',
		'vendor/akeeba/engine/engine/Filter/Regexfiles.php',
		'vendor/akeeba/engine/engine/Filter/Regexskipdirs.php',
		'vendor/akeeba/engine/engine/Filter/Regexskipfiles.php',
		'vendor/akeeba/engine/engine/Filter/Regexskiptabledata.php',
		'vendor/akeeba/engine/engine/Filter/Regexskiptables.php',
		// -- Post-processing engines
		'vendor/akeeba/engine/engine/Postproc/amazons3.ini',
		'vendor/akeeba/engine/engine/Postproc/amazons3.json',
		'vendor/akeeba/engine/engine/Postproc/Amazons3.php',
		'vendor/akeeba/engine/engine/Postproc/azure.ini',
		'vendor/akeeba/engine/engine/Postproc/azure.json',
		'vendor/akeeba/engine/engine/Postproc/Azure.php',
		'vendor/akeeba/engine/engine/Postproc/backblaze.ini',
		'vendor/akeeba/engine/engine/Postproc/backblaze.json',
		'vendor/akeeba/engine/engine/Postproc/Backblaze.php',
		'vendor/akeeba/engine/engine/Postproc/box.ini',
		'vendor/akeeba/engine/engine/Postproc/box.json',
		'vendor/akeeba/engine/engine/Postproc/Box.php',
		'vendor/akeeba/engine/engine/Postproc/cloudfiles.ini',
		'vendor/akeeba/engine/engine/Postproc/cloudfiles.json',
		'vendor/akeeba/engine/engine/Postproc/Cloudfiles.php',
		'vendor/akeeba/engine/engine/Postproc/cloudme.ini',
		'vendor/akeeba/engine/engine/Postproc/cloudme.json',
		'vendor/akeeba/engine/engine/Postproc/Cloudme.php',
		'vendor/akeeba/engine/engine/Postproc/dreamobjects.ini',
		'vendor/akeeba/engine/engine/Postproc/dreamobjects.json',
		'vendor/akeeba/engine/engine/Postproc/Dreamobjects.php',
		'vendor/akeeba/engine/engine/Postproc/dropbox.ini',
		'vendor/akeeba/engine/engine/Postproc/dropbox.json',
		'vendor/akeeba/engine/engine/Postproc/Dropbox.php',
		'vendor/akeeba/engine/engine/Postproc/dropbox2.ini',
		'vendor/akeeba/engine/engine/Postproc/dropbox2.json',
		'vendor/akeeba/engine/engine/Postproc/Dropbox2.php',
		'vendor/akeeba/engine/engine/Postproc/ftp.ini',
		'vendor/akeeba/engine/engine/Postproc/ftp.json',
		'vendor/akeeba/engine/engine/Postproc/Ftp.php',
		'vendor/akeeba/engine/engine/Postproc/ftpcurl.ini',
		'vendor/akeeba/engine/engine/Postproc/ftpcurl.json',
		'vendor/akeeba/engine/engine/Postproc/Ftpcurl.php',
		'vendor/akeeba/engine/engine/Postproc/googledrive.ini',
		'vendor/akeeba/engine/engine/Postproc/googledrive.json',
		'vendor/akeeba/engine/engine/Postproc/Googledrive.php',
		'vendor/akeeba/engine/engine/Postproc/googlestorage.ini',
		'vendor/akeeba/engine/engine/Postproc/googlestorage.json',
		'vendor/akeeba/engine/engine/Postproc/Googlestorage.php',
		'vendor/akeeba/engine/engine/Postproc/googlestoragejson.ini',
		'vendor/akeeba/engine/engine/Postproc/googlestoragejson.json',
		'vendor/akeeba/engine/engine/Postproc/Googlestoragejson.php',
		'vendor/akeeba/engine/engine/Postproc/idrivesync.ini',
		'vendor/akeeba/engine/engine/Postproc/idrivesync.json',
		'vendor/akeeba/engine/engine/Postproc/Idrivesync.php',
		'vendor/akeeba/engine/engine/Postproc/onedrive.ini',
		'vendor/akeeba/engine/engine/Postproc/onedrive.json',
		'vendor/akeeba/engine/engine/Postproc/Onedrive.php',
		'vendor/akeeba/engine/engine/Postproc/onedrivebusiness.ini',
		'vendor/akeeba/engine/engine/Postproc/onedrivebusiness.json',
		'vendor/akeeba/engine/engine/Postproc/Onedrivebusiness.php',
		'vendor/akeeba/engine/engine/Postproc/ovh.ini',
		'vendor/akeeba/engine/engine/Postproc/ovh.json',
		'vendor/akeeba/engine/engine/Postproc/Ovh.php',
		'vendor/akeeba/engine/engine/Postproc/pcloud.ini',
		'vendor/akeeba/engine/engine/Postproc/pcloud.json',
		'vendor/akeeba/engine/engine/Postproc/Pcloud.php',
		'vendor/akeeba/engine/engine/Postproc/Connector/Pcloud.php',
		'vendor/akeeba/engine/engine/Postproc/s3.ini',
		'vendor/akeeba/engine/engine/Postproc/s3.json',
		'vendor/akeeba/engine/engine/Postproc/S3.php',
		'vendor/akeeba/engine/engine/Postproc/sftp.ini',
		'vendor/akeeba/engine/engine/Postproc/sftp.json',
		'vendor/akeeba/engine/engine/Postproc/Sftp.php',
		'vendor/akeeba/engine/engine/Postproc/sftpcurl.ini',
		'vendor/akeeba/engine/engine/Postproc/sftpcurl.json',
		'vendor/akeeba/engine/engine/Postproc/Sftpcurl.php',
		'vendor/akeeba/engine/engine/Postproc/sugarsync.ini',
		'vendor/akeeba/engine/engine/Postproc/sugarsync.json',
		'vendor/akeeba/engine/engine/Postproc/Sugarsync.php',
		'vendor/akeeba/engine/engine/Postproc/swift.ini',
		'vendor/akeeba/engine/engine/Postproc/swift.json',
		'vendor/akeeba/engine/engine/Postproc/Swift.php',
		'vendor/akeeba/engine/engine/Postproc/webdav.ini',
		'vendor/akeeba/engine/engine/Postproc/webdav.json',
		'vendor/akeeba/engine/engine/Postproc/Webdav.php',
		// Pro application features
		'Solo/Controller/Alice.php',
		'Solo/Controller/Api.php',
		'Solo/Controller/Check.php',
		'Solo/Controller/Crons.php',
		'Solo/Controller/Discover.php',
		'Solo/Controller/Extradirs.php',
		'Solo/Controller/Json.php',
		'Solo/Controller/Multidb.php',
		'Solo/Controller/Regexdbfilters.php',
		'Solo/Controller/Regexfsfilters.php',
		'Solo/Controller/Remote.php',
		'Solo/Controller/Remotefiles.php',
		'Solo/Controller/Restore.php',
		'Solo/Controller/S3import.php',
		'Solo/Controller/Schedule.php',
		'Solo/Controller/Transfer.php',
		'Solo/Controller/Upload.php',

		'Solo/Model/Alice.php',
		'Solo/Model/Cron.php',
		'Solo/Model/Crons.php',
		'Solo/Model/Discover.php',
		'Solo/Model/Extradirs.php',
		'Solo/Model/Json.php',
		'Solo/Model/Multidb.php',
		'Solo/Model/Regexdbfilters.php',
		'Solo/Model/Regexfsfilters.php',
		'Solo/Model/Remotefiles.php',
		'Solo/Model/Restore.php',
		'Solo/Model/S3import.php',
		'Solo/Model/Schedule.php',
		'Solo/Model/Transfers.php',
		'Solo/Model/Upload.php',

		'media/js/solo/alice.min.js',
		'media/js/solo/alice.min.map',
		'media/js/solo/extradirs.min.js',
		'media/js/solo/extradirs.min.map',
		'media/js/solo/multidb.min.js',
		'media/js/solo/multidb.min.map',
		'media/js/solo/regexdbfilters.min.js',
		'media/js/solo/regexdbfilters.min.map',
		'media/js/solo/regexfsfilters.min.js',
		'media/js/solo/regexfsfilters.min.map',
		'media/js/solo/restore.min.js',
		'media/js/solo/restore.min.map',
		'media/js/solo/transfer.min.js',
		'media/js/solo/transfer.min.map',

		// Version 7 -- JSON and legacy API
		'remote.php',
		// NEVER DELETE restore.php – IT IS REQUIRED FOR INSTALLING UPDATES
		// 'restore.php',

		// Obsolete jQuery stuff
		'media/css/datepicker.min.css',
		'media/js/akjqnamespace.min.js',
		'media/js/jquery.min.js',
		'media/js/jquery.min.map',
		'media/js/jquery-migrate.min.js',

		// Obsolete language file
		'languages/akeebabackup/en-GB/en-GB.com_akeebabackup.ini',
	];

	/**
	 * @var array Folders to remove from Pro
	 */
	protected $removeFoldersPro = [
	];

	/**
	 * Files to remove only on WordPress, from both Core and Pro versions
	 *
	 * @var   string[]
	 * @since 8.1.0
	 */
	protected $removeWordPressOnlyFilesAll = [
		// HHVM is no longer running PHP files, so no warning necessary
		'helpers/hhvm.php',

		// Remove widgets
		'helpers/boot_widget.php',
		'helpers/Solo/Widget/BackupGlance.php',
		'helpers/Solo/Widget/QuickBackup.php',
	];

	/**
	 * Class constructor
	 *
	 * @param   \Awf\Container\Container  $container  The container of the application we are running in
	 */
	public function __construct(\Awf\Container\Container $container)
	{
		$this->container = $container;
	}

	/**
	 * Execute the post-upgrade actions
	 */
	public function execute()
	{
		// Do not execute the post-upgrade script in the development environment
		$realPath = realpath(__DIR__);

		if (@file_exists($realPath . '/../../.nopostupgrade'))
		{
			return;
		}

		// Invalidate the Composer files' OPCache.
		if (function_exists('opcache_invalidate'))
		{
			@opcache_invalidate(APATH_BASE . '/../vendor/autoload.php', true);
			@opcache_invalidate(APATH_BASE . '/../vendor/composer/autoload_classmap.php', true);
			@opcache_invalidate(APATH_BASE . '/../vendor/composer/autoload_namespaces.php', true);
			@opcache_invalidate(APATH_BASE . '/../vendor/composer/autoload_psr4.php', true);
			@opcache_invalidate(APATH_BASE . '/../vendor/composer/autoload_real.php', true);
			@opcache_invalidate(APATH_BASE . '/../vendor/composer/autoload_static.php', true);
		}

		// Migrate secretkey.php
		$this->migrateSecretKeyFile();

		// Special handling for running the Solo application inside WordPress.
		if ($this->container->segment->get('insideCMS', false))
		{
			if (defined('WPINC'))
			{
				$this->WordPressActions();
			}
		}

		// Remove obsolete files
		$this->processRemoveFiles();

		// Remove obsolete folders
		$this->processRemoveFolders();

		// Migrate profiles
		$this->migrateProfiles();

		// Migrate front-end API activation options
		$this->upgradeFrontendEnable();

	}

	/**
	 * Upgrades the frontend_enable option into the two separate legacyapi_enabled and jsonapi_enabled options.
	 *
	 * Before version 7 we had a single option to control both frontend backup APIs. Starting version 7 we can enable
	 * and disable them separately.
	 */
	public function upgradeFrontendEnable()
	{
		$currentValue = $this->container->appConfig->get('options.frontend_enable', null);

		if (is_null($currentValue))
		{
			return;
		}

		$this->container->appConfig->set('options.frontend_enable', null);
		$this->container->appConfig->set('options.legacyapi_enabled', $currentValue);
		$this->container->appConfig->set('options.jsonapi_enabled', $currentValue);

		$this->container->appConfig->saveConfiguration();
	}

	/**
	 * Removes obsolete files, depending on the edition (core or pro)
	 */
	protected function processRemoveFiles()
	{
		if (defined('WPINC'))
		{
			$basePath = rtrim($this->container->filesystemBase, '/' . DIRECTORY_SEPARATOR) . '/../';

			foreach ($this->removeWordPressOnlyFilesAll as $file)
			{
				$filePath = $basePath . $file;

				if (file_exists($filePath))
				{
					@unlink($filePath);
				}
			}
		}

		$removeFiles = $this->removeFilesAllVersions;

		if (defined('AKEEBABACKUP_PRO') && AKEEBABACKUP_PRO)
		{
			$removeFiles = array_merge($removeFiles, $this->removeFilesPro);
		}
		else
		{
			$removeFiles = array_merge($removeFiles, $this->removeFilesCore);
		}

		$this->_removeFiles($removeFiles);
	}

	/**
	 * Removes obsolete folders, depending on the edition (core or pro)
	 */
	protected function processRemoveFolders()
	{
		$removeFolders = $this->removeFoldersAllVersions;

		if (defined('AKEEBABACKUP_PRO') && AKEEBABACKUP_PRO)
		{
			$removeFolders = array_merge($removeFolders, $this->removeFoldersPro);
		}
		else
		{
			$removeFolders = array_merge($removeFolders, $this->removeFoldersCore);
		}

		$this->_removeFolders($removeFolders);
	}

	/**
	 * Specific actions to execute when we are running inside WordPress
	 */
	private function WordPressActions()
	{
		$this->WordPressUpgradeToUtf8mb4();
		$this->WordPressRemoveFolders();
		$this->WordPressRemoveFiles();
	}

	/**
	 * Remove obsolete folders from the WordPress installation
	 *
	 * @return  void
	 */
	private function WordPressRemoveFolders()
	{
		$removeFolders = [
			// Standalone platform
			'app/Solo/Platform',
			// Obsolete folders after the introduction of Akeeba Engine 2
			'helpers/platform/solowp',
		];

		// Remove WordPress-specific features from the Core release
		if (defined('AKEEBABACKUP_PRO') && !AKEEBABACKUP_PRO)
		{
			$removeFolders = array_merge(
				[
					'helpers/assets/mu-plugins',
					'wpcli',

				], $removeFolders
			);
		}

		$fsBase = rtrim($this->container->filesystemBase, '/' . DIRECTORY_SEPARATOR) . '/../';
		$fs     = $this->container->fileSystem;

		foreach ($removeFolders as $folder)
		{
			$fs->rmdir($fsBase . $folder, true);
		}
	}

	/**
	 * Remove obsolete files from the WordPress installation
	 *
	 * @return  void
	 */
	private function WordPressRemoveFiles()
	{
		$removeFiles = [
			// Migrating INI files to .json files
			"helpers/Platform/Wordpress/Config/04.quota.ini",
			"helpers/Platform/Wordpress/Config/02.advanced.ini",
			"helpers/Platform/Wordpress/Config/Pro/04.quota.ini",
			"helpers/Platform/Wordpress/Config/Pro/02.advanced.ini",
			"helpers/Platform/Wordpress/Config/Pro/01.basic.ini",
			"helpers/Platform/Wordpress/Config/Pro/02.platform.ini",
			"helpers/Platform/Wordpress/Config/Pro/03.filters.ini",
			"helpers/Platform/Wordpress/Config/Pro/05.tuning.ini",
			"helpers/Platform/Wordpress/Config/01.basic.ini",
			"helpers/Platform/Wordpress/Config/02.platform.ini",
			"helpers/Platform/Wordpress/Config/05.tuning.ini",
		];

		// Remove WordPress-specific features from the Core release
		if (defined('AKEEBABACKUP_PRO') && !AKEEBABACKUP_PRO)
		{
			$additionalFiles = [
				'helpers/boot_wpcli.php',
			];

			$removeFiles = array_merge($removeFiles, $additionalFiles);
		}

		if (empty($removeFiles))
		{
			return;
		}

		$fsBase = rtrim($this->container->filesystemBase, '/' . DIRECTORY_SEPARATOR) . '/../';
		$fs     = $this->container->fileSystem;

		foreach ($removeFiles as $file)
		{
			$fs->delete($fsBase . $file);
		}
	}

	/**
	 * Update WordPress tables to utf8mb4 if required
	 */
	private function WordPressUpgradeToUtf8mb4()
	{
		/** @var  wpdb $wpdb */ global $wpdb;

		// Is it really WordPress?
		if (!is_object($wpdb))
		{
			return;
		}

		// Is it really WordPress?
		if (!method_exists($wpdb, 'has_cap'))
		{
			return;
		}

		// Does the database support utf8mb4 at all?
		if (!$wpdb->has_cap('utf8mb4'))
		{
			return;
		}

		// Is the actual charset set to utf8mb4?
		$charset = strtolower($wpdb->charset);

		if ($charset != 'utf8mb4')
		{
			return;
		}

		// OK, all conditions met, let's upgrade the tables to utf8mb4
		$dbInstaller = new Installer($this->container);
		$dbInstaller->setForcedFile($this->container->basePath . '/assets/sql/xml/utf8mb4_update.xml');
		$dbInstaller->updateSchema();

		return;
	}

	/**
	 * Removes obsolete files given on a list
	 *
	 * @param   array  $removeFiles  List of files to remove
	 *
	 * @return void
	 */
	private function _removeFiles(array $removeFiles)
	{
		if (empty($removeFiles))
		{
			return;
		}

		$fsBase = rtrim($this->container->filesystemBase, '/' . DIRECTORY_SEPARATOR) . '/';
		$fs     = $this->container->fileSystem;

		foreach ($removeFiles as $file)
		{
			$fs->delete($fsBase . $file);
		}
	}

	/**
	 * Removes obsolete folders given on a list
	 *
	 * @param   array  $removeFolders  List of folders to remove
	 *
	 * @return void
	 */
	private function _removeFolders(array $removeFolders)
	{
		if (empty($removeFolders))
		{
			return;
		}

		$fsBase = rtrim($this->container->filesystemBase, '/' . DIRECTORY_SEPARATOR) . '/';
		$fs     = $this->container->fileSystem;

		foreach ($removeFolders as $folder)
		{
			$fs->rmdir($fsBase . $folder, true);
		}
	}

	/**
	 * Migrates existing backup profiles. The changes currently made are:
	 * * Change post-processing from "s3" (legacy) to "amazons3" (current version).
	 * * Fix profiles with invalid embedded installer settings.
	 * * Migrate to separate local and remote quota settings.
	 *
	 * @return  void
	 */
	private function migrateProfiles()
	{
		// Get a list of backup profiles
		$db       = $this->container->db;
		$query    = $db->getQuery(true)->select($db->qn('id'))->from($db->qn('#__ak_profiles'));
		$profiles = $db->setQuery($query)->loadColumn();

		// Normally this should never happen as we're supposed to have at least profile #1
		if (empty($profiles))
		{
			return;
		}

		// Migrate each profile
		foreach ($profiles as $profile)
		{
			// Initialization
			$dirty = false;

			// Load the profile configuration
			Platform::getInstance()->load_configuration($profile);
			$config = Factory::getConfiguration();

			// Remove key protection
			$protected = $config->getProtectedKeys();
			$config->setProtectedKeys([]);

			// -- Migrate obsolete "s3" engine to "amazons3"
			if ($config->get('akeeba.advanced.postproc_engine', '') == 's3')
			{
				$dirty = true;

				$config->setKeyProtection('akeeba.advanced.postproc_engine', false);
				$config->setKeyProtection('engine.postproc.amazons3.signature', false);
				$config->setKeyProtection('engine.postproc.amazons3.accesskey', false);
				$config->setKeyProtection('engine.postproc.amazons3.secretkey', false);
				$config->setKeyProtection('engine.postproc.amazons3.usessl', false);
				$config->setKeyProtection('engine.postproc.amazons3.bucket', false);
				$config->setKeyProtection('engine.postproc.amazons3.directory', false);
				$config->setKeyProtection('engine.postproc.amazons3.rrs', false);
				$config->setKeyProtection('engine.postproc.amazons3.customendpoint', false);
				$config->setKeyProtection('engine.postproc.amazons3.legacy', false);

				$config->set('akeeba.advanced.postproc_engine', 'amazons3');
				$config->set('engine.postproc.amazons3.signature', 's3');
				$config->set('engine.postproc.amazons3.accesskey', $config->get('engine.postproc.s3.accesskey'));
				$config->set('engine.postproc.amazons3.secretkey', $config->get('engine.postproc.s3.secretkey'));
				$config->set('engine.postproc.amazons3.usessl', $config->get('engine.postproc.s3.usessl'));
				$config->set('engine.postproc.amazons3.bucket', $config->get('engine.postproc.s3.bucket'));
				$config->set('engine.postproc.amazons3.directory', $config->get('engine.postproc.s3.directory'));
				$config->set('engine.postproc.amazons3.rrs', $config->get('engine.postproc.s3.rrs'));
				$config->set(
					'engine.postproc.amazons3.customendpoint', $config->get('engine.postproc.s3.customendpoint')
				);
				$config->set('engine.postproc.amazons3.legacy', $config->get('engine.postproc.s3.legacy'));
			}

			// Fix and migrate profiles with invalid, or outdated embedded installer settings.
			$currentInstaller = $config->get('akeeba.advanced.embedded_installer');

			if (
				empty($currentInstaller)
				|| ($currentInstaller === 'angie-joomla')
				|| ($currentInstaller === 'brs-joomla')
				|| ($currentInstaller === 'brs')
			    || (
					(substr($currentInstaller, 0, 3) != 'brs')
			        && ($currentInstaller != 'none'))
			)
			{
				$dirty = true;

				$newInstaller = str_replace('angie', 'brs', $currentInstaller);

				if (!in_array($newInstaller, ['brs', 'brs-generic', 'brs-wordpress']))
				{
					$newInstaller = defined('WPINC') ? 'brs-wordpress' : 'brs';
				}

				$config->setKeyProtection('akeeba.advanced.embedded_installer', false);
				$config->set('akeeba.advanced.embedded_installer', $newInstaller);
			}

			// Transcribe the local quota to remote quota settings if the legacy "Enable remote quotas" option is on.
			if ($config->get('akeeba.quota.remote', 0) == 1)
			{
				$dirty = true;

				$config->set('akeeba.quota.remote', null);
				$config->set('akeeba.quota.remotely.maxage.enable', $config->get('akeeba.quota.maxage.enable', 0));
				$config->set('akeeba.quota.remotely.maxage.maxdays', $config->get('akeeba.quota.maxage.maxdays', 31));
				$config->set('akeeba.quota.remotely.maxage.keepday', $config->get('akeeba.quota.maxage.keepday', 1));
				$config->set('akeeba.quota.remotely.enable_size_quota', $config->get('akeeba.quota.enable_size_quota', 0));
				$config->set('akeeba.quota.remotely.size_quota', $config->get('akeeba.quota.size_quota', 15728640));
				$config->set('akeeba.quota.remotely.enable_count_quota', $config->get('akeeba.quota.enable_count_quota', 1));
				$config->set('akeeba.quota.remotely.count_quota', $config->get('akeeba.quota.count_quota', 3));
			}

			// Restore key protection
			$config->setProtectedKeys($protected);

			// Save dirty records
			if ($dirty)
			{
				Platform::getInstance()->save_configuration($profile);
			}
		}
	}

	/**
	 * Migrate the settings encryption key file, if needed
	 *
	 * @return  void
	 * @since   8.0.0
	 */
	private function migrateSecretKeyFile(): void
	{
		$oldFile = $this->findBestSecretKeyFile();
		$newFile = APATH_BASE . '/Solo/secretkey.php';

		// Different secretkey.php when using WordPress
		if (defined('ABSPATH'))
		{
			$newFile = rtrim(
				            (defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : (rtrim(ABSPATH, '/') . '/wp-content')),
				            '/'
			            ) . '/akeebabackup_secretkey.php';
		}

		if (empty($oldFile) || !file_exists($oldFile))
		{
			return;
		}

		if ($oldFile !== $newFile)
		{
			if (@copy($oldFile, $newFile))
			{
				@unlink($oldFile);
			}
		}

		Factory::getSecureSettings()->setKeyFilename($newFile);
	}

	/**
	 * Find the secret key file which decrypts the most backup profiles.
	 *
	 * @return  string|null  The path the best key file. NULL if there's no file, or encryption is disabled.
	 * @since   8.1.0
	 */
	private function findBestSecretKeyFile(): ?string
	{
		// If encryption is not supported, or disabled, there's nothing I need to do. Right?
		$secureSettings = Factory::getSecureSettings();

		if (!$secureSettings->supportsEncryption())
		{
			return null;
		}

		// Extract the keys from the different possible files
		$files = [
			APATH_BASE . '/Solo/engine/secretkey.php',
			APATH_BASE . '/Solo/secretkey.php',
		];

		// Different secretkey.php when using WordPress
		if (defined('ABSPATH'))
		{
			$files[] = rtrim(
				            (defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : (rtrim(ABSPATH, '/') . '/wp-content')),
				            '/'
			            ) . '/akeebabackup_secretkey.php';
		}

		$keys = array_combine(
			$files,
			array_map([$this, 'getSecretKeyFromFile'], $files)
		);

		// Remove empty keys (file cannot be read, or does not exist).
		$keys = array_filter($keys);

		// Get the backup profile configuration from the database
		$platform = Platform::getInstance();
		$db = $this->container->db;
		try
		{
			$sql = $db->getQuery(true)
				->select([
					$db->quoteName('id'),
					$db->quoteName('configuration'),
				])
				->from($db->qn($platform->tableNameProfiles));

			$encryptedProfiles = $db->setQuery($sql)->loadAssocList('id', 'configuration');
		}
		catch (\Throwable $e)
		{
			return false;
		}

		// For each key file, try to decrypt the backup profiles and assign it the number of profiles it decrypted
		$keys = array_map(function ($key) use ($encryptedProfiles, $secureSettings) {
			$test = array_map(function($data) use ($secureSettings, $key) {
				$data = is_array($data) ? '' : $data;
				$data = $secureSettings->decryptSettings($data, $key);
				try
				{
					$data = json_decode($data, true, 512, JSON_THROW_ON_ERROR);

					if (!is_array($data) || empty($data))
					{
						return 0;
					}
				}
				catch (\JsonException $e)
				{
					return 0;
				}

				return 1;
			}, $encryptedProfiles);

			return array_sum($test);
		}, $keys);

		// Rank key files by number of decrypted profiles, ascending (best key file is last)
		asort($keys);

		// Remove key files which failed to decrypt anything
		$keys = array_filter($keys, fn($x) => $x > 0);

		// No key files succeeded. Oof.
		if (empty($keys))
		{
			return null;
		}

		// Get and return the highest ranking key file.
		$files = array_keys($keys);

		return array_pop($files);
	}

	/**
	 * Extract the secret key from a key file without including it (since we cannot redeclare constants)
	 *
	 * @param   string  $filePath
	 *
	 * @return  string|null  The key, or NULL if the file is missing, unreadable, or of an invalid format.
	 * @since   8.1.0
	 */
	private function getSecretKeyFromFile(string $filePath): ?string
	{
		// Make sure there is a file, and we can read its contents.
		if (!@file_exists($filePath) || !@is_file($filePath) || !@is_readable($filePath))
		{
			return null;
		}

		$fileContents = @file_get_contents($filePath);

		if ($fileContents === false)
		{
			return null;
		}

		// Try to locate the key value using a RegEx
		$pattern = '/define\s*\(\s*(\'AKEEBA_SERVERKEY\'|"AKEEBA_SERVERKEY")\s*,\s*(\'.*\'|".*")\s*\)/';

		if (!preg_match($pattern, $fileContents, $matches))
		{
			return null;
		}

		if (!isset($matches[2]) || !is_string($matches[2]) || empty($matches[2]))
		{
			return null;
		}

		// The matched string is either `'SOMETHING'` or `"SOMETHING"`. We need to find the quote type and remove it.
		$quote = substr($matches[2], 0, 1);

		$encodedKey = trim($matches[2], $quote);

		return base64_decode($encodedKey);
	}
}
