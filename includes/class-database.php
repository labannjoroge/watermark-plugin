<?php
declare(strict_types=1);

namespace WatermarkManager\Includes;

/**
 * Database handling class
 */
class Database {
    private const TABLE_NAME = 'WM_settings';
    private static ?\wpdb $wpdb = null;
    private static $logger = null;
    private static $cache_group = 'watermark_manager_settings';
    private static $cache_expiration = 3600; // 1 hour in seconds

    /**
     * Initialize database connection
     */
    private static function init(): void {
        global $wpdb;
        self::$wpdb = $wpdb;
        
        if (self::$logger === null) {
            self::$logger = Logger::get_instance();
        }
    }

    /**
     * Get table name with prefix
     */
    private static function get_table_name(): string {
        self::init();
        return self::$wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Create plugin table
     */
    public static function create_table(): bool {
        try {
            self::init();
            $table_name = self::get_table_name();
            $charset_collate = self::$wpdb->get_charset_collate();

            $sql = "CREATE TABLE $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                setting_name varchar(255) NOT NULL,
                setting_value longtext NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY setting_name (setting_name)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);

            return true;
        } catch (\Exception $e) {
            self::$logger->error('Failed to create table: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete all caches for this group
     */
    private static function clear_cache_group(): void {
        // WordPress doesn't have a built-in function to delete all caches in a group
        // We'll use a cache key containing all setting keys
        $all_keys_cache = wp_cache_get('all_cache_keys', self::$cache_group);
        if (is_array($all_keys_cache)) {
            foreach ($all_keys_cache as $key) {
                wp_cache_delete($key, self::$cache_group);
            }
        }
        wp_cache_delete('all_cache_keys', self::$cache_group);
        wp_cache_delete('all_settings', self::$cache_group);
    }

    /**
     * Add a key to the cache tracking array
     */
    private static function track_cache_key(string $key): void {
        $all_keys_cache = wp_cache_get('all_cache_keys', self::$cache_group);
        if (!is_array($all_keys_cache)) {
            $all_keys_cache = [];
        }
        if (!in_array($key, $all_keys_cache)) {
            $all_keys_cache[] = $key;
            wp_cache_set('all_cache_keys', $all_keys_cache, self::$cache_group);
        }
    }

    /**
     * Drop plugin table
     */
    public static function drop_table(): bool {
        try {
            self::init();
            $table_name = self::get_table_name();
            
            // Use esc_sql for table names, but do not use $wpdb->prepare for table names
            $sql = "DROP TABLE IF EXISTS `" . esc_sql($table_name) . "`";
            self::$wpdb->query($sql);
            
            // Clear all caches when dropping the table
            self::clear_cache_group();
            
            return true;
        } catch (\Exception $e) {
            self::$logger->error('Failed to drop table: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get setting value
     */
    public static function get_setting(string $name): mixed {
        try {
            // Check cache first
            $cache_key = 'setting_' . $name;
            $cached_value = wp_cache_get($cache_key, self::$cache_group);
            
            if ($cached_value !== false) {
                return $cached_value;
            }
            
            self::init();
            $table_name = self::get_table_name();
            
            // Use esc_sql for table names, but do not use $wpdb->prepare for table names
            $query = self::$wpdb->prepare(
                "SELECT setting_value FROM " . esc_sql($table_name) . " WHERE setting_name = %s",
                $name
            );
            
            $result = self::$wpdb->get_var($query);
            $unserialized_result = $result ? maybe_unserialize($result) : null;
            
            // Cache the result
            if ($unserialized_result !== null) {
                wp_cache_set($cache_key, $unserialized_result, self::$cache_group, self::$cache_expiration);
                self::track_cache_key($cache_key);
            }
            
            return $unserialized_result;
        } catch (\Exception $e) {
            self::$logger->error('Failed to get setting: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Update setting value
     */
    public static function update_setting(string $name, mixed $value): bool {
        try {
            self::init();
            $table_name = self::get_table_name();
            $serialized_value = maybe_serialize($value);
            
            $existing = self::get_setting($name);
            
            if ($existing === null) {
                $result = self::$wpdb->insert(
                    $table_name,
                    ['setting_name' => $name, 'setting_value' => $serialized_value],
                    ['%s', '%s']
                );
            } else {
                $result = self::$wpdb->update(
                    $table_name,
                    ['setting_value' => $serialized_value],
                    ['setting_name' => $name],
                    ['%s'],
                    ['%s']
                );
            }
            
            // Update cache
            $cache_key = 'setting_' . $name;
            wp_cache_set($cache_key, $value, self::$cache_group, self::$cache_expiration);
            self::track_cache_key($cache_key);
            
            // Also invalidate the all_settings cache
            wp_cache_delete('all_settings', self::$cache_group);
            
            return $result !== false;
        } catch (\Exception $e) {
            self::$logger->error('Failed to update setting: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete setting
     */
    public static function delete_setting(string $name): bool {
        try {
            self::init();
            $table_name = self::get_table_name();
            $result = self::$wpdb->delete(
                $table_name,
                ['setting_name' => $name],
                ['%s']
            );
            
            // Delete from cache
            $cache_key = 'setting_' . $name;
            wp_cache_delete($cache_key, self::$cache_group);
            
            // Also invalidate the all_settings cache
            wp_cache_delete('all_settings', self::$cache_group);
            
            return (bool) $result;
        } catch (\Exception $e) {
            self::$logger->error('Failed to delete setting: ' . $e->getMessage());
            return false;
        }
    }
    

    /**
     * Get all settings
     */
    public static function get_all_settings(): array {
        try {
            // Check cache first
            $cache_key = 'all_settings';
            $cached_value = wp_cache_get($cache_key, self::$cache_group);
            
            if ($cached_value !== false) {
                return $cached_value;
            }
            
            self::init();
            $table_name = self::get_table_name();
            
            // Use esc_sql for table names, but do not use $wpdb->prepare for table names
            $query = "SELECT setting_name, setting_value FROM " . esc_sql($table_name);
            $results = self::$wpdb->get_results($query, ARRAY_A);
            
            $settings = [];
            foreach ($results as $row) {
                $settings[$row['setting_name']] = maybe_unserialize($row['setting_value']);
            }
            
            // Cache the result
            wp_cache_set($cache_key, $settings, self::$cache_group, self::$cache_expiration);
            self::track_cache_key($cache_key);
            
            return $settings;
        } catch (\Exception $e) {
            self::$logger->error('Failed to get all settings: ' . $e->getMessage());
            return [];
        }
    }
}