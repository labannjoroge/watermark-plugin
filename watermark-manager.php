<?php
/**
 *  Watermark Manager
 *
 * @wordpress-plugin
 * Plugin Name: Watermark Manager
 * Plugin URI:   https://labanthegreat.com/plugins/watermark-manager
 * Description: A comprehensive WordPress plugin for image with advanced features.
 * Version:     1.0.0
 * Author:      Labantheegreat
 * Author URI:  https://labanthegreat.com
 * Text Domain: watermark-manager
 * Domain Path: /languages
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Tested up to: 6.7
 * 
 */


namespace WatermarkManager;

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit('Direct file access is not allowed.');
}

// Include the PluginConstants class first
require_once plugin_dir_path(__FILE__) . 'includes/class-plugin-constants.php';

// Initialize plugin constants
PluginConstants::init(__FILE__);

// Load autoloader
require_once plugin_dir_path(__FILE__) . 'includes/class-autoloader.php';

// Initialize autoloader
Includes\Autoloader::register();

// Define plugin constants in global scope
if (!defined('WM_VERSION')) {
    define('WM_VERSION', '1.0.0');
}
if (!defined('WM_PLUGIN_FILE')) {
    define('WM_PLUGIN_FILE', __FILE__);
}
if (!defined('WM_PLUGIN_DIR')) {
    define('WM_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('WM_PLUGIN_URL')) {
    define('WM_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('WM_PLUGIN_BASENAME')) {
    define('WM_PLUGIN_BASENAME', plugin_basename(__FILE__));
}
if (!defined('WM_MINIMUM_WP_VERSION')) {
    define('WM_MINIMUM_WP_VERSION', '5.8');
}
if (!defined('WM_MINIMUM_PHP_VERSION')) {
    define('WM_MINIMUM_PHP_VERSION', '7.4');
}



/**
 * Class Plugin
 *
 * Main plugin bootstrap class.
 *
 * @since 1.0.0
 */
final class Plugin
{

    private static ?Plugin $instance = null;
    private ?Includes\Core $core = null;
    private bool $initialized = false;
    private Includes\Logger $logger;

    /**
     * Plugin constructor.
     */
    private function __construct() {
        $this->logger = Includes\Logger::get_instance();
        $this->initializePlugin();
    }

    /**
     * Get plugin instance.
     */
    public static function get_instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize plugin functionality
     */
    private function initializePlugin(): void {
        try {
            $this->registerAutoloader();
            if (!$this->checkRequirements()) {
                return;
            }
            $this->setupHooks();
            $this->initializeCore();
        } catch (\Exception $e) {
            $this->logger->error('Plugin initialization failed: ' . $e->getMessage());
            add_action('admin_notices', [$this, 'displayInitializationError']);
        }
    }

    /**
     * Register autoloader
     */
    private function registerAutoloader(): void {
        if (class_exists('WatermarkManager\Includes\Autoloader')) {
            spl_autoload_register(['WatermarkManager\Includes\Autoloader', 'autoload']);
        } else {
            throw new \RuntimeException('Autoloader class could not be loaded.');
        }
    }

    /**
     * Check plugin requirements
     */
    private function checkRequirements(): bool {
        if (version_compare(PHP_VERSION, PluginConstants::MINIMUM_PHP_VERSION, '<')) {
            add_action('admin_notices', [$this, 'displayPhpVersionNotice']);
            return false;
        }

        if (version_compare($GLOBALS['wp_version'], PluginConstants::MINIMUM_WP_VERSION, '<')) {
            add_action('admin_notices', [$this, 'displayWpVersionNotice']);
            return false;
        }

        return true;
    }

    /**
     * Setup plugin hooks
     */
    private function setupHooks(): void {
        // Register activation/deactivation hooks
        register_activation_hook(PluginConstants::getPluginFile(), [Includes\Setup::class, 'activate']);
        register_deactivation_hook(PluginConstants::getPluginFile(), [Includes\Setup::class, 'deactivate']);
        
        // Initialize REST API
        add_action('rest_api_init', [$this, 'init_rest_api']);
    }

    /**
     * Initialize plugin core functionality
     */
    private function initializeCore(): void
    {
        if ($this->initialized) {
            return;
        }

        // Check requirements first
        if (!$this->checkRequirements()) {
            return;
        }
        
        add_action('plugins_loaded', function() {
            // Initialize cleanup routines
            $cleanup = new \WatermarkManager\Includes\Cleanup();
            $cleanup->schedule_cleanup();
        });

        // Initialize core
        if (class_exists('WatermarkManager\Includes\Core')) {
            $this->core = new Includes\Core();
            $this->core->run();
        }

        $this->initialized = true;
    }

    /**
     * Initialize REST API
     */
    public function init_rest_api(): void {
        try {
            $rest_controller = new Admin\RestController();
            $rest_controller->register_routes();
        } catch (\Exception $e) {
            $this->logger->error('REST API initialization failed: ' . $e->getMessage());
        }
    }

    /**
     * Display notices
     */
    public function displayPhpVersionNotice(): void {
        $message = sprintf(
            /* translators: %s: PHP version */
            esc_html__('Watermark Manager requires PHP version %s or higher.', 'watermark-manager'),
            PluginConstants::MINIMUM_PHP_VERSION
        );
        $this->displayAdminNotice($message, 'error');
    }

    public function displayWpVersionNotice(): void {
        $message = sprintf(
            /* translators: %s: WordPress version */
            esc_html__('Watermark Manager requires WordPress version %s or higher.', 'watermark-manager'),
            PluginConstants::MINIMUM_WP_VERSION
        );
        $this->displayAdminNotice($message, 'error');
    }

    public function displayInitializationError(): void {
        $message = esc_html__('Watermark Manager failed to initialize properly. Please check error logs for details.', 'watermark-manager');
        $this->displayAdminNotice($message, 'error');
    }

    private function displayAdminNotice(string $message, string $type = 'info'): void {
        $class = sprintf('notice notice-%s', esc_attr($type));
        printf('<div class="%s"><p>%s</p></div>', esc_attr($class), wp_kses_post($message));
    }

    /**
     * Prevent cloning of the instance
     */
    private function __clone() {
    }

    /**
     * Prevent unserializing of the instance
     */
    public function __wakeup() {
        throw new \Exception('Cannot unserialize singleton');
    }
}

// Initialize the plugin
Plugin::get_instance();