<?php
declare(strict_types=1);

namespace WatermarkManager\Includes;
use WatermarkManager\PluginConstants;
/**
 * Autoloader class for the plugin.
 */
class Autoloader {
    private const NAMESPACE_PREFIX = 'WatermarkManager\\';

    /**
     * Autoload function for class loading.
     */
    public static function autoload(string $class): void {
        try {
            // Check if the class uses our namespace prefix
            $len = strlen(self::NAMESPACE_PREFIX);
            if (strncmp(self::NAMESPACE_PREFIX, $class, $len) !== 0) {
                return;
            }

            $relative_class = substr($class, $len);
            $file = self::convertClassToFilePath($relative_class);
            
            if (file_exists($file)) {
                require $file;
            } else {
                throw new \RuntimeException(
                    sprintf('Class file %s not found for class %s', $file, $class)
                );
            }
        } catch (\Exception $e) {
             $this->logger->error('Autoloader failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Convert class name to file path.
     */
    private static function convertClassToFilePath(string $class): string {
        // Convert namespace separators to directory separators
        $file_path = str_replace('\\', '/', $class);
        
        // Convert CamelCase to hyphen-case and add 'class-' prefix
        $file_name = 'class-' . strtolower(preg_replace('/([a-zA-Z])(?=[A-Z])/', '$1-', basename($file_path)));
        
        // Create the final file path
        return PluginConstants::getPluginDir() . dirname($file_path) . '/' . $file_name . '.php';
    }

    /**
     * Register the autoloader
     */
    public static function register(): bool {
        return spl_autoload_register([self::class, 'autoload'], true, true);
    }

    /**
     * Unregister the autoloader
     */
    public static function unregister(): bool {
        return spl_autoload_unregister([self::class, 'autoload']);
    }
}