<?php die();?>
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
