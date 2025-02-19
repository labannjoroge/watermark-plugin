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

    public function __construct() {
        $this->fonts_dir = WM_PLUGIN_DIR . 'assets/fonts/';
    }

    public function setup() {
        if (!file_exists($this->fonts_dir)) {
            if (!mkdir($this->fonts_dir, 0755, true)) {
                error_log('Failed to create fonts directory');
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
                error_log("Failed to download font: $font_name");
                continue;
            }

            $font_content = wp_remote_retrieve_body($font_data);
            if (empty($font_content)) {
                error_log("Empty font content for: $font_name");
                continue;
            }

            if (!file_put_contents($font_path, $font_content)) {
                error_log("Failed to save font: $font_name");
                continue;
            }
        }

        return true;
    }

    public function get_font_path($font_name = 'Roboto') {
        return $this->fonts_dir . $font_name . '.ttf';
    }
}