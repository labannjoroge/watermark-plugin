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
use WatermarkManager\PluginConstants;

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
class Setup
{
    /**
     * Plugin activation handler
     *
     * Performs initial setup and updates when the plugin is activated.
     *
     * @since  1.0.0
     * @access public
     * @return void
     */
    public static function activate(): void
    {
        // Initialize WP_Filesystem
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }

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
        update_option('WM_version', PluginConstants::VERSION);

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
    public static function deactivate(): void
    {
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
    public static function uninstall(): void
    {
        // Remove all options
        delete_option('WM_version');
        delete_option('WM_image_watermark_options');

        // Remove database tables
        Database::drop_table();

        // Remove all plugin directories
        self::remove_plugin_directories();
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
    private static function install(): void
    {
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
    private static function upgrade(): void
    {
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
    private static function setup_directories(): void
    {
        global $wp_filesystem;

        $upload_dir = wp_upload_dir();
        $directories = [
            $upload_dir['basedir'] . '/WM-watermarks',
            $upload_dir['basedir'] . '/WM-temp',
            $upload_dir['basedir'] . '/WM-fonts',
        ];

        foreach ($directories as $directory) {
            if (!$wp_filesystem->exists($directory)) {
                wp_mkdir_p($directory);
                $wp_filesystem->put_contents($directory . '/.htaccess', 'deny from all', FS_CHMOD_FILE);
                $wp_filesystem->put_contents($directory . '/index.php', '<?php // Silence is golden', FS_CHMOD_FILE);
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
    private static function remove_plugin_directories(): void
    {
        global $wp_filesystem;

        $upload_dir = wp_upload_dir();
        $directories = [
            $upload_dir['basedir'] . '/WM-watermarks',
            $upload_dir['basedir'] . '/WM-temp',
            $upload_dir['basedir'] . '/WM-fonts',
        ];

        foreach ($directories as $directory) {
            if ($wp_filesystem->exists($directory) && $wp_filesystem->is_dir($directory)) {
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
    private static function set_default_options(): void
    {
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
    private static function cleanup_temp_files(): void
    {
        global $wp_filesystem;

        $temp_dir = wp_upload_dir()['basedir'] . '/WM-temp';
        if ($wp_filesystem->exists($temp_dir) && $wp_filesystem->is_dir($temp_dir)) {
            self::recursive_remove_directory($temp_dir);
        }
    }

    /**
     * Recursively remove a directory
     *
     * Helper function to remove a directory and all its contents using WP_Filesystem.
     *
     * @since  1.0.0
     * @access private
     * @param  string $directory Directory path to remove
     * @return void
     */
    private static function recursive_remove_directory(string $directory): void
    {
        global $wp_filesystem;

        if ($wp_filesystem->exists($directory) && $wp_filesystem->is_dir($directory)) {
            $files = $wp_filesystem->dirlist($directory);

            foreach ($files as $file) {
                $path = $directory . '/' . $file['name'];

                if ($file['type'] === 'd') {
                    // If it's a directory, recursively remove it
                    self::recursive_remove_directory($path);
                } else {
                    // If it's a file, delete it
                    wp_delete_file($path);
                }
            }

            // Remove the empty directory
            $wp_filesystem->rmdir($directory);
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
    private static function init_settings(): void
    {
        // Register settings
        register_setting(
            'WM_image_options',
            'WM_image_watermark_options',
            [self::class, 'sanitize_image_watermark_options']
        );

        // Set default options if they don't exist
        self::set_default_options();
    }

    /**
     * Sanitize image watermark options
     *
     * @since  1.0.0
     * @access private
     * @param  array $input The input options to sanitize
     * @return array Sanitized options
     */
    private static function sanitize_image_watermark_options(array $input): array
    {
        $sanitized_input = [];

        // Sanitize boolean
        $sanitized_input['enabled'] = isset($input['enabled']) ? (bool) $input['enabled'] : false;

        // Sanitize position
        $allowed_positions = ['top-left', 'top-right', 'bottom-left', 'bottom-right', 'center'];
        $sanitized_input['position'] = in_array($input['position'], $allowed_positions) ? $input['position'] : 'bottom-right';

        // Sanitize opacity (ensure it's between 0 and 100)
        $sanitized_input['opacity'] = min(max((int) $input['opacity'], 0), 100);

        // Sanitize quality (ensure it's between 0 and 100)
        $sanitized_input['quality'] = min(max((int) $input['quality'], 0), 100);

        // Sanitize text
        $sanitized_input['text'] = sanitize_text_field($input['text']);

        // Sanitize font
        $sanitized_input['font'] = sanitize_text_field($input['font']);

        // Sanitize font size
        $sanitized_input['font_size'] = min(max((int) $input['font_size'], 8), 72);

        // Sanitize font color
        $sanitized_input['font_color'] = sanitize_hex_color($input['font_color']);

        // Sanitize padding
        $sanitized_input['padding'] = min(max((int) $input['padding'], 0), 100);

        // Sanitize background
        $sanitized_input['background'] = sanitize_text_field($input['background']);

        return $sanitized_input;
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
    private static function upgrade_to_110(): void
    {
        // Add new options
        $image_options = get_option('WM_image_watermark_options', []);
        $image_options['padding'] = $image_options['padding'] ?? 20;
        $image_options['background'] = $image_options['background'] ?? 'transparent';
        update_option('WM_image_watermark_options', $image_options);
    }

    /**
     * Set up the React build directory
     *
     * Creates the React build directory if it doesn't exist.
     *
     * @since  1.1.0
     * @access private
     * @return void
     */
    private static function setup_react_build_directory(): void
    {
        global $wp_filesystem;

        $build_dir = PluginConstants::getPluginDir() . 'admin/build';
        if (!$wp_filesystem->exists($build_dir)) {
            wp_mkdir_p($build_dir);
        }
    }
}

register_activation_hook(__FILE__, function () {
    Database::create_table();
    SettingsHandler::migrate_from_options();
});