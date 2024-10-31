<?php
/*
Plugin Name: PAS Debug Log Manager
Text Domain: pas-debug-log-manager
Description: Simple plugin to view and manage debug log
Author: Azeez
Version: 1.0.03
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

define('PAS_DEBUG_LOG_VERSION', '1.0.0');

function pas_dlm_add_debug_log_menu_item() {
    add_menu_page(
        'PAS Debug Log Manager',
        'PAS Debug Log',
        'manage_options',
        'pas-debug-log-manager',
        'pas_dlm_display_debug_log_reverse',
        'dashicons-admin-tools',
        100
    );
}

function pas_dlm_display_debug_log_reverse() {
    global $wp_filesystem;

    // Initialize WP_Filesystem API
    if ( ! function_exists('WP_Filesystem') ) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }

    WP_Filesystem();

    $log_file = WP_CONTENT_DIR . '/debug.log'; // Path to the debug.log file

    echo '<div class="wrap">';
    echo '<h1>PAS Debug Log Manager</h1>';

    // Display the size of the debug log file
    if ($wp_filesystem->exists($log_file)) {
        $file_size = $wp_filesystem->size($log_file);
        echo '<p>Log file size: ' . esc_html(size_format($file_size)) . '</p>'; // Display file size
    }

    // Auto-refresh and line count selection
    echo '<label><input type="checkbox" id="auto-refresh" name="auto-refresh"> ' . esc_html__('Auto-refresh', 'pas-debug-log-manager') . '</label>';
    echo ' every <select id="refresh-interval" name="refresh-interval" disabled>
        <option value="5000">5 seconds</option>
        <option value="10000">10 seconds</option>
        <option value="30000">30 seconds</option>
        <option value="60000">1 minute</option>
    </select>';
    echo '<span id="refresh-status"></span>';

    echo ' | Show <select id="line-count" name="line-count">
        <option value="100">100 lines</option>
        <option value="500">500 lines</option>
        <option value="1000">1000 lines</option>
        <option value="2000">2000 lines</option>
        <option value="3000">3000 lines</option>
        <option value="4000">4000 lines</option>
        <option value="5000">5000 lines</option>
        <option value="6000">6000 lines</option>
        <option value="7000">7000 lines</option>
        <option value="8000">8000 lines</option>
        <option value="9000">9000 lines</option>
        <option value="10000">10000 lines</option>
    </select>';

    echo '<form method="post" action="" style="margin-top: 10px; margin-bottom: 10px;">';
    wp_nonce_field('pas_dlm_clear_debug_log_nonce');
    echo '<input type="hidden" name="clear_log" value="1">';
    echo '<input type="submit" value="Clear Logs" class="button button-secondary" />';
    echo '</form>';

    // Handle log clearing
    if (isset($_POST['clear_log']) && check_admin_referer('pas_dlm_clear_debug_log_nonce') && current_user_can('manage_options')) {
        $wp_filesystem->put_contents($log_file, '');
        echo '<p style="color: green;">' . esc_html__('Logs have been cleared.', 'pas-debug-log-manager') . '</p>';
    }

    // Handle debug log toggle
    if (isset($_POST['toggle_debug_log']) && check_admin_referer('pas_dlm_toggle_debug_log_nonce') && current_user_can('manage_options')) {
        pas_dlm_toggle_debug_log();
    }

    $debug_enabled = pas_dlm_is_debug_log_enabled();

    echo '<form method="post" action="">';
    wp_nonce_field('pas_dlm_toggle_debug_log_nonce');
    echo '<p>';
    echo '<label><input type="checkbox" name="debug_log" value="1"' . checked($debug_enabled, true, false) . '> Enable Debug Logging</label>';
    echo '</p>';
    echo '<p><input type="submit" name="toggle_debug_log" value="Save" class="button button-primary" /></p>';
    echo '</form>';

    echo '<div id="debug-log-content">';
    echo pas_dlm_get_debug_log_content();
    echo '</div>';
    echo '</div>';
}

function pas_dlm_get_debug_log_content($line_count = 100) {

    if(wp_doing_ajax()){
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        WP_Filesystem();
    }

    global $wp_filesystem;

    $log_file = WP_CONTENT_DIR . '/debug.log';

    if ($wp_filesystem->exists($log_file)) {
        // Read the file from the end
        $file = new SplFileObject($log_file, 'r');
        $file->seek(PHP_INT_MAX);
        $last_line = $file->key();

        if ($last_line == 0) {
            return '<p>No logs found.</p>';
        }

        // Ensure $line_count doesn't exceed the total number of lines
        $start_line = max(0, $last_line - $line_count);
        $lines = new LimitIterator($file, $start_line, $last_line);
        $log_lines = iterator_to_array($lines);
        $log_lines = array_reverse($log_lines); // Reverse to get chronological order

        if ($log_lines) {
            $parsed_logs = array();
            $pattern = '/\[(.*?)\] (.+?):\s+(.+)/';

            // Assign an index to each line after reversing
            $index = 1; // Start with index 1
            foreach ($log_lines as $line) {
                $line = trim($line);
                if (preg_match($pattern, $line, $matches)) {
                    $timestamp = strtotime($matches[1]);
                    $datetime = date('Y-m-d H:i:s', $timestamp);
                    $error_type = $matches[2];
                    $error_message = $matches[3];
                    $parsed_logs[] = array(
                        'index' => $index,  // Assign index here
                        'datetime' => $datetime,
                        'error_type' => $error_type,
                        'error_message' => $error_message
                    );
                    $index++; // Increase the index for the next log
                } else {
                    // Handle lines that don't match the pattern
                    if (!empty($parsed_logs)) {
                        $parsed_logs[count($parsed_logs) - 1]['error_message'] .= "\n" . $line;
                    }
                }
            }

            $output = '<table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th style="border: 1px solid #ddd; padding: 8px; background-color: #f2f2f2;width: 150px;">Datetime</th>
                                <th style="border: 1px solid #ddd; padding: 8px; background-color: #f2f2f2;">Error Type</th>
                                <th style="border: 1px solid #ddd; padding: 8px; background-color: #f2f2f2;">Error Message</th>
                            </tr>
                        </thead>
                        <tbody>';
            foreach ($parsed_logs as $log_entry) {
                $output .= '<tr>
                            <td style="border: 1px solid #ddd; padding: 8px; vertical-align: top;">' . esc_html($log_entry['datetime']) . '</td>
                            <td style="border: 1px solid #ddd; padding: 8px; vertical-align: top;">' . esc_html($log_entry['error_type']) . '</td>
                            <td style="border: 1px solid #ddd; padding: 8px; vertical-align: top;"><pre style="margin: 0; white-space: pre-wrap;">' . esc_html($log_entry['error_message']) . '</pre></td>
                          </tr>';
            }
            $output .= '</tbody></table>';
            pas_dlm_check_and_backup_log_file();
            return $output;
        } else {
            return '<p>No logs found.</p>';
        }
    } else {
        return '<p>Debug log file does not exist.</p>';
    }
}

// Function to check if debug logging is enabled
function pas_dlm_is_debug_log_enabled() {
    global $wp_filesystem;

    $wp_config = $wp_filesystem->get_contents(ABSPATH . 'wp-config.php');

    $debug_pattern = "/define\(\s*'WP_DEBUG'\s*,\s*true\s*\)\s*;/i";
    $debug_log_pattern = "/define\(\s*'WP_DEBUG_LOG'\s*,\s*true\s*\)\s*;/i";

    return preg_match($debug_pattern, $wp_config) && preg_match($debug_log_pattern, $wp_config);
}

// Function to toggle debug log state
function pas_dlm_toggle_debug_log() {
    global $wp_filesystem;

    $wp_config_file = ABSPATH . 'wp-config.php';

    if (!$wp_filesystem->exists($wp_config_file)) {
        echo '<p style="color: red;">Error: wp-config.php file not found.</p>';
        return;
    }

    if (!$wp_filesystem->is_writable($wp_config_file)) {
        echo '<p style="color: red;">Error: wp-config.php is not writable. Please check file permissions.</p>';
        return;
    }

    $wp_config = $wp_filesystem->get_contents($wp_config_file);

    $debug_pattern = "/define\(\s*'WP_DEBUG'\s*,\s*(true|false)\s*\)\s*;/i";
    $debug_log_pattern = "/define\(\s*'WP_DEBUG_LOG'\s*,\s*(true|false)\s*\)\s*;/i";

    $debug_enabled = pas_dlm_is_debug_log_enabled();

    if ($debug_enabled) {
        $wp_config = preg_replace($debug_pattern, "define('WP_DEBUG', false);", $wp_config);
        $wp_config = preg_replace($debug_log_pattern, "define('WP_DEBUG_LOG', false);", $wp_config);
        $wp_filesystem->put_contents($wp_config_file, $wp_config);
        echo '<p style="color: green;">Debug logging has been disabled.</p>';
    } else {
        if (preg_match($debug_pattern, $wp_config)) {
            $wp_config = preg_replace($debug_pattern, "define('WP_DEBUG', true);", $wp_config);
        } else {
            $wp_config = preg_replace("/(<\?php)/", "<?php\n\n define('WP_DEBUG', true);", $wp_config, 1);
        }

        if (preg_match($debug_log_pattern, $wp_config)) {
            $wp_config = preg_replace($debug_log_pattern, "define('WP_DEBUG_LOG', true);", $wp_config);
        } else {
            $wp_config = preg_replace("/(<\?php)/", "<?php\n\n define('WP_DEBUG_LOG', true);", $wp_config, 1);
        }

        $wp_filesystem->put_contents($wp_config_file, $wp_config);
        echo '<p style="color: green;">Debug logging has been enabled.</p>';
    }
}

add_action('admin_menu', 'pas_dlm_add_debug_log_menu_item');

// Add AJAX action for refreshing the log
add_action('wp_ajax_pas_dlm_refresh_debug_log', 'pas_dlm_ajax_refresh_debug_log');

function pas_dlm_ajax_refresh_debug_log() {
    $line_count = isset($_REQUEST['line_count']) && !empty($_REQUEST['line_count']) ? intval($_REQUEST['line_count']) : 100;

    if (!in_array($line_count, array(100, 500, 1000, 2000, 3000, 4000, 5000, 6000, 7000, 8000, 9000, 10000), true)) {
        $line_count = 100; // Set default if validation fails
    }

    echo pas_dlm_get_debug_log_content($line_count);

    wp_die();
}

function pas_dlm_check_and_backup_log_file() {
    global $wp_filesystem;

    $log_file = WP_CONTENT_DIR . '/debug.log';
    $max_size = apply_filters('pas_dlm/debug-log/max-size', 20971520); // 20MB in bytes

    if ($wp_filesystem->exists($log_file)) {
        $file_size = $wp_filesystem->size($log_file);

        if ($file_size > $max_size) {
            $backup_dir = WP_CONTENT_DIR . '/debug-log-backups';

            if (!$wp_filesystem->exists($backup_dir)) {
                $wp_filesystem->mkdir($backup_dir, 0755);
            }

            $backup_file = $backup_dir . '/debug-backup-' . date('Y-m-d_H-i-s') . '.log';

            if ($wp_filesystem->copy($log_file, $backup_file)) {
                $wp_filesystem->put_contents($log_file, '');

                echo '<p style="color: green;">Debug log exceeded 20MB. Created a backup and cleared the log file.</p>';
            } else {
                echo '<p style="color: red;">Failed to create a backup of the debug log file.</p>';
            }
        }
    } else {
        echo '<p style="color: red;">Debug log file does not exist.</p>';
    }
}

function pas_dlm_enqueue_admin_scripts($hook) {
    // Only enqueue on the PAS Debug Log Manager page
    if ($hook !== 'toplevel_page_pas-debug-log-manager') {
        return;
    }

    // Register and enqueue JavaScript
    wp_register_script('pas_dlm_script', plugins_url('/assets/js/pas-dlm-script.js', __FILE__), array('jquery'), PAS_DEBUG_LOG_VERSION, true);
    wp_enqueue_script('pas_dlm_script');

    // Register and enqueue CSS
    wp_register_style('pas_dlm_style', plugins_url('/assets/css/pas-dlm-style.css', __FILE__), array(), PAS_DEBUG_LOG_VERSION);
    wp_enqueue_style('pas_dlm_style');
}

add_action('admin_enqueue_scripts', 'pas_dlm_enqueue_admin_scripts');
