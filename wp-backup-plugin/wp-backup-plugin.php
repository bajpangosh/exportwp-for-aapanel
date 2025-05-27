<?php
/**
 * Plugin Name:       WP Backup Plugin
 * Plugin URI:        https://example.com/plugins/wp-backup-plugin/
 * Description:       A plugin to back up WordPress files and database to a zip file in the root directory.
 * Version:           1.0.0
 * Author:            Jules AI Agent
 * Author URI:        https://example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-backup-plugin
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Plugin code will go here

/**
 * Backs up the WordPress files to a zip archive.
 *
 * @param string $source_dir The absolute path to the WordPress root directory.
 * @param ZipArchive $zip The ZipArchive object.
 * @param array $exclusions An array of file/directory names or relative paths to exclude.
 * @return bool True on success, false on failure.
 */
function wpbp_backup_files( $source_dir, &$zip, $exclusions = array() ) {
    // Default exclusions
    $default_exclusions = array(
        'wpbackup.zip', // Name of the final zip file
        'wp-config.php',
        'wp-content/cache',
        'wp-content/backups', // Common backup plugin directory
        '.git',
        '.svn',
        '.DS_Store',
    );
    $exclusions = array_merge($default_exclusions, $exclusions);

    $source_dir = rtrim($source_dir, '/\\'); // Normalize path

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($files as $file) {
        $file_path = $file->getRealPath();
        $relative_path = substr($file_path, strlen($source_dir) + 1);

        // Check exclusions
        $skip = false;
        foreach ($exclusions as $exclusion) {
            if (strpos($relative_path, $exclusion) === 0) {
                $skip = true;
                break;
            }
        }
        if ($skip) {
            continue;
        }

        if ($file->isDir()) {
            $zip->addEmptyDir($relative_path);
        } else {
            $zip->addFile($file_path, $relative_path);
        }
    }

    return true;
}

/**
 * Backs up the WordPress database to a SQL file in the zip archive.
 *
 * @param ZipArchive $zip The ZipArchive object.
 * @return bool True on success, false on failure.
 */
function wpbp_backup_database( &$zip ) {
    global $wpdb;
    if ( ! isset( $wpdb ) ) {
        // Try to load WordPress environment if $wpdb is not set.
        // This might be the case if the script is called outside a proper WordPress hook.
        // Adjust path as necessary if your plugin structure is different.
        if ( file_exists( ABSPATH . 'wp-load.php' ) ) {
            require_once( ABSPATH . 'wp-load.php' );
        } else {
            // Fallback if wp-load.php is not found directly in ABSPATH
            // This is a less reliable way to find wp-load.php
            $path = dirname(dirname(dirname(dirname(__FILE__)))); // Assumes plugin is in wp-content/plugins/your-plugin-folder
             if (file_exists($path . '/wp-load.php')) {
                require_once($path . '/wp-load.php');
            } else {
                error_log('WP Backup Plugin: Could not load $wpdb. WordPress environment not found.');
                return false; // Cannot proceed without $wpdb
            }
        }
    }


    $sql_dump = "";

    $tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);

    if (!$tables) {
        error_log('WP Backup Plugin: No tables found in the database.');
        return false;
    }

    foreach ($tables as $table_row) {
        $table_name = $table_row[0];

        // Add DROP TABLE statement
        $sql_dump .= "DROP TABLE IF EXISTS `{$table_name}`;\n";

        // Add CREATE TABLE statement
        $create_table_query = $wpdb->get_row("SHOW CREATE TABLE `{$table_name}`", ARRAY_A);
        if ($create_table_query && isset($create_table_query['Create Table'])) {
            $sql_dump .= $create_table_query['Create Table'] . ";\n\n";
        } else {
            error_log("WP Backup Plugin: Could not get CREATE TABLE statement for table {$table_name}.");
            continue; // Skip this table if create statement fails
        }

        // Add INSERT INTO statements
        $rows = $wpdb->get_results("SELECT * FROM `{$table_name}`", ARRAY_A);
        if ($rows) {
            foreach ($rows as $row) {
                $sql_dump .= "INSERT INTO `{$table_name}` VALUES(";
                $values = array();
                foreach ($row as $value) {
                    if ($value === null) {
                        $values[] = "NULL";
                    } else {
                        $values[] = "'" . $wpdb->_real_escape($value) . "'";
                    }
                }
                $sql_dump .= implode(", ", $values) . ");\n";
            }
            $sql_dump .= "\n\n"; // Add some space after each table's data
        }
    }

    if (empty($sql_dump)) {
        error_log('WP Backup Plugin: SQL dump is empty. No data backed up.');
        return false;
    }

    if ($zip->addFromString('database_backup.sql', $sql_dump)) {
        return true;
    } else {
        error_log('WP Backup Plugin: Failed to add database_backup.sql to zip.');
        return false;
    }
}

/**
 * Adds the plugin admin menu page.
 */
