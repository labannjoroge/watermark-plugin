<?php
/**
 * Plugin setup functionality.
 *
 * @package    WatermarkManager
 * @subpackage Includes
 * @since      1.0.0
 */

namespace WatermarkManager\Includes;
use WatermarkManager\Includes\Database;
use WatermarkManager\Includes\FontSetup;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die('Direct access is not allowed.');
}

/**
 * Setup Class
 *
 * Handles plugin activation, deactivation, and upgrade processes.
 *
 * @since 1.0.0
 */
class Setup {
    /**
     * Plugin activation handler
     *
     * Performs initial setup and updates when the plugin is activated.
     *
     * @since  1.0.0
     * @access public
     * @return void
     */
    public static function activate(): void {
        // Check if this is a fresh installation
        if (!get_option('WM_version')) {
            self::install();
        } else {
            self::upgrade();
        }

        // Create database tables
        Database::create_table();

        // Set up required directories
        self::setup_directories();

        // Initialize default settings
        self::init_settings();

        // Set up fonts
        $font_setup = new FontSetup();
        $font_setup->setup();

        // Schedule cleanup events
        wp_schedule_event(time(), 'daily', 'WM_daily_cleanup');

        // Add React build directory setup
        self::setup_react_build_directory();

        // Update version
        update_option('WM_version', WM_VERSION);

        // Clear rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation handler
     *
     * Cleans up plugin data and scheduled tasks on deactivation.
     *
     * @since  1.0.0
     * @access public
     * @return void
     */
    public static function deactivate(): void {
        // Clear scheduled events
        wp_clear_scheduled_hook('WM_daily_cleanup');

        // Clean up temporary files
        self::cleanup_temp_files();

        // Clear rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin uninstall handler
     *
     * Removes all plugin data when uninstalling.
     *
     * @since  1.0.0
     * @access public
     * @return void
     */
    public static function uninstall(): void {
        // Remove all options
        delete_option('WM_version');
        delete_option('WM_image_watermark_options');
        delete_option('WM_content_watermark_options');

        // Remove database tables
        Database::drop_table();

        // Remove all plugin directories
        self::remove_plugin_directories();
    }

    private static function setup_react_build_directory(): void {
        $build_dir = WM_PLUGIN_DIR . 'admin/build';
        if (!file_exists($build_dir)) {
            wp_mkdir_p($build_dir);
        }
    }

    /**
     * Fresh plugin installation
     *
     * Sets up a new installation of the plugin.
     *
     * @since  1.0.0
     * @access private
     * @return void
     */
    private static function install(): void {
        // Create necessary directories
        self::setup_directories();

        // Set default options
        self::set_default_options();
    }

    /**
     * Plugin upgrade handler
     *
     * Manages version-specific upgrades.
     *
     * @since  1.0.0
     * @access private
     * @return void
     */
    private static function upgrade(): void {
        $current_version = get_option('WM_version');

        // Version-specific upgrade routines
        if (version_compare($current_version, '1.1.0', '<')) {
            self::upgrade_to_110();
        }

        // Future version upgrades can be added here
    }

    /**
     * Set up plugin directories
     *
     * Creates necessary directories for plugin operation.
     *
     * @since  1.0.0
     * @access private
     * @return void
     */
    private static function setup_directories(): void {
        $upload_dir = wp_upload_dir();
        $directories = [
            $upload_dir['basedir'] . '/WM-watermarks',
            $upload_dir['basedir'] . '/WM-temp',
            $upload_dir['basedir'] . '/WM-fonts',
        ];

        foreach ($directories as $directory) {
            if (!file_exists($directory)) {
                wp_mkdir_p($directory);
                file_put_contents($directory . '/.htaccess', 'deny from all');
                file_put_contents($directory . '/index.php', '<?php // Silence is golden');
            }
        }
    }

    /**
     * Remove plugin directories
     *
     * Removes all plugin-created directories during uninstall.
     *
     * @since  1.0.0
     * @access private
     * @return void
     */
    private static function remove_plugin_directories(): void {
        $upload_dir = wp_upload_dir();
        $directories = [
            $upload_dir['basedir'] . '/WM-watermarks',
            $upload_dir['basedir'] . '/WM-temp',
            $upload_dir['basedir'] . '/WM-fonts',
        ];

        foreach ($directories as $directory) {
            if (is_dir($directory)) {
                self::recursive_remove_directory($directory);
            }
        }
    }

    /**
     * Set default plugin options
     *
     * Initializes default settings for the plugin.
     *
     * @since  1.0.0
     * @access private
     * @return void
     */
    private static function set_default_options(): void {
        $default_image_options = [
            'enabled' => true,
            'position' => 'bottom-right',
            'opacity' => 70,
            'quality' => 90,
            'text' => get_bloginfo('name'),
            'font' => 'Roboto',
            'font_size' => 24,
            'font_color' => '#ffffff',
            'padding' => 20,
            'background' => 'transparent',
        ];

        $default_content_options = [
            'enabled' => false,
            'text' => '© ' . get_bloginfo('name'),
            'position' => 'after',
            'css_class' => 'WM-watermark',
            'exclude_post_types' => ['page'],
        ];

        if (!get_option('WM_image_watermark_options')) {
            update_option('WM_image_watermark_options', $default_image_options);
        }

        if (!get_option('WM_content_watermark_options')) {
            update_option('WM_content_watermark_options', $default_content_options);
        }
    }

    /**
     * Clean up temporary files
     *
     * Removes all temporary files created by the plugin.
     *
     * @since  1.0.0
     * @access private
     * @return void
     */
    private static function cleanup_temp_files(): void {
        $temp_dir = wp_upload_dir()['basedir'] . '/WM-temp';
        if (is_dir($temp_dir)) {
            self::recursive_remove_directory($temp_dir);
        }
    }

    /**
     * Recursively remove a directory
     *
     * Helper function to remove a directory and all its contents.
     *
     * @since  1.0.0
     * @access private
     * @param  string $directory Directory path to remove
     * @return void
     */
    private static function recursive_remove_directory(string $directory): void {
        if (is_dir($directory)) {
            $files = scandir($directory);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    $path = $directory . '/' . $file;
                    if (is_dir($path)) {
                        self::recursive_remove_directory($path);
                    } else {
                        unlink($path);
                    }
                }
            }
            rmdir($directory);
        }
    }

    /**
     * Initialize plugin settings
     *
     * Sets up all plugin settings and options.
     *
     * @since  1.0.0
     * @access private
     * @return void
     */
    private static function init_settings(): void {
        // Register settings
        register_setting('WM_image_options', 'WM_image_watermark_options');
        register_setting('WM_content_options', 'WM_content_watermark_options');

        // Set default options if they don't exist
        self::set_default_options();
    }

    /**
     * Upgrade to version 1.1.0
     *
     * Handles upgrade routines for version 1.1.0.
     *
     * @since  1.1.0
     * @access private
     * @return void
     */
    private static function upgrade_to_110(): void {
        // Add new options
        $image_options = get_option('WM_image_watermark_options', []);
        $image_options['padding'] = $image_options['padding'] ?? 20;
        $image_options['background'] = $image_options['background'] ?? 'transparent';
        update_option('WM_image_watermark_options', $image_options);
    }
}
register_activation_hook(__FILE__, function() {
    Database::create_table();
    SettingsHandler::migrate_from_options();
});

