<?php die();?>
Akeeba Solo 9.2.0
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
# [LOW] Setup wizard: Previous button linked to a nonexistent view

Akeeba Solo 9.1.8
================================================================================
+ Site Transfer Wizard: transfer any backup archive present on the server, not just the latest one (gh-239)
# [HIGH] Database dumps silently lost precision on DOUBLE columns (PDO MySQL and PostgreSQL)
# [HIGH] Box, Dropbox, OneDrive stored files under the local temp name
# [HIGH] Uploads failed when using a bucket-restricted BackBlaze B2 key
# [MEDIUM] Box reported an expired authorisation as an opaque HTTP 401 error
# [MEDIUM] Dropbox did not explain a missing or lapsed Download ID
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
# [MEDIUM] Hardened access control checks for the profiles and user management pages
# [LOW] Removed the unused, legacy FTP/SFTP directory browser
# [LOW] Broadened anti-CSRF token coverage across AJAX and maintenance actions
# [LOW] Front-end backup and post-backup check endpoints now use constant-time secret word comparison
# [LOW] Additional two-factor authentication and anti-CSRF token comparison hardening
# [LOW] WordPress: the control panel is no longer rendered when the application files are accessed directly
# [MEDIUM] FTP/FTPS connection test ignored the "Use FTP over SSL" setting due to a config key mismatch, causing it to fail against FTPS-only servers
# [LOW] Legacy (hard-disabled) SFTP directory browser model used the wrong option keys for key-based authentication

Akeeba Solo 9.1.7
================================================================================
~ Switched to BackBlaze B2 v4 API
~ Obfuscate kickstart.txt so that broken file scanners (OVH) don't cause problems by misidentifying it as "malicious"
# [HIGH] The Site Transfer Wizard was not working
# [HIGH] DirectFTP would not work due to setting erroneous directory permissions

Akeeba Solo 9.1.6
================================================================================
~ HTML output hardening

Akeeba Solo 9.1.5
================================================================================
! Wrong packaging led into raw HTML being output in many pages of the software

Akeeba Solo 9.1.4
================================================================================
# [HIGH] Site Transfer Wizard: PHP fatal error when loading the page
# [MEDIUM] Fix stdClass warning when reading akeeba.quota.logfiles configuration key

Akeeba Solo 9.1.3
================================================================================
+ Add "Delete obsolete log files" quota feature
~ Manage Backups: the View Log button is now disabled with a tooltip when the log file no longer exists on the server
# [HIGH] Check file upload: SQL error on all PHP versions when checking for failed uploads
# [MEDIUM] Configuration page: saving an SFTP password containing an angle bracket (e.g. `<F9`) would blank the profile description and deselect the one-click backup icon

Akeeba Solo 9.1.2
================================================================================
+ Failed backup upload check
~ curl_close is deprecated in PHP 8.5.0

Akeeba Solo 9.1.1
================================================================================
- Remove obsolete JSON tasks
# [HIGH] Obsolete update JSON task caused the API to fail

Akeeba Solo 9.1.0
================================================================================
+ Site Transfer Wizard now uploads Kickstart under a random filename
~ New archive extraction script (based on Kickstart 9)
# [HIGH] MySQL to MariaDB: SQL errors when the collation is converted to
 `uca1400_*`
# [LOW] Moving from MariaDB to MySQL could result in SQL error.

Akeeba Solo 9.0.6
================================================================================
+ Using IMDSv2 for getting the credentials off EC2 instances
+ More inline text explaining the concept of backup profiles throughout the interface
- Remove platform check from updates due to confusing results in some cases
# [HIGH] "Normalise character set" can break the restoration

Akeeba Solo 9.0.5
================================================================================
+ Amazon S3: Support for ACLs on the uploaded backup archives
+ Warn about using bak_ as the database table name prefix
~ Improved layout in the Database Tables Exclusion page
# [MEDIUM] PHP Error doing a site DB only backup when additional database definitions are present

Akeeba Solo 9.0.4
================================================================================
+ Support for const instead of define() in wp-config.php files

Akeeba Solo 9.0.3
================================================================================
+ Support for tables with backticks in their names
+ WordPress backup: automatically exclude WordPress' debug.log
+ Profiles page: button to reset selected backup profiles
~ Restoration: Eliminate deprecation notices under PHP 8.4
# [HIGH] Restoration: lack of otherwise optional mbstring would result in an error
# [HIGH] CLI restoration: WordPress restoration always complains about `siteurl` not being set
# [HIGH] CLI restoration: error about the DB port being out of range
# [HIGH] WordPress restoration CLI: wrong variable name leads to PHP error
# [MEDIUM] Some configuration settings are inherited from the default profile when a profile is reset or created afresh
# [LOW] WordPress data replacement would fail on duplicate options keys

