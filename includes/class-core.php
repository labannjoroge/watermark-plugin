<?php
/**
 * Core functionality for the  Watermark Manager plugin.
 *
 * @package    WatermarkManager
 * @subpackage Includes
 * @since      1.0.0
 */

namespace WatermarkManager\Includes;

use WatermarkManager\Admin\Admin;
use WatermarkManager\Includes\ContentWatermark;
use WatermarkManager\Includes\ImageWatermark;

/**
 * Core Class
 *
 * Handles the core functionality of the plugin including service initialization,
 * hook registration, and coordination between different plugin components.
 *
 * @since 1.0.0
 */
class Core
{
    /**
     * Service container
     *
     * @since  1.0.0
     * @access private
     * @var    array
     */
    private array $services = [];

    /**
     * The loader that's responsible for maintaining and registering all hooks.
     *
     * @since  1.0.0
     * @access private
     * @var    Loader
     */
    private Loader $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since  1.0.0
     * @access protected
     * @var    string
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since  1.0.0
     * @access protected
     * @var    string
     */
    protected $version;

    protected $admin;
    /**
     * Initialize the class and set its properties.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->plugin_name = 'watermark-manager';
        $this->version = WM_VERSION;
        $this->loader = new Loader();
        $this->init_services();
        $this->register_hooks();
    }

    /**
     * Initialize plugin services
     *
     * @since  1.0.0
     * @access private
     * @return void
     */
    private function init_services(): void
    {
        // Initialize core services
        $this->services['image_watermark'] = new ImageWatermark();
        $this->services['content_watermark'] = new ContentWatermark();
        $this->services['admin'] = new Admin($this->plugin_name, $this->version);
    }

    /**
     * Register all hooks for the plugin.
     *
     * @since  1.0.0
     * @access private
     * @return void
     */
    private function register_hooks(): void
    {
        // Admin hooks
        $this->loader->add_action('admin_enqueue_scripts', $this->services['admin'], 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $this->services['admin'], 'enqueue_scripts');
        $this->loader->add_action('admin_menu', $this->services['admin'], 'add_plugin_admin_menu');
        $this->loader->add_filter('plugin_action_links_' . WM_PLUGIN_BASENAME, $this->services['admin'], 'add_action_links');

        // Image processing hooks
        $this->loader->add_filter('wp_generate_attachment_metadata', $this, 'process_image', 10, 2);
        $this->loader->add_action('delete_attachment', $this, 'cleanup_watermarks');

        // Schedule watermark application
        add_action('init', [$this, 'schedule_watermark_application']);

        $this->loader->add_action('delete_attachment', $this->services['image_watermark'], 'clear_watermark_path_cache');
    }

    /**
     * Process uploaded images for watermarking
     *
     * @since  1.0.0
     * @access public
     * @param  array $metadata      Attachment metadata
     * @param  int   $attachment_id Attachment ID
     * @return array
     */
    public function process_image(array $metadata, int $attachment_id): array
    {
        try {
            // Check if image should be watermarked
            if (!$this->should_watermark($attachment_id)) {
                return $metadata;
            }

            // Get watermark settings
            $settings = get_option('WM_image_settings', []);

            // Process the image
            return $this->services['image_watermark']->process($metadata, $attachment_id, $settings);
        } catch (\Exception $e) {
            // Log error and return original metadata
            error_log(sprintf(
                '[ Watermark Manager] Error processing image %d: %s',
                $attachment_id,
                $e->getMessage()
            ));
            return $metadata;
        }
    }

    /**
     * Check if an image should be watermarked
     *
     * @since  1.0.0
     * @access private
     * @param  int $attachment_id Attachment ID
     * @return bool
     */
    private function should_watermark(int $attachment_id): bool
    {
        // Get settings
        $settings = get_option('WM_image_settings', []);
        if (empty($settings['enabled'])) {
            return false;
        }

        // Check mime type
        $mime_type = get_post_mime_type($attachment_id);
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($mime_type, $allowed_types, true)) {
            return false;
        }

        // Check image dimensions
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (!empty($settings['min_width']) && $metadata['width'] < $settings['min_width']) {
            return false;
        }

        // Allow filtering
        return apply_filters('WM_should_watermark_image', true, $attachment_id, $settings);
    }

    public function schedule_watermark_application()
    {
        add_action('WM_apply_watermark_to_attachment', [$this->services['image_watermark'], 'apply_watermark'], 10, 1);
    }

    public function cleanup_watermarks(int $attachment_id): void
    {
        try {
            $this->services['image_watermark']->cleanup($attachment_id);
        } catch (\Exception $e) {
            error_log(sprintf('[ Watermark Manager] Error cleaning up watermarks for attachment %d: %s', $attachment_id, $e->getMessage()));
        }
    }

    /**
     * Run the loader to execute all registered hooks
     *
     * @since  1.0.0
     * @access public
     * @return void
     */
    public function run(): void
    {
        $this->loader->run();
    }
}