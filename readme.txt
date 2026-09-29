=== Akeeba Backup CORE for WordPress ===
Contributors: nikosdion
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=10903325
Tags: backup, restore, migrate, move
Requires at least: 6.3.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 9.1.2
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