Akeeba Solo 9.0.2
================================================================================
~ WP restoration: rewritten data replacement engine for performance
# [MEDIUM] Restoration: PHP error when the server reports the site's root as the filesystem root (chroot jail)
# [MEDIUM] Joomla restoration: mail online setting not respected in the web interface
# [LOW] Possible PHP error trying to parse invalid URLs
# [LOW] Deleting the items of the last page in Manage Backups page results in an empty display you can't easily get out of

Akeeba Solo 9.0.1
================================================================================
~ Automatically exclude the .cagefs directory present in some cPanel installations
# [HIGH] Joomla restoration: PHP Error resetting Joomla! 4 MFA
# [MEDIUM] Possible restoration issues if the upgrade code does not execute when installing the update
# [LOW] Restoration: PHP Deprecated warnings when checking for legacy magic quotes features on PHP 7

Akeeba Solo 9.0.0
================================================================================
+ New restoration script framework, with a minimum requirement of PHP 7.2
~ PHP 8.4: Implicit nullable types are not allowed
~ Maximum batch row size for database backup is now 10000 by default, with a maximum of 1000000
# [HIGH] WordPress restoration: `meta_key` column data had its values data-replaced for multisite installations
# [HIGH] Box: cannot refresh the authentication token
# [LOW] The list of tables was no longer output
# [LOW] WebDAV: deleting backups may file on some servers

Akeeba Solo 8.3.0
================================================================================
~ Make accurate PHP CLI path detection optional
# [HIGH] Some OneDrive multipart uploads fail

Akeeba Solo 8.2.7
================================================================================
! Could not work with MySQL 5.x and MariaDB 10.x

Akeeba Solo 8.2.5
================================================================================
+ More accurate information about PHP CLI in the Schedule Automatic Backups page
+ Improved database dump engine
~ Option to disable PHP version checks for updates
~ Adjust the size and text on the warning about ad blockers

Akeeba Solo 8.2.4
================================================================================
+ Edit and reset the cache directory (Joomla! 5.1+) on restoration
+ Remove MariaDB MyISAM option PAGE_CHECKSUM from the database dump
~ Improve database dump with table names similar to default values
~ Change the wording of the message when navigating to an off-site directory in the directory browser
~ PHP 8.4 compatibility: MD5 and SHA-1 functions are deprecated
# [HIGH] Custom OAuth2 token refresh did not work reliably
# [MEDIUM] Tables or databases named `0` can cause the database dump to stop prematurely, or not execute at all
# [MEDIUM] Akeeba Backup CORE showed the WP-CRON link but the feature is only shipped with Professional

Akeeba Solo 8.2.3
================================================================================
+ Option to avoid using `flush()` on broken servers
# [HIGH] OAuth2 Helpers didn't work properly due to a typo in the released version

Akeeba Solo 8.2.2
================================================================================
- Remove the deprecated, ineffective CURLOPT_BINARYTRANSFER flag
+ Alternate Configuration page saving method which doesn't hit maximum POST parameter count limits
+ ShowOn in the System Configuration page
+ Self-hosted OAuth2 helpers
# [LOW] Deprecation notice in Configuration Wizard

Akeeba Solo 8.2.1
================================================================================
+ Upload to OneDrive (app-specific folder)
# [LOW] PHP error when two processes try to store update information concurrently

Akeeba Solo 8.2.0
================================================================================
! Cannot complete the setup due to an inversion of login in the Setup view
+ Expert options for the Upload to Amazon S3 configuration
+ Separate remote and local quota settings
# [MEDIUM] Clicking on Backup Now would start the backup automatically

Akeeba Solo 8.1.2
================================================================================
+ Automatically downgrade utf8mb4_900_* collations to utf8mb4_unicode_520_ci on MariaDB
+ Joomla restoration: allows you to change the robots (search engine) option
~ Change the message when the PHP or WordPress requirements are not met in available updates
~ Remove the message about the release being 120 days old

Akeeba Solo 8.1.1
================================================================================
- Removed support for Akeeba Backup JSON API v1 (APIv1)
- Removed support for the legacy Akeeba Backup JSON API endpoint (wp-content/plugins/akeebabackupwp/app/index.php)
# [MEDIUM] PHP error when adding Solo to the backup

Akeeba Solo 8.1.0
================================================================================
# [HIGH] PHP error in Manage Backups when you have pending or failed backups

Akeeba Solo 8.1.0.b1
================================================================================
# [LOW] Downgrading from Pro to Core would make it so that you always saw an update available
# [LOW] Management column show the wrong file extension for the last file you need to download

Akeeba Solo 8.0.0
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