function wpbp_admin_menu() {
    add_menu_page(
        'WP Backup Plugin',          // Page title
        'WP Backup',                 // Menu title
        'manage_options',            // Capability
        'wp-backup-plugin',          // Menu slug
        'wpbp_render_admin_page',    // Callback function
        'dashicons-database-export', // Icon URL
        null                         // Position (optional, default)
    );
}
add_action('admin_menu', 'wpbp_admin_menu');

/**
 * Renders the admin page HTML.
 */
function wpbp_render_admin_page() {
    ?>
    <div class="wrap">
        <h1>WP Backup Plugin</h1>
        <p>Click the button below to create a full backup of your WordPress files and database. The backup will be saved as <code>wpbackup.zip</code> in your WordPress root directory.</p>
        
        <?php wp_nonce_field('wpbp_backup_action', 'wpbp_backup_nonce'); ?>
        
        <button type="button" id="wpbp-start-backup-button" class="button button-primary">Start Backup Now</button>
        
        <div id="wpbp-progress-area" style="margin-top: 20px; padding: 10px; border: 1px solid #ccc; background-color: #f9f9f9; display: none;">
            <!-- Progress messages will appear here -->
        </div>
        
        <div id="wpbp-download-link-area" style="margin-top: 20px;">
            <!-- Download link will appear here -->
        </div>
    </div>
    <?php
}

/**
 * Handles the AJAX request to run the backup process.
 */
function wpbp_handle_run_backup() {
    // Security Checks
    check_ajax_referer('wpbp_backup_action', 'nonce'); // 'nonce' is the key sent in JS data
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized access.', 403);
    }

    // Initialization
    if (!class_exists('ZipArchive')) {
        wp_send_json_error('ZipArchive PHP extension is required but not enabled on the server.');
    }

    $root_path = ABSPATH;
    $backup_file_name = 'wpbackup.zip'; // This name is also in wpbp_backup_files exclusions
    $backup_full_path = $root_path . $backup_file_name;

    // Prepare for Backup
    if (file_exists($backup_full_path)) {
        if (!unlink($backup_full_path)) {
            // If unlink fails, it might cause issues with ZipArchive::CREATE or ::OVERWRITE.
            // However, ZipArchive::OVERWRITE should typically handle an existing file.
            // Logging an error here might be useful for debugging permission issues.
            error_log('WP Backup Plugin: Could not delete existing backup file: ' . $backup_full_path);
        }
    }

    // ZipArchive Operations
    $zip = new ZipArchive();
    if ($zip->open($backup_full_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        wp_send_json_error('Failed to create backup zip file. Check file permissions in the WordPress root directory.');
    }

    // Perform Backups
    // wpbp_backup_files function has default exclusions, including $backup_file_name.
    // No need to pass third argument if defaults are sufficient.
    if (!wpbp_backup_files($root_path, $zip)) {
        $zip->close();
        if (file_exists($backup_full_path)) {
            unlink($backup_full_path); // Attempt to clean up
        }
        wp_send_json_error('File backup process failed.');
    }

    if (!wpbp_backup_database($zip)) {
        $zip->close();
        if (file_exists($backup_full_path)) {
            unlink($backup_full_path); // Attempt to clean up
        }
        wp_send_json_error('Database backup process failed.');
    }

    // Finalize
    if (!$zip->close()) {
        if (file_exists($backup_full_path)) {
            unlink($backup_full_path); // Attempt to clean up
        }
        wp_send_json_error('Failed to finalize the backup zip file.');
    }

    // Success Response
    $download_url = site_url('/' . $backup_file_name);
    wp_send_json_success(array(
        'message' => 'Backup completed successfully!',
        'download_url' => $download_url
    ));

    // wp_send_json_success and wp_send_json_error call wp_die() internally.
}
add_action('wp_ajax_wpbp_run_backup', 'wpbp_handle_run_backup');

/**
 * Enqueues admin scripts and styles.
 *
 * @param string $hook_suffix The current admin page hook.
 */
function wpbp_enqueue_admin_scripts($hook_suffix) {
    // Assumes the menu slug from add_menu_page is 'wp-backup-plugin'
    // The hook_suffix for a top-level page is 'toplevel_page_wp-backup-plugin'
    // For a page under 'Settings' it would be 'settings_page_wp-backup-plugin'
    // For 'Tools': 'tools_page_wp-backup-plugin'
    if ('toplevel_page_wp-backup-plugin' !== $hook_suffix) {
        return;
    }

    wp_enqueue_script(
        'wpbp-admin-script', // Handle
        plugin_dir_url(__FILE__) . 'admin-backup.js', // Source
        array('jquery'), // Dependencies
        '1.0.0', // Version
        true // In footer
    );

    // Although ajaxurl is often globally available, localizing it is a good practice,
    // especially if you need to pass other PHP variables to your script.
    // The JS file uses 'ajaxurl' directly. If it were to use 'wpbp_ajax_object.ajaxurl',
    // then this localization would be strictly necessary for that specific usage pattern.
    wp_localize_script(
        'wpbp-admin-script',
        'wpbp_ajax_object', // Object name in JavaScript
        array('ajaxurl' => admin_url('admin-ajax.php')) // Data to pass
    );
}
add_action('admin_enqueue_scripts', 'wpbp_enqueue_admin_scripts');

?>
