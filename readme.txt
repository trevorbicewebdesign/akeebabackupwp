=== Akeeba Backup CORE for WordPress ===
Contributors: nikosdion
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=10903325
Tags: backup, restore, migrate, move
Requires at least: 6.3.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 9.3.0
License: GPL-3.0-or-later
License URI: http://www.gnu.org/licenses/gpl.html

Easily backup, restore and move your WordPress site with the fastest, most robust, native PHP backup plugin.

== Description ==

Akeeba Backup Core for WordPress is an open-source, free of charge backup plugin for WordPress, quite a bit different
than the others. Its mission is simple: create a full site backup (files and database) that can be restored on any
WordPress-capable server. Even without having WordPress already installed.

Akeeba Backup creates a full backup of your site in a single archive. The archive contains all the files,
a database snapshot and a web installer which is as easy to use as WordPress' famous five minute installation procedure.
The backup and restore process is AJAX powered to avoid server timeouts, even with huge sites. Serialised data is
handled automatically. Our long experience –the backup engine is being continuously developed and perfected since 2006–
guarantees that. You can also make a backup of only your database, or only your site's files.

If you want a reliable, easy to use, open source backup solution for your WordPress site, you've found it!

*Important note*: The software, its [documentation](https://www.akeeba.com/documentation/akeeba-solo.html)
 and [video tutorials](https://www.akeeba.com/videos/1215-akeeba-backup-wordpress.html) are
 provided free of charge. Personalised support is not free; it requires paying for a support subscription. That's what
 pays the bills and lets us keep on writing good quality software full time.

Features:

* You own your data. Hosted services hold your data only as long as you pay them a monthly fee. With Akeeba Backup you have full control over the backup archives you generate.
* Send your backups to another server by FTP or SFTP. (SFTP support requires the SSH2 PHP module to be installed on the server hosting your WordPress site).
* Serialised data are automatically adjusted on restoration WITHOUT third party tools and WITHOUT precarious regular expressions which can break your site.
* WordPress Multisite supported out of the box, today.
* The fastest native PHP backup engine. You don't need to upload Linux executable files on your server!
* Works on any virtually any server environment: Apache, NginX, Lightspeed, Lighttpd, IIS and more on Windows, Linux, Mac OS X, Solaris and more.
* No more timeouts on large sites. Our renowned engine is designed for big sites in mind. Largest successfully backed up site reported so far: 110GB (yes, Gigabytes).
* It configures itself for optimal operation with your site. Just click on Configuration Wizard.
* One click backup with desktop notifications when it's finished. No need to stare at the screen any more.
* AJAX-powered backup (site and database, database only, files only or incremental files only backup).
* Choose between standard ZIP format, the highly efficient JPA archive format or the encrypted JPS format (encrypted JPS format available in paid version only).
* You can exclude specific files and folders.
* You can exclude specific database tables or just their contents.
* Unattended backup mode (scheduled / automated backups), fully compatible with WebCRON.org.
* *NEW* Scheduled backups with CRON jobs running on your server.
* *NEW* Automatic log analyser to help you fix backup issues without having to pay for a support subscription.
* AJAX-powered site restoration script included in the backup.
* *NEW* Integrated restoration for restoring the backup on the same server you backed up from.
* Import backup archives after uploading them back to your server. Useful for restoring after reinstalling WordPress on the same or a new server.
* Archives can be restored on any host using Akeeba Kickstart (free of charge script to extract the backup archives on any server, *without* installing WordPress and Akeeba Backup). Useful for transferring your site between subdomains/hosts or even to/from your local testing server (XAMPP, WAMPServer, MAMP, Zend Server, etc).

and much, much more!

Indicative uses:

* Security backups.
* Creating development sites to test new ideas, make site redesigns or troubleshoot issues.
* Transfer a site you created locally to a live server.
* Create "template" sites and clone them to fast-track the development of your clients' sites.

Restoring your backups requires extracting them first. If you are restoring to a different server you need to download
our [free of charge Akeeba Kickstart script](https://www.akeeba.com/download/akeeba-kickstart.html) from our site.
If you are restoring on the same server you can simply use the integrated restoration feature in the plugin itself.

If you need to extract a backup archive on your Windows, Linux or Mac OS X computer you can use our free of charge
[Akeeba eXtract Wizard](github.com/akeeba/nativexplatform/releases) desktop software.

[More features](https://www.akeeba.com/products/1610-akeeba-wp-core-vs-professional.html) are available in the
separate product called "Akeeba Backup Professional for WordPress" which you can only download after purchasing a
[support subscription](https://www.akeeba.com/subscribe/new/backupwp.html?layout=default) on our site. This
includes automatically transferring your backups to Amazon S3, Dropbox, OneDrive, Box.com and another 40+ storage
providers for safekeeping. Clarification: these features are NOT available in Akeeba Backup CORE for WordPress available
from WordPress.org. These premium features are only provided as a thank-you to people who choose to support us
financially by purchasing a support subscription on our site.

== Installation ==

1. Install Akeeba Backup for WordPress either via the WordPress.org plugin directory, or by uploading the files to your
   server. In the latter case we suggest you to upload the files into your site's `/wp-content/plugins/akeebabackupwp`
   directory.
1. Activate the plugin through the 'Plugins' menu in WordPress
1. You will see the Akeeba Backup icon in your sidebar, below the Plugins section in the wp-admin area of your site.
   Click on it.
1. Click on the Configuration Wizard button and site back while the plugin configures itself *automatically*.
1. Every time you want to take a backup, click on the big blue Backup Now button in the plugin's interface.
1. That's it! Really, it is that simple!

== Frequently Asked Questions ==

= I have spotted a bug. Now what? =

Please use [our Contact Us page](https://www.akeeba.com/contact-us.html) to file a bug report. Make sure that you
indicate "Bug report" in the Category selection. We will review your bug report and work to fix it. We may contact you
for further information if necessary. If we don't contact you be assured that if you did report a bug we are already
working on fixing it.

= I am trying to install the plugin but the upload fails =

The plugin is quite big (around 4MB). Most servers have an upload limit of 2MB. You can either ask your host to increase
the file upload limit to 5MB or you can install the plugin manually. Please see the Installation section for more
information.

= I have a problem using the plugin. What should I do? =

The first thing you should do is [read our extensive documentation](https://www.akeeba.com/documentation/akeeba-solo.html)
and our [troubleshooter](https://www.akeeba.com/documentation/troubleshooter.html). If you'd like to receive
personalised support from the developers of the plugin you can [subscribe](https://www.akeeba.com/subscribe/new/backupwp.html?layout=default)
to our services. Due to the very specialised nature of the software and our goal of providing exceptional support we do
not outsource our support. All support requests are answered by the developers who write the software. This is why we
require a subscription to provide support.

= Does your software support WordPress MU (multi-sites a.k.a. blog networks)? =

Yes. We have added full WordPress multi-sites support since late 2014. You can restore backups to different servers or
locations and things will still work.

= What about serialised data? =

Not a problem! You've probably used a lot of tools to try and manually replace serialised data after moving your site to
a different domain or directory and you were worried because they don't always work very well. We have implemented our
own tokeniser and assembler for serialised data which works the same way PHP works under the hood. Simply put, our
solution doesn't use precarious regular expressions and isn't even the least inclined on killing your serialised data.

Please note that for data replacement to work properly all of your plugins must be storing their data in UTF-8 encoding
in the database. Some themes use a double encoding which may result in invalid data. Unfortunately that's a problem with
these themes and we can't fix it. On the other hand these themes' developers seem to be aware of this issue and provide
their own settings export and import. If your theme provides such a feature please use it. We can't reliably work around
third party code not following the character encoding standards established well over twenty years ago...

= WordPress moved to UTF8MB4 (UTF-8 Multibyte). Do you support it? =

Yes, in full. Akeeba Backup will work no matter if your site uses UTF8MB4 or the old UTF-8 encoding. If you backup a
site with data encoded in UTF-8 the restoration will work on a server supporting UTF8MB4. Going the opposite way will
not work because of a MySQL restriction. If you end up with truncated text or MySQL errors on restoration that's the
reason. In this case you will have to ask your host to update their version of MySQL to 5.5 or later.

= What are the requirements for your plugin? =

Akeeba Backup for WordPress requires PHP 5.4 or any later version. Older versions of PHP including PHP 4, 5.0, 5.1,
5.2 and 5.3 are not supported. We recommend using PHP 5.6 or later for security and performance reasons.

Akeeba Backup for WordPress has been tested on WordPress 3.8 and later. It should work on earlier versions of WordPress
but we cannot guarantee this.

Akeeba Backup for WordPress requires at least 16MB of PHP memory (memory_limit). We strongly suggest 64MB or more for
optimal operation on large sites with hundreds of media files and hundreds of thousands of comments.

Some features may require the PHP cURL extension to be installed and activated on your server. If unsure ask your host.

Finally, you need adequate disk space to take a backup of your site. As a rule of thumb, that's about 80% the current
size of your site's public web directory (usually called public_html, htdocs, httpdocs, www or something in the like).

= Can I use this plugin on commercial sites / sites I am building for my clients? =

Yes, of course! Our plugin is licensed under the GNU General Public License version 3 or, at your option, any later
version of the license published by the Free Software Foundation. This license gives you the same Four Freedoms as
WordPress' license; in fact, GPLv3 is simply a newer version of the same GPLv2 license WordPress is using, one which
protects your interests even more.

= I have sites using other scripts / CMS. Can I use your software with them? =

Akeeba Backup is available in three different packages. Akeeba Backup for WordPress is designed to backup and restore
WordPress sites. Akeeba Backup for Joomla! does the same for Joomla! sites. Akeeba Solo is our standalone backup
software which support WordPress, Joomla!, Magento, PrestaShop, phpBB3 and many other CMS and scripts. Use the contact
link on our site to request more information for your specific needs.

== Screenshots ==

1. A control panel interface puts everything you need under your fingertips.
2. Akeeba Backup automatically configures itself for optimal performance on your site.
3. Click on Backup Now, sit back and your backup is taken in a snap.
4. Managing backups is dead simple. And see just how fast backups are!
5. Advanced users can tweak Akeeba Backup to their liking
6. Excluding directories uses an intuitive file manager. No need to fiddle with unsightly directory names!
7. Want to automate your backups? Akeeba Backup will give you step by step instructions, specific to your site.

== Changelog ==

* eeba Backup 9.3.0
* Restoration: archive entries with dot paths in their name could escape the extraction target root
* Import Archives from S3 now works with S3-compatible storage, using the profile's custom endpoint
* Removed "Upload to Microsoft OneDrive (LEGACY)" and "Upload to Microsoft OneDrive (App-Specific Folder)"
* Removed the obsolete Akeeba Backup JSON API v1 endpoint and its "APIv1 removed" answer
* Control Panel now warns when the OpenSSL extension is missing, since settings then store as plaintext
* Restoration: removed SFTP and "use FTP for all files" write modes; only Direct and Hybrid remain
* Restoration: the resume allow-list is now derived from the extraction engine's own class hierarchy
* PostgreSQL backup: only pass --no-policies to pg_dump 18+, older versions reject it
* PostgreSQL backup: Exempt CREATE UNIQUE INDEX from the IF NOT EXISTS rewrite too
* PostgreSQL backup: Quote jsonb values as text, not as hex-encoded BYTEA
* PostgreSQL backup: Rewrite CREATE VIEW to OR REPLACE, not unsupported IF NOT EXISTS
* Packages no longer ship third-party development and test files
* Packages no longer ship the developer-only Errortest controller
* Core packages no longer ship the Pro-only Crons (WP-Cron scheduling) files
* Packages no longer ship the application's composer.json and composer.lock
* Packages no longer ship a stale plain-text Kickstart copy, the old stats collector and CSS source maps
* PHP 8.4 and 8.5 are now officially supported
* CLI scripts now locate WordPress by wp-settings.php, which also supports layouts such as Bedrock
* Schedule: warns that a weak Secret Key makes the front-end and JSON API fail, and strikes out the URLs and commands
* Core packages no longer ship leftover files of the Pro-only Site Transfer Wizard
* [HIGH] JSON API startBackup: overrides parameter could rewrite arbitrary engine configuration
* [HIGH] Backup engine: finalization queue could be made to instantiate an arbitrary class
* [HIGH] Backup engine: Amazon S3 custom endpoint scheme sanitisation had a typo'd check
* [HIGH] Restoration: ZIP archives written with "fast" compression (7-Zip, WinZip) reported as corrupt
* [HIGH] Restoration: "delete site files before extracting" fatally errored before deleting anything
* [HIGH] Restoration: "delete site files before extracting" also failed under restore.php
* [HIGH] Restoration: Hybrid write mode corrupted its snapshot, breaking multi-step extractions
* [HIGH] Restoration: removed the dead Mcrypt AES adapter; JPS decryption now fails cleanly without OpenSSL
* [HIGH] Backup page return URL could run script or redirect off-site via control characters or "///" URLs
* [HIGH] OAuth2 Authenticate button (Box, Dropbox, Google Drive, OneDrive) failed with an invalid security token error
* [HIGH] Failed upload checks (check-failed-upload, altcheck-failed-upload, uploadcheck) crashed instead of e-mailing
* [HIGH] Upgrade step could reset encrypted backup profiles to defaults when settings encryption was just enabled
* [HIGH] Core: a failed backup left the Backup page blank instead of showing the error and the log link
* [HIGH] Front-end backup CRON jobs failed with a 403 when the Secret Key contained & # + or %
* [HIGH] WP-CLI: "backup upload" always failed with a PHP error instead of re-uploading the backup
* [HIGH] WP-CLI: "backup fetch" failed for backups of any backup profile other than the default one
* [HIGH] WP-CLI: "filter include-database" could not be given a host and stored the user name under the wrong key
* [HIGH] Site Transfer Wizard skipped every other part of a multi-part backup, so Kickstart could not extract it
* [MEDIUM] ALICE diagnostics rendered attacker-controlled log content as raw, unescaped HTML
* [MEDIUM] OAuth2 linking (Box, Dropbox, Google Drive, OneDrive) lacked anti-CSRF state protection
* [MEDIUM] Backup engine did not strip control characters from logged messages
* [MEDIUM] Backup engine logged the raw PushBullet API key when a notification failed
* [MEDIUM] FTP download-to-browser no longer exposes the FTP username and password in a redirect
* [MEDIUM] Restoration: the serialised extraction state was unserialized without a class allow-list
* [MEDIUM] Cancelling an edit form could redirect to an external site given in the return URL
* [MEDIUM] Saving System Configuration or changing backup profiles could redirect to an external site
* [MEDIUM] Updates were checked over plain HTTP and packages were accepted from any server the update info named
* [MEDIUM] Folder browser and S3 Import reflected the folder parameter unescaped into the page
* [MEDIUM] Manage Backups: deleting the files of a single backup also deleted its backup record
* [MEDIUM] Site Transfer Wizard could not upload multipart backups: part files were named site./01 instead of site.j01
* [MEDIUM] Restoration: "display_errors" warning appeared even when the host had display_errors turned off
* [MEDIUM] Manage Backups: toolbar View / Edit comment and Download used the record opened last, not the ticked one
* [MEDIUM] Importing a profile stored its secrets (S3 keys, passwords) unencrypted despite settings encryption
* [MEDIUM] Pages requested a missing fef.css and never loaded the plugin's own FEF stylesheet, breaking the layout
* [MEDIUM] Multiple Databases: the editor did not show the stored port, so saving an edit blanked or replaced it
* [MEDIUM] Configuration: script error with OneDrive as the upload engine, and when the Google Drive list arrived late
* [MEDIUM] Configuration: Save & Close, Save & New and Cancel stayed on the page; Save & New left a stray profile
* [MEDIUM] Core: the Configuration page raised a JavaScript error, so the Configuration Wizard prompt never appeared
* [MEDIUM] Schedule: the JSON API commands showed "&" in the Secret Key as "&amp;", so the copied command failed
* [MEDIUM] RegEx exclusion filters lost the <...> of lookbehinds and named groups when saved, matching the wrong items
* [MEDIUM] Off-site Directories: exclusion filters stopped applying once the path was made portable
* [MEDIUM] Remotely stored files: "Download to your desktop" showed an empty admin page instead of downloading
* [MEDIUM] Import Archives from S3: keys which Amazon rejects ended in an application error instead of a message
* [MEDIUM] Database Table Exclusion failed with an HTTP 500 error on PHP 8 when the profile has no database
* [MEDIUM] WP-CLI: "option set" stored "yes" as 0 for on/off options and accepted values outside an option's choices
* [MEDIUM] WP-CLI: "profile import" stored S3 keys and passwords unencrypted despite settings encryption
* [MEDIUM] altcheck-failed.php and altcheck-failed-upload.php exited with 0 when the check failed, hiding it from cron
* [MEDIUM] backup.php and altbackup.php took profile 0, a negative ID or text for profile 1 instead of refusing it
* [MEDIUM] WP-CLI: profile 0 or a negative ID was taken for profile 1; "option set" even changed that profile
* [MEDIUM] Site Transfer Wizard: the "Over HTTP / HTTPS" archive transfer mode failed with "No data specified"
* [MEDIUM] Site Transfer Wizard: an address whose domain does not resolve showed nothing instead of the warning
* [MEDIUM] CLI backups given a non-existent profile ID were recorded against it; they now use the default profile
* [MEDIUM] Site Health measured backup staleness from a failed backup when failed-backup warnings were disabled
* [MEDIUM] System Configuration: the Update tab had a duplicate HTML ID and a tuning option repeated its label
* [MEDIUM] Backup: an error starting the backup was retried, then shown as a misleading "restart your backup" error
* [LOW] PHP notices could appear during backups, database filtering, missing-record actions and FTP transfers
* [LOW] Invalid configuration files loaded silently; grid actions and failed DB connections raised warnings
* [LOW] Log view could switch the active profile before its own access check ran
* [LOW] Control Panel's latest-backup popup did not escape the backup description
* [LOW] Backup view's comment textarea did not escape the reflected comment
* [LOW] Remote backup files whose storage engine name used upper case letters were not recognised as remote
* [LOW] Several JS error messages used innerHTML instead of textContent, risking markup injection
* [LOW] OAuth2 callback/helper/refresh URLs in System Configuration were not HTML-escaped
* [LOW] Root-containment checks treated sibling folders sharing a name prefix as inside the root
* [LOW] Two exception-chain walkers could loop or recurse forever on a wrapped exception
* [LOW] Log viewer did not escape the timestamp field, only the message field
* [LOW] OAuth2 refresh saved the new refresh token under a misspelled key, discarding it
* [LOW] OAuth2 errors showed the error_uri value instead of the actual error description
* [LOW] Site Transfer's backup-selection ordering was passed as a raw SQL string, silently ignored
* [LOW] Backup engine's untagged log file lacked a real safety guard against direct access
* [LOW] Backup engine did not sanitise backup warnings before queuing them
* [LOW] Backup engine's memory file safety guard did not actually prevent direct access
* [LOW] Backup engine's SQLite driver ignored the LIKE-wildcard escaping flag
* [LOW] Backup engine's statistics UPDATE did not restrict columns, unlike its INSERT
* [LOW] Backup engine did not quote identifiers when renaming SQLite tables or reading schema
* [LOW] Backup engine contained dead code that could misdirect proxy credentials
* [LOW] Backup engine's settings key file lookup depended on the current working directory
* [LOW] Restoration: PHP 8.1 deprecation notice extracting a JPS archive containing a symbolic link
* [LOW] Restoration: JPS decryption never trimmed padding from a decrypted block (already trimmed by callers)
* [LOW] Restoration: PHP 8.5 deprecation notices when a JPS archive password is under 16 characters
* [LOW] Restoration: PHP 8.1 deprecation notice extracting into an existing folder with no target configured
* [LOW] Restoration: PHP 8.1 deprecation notices from the file zapper and restoration finalisation
* [LOW] Deleting an obsolete backup log file variant emitted a harmless PHP "file not found" warning
* [LOW] The 7-to-8 migration link could be triggered cross-site or by users without the Configure privilege
* [LOW] Users with only the Backup privilege got an error when opening Manage Backups from the Control Panel
* [LOW] Manage Backups comment editor and popovers could render backup comments as HTML
* [LOW] Backup-before-WordPress-update page echoed request values unescaped
* [LOW] Manage Backups download notice did not escape the archive name
* [LOW] Some admin pages and error pages printed configuration values and error messages unescaped
* [LOW] Folder-inside-site checks gave wrong answers on Windows when path separators were mixed
* [LOW] Backup archive downloads did not tell browsers and proxies not to store them
* [LOW] Site Transfer Wizard always uploaded Kickstart as kickstart.php instead of a random filename
* [LOW] Manage Backups read its list offset (limitstart) unfiltered because of a mismatched state key
* [LOW] Version 8 migration did not mark all backup records with missing archives when there were 100 or more
* [LOW] Leftover-file cleanup never recognised multipart archive parts such as .z01 due to a broken pattern
* [LOW] Failed backup notifications were not sent to administrators when no email address was configured
* [LOW] S3 Import did not list .jps (encrypted) backup archives
* [LOW] Deleting remotely stored backup files in several steps stopped after the first step (missing anti-CSRF token)
* [LOW] Akeeba Backup Core installed the Pro-only backup-on-WordPress-update helper
* [LOW] Backup profiles could be read cross-site through a JSONP callback parameter
* [LOW] A request with a crafted Host header could load a leftover developer overrides file
* [LOW] On IIS, protective web.config files did not block direct access to PHP and other files
* [LOW] Pages left open across a WordPress nonce tick boundary failed with "Invalid security token"
* [LOW] The plugin's own app/index.php and app/remote.php answered web requests, showing server paths
* [LOW] The Backup page's anti-CSRF check accepted a WordPress nonce issued for any action
* [LOW] WP-CLI CSV output could carry spreadsheet formulas from backup or profile descriptions
* [LOW] Manage Backups: pagination footer always said "Page 1" and the first-page link needed two clicks
* [LOW] Control Panel: latest backup showed a dash as origin for JSON API and WP-Cron backups
* [LOW] Configuration: Apply left the profile unnamed when the One-click icon changed and the name box was empty
* [LOW] Configuration: picking the site root as the output directory in the folder browser showed no warning
* [LOW] Site Transfer Wizard raised a JavaScript error when there was no backup to transfer
* [LOW] Configuration Wizard: the last step (splitting the archive) never showed as completed
* [LOW] Multiple Databases: saving the empty "add" row stored an empty database definition
* [LOW] Files and Directories Exclusion: changing the root listed twice and raised an error in tabular view
* [LOW] Exclusion tabular views: a filter the server refused stayed listed as if it had been saved
* [LOW] RegEx Database Tables Exclusion: changing the database in the root selector did not reload the list
* [LOW] RegEx Exclusion pages showed HTML entities in the label of a just-saved expression, until reloaded
* [LOW] RegEx Exclusion pages treated a save the server refused as saved, and a refused rename deleted the original
* [LOW] Schedule: the Check tabs printed a stray "; ?>" after the note about substituting the PHP path
* [LOW] Schedule: the Check tabs of Akeeba Backup for WordPress printed the site URL twice in the front-end URLs
* [LOW] Schedule: a very long Secret Key made the page scroll sideways
* [LOW] Leftovers notice ignored the .j01, .j02 ... parts of split JPA and JPS archives left in the site root
* [LOW] Restoration leftovers could be deleted by any plugin user, not only by those who can manage options
* [LOW] Restoration leftovers notice was hidden from backup operators and managers; they now see it, without Delete
* [LOW] Configuration: the Swift engine and "Disable step break between domains" descriptions showed raw language keys
* [LOW] The plugin's pages loaded the theme stylesheet (theme.min.css) twice
* [LOW] Database Table Exclusion opened on an additional database instead of the site's main database
* [LOW] Import Archives: a single-part archive was recorded as multipart, unlike a backup taken by the product
* [LOW] Fetching a remote backup back to the server left a stray .tmp file in the output directory
* [LOW] Import Archives from S3 did not pre-fill the keys from the profile's Amazon S3 settings
* [LOW] Updated view templates could keep running stale code on servers with OPcache timestamp checks disabled
* [LOW] WP-CLI: backup info, modify, delete, upload and fetch crashed or claimed success on a missing backup record
* [LOW] WP-CLI: "option set" stored text as 0 for number options; "option get" printed nothing or crashed on a bad key
* [LOW] WP-CLI: "profile create" said "Copy successful" instead of saying that it created a profile
* [LOW] WP-CLI: "sysconfig set" accepted any text for numbers, ports, on/off switches and drop-down options
* [LOW] Alternate CLI scripts named the wrong script or product in their banner and configuration errors
* [LOW] altbackup.php: --no-verify was inverted; the TLS certificate is now verified unless --no-verify is given
* [LOW] WP-CLI: the "filter" help told to name an off-site directory root by its UUID, which the commands refuse
* [LOW] WP-CLI: "backup upload" without remote storage claimed success and marked the backup as uploaded
* [LOW] View Log raised a PHP 8.1+ deprecation notice until a log file was selected
* [LOW] System Configuration raised PHP 8.1+ deprecation notices when no filesystem driver was configured
* [LOW] Exclusion pages raised PHP warnings when changing the database or resetting file filters (PHP 8.1+)
* [LOW] Every backup step raised two PHP "headers already sent" warnings, which could fill the PHP log
* [LOW] Schedule: loading the page rewrote the application configuration file eight times instead of twice
* [LOW] WP-CLI: "backup fetch" of a backup recorded with no remote storage reported a confusing file error
* [LOW] Site Transfer Wizard: manual method showed blank names for the parts of a multi-part archive
* [LOW] Site Transfer Wizard: the passive mode workaround switch showed the passive mode setting after a reload
* [LOW] Site Transfer Wizard: PHP 8.1+ deprecation notice when the new site's address has no path
* [LOW] Site Transfer Wizard did not warn when the new site's address was the site being backed up
* [LOW] Site Transfer Wizard error messages showed raw HTML tags such as <code> and <em>
* [LOW] Backing up another site (root override) told the restoration script the wrong site root
* [LOW] Backups recorded the application's own host name, not the Site URL option, in the restoration data
* [LOW] System Configuration saved every request variable, cookies included, into the configuration
* [LOW] Upgrade removes the session and login cookies which older System Configuration saves stored in the configuration
* [LOW] ALICE never warned that an additional database is the site's own database, as it read the wrong settings
* [LOW] Application errors returned HTTP 200 and showed exception details to any visitor outside debug mode
* [LOW] Some template and bootstrap files could be requested directly, revealing the server path in PHP errors
* [LOW] Profile site overrides: the database SSL options showed even while the SSL switch was off
* [LOW] Site Transfer Wizard: a thrown upload error was sent under the wrong key and displayed only by luck
* [LOW] Restoration: tables with a backtick in their name were not dropped, so restoring over them failed

* eeba Backup 9.2.0
* wp akeeba profile import: select profile with --profile; overwrite it with --force
* Log files never use .log; hosts blocking .log.php get .php log files
* Fine-tuned access control across all views and tasks
* [HIGH] wp akeeba filter commands ignored --profile, wrote to profile 1, and returned "Success"
* [HIGH] Database dumps lost DOUBLE precision with PDO MySQL and PostgreSQL
* [MEDIUM] Restoration: FTP test ignored FTPS and passive mode settings
* [MEDIUM] Restoration and self-update saved wrong FTPS and passive mode extraction settings
* [MEDIUM] FTP and SFTP tests showed the password in plain text on authentication failure
* [MEDIUM] Split archives could end with an empty part, failing post-processing
* [MEDIUM] ALICE never listed failed backup logs for selection
* [MEDIUM] wp akeeba backup take ignored --overrides=…
* [MEDIUM] Pythia could corrupt or reject wp-config.php DB credentials due to faulty constant parsing
* [MEDIUM] canAccess() allowed unauthenticated access when the privilege list was empty
* [MEDIUM] Profile switching and update checks did not validate the return URL
* [LOW] Front-end check and upload check endpoints threw instead of returning HTTP 403
* [LOW] FTP over cURL test ignored the passive mode workaround
* [LOW] simplifyPath() stripped 'administrator' when it was not a complete path segment
* [LOW] convertMemoryLimitToBytes() could return a string instead of an integer
* [LOW] System Configuration: JavaScript error from the removed FTP/SFTP directory browser
* [LOW] Profile import without a selected file showed an error page
* [LOW] wp akeeba profile import treated the file path as JSON instead of reading it
* [LOW] Release candidate and dev versions were normalised with the wrong revision
* [LOW] Base64-like return URLs were decoded into binary garbage
* [LOW] Backup sizes of 1EB or more had no unit
* [LOW] Format::fileSize() raised a TypeError when given a unit name
* [LOW] Archive relative path was wrong when the archive folder was above the site root
* [LOW] wp akeeba backup take reported long elapsed times incorrectly
* [LOW] wp akeeba backup list --description=0 ignored the filter

* eeba Backup 9.1.8
* Site Transfer Wizard: transfer any backup archive present on the server, not just the latest one (gh-239)
* [HIGH] Fix WP-CLI wp akeeba backup fetch crashing with ArgumentCountError
* [HIGH] Box, Dropbox, OneDrive stored files under the local temp name
* [HIGH] Uploads failed when using a bucket-restricted BackBlaze B2 key
* [MEDIUM] WebDAV did not surface failed upload reason to the UI or the log file
* [MEDIUM] Box, Dropbox, Google Drive, OneDrive token refresh raced expiry
* [MEDIUM] RackSpace CloudFiles validated the username, not the API key
* [MEDIUM] Dropbox public download URL embedded the access token
* [MEDIUM] OneDrive for Business signed download URL was broken on Graph
* [MEDIUM] S3 v4 pre-signed URLs failed (403) on path-style non-AWS hosts
* [MEDIUM] Google Storage pre-signed URLs duplicated the bucket name (403)
* [LOW] Box folder listing paginated incorrectly
* [LOW] BackBlaze cancelUpload sent the request body as form-data, not JSON
* [LOW] BackBlaze downloadFileById used the wrong API path (404)
* [LOW] WebDAV options() dropped capabilities from repeated DAV headers
* [MEDIUM] Hardened access control checks for the backup management pages
* [LOW] Removed the unused, legacy FTP/SFTP directory browser
* [LOW] Broadened anti-CSRF token coverage across AJAX and maintenance actions
* [LOW] Front-end backup and post-backup check endpoints now use constant-time secret word comparison
* [LOW] Additional two-factor authentication and anti-CSRF token comparison hardening
* [LOW] The control panel is no longer rendered when the plugin application files are accessed directly

* eeba Backup 9.1.7
* Switched to BackBlaze B2 v4 API
* Obfuscate kickstart.txt so that broken file scanners (OVH) don't cause problems by misidentifying it as "malicious"
* [HIGH] The Site Transfer Wizard was not working
* [HIGH] DirectFTP would not work due to setting erroneous directory permissions

* eeba Backup 9.1.6
* HTML output hardening
* [MEDIUM] WordPress 7.0 resutls in updates to the plugin no longer being found

* eeba Backup 9.1.5
* Wrong packaging led into raw HTML being output in many pages of the plugin

* eeba Backup 9.1.4
* [HIGH] Site Transfer Wizard: PHP fatal error when loading the page
* [MEDIUM] Fix stdClass warning when reading akeeba.quota.logfiles configuration key
* [MEDIUM] WP-CLI backup: PHP fatal error after backup completion due to closed database connection

* eeba Backup 9.1.3
* Add "Delete obsolete log files" quota feature
* Manage Backups: the View Log button is now disabled with a tooltip when the log file no longer exists on the server
* [HIGH] WP-CRON backups always time out on servers with a short PHP execution time limit
* [HIGH] Check file upload: SQL error on all PHP versions when checking for failed uploads
* [MEDIUM] Configuration page: saving an SFTP password containing an angle bracket (e.g. `<F9`) would blank the profile description and deselect the one-click backup icon
* [LOW] PHP Error "Trying to access array offset on null" in AkeebaBackupWPUpdater
* [LOW] PHP Warning "Undefined property: stdClass::$response / $no_update" in AkeebaBackupWPUpdater

* eeba Backup 9.1.2
* Failed backup upload check
* curl_close is deprecated in PHP 8.5.0
* Always showing update information in the Plugins page

* eeba Backup 9.1.0
* Site Transfer Wizard now uploads Kickstart under a random filename
* Akeeba Backup for WordPress will now notify you of leftovers after restoration in your WordPress admin panel
* New archive extraction script (based on Kickstart 9)
* [HIGH] MySQL to MariaDB: SQL errors when the collation is converted to
* ca1400_*`
* [LOW] PHP Warning when using WP-CRON scheduling
* [LOW] Moving from MariaDB to MySQL could result in SQL error.

* eeba Backup 9.0.6
* Remove platform check from updates due to confusing results in some cases
* More inline text explaining the concept of backup profiles throughout the interface
* [HIGH] "Normalise character set" can break the restoration
* [LOW] Suppress WordPress' deprecation notice about PHPmailer; we are aware of the change, we're only using the deprecated include file for the benefit of certain broken third party plugins.

* eeba Backup 9.0.5
* Amazon S3: Support for ACLs on the uploaded backup archives
* Warn about using bak_ as the database table name prefix
* Improved layout in the Database Tables Exclusion page
* Workaround for third party plugins loading PHPMailer in an unsafe manner, e.g. QuForm
* [MEDIUM] PHP Error doing a site DB only backup when additional database definitions are present

* eeba Backup 9.0.4
* Support for const instead of define() in wp-config.php files
* [HIGH] Statistics collection makes WordPress report an error

* eeba Backup 9.0.3
* Support for tables with backticks in their names
* WordPress backup: automatically exclude WordPress' debug.log
* Profiles page: button to reset selected backup profiles
* Schedule Automatic Backups, Check Backup Status tab now uses wp-ajax.php just like the backup
* Restoration: Eliminate deprecation notices under PHP 8.4
* [HIGH] Restoration: lack of otherwise optional mbstring would result in an error
* [HIGH] CLI restoration: WordPress restoration always complains about `siteurl` not being set
* [HIGH] CLI restoration: error about the DB port being out of range
* [HIGH] WordPress restoration CLI: wrong variable name leads to PHP error
* [MEDIUM] Some configuration settings are inherited from the default profile when a profile is reset or created afresh
* [LOW] WordPress data replacement would fail on duplicate options keys

* eeba Backup 9.0.2
* WP restoration: rewritten data replacement engine for performance
* [MEDIUM] Restoration: PHP error when the server reports the site's root as the filesystem root (chroot jail)
* [LOW] Possible PHP error trying to parse invalid URLs
* [LOW] Deleting the items of the last page in Manage Backups page results in an empty display you can't easily get out of

* eeba Backup 9.0.1
* "Do not check minimum supported PHP version" is now forcibly enabled at all times
* Automatically exclude the .cagefs directory present in some cPanel installations
* [MEDIUM] Possible restoration issues if the upgrade code does not execute when installing the update
* [LOW] Restoration: PHP Deprecated warnings when checking for legacy magic quotes features on PHP 7

* eeba Backup 9.0.0
* PHP 8.4: Implicit nullable types are not allowed
* Maximum batch row size for database backup is now 10000 by default, with a maximum of 1000000
* Improved routing in WordPress
* [HIGH] WordPress restoration: `meta_key` column data had its values data-replaced for multisite installations
* [HIGH] Box: cannot refresh the authentication token
* [LOW] The list of tables was no longer output
* [LOW] WebDAV: deleting backups may file on some servers

* eeba Backup 8.3.0
* Roles and Capabilities support
* Make accurate PHP CLI path detection optional
* [MEDIUM] WordPress always reports updates as incompatible
* [HIGH] Some OneDrive multipart uploads fail

* eeba Backup 8.2.7
* Could not work with MySQL 5.x and MariaDB 10.x

* eeba Backup 8.2.5
* More accurate information about PHP CLI in the Schedule Automatic Backups page
* Improved database dump engine
* Option to disable PHP version checks for updates
* WordPress' Site Health feature now displays if the backup is out of date, or has failed
* Adjust the size and text on the warning about ad blockers
* [HIGH] Fatal error uninstalling the plugin
* [HIGH] Erroneous report of a fatal error activating the plugin
* [MEDIUM] Erroneous WordPress log entry "Cron reschedule event error for hook: abwp_cron_scheduling, Error code: invalid_schedule" in the Pro version
* [LOW] The profiles table is not uninstalled when the plugin is deleted
* [LOW] Error thrown when faulty third party plugins return invalid data to the site_status_tests hook before we handle it.
* [LOW] Fatal error when profile configuration data is missing (something's broken in your database)

* eeba Backup 8.2.4
* Remove MariaDB MyISAM option PAGE_CHECKSUM from the database dump
* Improve database dump with table names similar to default values
* Change the wording of the message when navigating to an off-site directory in the directory browser
* PHP 8.4 compatibility: MD5 and SHA-1 functions are deprecated
* [MEDIUM] Tables or databases named `0` can cause the database dump to stop prematurely, or not execute at all

* eeba Backup 8.2.3
* Option to avoid using `flush()` on broken servers
* [HIGH] OAuth2 Helpers didn't work properly due to a typo in the released version

* eeba Backup 8.2.2
* Remove the deprecated, ineffective CURLOPT_BINARYTRANSFER flag
* Alternate Configuration page saving method which doesn't hit maximum POST parameter count limits
* ShowOn in the System Configuration page
* Self-hosted OAuth2 helpers
* [LOW] Deprecation notice in Configuration Wizard

* eeba Backup 8.2.1
* Upload to OneDrive (app-specific folder)
* [LOW] PHP error when two processes try to store update information concurrently

* eeba Backup 8.2.0
* Expert options for the Upload to Amazon S3 configuration
* Separate remote and local quota settings
* [MEDIUM] Clicking on Backup Now would start the backup automatically
* [MEDIUM] CLI backups would not send emails, reporting the mysqli connection is already closed

* eeba Backup 8.1.2
* Automatically downgrade utf8mb4_900_* collations to utf8mb4_unicode_520_ci on MariaDB
* Remove the message about the release being 120 days old

* eeba Backup 8.1.1
* Removed support for Akeeba Backup JSON API v1 (APIv1)
* Re-enabled integrated updates with WordPress
* [HIGH] Raw views include WordPress HTML
* [MEDIUM] SQL error after finishing migrating archives
* [LOW] Double URL in the JSON API section of the scheduling info page

* eeba Backup 8.1.0
* [HIGH] PHP error in Manage Backups when you have pending or failed backups
* [HIGH] CLI scripts did not work through the 8.1.0 betas

* eeba Backup 8.1.0.b3
* [HIGH] Wrong update URL

* eeba Backup 8.1.0.b2
* [HIGH] The migration is never over if you have backup records claiming their archive files exist but, in fact, they do not

* eeba Backup 8.1.0.b1
* Moved the default backup output folder to wp-content/backups
* Moved the settings encryption key to wp-content/akeebabackupwp_secretkey.php
* Automatic migration of backup archives and backup profiles outside the plugin's root folder
* Path shown for backups is now relative to WordPress' root folder (as reported by its `ABSPATH` constant)
* Removed admin dashboard widgets
* [LOW] Downgrading from Pro to Core would make it so that you always saw an update available
* [LOW] Management column show the wrong file extension for the last file you need to download

* eeba Backup 8.0.0.2
* [HIGH] Change in WordPress itself causes a PHP fatal error at the end of the update
* [HIGH] Fatal error sending emails at the end of the backup
* [LOW] Cosmetic issue: application name appeared as Akeeba Solo instead of Akeeba Backup for WordPress in some screens
* [LOW] Translations not loaded during frontend and remote JSON API backups

* eeba Backup 8.0.0.1
* [HIGH] Profile encryption key migration does not work when using WordPress' plugins update
* [LOW] PHP deprecated warnings

* eeba Backup 8.0.0
* Minimum PHP version is now 7.4.0
* Using Composer to load all internal dependencies (AWF, backup engine, S3 library)
* Workaround for Wasabi S3v4 signatures
* Support for uploading to Shared With Me folders in Google Drive
* Improved error reporting, removing the unhelpful "(HTML containing script tags)" message
* Improved mixed– and upper–case database prefix support at backup time
* [MEDIUM] Resetting corrupt backups can cause a crash of the Control Panel page
* [MEDIUM] Upload to S3 would always use v2 signatures with a custom endpoint.
* [MEDIUM] Some transients need data replacements to take place in WP 6.3
* [HIGH] Not choosing a forced backup timezone in System Configuration results in the WP-CRON Scheduling page throwing an error


== Upgrade Notice ==

Please consult our site