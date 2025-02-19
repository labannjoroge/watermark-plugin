<?php
namespace WatermarkManager\Includes;

/**
 * Autoloader class for the plugin.
 *
 * @since      1.0.0
 * @package    WatermarkManager
 * @subpackage WatermarkManager/includes
 */
class Autoloader {
    /**
     * Autoload function for class loading.
     *
     * @param string $class The fully-qualified class name.
     */
    public static function autoload($class) {
        // Project-specific namespace prefix
        $prefix = 'WatermarkManager\\';

        // Base directory for the namespace prefix
        $base_dir = WM_PLUGIN_DIR;

        // Does the class use the namespace prefix?
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            // No, move to the next registered autoloader
            return;
        }

        // Get the relative class name
        $relative_class = substr($class, $len);

        // Convert the relative class name to a file path
        $file_path = str_replace('\\', '/', $relative_class);
        
        // Convert CamelCase to hyphen-case and add 'class-' prefix
        $file_name = 'class-' . strtolower(preg_replace('/([a-zA-Z])(?=[A-Z])/', '$1-', basename($file_path)));
        
        // Create the final file path
        $file = $base_dir . dirname($file_path) . '/' . $file_name . '.php';

        // If the file exists, require it
        if (file_exists($file)) {
            require $file;
        }
    }
}