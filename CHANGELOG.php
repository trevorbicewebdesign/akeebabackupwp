<?php die(); ?>
Akeeba Backup 9.2.0
================================================================================
+ wp akeeba profile import: select profile with --profile; overwrite it with --force
~ Log files never use .log; hosts blocking .log.php get .php log files
~ Fine-tuned access control across all views and tasks
# [HIGH] wp akeeba filter commands ignored --profile, wrote to profile 1, and returned "Success"
# [HIGH] Database dumps lost DOUBLE precision with PDO MySQL and PostgreSQL
# [MEDIUM] Restoration: FTP test ignored FTPS and passive mode settings
# [MEDIUM] Restoration and self-update saved wrong FTPS and passive mode extraction settings
# [MEDIUM] FTP and SFTP tests showed the password in plain text on authentication failure
# [MEDIUM] Split archives could end with an empty part, failing post-processing
# [MEDIUM] ALICE never listed failed backup logs for selection
# [MEDIUM] wp akeeba backup take ignored --overrides=…
# [MEDIUM] Pythia could corrupt or reject wp-config.php DB credentials due to faulty constant parsing
# [MEDIUM] canAccess() allowed unauthenticated access when the privilege list was empty
# [MEDIUM] Profile switching and update checks did not validate the return URL
# [LOW] Front-end check and upload check endpoints threw instead of returning HTTP 403
# [LOW] FTP over cURL test ignored the passive mode workaround
# [LOW] simplifyPath() stripped 'administrator' when it was not a complete path segment
# [LOW] convertMemoryLimitToBytes() could return a string instead of an integer
# [LOW] System Configuration: JavaScript error from the removed FTP/SFTP directory browser
# [LOW] Profile import without a selected file showed an error page
# [LOW] wp akeeba profile import treated the file path as JSON instead of reading it
# [LOW] Release candidate and dev versions were normalised with the wrong revision
# [LOW] Base64-like return URLs were decoded into binary garbage
# [LOW] Backup sizes of 1EB or more had no unit
# [LOW] Format::fileSize() raised a TypeError when given a unit name
# [LOW] Archive relative path was wrong when the archive folder was above the site root
# [LOW] wp akeeba backup take reported long elapsed times incorrectly
# [LOW] wp akeeba backup list --description=0 ignored the filter

Akeeba Backup 9.1.8
================================================================================
+ Site Transfer Wizard: transfer any backup archive present on the server, not just the latest one (gh-239)
# [HIGH] Fix WP-CLI wp akeeba backup fetch crashing with ArgumentCountError
# [HIGH] Box, Dropbox, OneDrive stored files under the local temp name
# [HIGH] Uploads failed when using a bucket-restricted BackBlaze B2 key
# [MEDIUM] WebDAV did not surface failed upload reason to the UI or the log file
# [MEDIUM] Box, Dropbox, Google Drive, OneDrive token refresh raced expiry
# [MEDIUM] RackSpace CloudFiles validated the username, not the API key
# [MEDIUM] Dropbox public download URL embedded the access token
# [MEDIUM] OneDrive for Business signed download URL was broken on Graph
# [MEDIUM] S3 v4 pre-signed URLs failed (403) on path-style non-AWS hosts
# [MEDIUM] Google Storage pre-signed URLs duplicated the bucket name (403)
# [LOW] Box folder listing paginated incorrectly
# [LOW] BackBlaze cancelUpload sent the request body as form-data, not JSON
# [LOW] BackBlaze downloadFileById used the wrong API path (404)
# [LOW] WebDAV options() dropped capabilities from repeated DAV headers
# [MEDIUM] Hardened access control checks for the backup management pages
# [LOW] Removed the unused, legacy FTP/SFTP directory browser
# [LOW] Broadened anti-CSRF token coverage across AJAX and maintenance actions
# [LOW] Front-end backup and post-backup check endpoints now use constant-time secret word comparison
# [LOW] Additional two-factor authentication and anti-CSRF token comparison hardening
# [LOW] The control panel is no longer rendered when the plugin application files are accessed directly

Akeeba Backup 9.1.7
================================================================================
~ Switched to BackBlaze B2 v4 API
~ Obfuscate kickstart.txt so that broken file scanners (OVH) don't cause problems by misidentifying it as "malicious"
# [HIGH] The Site Transfer Wizard was not working
# [HIGH] DirectFTP would not work due to setting erroneous directory permissions

Akeeba Backup 9.1.6
================================================================================
~ HTML output hardening
# [MEDIUM] WordPress 7.0 resutls in updates to the plugin no longer being found

Akeeba Backup 9.1.5
================================================================================
! Wrong packaging led into raw HTML being output in many pages of the plugin

Akeeba Backup 9.1.4
================================================================================
# [HIGH] Site Transfer Wizard: PHP fatal error when loading the page
# [MEDIUM] Fix stdClass warning when reading akeeba.quota.logfiles configuration key
# [MEDIUM] WP-CLI backup: PHP fatal error after backup completion due to closed database connection

