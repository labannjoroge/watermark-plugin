<?php
/**
 *  Watermark Manager
 *
 * This is the main plugin file that bootstraps the entire plugin functionality.
 * It handles initialization, autoloading, and hooks registration.
 *
 * @package     WatermarkManager
 * @author      Labantheegreat
 * @copyright   2024 Labantheegreat 
 * @license     GPL-2.0+
 *
 * @wordpress-plugin
 * Plugin Name: Watermark Manager
 * Plugin URI:  https://example.com/watermark-manager
 * Description: A comprehensive WordPress plugin for image and content watermarking with advanced features.
 * Version:     1.0.0
 * Author:      Labantheegreat
 * Author URI:  https://example.com
 * Text Domain: watermark-manager
 * Domain Path: /languages
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

namespace WatermarkManager;

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit('Direct file access is not allowed.');
}


// Define plugin constants
define('WM_VERSION', '1.0.0');
define('WM_PLUGIN_FILE', __FILE__);
define('WM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WM_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('WM_MINIMUM_WP_VERSION', '5.8');
define('WM_MINIMUM_PHP_VERSION', '7.4');


// Load autoloader first
require_once WM_PLUGIN_DIR . 'includes/class-autoloader.php';
/**
 * Class Plugin
 *
 * Main plugin bootstrap class.
 *
 * @since 1.0.0
 */
final class Plugin
{
    /**
     * Plugin instance.
     *
     * @since 1.0.0
     * @var Plugin|null
     */
    private static ?Plugin $instance = null;

    /**
     * Plugin constructor.
     *
     * @since 1.0.0
     */
    private function __construct()
    {
        // Register autoloader before anything else
        if (class_exists('WatermarkManager\Includes\Autoloader')) {
            spl_autoload_register(['WatermarkManager\Includes\Autoloader', 'autoload']);
        } else {
            add_action('admin_notices', function () {
                $message = __('Watermark Manager: Autoloader class could not be loaded.', 'watermark-manager');
                echo '<div class="error"><p>' . esc_html($message) . '</p></div>';
            });
            return;
        }

        // Now that autoloader is registered, we can initialize
        $this->init();
    }

    /**
     * Get plugin instance.
     *
     * @since 1.0.0
     * @return Plugin
     */
    public static function get_instance(): Plugin
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize plugin functionality
     *
     * @since 1.0.0
     * @return void
     */
    private function init(): void
    {
        // Check requirements first
        if (!$this->check_requirements()) {
            return;
        }

        // Load plugin textdomain
        add_action('plugins_loaded', [$this, 'load_textdomain']);

        // Register activation/deactivation hooks after autoloader is set up
        register_activation_hook(WM_PLUGIN_FILE, [Includes\Setup::class, 'activate']);
        register_deactivation_hook(WM_PLUGIN_FILE, [Includes\Setup::class, 'deactivate']);

        // Initialize core functionality
        $this->init_plugin();

        // Initialize REST API
        add_action('rest_api_init', [$this, 'init_rest_api']);
    }

    public function init_rest_api(): void
    {
        $rest_controller = new Admin\RestController();
        $rest_controller->register_routes();
    }

    /**
     * Check if plugin requirements are met
     *
     * @since 1.0.0
     * @return bool
     */
    private function check_requirements(): bool
    {
        if (version_compare(PHP_VERSION, WM_MINIMUM_PHP_VERSION, '<')) {
            add_action('admin_notices', [$this, 'php_version_notice']);
            return false;
        }

        if (version_compare($GLOBALS['wp_version'], WM_MINIMUM_WP_VERSION, '<')) {
            add_action('admin_notices', [$this, 'wp_version_notice']);
            return false;
        }

        return true;
    }

    /**
     * Initialize plugin core functionality
     *
     * @since 1.0.0
     * @return void
     */
    private function init_plugin(): void
    {
        if (class_exists('WatermarkManager\Includes\Core')) {
            $core = new Includes\Core();
            $core->run();
        }
    }

    /**
     * Load plugin textdomain
     *
     * @since 1.0.0
     * @return void
     */
    public function load_textdomain(): void
    {
        load_plugin_textdomain(
            'watermark-manager',
            false,
            dirname(WM_PLUGIN_BASENAME) . '/languages/'
        );
    }

    /**
     * Display PHP version notice
     *
     * @since 1.0.0
     * @return void
     */
    public function php_version_notice(): void
    {
        $message = sprintf(
            /* translators: %s: PHP version */
            esc_html__('Watermark Manager requires PHP version %s or higher.', 'watermark-manager'),
            WM_MINIMUM_PHP_VERSION
        );
        $html_message = sprintf('<div class="error">%s</div>', wpautop($message));
        echo wp_kses_post($html_message);
    }

    /**
     * Display WordPress version notice
     *
     * @since 1.0.0
     * @return void
     */
    public function wp_version_notice(): void
    {
        $message = sprintf(
            /* translators: %s: WordPress version */
            esc_html__('Watermark Manager requires WordPress version %s or higher.', 'watermark-manager'),
            WM_MINIMUM_WP_VERSION
        );
        $html_message = sprintf('<div class="error">%s</div>', wpautop($message));
        echo wp_kses_post($html_message);
    }
}

// Initialize the plugin
Plugin::get_instance();
