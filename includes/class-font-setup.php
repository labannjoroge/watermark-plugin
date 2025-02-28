<?php
namespace WatermarkManager\Includes;

/**
 * Font Setup class for managing plugin fonts
 *
 * @since      1.0.0
 * @package    WatermarkManager
 * @subpackage WatermarkManager/includes
 */
class FontSetup {
    private $fonts_dir;
    private $fonts = [
        'Roboto' => 'https://github.com/google/fonts/raw/main/apache/roboto/static/Roboto-Regular.ttf',
        'RobotoBold' => 'https://github.com/google/fonts/raw/main/apache/roboto/static/Roboto-Bold.ttf',
    ];
    private $logger;
    private $wp_filesystem;

    public function __construct() {
        $this->fonts_dir = WM_PLUGIN_DIR . 'assets/fonts/';
        $this->logger = Logger::get_instance();
        
        // Initialize WP Filesystem
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once(ABSPATH . '/wp-admin/includes/file.php');
            WP_Filesystem();
            $this->wp_filesystem = $wp_filesystem;
        } else {
            $this->wp_filesystem = $wp_filesystem;
        }
    }

    public function setup() {
        if (!file_exists($this->fonts_dir)) {
            $wp_upload_dir = wp_upload_dir();
            $credentials = request_filesystem_credentials($wp_upload_dir['basedir']);
            if (!WP_Filesystem($credentials)) {
                $this->logger->error('Failed to initialize WP Filesystem');
                return false;
            }
            
            if (!$this->wp_filesystem->mkdir($this->fonts_dir, 0755)) {
                $this->logger->error('Failed to create fonts directory');
                return false;
            }
        }

        foreach ($this->fonts as $font_name => $font_url) {
            $font_path = $this->fonts_dir . $font_name . '.ttf';
            
            if (file_exists($font_path)) {
                continue;
            }

            $font_data = wp_remote_get($font_url);
            if (is_wp_error($font_data)) {
                $this->logger->error("Failed to download font: $font_name");
                continue;
            }

            $font_content = wp_remote_retrieve_body($font_data);
            if (empty($font_content)) {
                $this->logger->error("Empty font content for: $font_name");
                continue;
            }

            if (!$this->wp_filesystem->put_contents($font_path, $font_content, FS_CHMOD_FILE)) {
                $this->logger->error("Failed to save font: $font_name");
                continue;
            }
        }

        return true;
    }

    public function get_font_path($font_name = 'Roboto') {
        return $this->fonts_dir . $font_name . '.ttf';
    }
}