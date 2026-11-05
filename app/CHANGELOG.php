<?php die();?>
Akeeba Solo 9.3.0
================================================================================
! Restoration: archive entries with dot paths in their name could escape the extraction target root
+ Added support for seven additional Amazon S3 regions
+ Import Archives from S3 now works with S3-compatible storage, using the profile's custom endpoint
- Removed "Upload to Microsoft OneDrive (LEGACY)" and "Upload to Microsoft OneDrive (App-Specific Folder)"
- Removed the obsolete Akeeba Backup JSON API v1 endpoint and its "APIv1 removed" answer
~ Control Panel now warns when the OpenSSL extension is missing, since settings then store as plaintext
~ Restoration: removed SFTP and "use FTP for all files" write modes; only Direct and Hybrid remain
~ Restoration: the resume allow-list is now derived from the extraction engine's own class hierarchy
~ PostgreSQL backup: only pass --no-policies to pg_dump 18+, older versions reject it
~ PostgreSQL backup: Exempt CREATE UNIQUE INDEX from the IF NOT EXISTS rewrite too
~ PostgreSQL backup: Quote jsonb values as text, not as hex-encoded BYTEA
~ PostgreSQL backup: Rewrite CREATE VIEW to OR REPLACE, not unsupported IF NOT EXISTS
~ Packages no longer ship third-party development and test files
~ Packages no longer ship the developer-only Errortest controller
~ Core packages no longer ship the Pro-only Crons (WP-Cron scheduling) files
~ Packages no longer ship the application's composer.json and composer.lock
~ Packages no longer ship a stale plain-text Kickstart copy, the old stats collector and CSS source maps
~ PHP 8.4 and 8.5 are now officially supported
~ Schedule: warns that a weak Secret Key makes the front-end and JSON API fail, and strikes out the URLs and commands
~ Core packages no longer ship leftover files of the Pro-only Site Transfer Wizard
# [HIGH] JSON API startBackup: overrides parameter could rewrite arbitrary engine configuration
# [HIGH] Backup engine: finalization queue could be made to instantiate an arbitrary class
# [HIGH] Backup engine: Amazon S3 custom endpoint scheme sanitisation had a typo'd check
# [HIGH] Restoration: ZIP archives written with "fast" compression (7-Zip, WinZip) reported as corrupt
# [HIGH] Restoration: "delete site files before extracting" fatally errored before deleting anything
# [HIGH] Restoration: "delete site files before extracting" also failed under restore.php
# [HIGH] Restoration: Hybrid write mode corrupted its snapshot, breaking multi-step extractions
# [HIGH] Restoration: removed the dead Mcrypt AES adapter; JPS decryption now fails cleanly without OpenSSL
# [HIGH] Restoration: a database hostname containing an underscore had its port number appended twice
# [HIGH] Backup page return URL could run script or redirect off-site via control characters or "///" URLs
# [HIGH] YubiKey two-factor authentication accepted a one-time password from any YubiKey
# [HIGH] OAuth2 Authenticate button (Box, Dropbox, Google Drive, OneDrive) failed with an invalid security token error
# [HIGH] Failed upload checks (check-failed-upload, altcheck-failed-upload, uploadcheck) crashed instead of e-mailing
# [HIGH] Upgrade step could reset encrypted backup profiles to defaults when settings encryption was just enabled
# [HIGH] PHP 7.4: Configuration page failed with "Call to undefined function str_contains()"
# [HIGH] Core: a failed backup left the Backup page blank instead of showing the error and the log link
# [HIGH] Front-end backup CRON jobs failed with a 403 when the Secret Key contained & # + or %
# [HIGH] WP-CLI: "backup upload" always failed with a PHP error instead of re-uploading the backup
# [HIGH] WP-CLI: "backup fetch" failed for backups of any backup profile other than the default one
# [HIGH] WP-CLI: "filter include-database" could not be given a host and stored the user name under the wrong key
# [HIGH] Site Transfer Wizard skipped every other part of a multi-part backup, so Kickstart could not extract it
# [HIGH] Solo: page loads at the same time could overwrite config.php with a stub, losing the database settings
# [MEDIUM] ALICE diagnostics rendered attacker-controlled log content as raw, unescaped HTML
# [MEDIUM] OAuth2 linking (Box, Dropbox, Google Drive, OneDrive) lacked anti-CSRF state protection
# [MEDIUM] Backup engine did not strip control characters from logged messages
# [MEDIUM] Backup engine logged the raw PushBullet API key when a notification failed
# [MEDIUM] FTP download-to-browser no longer exposes the FTP username and password in a redirect
# [MEDIUM] Restoration: the serialised extraction state was unserialized without a class allow-list
# [MEDIUM] Cancelling an edit form could redirect to an external site given in the return URL
# [MEDIUM] Saving System Configuration or changing backup profiles could redirect to an external site
# [MEDIUM] Updates were checked over plain HTTP and packages were accepted from any server the update info named
# [MEDIUM] Folder browser and S3 Import reflected the folder parameter unescaped into the page
# [MEDIUM] Manage Backups: deleting the files of a single backup also deleted its backup record
# [MEDIUM] Site Transfer Wizard could not upload multipart backups: part files were named site./01 instead of site.j01
# [MEDIUM] Two-factor authentication setup QR code no longer displayed; drawing it sent the secret key to Google
# [MEDIUM] Restoration: "display_errors" warning appeared even when the host had display_errors turned off
# [MEDIUM] A backup that died in its first step stayed "pending" forever instead of being marked failed
# [MEDIUM] Configuration: the Test connection button of the DirectSFTP over cURL archiver did nothing
# [MEDIUM] FTP connection tests and site transfer failed with an HTTP 500 error when PHP's FTP extension is missing
# [MEDIUM] Manage Backups: toolbar View / Edit comment and Download used the record opened last, not the ticked one
# [MEDIUM] Importing a profile stored its secrets (S3 keys, passwords) unencrypted despite settings encryption
# [MEDIUM] Multiple Databases: the editor did not show the stored port, so saving an edit blanked or replaced it
# [MEDIUM] Configuration: script error with OneDrive as the upload engine, and when the Google Drive list arrived late
# [MEDIUM] Core: the Configuration page raised a JavaScript error, so the Configuration Wizard prompt never appeared
# [MEDIUM] Schedule: the JSON API commands showed "&" in the Secret Key as "&amp;", so the copied command failed
# [MEDIUM] Database Table Exclusion failed with an HTTP 500 error on PHP 8 when the profile has no database
# [MEDIUM] RegEx exclusion filters lost the <...> of lookbehinds and named groups when saved, matching the wrong items
# [MEDIUM] Off-site Directories: exclusion filters stopped applying once the path was made portable
# [MEDIUM] Import Archives from S3: keys which Amazon rejects ended in an application error instead of a message
# [MEDIUM] altcheck-failed.php and altcheck-failed-upload.php exited with 0 when the check failed, hiding it from cron
# [MEDIUM] backup.php and altbackup.php took profile 0, a negative ID or text for profile 1 instead of refusing it
# [MEDIUM] WP-CLI: "option set" stored "yes" as 0 for on/off options and accepted values outside an option's choices
# [MEDIUM] WP-CLI: "profile import" stored S3 keys and passwords unencrypted despite settings encryption
# [MEDIUM] WP-CLI: profile 0 or a negative ID was taken for profile 1; "option set" even changed that profile
# [MEDIUM] Site Transfer Wizard: the "Over HTTP / HTTPS" archive transfer mode failed with "No data specified"
# [MEDIUM] Site Transfer Wizard: an address whose domain does not resolve showed nothing instead of the warning
# [MEDIUM] System Configuration: the Database tab changed nothing; it now saves settings that connect and refuses others
# [MEDIUM] System Configuration: the Database tab saved a database without Solo's tables silently; now it warns
# [MEDIUM] CLI backups given a non-existent profile ID were recorded against it; they now use the default profile
# [MEDIUM] System Configuration: the Update tab had a duplicate HTML ID and a tuning option repeated its label
# [MEDIUM] Backup: an error starting the backup was retried, then shown as a misleading "restart your backup" error
# [LOW] PHP notices could appear during backups, database filtering, missing-record actions and FTP transfers
# [LOW] Invalid configuration files loaded silently; grid actions and failed DB connections raised warnings
# [LOW] Exporting a missing backup profile returned HTTP 500 instead of a JSON 404 response
# [LOW] Log view could switch the active profile before its own access check ran
# [LOW] Control Panel's latest-backup popup did not escape the backup description
# [LOW] Backup view's comment textarea did not escape the reflected comment
# [LOW] Remote backup files whose storage engine name used upper case letters were not recognised as remote
# [LOW] Several JS error messages used innerHTML instead of textContent, risking markup injection
# [LOW] OAuth2 callback/helper/refresh URLs in System Configuration were not HTML-escaped
# [LOW] Root-containment checks treated sibling folders sharing a name prefix as inside the root
# [LOW] Two exception-chain walkers could loop or recurse forever on a wrapped exception
# [LOW] Log viewer did not escape the timestamp field, only the message field
# [LOW] OAuth2 refresh saved the new refresh token under a misspelled key, discarding it
# [LOW] OAuth2 errors showed the error_uri value instead of the actual error description
# [LOW] Site Transfer's backup-selection ordering was passed as a raw SQL string, silently ignored
# [LOW] Backup engine's untagged log file lacked a real safety guard against direct access
# [LOW] Backup engine did not sanitise backup warnings before queuing them
# [LOW] Backup engine's memory file safety guard did not actually prevent direct access
# [LOW] Backup engine's SQLite driver ignored the LIKE-wildcard escaping flag
# [LOW] Backup engine's statistics UPDATE did not restrict columns, unlike its INSERT
# [LOW] Backup engine did not quote identifiers when renaming SQLite tables or reading schema
# [LOW] Backup engine contained dead code that could misdirect proxy credentials
# [LOW] Backup engine's settings key file lookup depended on the current working directory
# [LOW] Restoration: PHP 8.1 deprecation notice extracting a JPS archive containing a symbolic link
# [LOW] Restoration: JPS decryption never trimmed padding from a decrypted block (already trimmed by callers)
# [LOW] Restoration: PHP 8.5 deprecation notices when a JPS archive password is under 16 characters
# [LOW] Restoration: PHP 8.1 deprecation notice extracting into an existing folder with no target configured
# [LOW] Restoration: PHP 8.1 deprecation notices from the file zapper and restoration finalisation
# [LOW] Restoration: PHP 8.1 deprecation notices, PHP 9 fatal error, for UNIX socket/named pipe DB hostnames
# [LOW] Deleting an obsolete backup log file variant emitted a harmless PHP "file not found" warning
# [LOW] In debug mode, the Setup wizard's steps could be triggered cross-site after installation
# [LOW] Users with only the Backup privilege got an error when opening Manage Backups from the Control Panel
# [LOW] Setup wizard reflected database and error parameters unescaped into the page
# [LOW] Manage Backups comment editor and popovers could render backup comments as HTML
# [LOW] Manage Backups download notice did not escape the archive name
# [LOW] Some admin pages and error pages printed configuration values and error messages unescaped
# [LOW] Two-factor authentication codes were compared loosely
# [LOW] Folder-inside-site checks gave wrong answers on Windows when path separators were mixed
# [LOW] Backup archive downloads did not tell browsers and proxies not to store them
# [LOW] Site Transfer Wizard always uploaded Kickstart as kickstart.php instead of a random filename
# [LOW] Manage Backups read its list offset (limitstart) unfiltered because of a mismatched state key
# [LOW] Failed backup notifications were not sent to administrators when no email address was configured
# [LOW] S3 Import did not list .jps (encrypted) backup archives
# [LOW] Deleting remotely stored backup files in several steps stopped after the first step (missing anti-CSRF token)
# [LOW] Usage statistics reported standalone Akeeba Solo sites as Akeeba Backup for WordPress
# [LOW] Two-factor authentication secrets were generated with a predictable random number generator
# [LOW] Backup profiles could be read cross-site through a JSONP callback parameter
# [LOW] A request with a crafted Host header could load a leftover developer overrides file
# [LOW] On IIS, protective web.config files did not block direct access to PHP and other files
# [LOW] Login page could be pre-filled with credentials passed in the URL or by HTTP Basic authentication
# [LOW] Session cookie was sent without the HttpOnly, Secure and SameSite flags
# [LOW] Standalone pages could be framed by other sites and lacked nosniff and referrer-policy headers
# [LOW] Manage Backups: pagination footer always said "Page 1" and the first-page link needed two clicks
# [LOW] Configuration check warning q204 (disabled PHP functions) never appeared
# [LOW] Control Panel: latest backup showed a dash as origin for JSON API and WP-Cron backups
# [LOW] Configuration: Apply left the profile unnamed when the One-click icon changed and the name box was empty
# [LOW] Site Transfer Wizard raised a JavaScript error when there was no backup to transfer
# [LOW] Configuration Wizard: the last step (splitting the archive) never showed as completed
# [LOW] Multiple Databases: saving the empty "add" row stored an empty database definition
# [LOW] Files and Directories Exclusion: changing the root listed twice and raised an error in tabular view
# [LOW] Exclusion tabular views: a filter the server refused stayed listed as if it had been saved
# [LOW] RegEx Database Tables Exclusion: changing the database in the root selector did not reload the list
# [LOW] RegEx Exclusion pages showed HTML entities in the label of a just-saved expression, until reloaded
# [LOW] RegEx Exclusion pages treated a save the server refused as saved, and a refused rename deleted the original
# [LOW] Configuration: the "configuration saved" message never appeared after Apply or Save
# [LOW] Configuration: choosing "Custom" for a number option saved the previously chosen preset instead
# [LOW] Schedule: the Check tabs printed a stray "; ?>" after the note about substituting the PHP path
# [LOW] Schedule: a very long Secret Key made the page scroll sideways
# [LOW] Configuration: the OpenStack Swift upload engine description showed a raw language key
# [LOW] Database Table Exclusion opened on an additional database instead of the site's main database
# [LOW] Import Archives: a single-part archive was recorded as multipart, unlike a backup taken by the product
# [LOW] Fetching a remote backup back to the server left a stray .tmp file in the output directory
# [LOW] Import Archives from S3 did not pre-fill the keys from the profile's Amazon S3 settings
# [LOW] Updated view templates could keep running stale code on servers with OPcache timestamp checks disabled
# [LOW] Alternate CLI scripts named the wrong script or product in their banner and configuration errors
# [LOW] altbackup.php: --no-verify was inverted; the TLS certificate is now verified unless --no-verify is given
# [LOW] WP-CLI: backup info, modify, delete, upload and fetch crashed or claimed success on a missing backup record
# [LOW] WP-CLI: "option set" stored text as 0 for number options; "option get" printed nothing or crashed on a bad key
# [LOW] WP-CLI: "profile create" said "Copy successful" instead of saying that it created a profile
# [LOW] WP-CLI: "sysconfig set" accepted any text for numbers, ports, on/off switches and drop-down options
# [LOW] WP-CLI: the "filter" help told to name an off-site directory root by its UUID, which the commands refuse
# [LOW] WP-CLI: "backup upload" without remote storage claimed success and marked the backup as uploaded
# [LOW] View Log raised a PHP 8.1+ deprecation notice until a log file was selected
# [LOW] Exclusion pages raised PHP warnings when changing the database or resetting file filters (PHP 8.1+)
# [LOW] Schedule: loading the page rewrote the application configuration file eight times instead of twice
# [LOW] WP-CLI: "backup fetch" of a backup recorded with no remote storage reported a confusing file error
# [LOW] Site Transfer Wizard: manual method showed blank names for the parts of a multi-part archive
# [LOW] Site Transfer Wizard: the passive mode workaround switch showed the passive mode setting after a reload
# [LOW] Site Transfer Wizard: PHP 8.1+ deprecation notice when the new site's address has no path
# [LOW] Site Transfer Wizard error messages showed raw HTML tags such as <code> and <em>
# [LOW] Backing up another site (root override) told the restoration script the wrong site root
# [LOW] Backups recorded the application's own host name, not the Site URL option, in the restoration data
# [LOW] Setup wizard: the Database step raised PHP warnings about undefined SSL settings on its first visit
# [LOW] System Configuration saved every request variable, cookies included, into the configuration
# [LOW] Upgrade removes the session and login cookies which older System Configuration saves stored in the configuration
# [LOW] Upgrade removes the unused database keys, one with the password, which the Database tab left in config.php
# [LOW] ALICE never warned that an additional database is the site's own database, as it read the wrong settings
# [LOW] Solo: an empty or damaged config.php showed any visitor its path and a stack trace, with HTTP 200
# [LOW] Application errors returned HTTP 200 and showed exception details to any visitor outside debug mode
# [LOW] CLI scripts printed a stack trace when config.php was empty or damaged; they now say so and stop
# [LOW] OAuth2 helper and page template files could be requested directly, revealing the server path in PHP errors
# [LOW] Site Transfer Wizard: a thrown upload error was sent under the wrong key and displayed only by luck
# [LOW] Restoration: tables with a backtick in their name were not dropped, so restoring over them failed

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