Akeeba Backup 9.1.3
================================================================================
+ Add "Delete obsolete log files" quota feature
~ Manage Backups: the View Log button is now disabled with a tooltip when the log file no longer exists on the server
# [HIGH] WP-CRON backups always time out on servers with a short PHP execution time limit
# [HIGH] Check file upload: SQL error on all PHP versions when checking for failed uploads
# [MEDIUM] Configuration page: saving an SFTP password containing an angle bracket (e.g. `<F9`) would blank the profile description and deselect the one-click backup icon
# [LOW] PHP Error "Trying to access array offset on null" in AkeebaBackupWPUpdater
# [LOW] PHP Warning "Undefined property: stdClass::$response / $no_update" in AkeebaBackupWPUpdater

Akeeba Backup 9.1.2
================================================================================
+ Failed backup upload check
~ curl_close is deprecated in PHP 8.5.0
~ Always showing update information in the Plugins page

Akeeba Backup 9.1.0
================================================================================
+ Site Transfer Wizard now uploads Kickstart under a random filename
+ Akeeba Backup for WordPress will now notify you of leftovers after restoration in your WordPress admin panel
~ New archive extraction script (based on Kickstart 9)
# [HIGH] MySQL to MariaDB: SQL errors when the collation is converted to
`uca1400_*`
# [LOW] PHP Warning when using WP-CRON scheduling
# [LOW] Moving from MariaDB to MySQL could result in SQL error.

Akeeba Backup 9.0.6
================================================================================
- Remove platform check from updates due to confusing results in some cases
+ More inline text explaining the concept of backup profiles throughout the interface
# [HIGH] "Normalise character set" can break the restoration
# [LOW] Suppress WordPress' deprecation notice about PHPmailer; we are aware of the change, we're only using the deprecated include file for the benefit of certain broken third party plugins.

Akeeba Backup 9.0.5
================================================================================
+ Amazon S3: Support for ACLs on the uploaded backup archives
+ Warn about using bak_ as the database table name prefix
~ Improved layout in the Database Tables Exclusion page
~ Workaround for third party plugins loading PHPMailer in an unsafe manner, e.g. QuForm
# [MEDIUM] PHP Error doing a site DB only backup when additional database definitions are present

Akeeba Backup 9.0.4
================================================================================
+ Support for const instead of define() in wp-config.php files
# [HIGH] Statistics collection makes WordPress report an error

Akeeba Backup 9.0.3
================================================================================
+ Support for tables with backticks in their names
+ WordPress backup: automatically exclude WordPress' debug.log
+ Profiles page: button to reset selected backup profiles
~ Schedule Automatic Backups, Check Backup Status tab now uses wp-ajax.php just like the backup
~ Restoration: Eliminate deprecation notices under PHP 8.4
# [HIGH] Restoration: lack of otherwise optional mbstring would result in an error
# [HIGH] CLI restoration: WordPress restoration always complains about `siteurl` not being set
# [HIGH] CLI restoration: error about the DB port being out of range
# [HIGH] WordPress restoration CLI: wrong variable name leads to PHP error
# [MEDIUM] Some configuration settings are inherited from the default profile when a profile is reset or created afresh
# [LOW] WordPress data replacement would fail on duplicate options keys

Akeeba Backup 9.0.2
================================================================================
~ WP restoration: rewritten data replacement engine for performance
# [MEDIUM] Restoration: PHP error when the server reports the site's root as the filesystem root (chroot jail)
# [LOW] Possible PHP error trying to parse invalid URLs
# [LOW] Deleting the items of the last page in Manage Backups page results in an empty display you can't easily get out of

Akeeba Backup 9.0.1
================================================================================
~ "Do not check minimum supported PHP version" is now forcibly enabled at all times
~ Automatically exclude the .cagefs directory present in some cPanel installations
# [MEDIUM] Possible restoration issues if the upgrade code does not execute when installing the update
# [LOW] Restoration: PHP Deprecated warnings when checking for legacy magic quotes features on PHP 7

Akeeba Backup 9.0.0
================================================================================
~ PHP 8.4: Implicit nullable types are not allowed
~ Maximum batch row size for database backup is now 10000 by default, with a maximum of 1000000
~ Improved routing in WordPress
# [HIGH] WordPress restoration: `meta_key` column data had its values data-replaced for multisite installations
# [HIGH] Box: cannot refresh the authentication token
# [LOW] The list of tables was no longer output
# [LOW] WebDAV: deleting backups may file on some servers

Akeeba Backup 8.3.0
================================================================================
+ Roles and Capabilities support
~ Make accurate PHP CLI path detection optional
# [MEDIUM] WordPress always reports updates as incompatible
# [HIGH] Some OneDrive multipart uploads fail

Akeeba Backup 8.2.7
================================================================================
! Could not work with MySQL 5.x and MariaDB 10.x

