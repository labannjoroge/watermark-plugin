<?php
namespace WatermarkManager;

/**
 * Plugin Constants
 * 
 * Handles all plugin constants and paths
 */
class PluginConstants {
    // Plugin Info
    public const VERSION = '1.0.0';
    public const TEXT_DOMAIN = 'watermark-manager';
    
    // System Requirements
    public const MINIMUM_WP_VERSION = '5.8';
    public const MINIMUM_PHP_VERSION = '7.4';
    
    // Database Constants
    public const DB_VERSION = '1.0.0';
    public const TABLE_NAME = 'WM_settings';
    
    // Static properties for dynamic paths
    private static ?string $pluginFile = null;
    private static ?string $pluginDir = null;
    private static ?string $pluginUrl = null;
    private static ?string $pluginBasename = null;
    private static ?array $uploadPaths = null;

    /**
     * Initialize plugin constants
     */
    public static function init(string $pluginFile): void {
        self::$pluginFile = $pluginFile;
        self::$pluginDir = plugin_dir_path($pluginFile);
        self::$pluginUrl = plugin_dir_url($pluginFile);
        self::$pluginBasename = plugin_basename($pluginFile);
        
        // Define global constants for backward compatibility
        if (!defined('WM_VERSION')) {
            define('WM_VERSION', self::VERSION);
            define('WM_PLUGIN_DIR', self::getPluginDir());
            define('WM_PLUGIN_URL', self::getPluginUrl());
            define('WM_PLUGIN_FILE', self::getPluginFile());
            define('WM_PLUGIN_BASENAME', self::getPluginBasename());
            define('WM_MINIMUM_WP_VERSION', self::MINIMUM_WP_VERSION);
            define('WM_MINIMUM_PHP_VERSION', self::MINIMUM_PHP_VERSION);
        }

        // Initialize upload paths
        self::initUploadPaths();
    }

    /**
     * Get plugin file path
     */
    public static function getPluginFile(): string {
        if (self::$pluginFile === null) {
            throw new \RuntimeException('Plugin constants not initialized. Call PluginConstants::init() first.');
        }
        return self::$pluginFile;
    }

    /**
     * Get plugin directory path
     */
    public static function getPluginDir(): string {
        if (self::$pluginDir === null) {
            throw new \RuntimeException('Plugin constants not initialized. Call PluginConstants::init() first.');
        }
        return self::$pluginDir;
    }

    /**
     * Get plugin URL
     */
    public static function getPluginUrl(): string {
        if (self::$pluginUrl === null) {
            throw new \RuntimeException('Plugin constants not initialized. Call PluginConstants::init() first.');
        }
        return self::$pluginUrl;
    }

    /**
     * Get plugin basename
     */
    public static function getPluginBasename(): string {
        if (self::$pluginBasename === null) {
            throw new \RuntimeException('Plugin constants not initialized. Call PluginConstants::init() first.');
        }
        return self::$pluginBasename;
    }

    /**
     * Initialize upload paths
     */
    private static function initUploadPaths(): void {
        $upload_dir = wp_upload_dir();
        self::$uploadPaths = [
            'watermarks' => $upload_dir['basedir'] . '/WM-watermarks',
            'temp' => $upload_dir['basedir'] . '/WM-temp',
            'fonts' => $upload_dir['basedir'] . '/WM-fonts',
            'logs' => $upload_dir['basedir'] . '/WM-logs'
        ];
    }

    /**
     * Get upload directory paths
     */
    public static function getUploadPaths(): array {
        if (self::$uploadPaths === null) {
            throw new \RuntimeException('Upload paths not initialized. Call PluginConstants::init() first.');
        }
        return self::$uploadPaths;
    }

    /**
     * Get plugin settings
     */
    public static function getDefaultSettings(): array {
        return [
            'image_watermark' => [
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
            ],
            'content_watermark' => [
                'enabled' => false,
                'text' => '© ' . get_bloginfo('name'),
                'position' => 'after',
                'css_class' => 'WM-watermark',
                'exclude_post_types' => ['page'],
            ]
        ];
    }
}

