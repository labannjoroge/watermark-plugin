<?php
declare(strict_types=1);

namespace WatermarkManager\Includes;

use WatermarkManager\Admin\Admin;
use WatermarkManager\PluginConstants;
/**
 * Core functionality for the Watermark Manager plugin.
 */
class Core {
    private const PLUGIN_NAME = 'watermark-manager';
    
    /** @var array<string, object> */
    private array $services = [];
    private Logger $logger;
    private Loader $loader;
    private string $version;
    private Admin $admin;
    private $cleanup;

    /**
     * Initialize the class and set its properties.
     */
    public function __construct() {
        try {
            $this->version = PluginConstants::VERSION;
            $this->loader = new Loader();
            $this->logger = Logger::get_instance();
            $this->cleanup = new Cleanup();

            $this->init_services();
            $this->register_hooks();
            $this->cleanup->schedule_cleanup();
        } catch (\Exception $e) {
            $this->logger->error('Core initialization failed: ' . $e->getMessage());
            add_action('admin_notices', [$this, 'display_initialization_error']);
        }
    }

    /**
     * Initialize plugin services
     */
    private function init_services(): void {
        try {
            $this->services['image_watermark'] = new ImageWatermark();
            $this->services['admin'] = new Admin(self::PLUGIN_NAME, $this->version);
        } catch (\Exception $e) {
            $this->logger->error('Service initialization failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Register all hooks for the plugin.
     */
    private function register_hooks(): void {
        try {
            // Admin hooks
            $this->loader->add_action('admin_enqueue_scripts', $this->services['admin'], 'enqueue_styles');
            $this->loader->add_action('admin_enqueue_scripts', $this->services['admin'], 'enqueue_scripts');
            $this->loader->add_action('admin_menu', $this->services['admin'], 'add_plugin_admin_menu');
            $this->loader->add_filter(
                'plugin_action_links_' . WM_PLUGIN_BASENAME,
                $this->services['admin'],
                'add_action_links'
            );

            // Image processing hooks
            $this->loader->add_filter('wp_generate_attachment_metadata', $this, 'process_image', 10, 2);
            $this->loader->add_action('delete_attachment', $this, 'cleanup_watermarks');

            // Schedule watermark application
            add_action('init', [$this, 'schedule_watermark_application']);

            $this->loader->add_action(
                'delete_attachment',
                $this->services['image_watermark'],
                'clear_watermark_path_cache'
            );
        } catch (\Exception $e) {
            $this->logger->error('Hook registration failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process uploaded images for watermarking
     */
    public function process_image(array $metadata, int $attachment_id): array {
        try {
            if (!$this->should_watermark($attachment_id)) {
                return $metadata;
            }

            $settings = get_option('WM_image_settings', []);
            return $this->services['image_watermark']->process($metadata, $attachment_id, $settings);
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'Error processing image %d: %s',
                $attachment_id,
                $e->getMessage()
            ));
            return $metadata;
        }
    }

    /**
     * Check if an image should be watermarked
     */
    private function should_watermark(int $attachment_id): bool {
        try {
            $settings = get_option('WM_image_settings', []);
            if (empty($settings['enabled'])) {
                return false;
            }

            $mime_type = get_post_mime_type($attachment_id);
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($mime_type, $allowed_types, true)) {
                return false;
            }

            $metadata = wp_get_attachment_metadata($attachment_id);
            if (!empty($settings['min_width']) && $metadata['width'] < $settings['min_width']) {
                return false;
            }

            return (bool) apply_filters('WM_should_watermark_image', true, $attachment_id, $settings);
        } catch (\Exception $e) {
            $this->logger->error('Error checking watermark conditions: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Schedule watermark application
     */
    public function schedule_watermark_application(): void {
        try {
            add_action(
                'WM_apply_watermark_to_attachment',
                [$this->services['image_watermark'], 'apply_watermark'],
                10,
                1
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to schedule watermark application: ' . $e->getMessage());
        }
    }

    /**
     * Clean up watermarks when attachment is deleted
     */
    public function cleanup_watermarks(int $attachment_id): void {
        try {
            $this->services['image_watermark']->cleanup($attachment_id);
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'Error cleaning up watermarks for attachment %d: %s',
                $attachment_id,
                $e->getMessage()
            ));
        }
    }

    /**
     * Run the loader to execute all registered hooks
     */
    public function run(): void {
        try {
            $this->loader->run();
        } catch (\Exception $e) {
            $this->logger->error('Failed to run hooks: ' . $e->getMessage());
        }
    }

    /**
     * Display initialization error notice
     */
    public function display_initialization_error(): void {
        $class = 'notice notice-error';
        $message = esc_html__('Watermark Manager failed to initialize properly. Please check error logs.', 'watermark-manager');
        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
    }
}