Akeeba Backup 8.2.5
================================================================================
+ More accurate information about PHP CLI in the Schedule Automatic Backups page
+ Improved database dump engine
~ Option to disable PHP version checks for updates
~ WordPress' Site Health feature now displays if the backup is out of date, or has failed
~ Adjust the size and text on the warning about ad blockers
# [HIGH] Fatal error uninstalling the plugin
# [HIGH] Erroneous report of a fatal error activating the plugin
# [MEDIUM] Erroneous WordPress log entry "Cron reschedule event error for hook: abwp_cron_scheduling, Error code: invalid_schedule" in the Pro version
# [LOW] The profiles table is not uninstalled when the plugin is deleted
# [LOW] Error thrown when faulty third party plugins return invalid data to the site_status_tests hook before we handle it.
# [LOW] Fatal error when profile configuration data is missing (something's broken in your database)

Akeeba Backup 8.2.4
================================================================================
+ Remove MariaDB MyISAM option PAGE_CHECKSUM from the database dump
~ Improve database dump with table names similar to default values
~ Change the wording of the message when navigating to an off-site directory in the directory browser
~ PHP 8.4 compatibility: MD5 and SHA-1 functions are deprecated
# [MEDIUM] Tables or databases named `0` can cause the database dump to stop prematurely, or not execute at all

Akeeba Backup 8.2.3
================================================================================
+ Option to avoid using `flush()` on broken servers
# [HIGH] OAuth2 Helpers didn't work properly due to a typo in the released version

Akeeba Backup 8.2.2
================================================================================
- Remove the deprecated, ineffective CURLOPT_BINARYTRANSFER flag
+ Alternate Configuration page saving method which doesn't hit maximum POST parameter count limits
+ ShowOn in the System Configuration page
+ Self-hosted OAuth2 helpers
# [LOW] Deprecation notice in Configuration Wizard

Akeeba Backup 8.2.1
================================================================================
+ Upload to OneDrive (app-specific folder)
# [LOW] PHP error when two processes try to store update information concurrently

Akeeba Backup 8.2.0
================================================================================
+ Expert options for the Upload to Amazon S3 configuration
+ Separate remote and local quota settings
# [MEDIUM] Clicking on Backup Now would start the backup automatically
# [MEDIUM] CLI backups would not send emails, reporting the mysqli connection is already closed

Akeeba Backup 8.1.2
================================================================================
+ Automatically downgrade utf8mb4_900_* collations to utf8mb4_unicode_520_ci on MariaDB
~ Remove the message about the release being 120 days old

Akeeba Backup 8.1.1
================================================================================
- Removed support for Akeeba Backup JSON API v1 (APIv1)
+ Re-enabled integrated updates with WordPress
# [HIGH] Raw views include WordPress HTML
# [MEDIUM] SQL error after finishing migrating archives
# [LOW] Double URL in the JSON API section of the scheduling info page

Akeeba Backup 8.1.0
================================================================================
# [HIGH] PHP error in Manage Backups when you have pending or failed backups
# [HIGH] CLI scripts did not work through the 8.1.0 betas

Akeeba Backup 8.1.0.b3
================================================================================
# [HIGH] Wrong update URL

Akeeba Backup 8.1.0.b2
================================================================================
# [HIGH] The migration is never over if you have backup records claiming their archive files exist but, in fact, they do not

Akeeba Backup 8.1.0.b1
================================================================================
~ Moved the default backup output folder to wp-content/backups
~ Moved the settings encryption key to wp-content/akeebabackupwp_secretkey.php
~ Automatic migration of backup archives and backup profiles outside the plugin's root folder
~ Path shown for backups is now relative to WordPress' root folder (as reported by its `ABSPATH` constant)
- Removed admin dashboard widgets
# [LOW] Downgrading from Pro to Core would make it so that you always saw an update available
# [LOW] Management column show the wrong file extension for the last file you need to download

Akeeba Backup 8.0.0.2
================================================================================
# [HIGH] Change in WordPress itself causes a PHP fatal error at the end of the update
# [HIGH] Fatal error sending emails at the end of the backup
# [LOW] Cosmetic issue: application name appeared as Akeeba Solo instead of Akeeba Backup for WordPress in some screens
# [LOW] Translations not loaded during frontend and remote JSON API backups

Akeeba Backup 8.0.0.1
================================================================================
# [HIGH] Profile encryption key migration does not work when using WordPress' plugins update
# [LOW] PHP deprecated warnings

Akeeba Backup 8.0.0
================================================================================
+ Minimum PHP version is now 7.4.0
+ Using Composer to load all internal dependencies (AWF, backup engine, S3 library)
+ Workaround for Wasabi S3v4 signatures
+ Support for uploading to Shared With Me folders in Google Drive
~ Improved error reporting, removing the unhelpful "(HTML containing script tags)" message
~ Improved mixed– and upper–case database prefix support at backup time
# [MEDIUM] Resetting corrupt backups can cause a crash of the Control Panel page
# [MEDIUM] Upload to S3 would always use v2 signatures with a custom endpoint.
# [MEDIUM] Some transients need data replacements to take place in WP 6.3
# [HIGH] Not choosing a forced backup timezone in System Configuration results in the WP-CRON Scheduling page throwing an error
