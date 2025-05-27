=== WP Backup Plugin ===
Contributors: jules.ai.agent
Donate link: https://example.com/donate
Tags: backup, zip, database, files, admin, utility
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A simple plugin to back up your WordPress files and database into a single zip file named `wpbackup.zip` in your WordPress root directory.

== Description ==

This plugin provides a simple way to create a full backup of your WordPress installation.
With a single click, it will:
*   Back up all your WordPress files (excluding the backup file itself, wp-config.php, cache, and common version control directories).
*   Back up your complete WordPress database (structure and data).
*   Store everything in a single `wpbackup.zip` file located in your main WordPress root folder.
*   Provide a download link for the backup file.

This is a basic backup solution. For more advanced features like scheduled backups, cloud storage, or migrations, consider a more comprehensive backup plugin.

== Installation ==

1.  Upload the `wp-backup-plugin` folder to the `/wp-content/plugins/` directory on your WordPress installation.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  Go to the "WP Backup" menu item in your WordPress admin dashboard.
4.  Click the "Start Backup Now" button.
5.  Once the backup is complete, a download link for `wpbackup.zip` (located in your WordPress root directory) will appear.

== Frequently Asked Questions ==

= Where is the backup file stored? =

The backup file, `wpbackup.zip`, is stored in the root directory of your WordPress installation.

= Is `wp-config.php` included in the backup? =

No, for security reasons, `wp-config.php` is excluded from the backup. You should secure this file separately.

= Can I schedule backups? =

No, this plugin only supports manual backups. For scheduled backups, please use a different plugin.

= What if the backup fails? =

Ensure your server has enough disk space and the `ZipArchive` PHP extension is enabled. Check PHP error logs for more details. Common issues include file permissions or running out of server resources for very large sites.

== Changelog ==

= 1.0.0 - 2025-05-26 =
*   Initial release.
*   Manual backup of WordPress files and database.
*   Creates `wpbackup.zip` in the WordPress root directory.
*   Provides a download link.

== Upgrade Notice ==

= 1.0.0 =
Initial release of the plugin.
