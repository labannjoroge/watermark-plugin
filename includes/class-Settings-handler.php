<?php
namespace WatermarkManager\Includes;

use Exception;
use WP_Error;

class SettingsHandler {
    private const SETTINGS_KEY = 'watermark_settings';
    
    /**
     * Get default settings
     *
     * @return array
     */
    private static function get_defaults(): array {
        return [
            'watermarkImage' => null,
            'position' => 'bottom-right',
            'opacity' => 50,
            'size' => 50,
            'rotation' => 0,
            'autoWatermark' => false,
            'backupOriginals' => true,
        ];
    }

    /**
     * Get all settings
     *
     * @return array
     */
    public static function get_settings(): array {
        $settings = Database::get_setting(self::SETTINGS_KEY);
        return wp_parse_args($settings, self::get_defaults());
    }

    /**
     * Update settings
     *
     * @param array $settings
     * @return bool|WP_Error
     */
    public static function update_settings(array $settings) {
        try {
            // Validate settings
            $validation_result = self::validate_settings($settings);
            if (is_wp_error($validation_result)) {
                return $validation_result;
            }

            // Sanitize settings
            $sanitized_settings = self::sanitize_settings($settings);

            // Merge with existing settings to preserve any missing values
            $current_settings = self::get_settings();
            $merged_settings = wp_parse_args($sanitized_settings, $current_settings);

            // Save to database
            $success = Database::update_setting(self::SETTINGS_KEY, $merged_settings);

            if (!$success) {
                throw new Exception('Failed to update settings in database');
            }

            return true;

        } catch (Exception $e) {
            return new WP_Error(
                'settings_update_failed',
                $e->getMessage()
            );
        }
    }

    /**
     * Validate settings
     *
     * @param array $settings
     * @return true|WP_Error
     */
    public static function validate_settings(array $settings) {
        $errors = [];

        // Validate watermarkImage if provided
        if (isset($settings['watermarkImage'])) {
            if (is_numeric($settings['watermarkImage'])) {
                if (!wp_get_attachment_url($settings['watermarkImage'])) {
                    $errors[] = 'Invalid watermark image attachment ID';
                }
            } elseif (!empty($settings['watermarkImage']) && !filter_var($settings['watermarkImage'], FILTER_VALIDATE_URL)) {
                $errors[] = 'Invalid watermark image URL format';
            }
        }

        // Validate position if provided
        if (isset($settings['position'])) {
            $valid_positions = ['top-left', 'top-right', 'bottom-left', 'bottom-right', 'center'];
            if (!in_array($settings['position'], $valid_positions, true)) {
                $errors[] = 'Invalid position value';
            }
        }

        // Validate numeric ranges if provided
        $numeric_validations = [
            'opacity' => ['min' => 0, 'max' => 100],
            'size' => ['min' => 1, 'max' => 100],
            'rotation' => ['min' => 0, 'max' => 360]
        ];

        foreach ($numeric_validations as $field => $range) {
            if (isset($settings[$field])) {
                if (!is_numeric($settings[$field]) || 
                    $settings[$field] < $range['min'] || 
                    $settings[$field] > $range['max']) {
                    $errors[] = sprintf(
                        'Invalid %s value. Must be between %d and %d',
                        $field,
                        $range['min'],
                        $range['max']
                    );
                }
            }
        }

        if (!empty($errors)) {
            return new WP_Error(
                'invalid_settings',
                'Settings validation failed',
                $errors
            );
        }

        return true;
    }

    /**
     * Sanitize settings
     *
     * @param array $settings
     * @return array
     */
    public static function sanitize_settings(array $settings): array {
        $sanitized = [];

        // Handle watermarkImage
        if (isset($settings['watermarkImage'])) {
            if (is_numeric($settings['watermarkImage'])) {
                $sanitized['watermarkImage'] = absint($settings['watermarkImage']);
            } else {
                $sanitized['watermarkImage'] = esc_url_raw($settings['watermarkImage']);
            }
        }

        // Handle position
        if (isset($settings['position'])) {
            $sanitized['position'] = sanitize_text_field($settings['position']);
        }

        // Handle numeric values
        $numeric_fields = [
            'opacity' => ['min' => 0, 'max' => 100],
            'size' => ['min' => 1, 'max' => 100],
            'rotation' => ['min' => 0, 'max' => 360]
        ];

        foreach ($numeric_fields as $field => $range) {
            if (isset($settings[$field])) {
                $sanitized[$field] = max($range['min'], min($range['max'], intval($settings[$field])));
            }
        }

        // Handle boolean values
        $boolean_fields = ['autoWatermark', 'backupOriginals'];
        foreach ($boolean_fields as $field) {
            if (isset($settings[$field])) {
                $sanitized[$field] = (bool) $settings[$field];
            }
        }

        return $sanitized;
    }

    /**
     * Migrate settings from wp_options to custom table
     *
     * @return bool|WP_Error
     */
    public static function migrate_from_options() {
        try {
            $old_settings = get_option('WM_image_watermark_options', false);
            if ($old_settings) {
                $result = self::update_settings($old_settings);
                if (is_wp_error($result)) {
                    throw new Exception($result->get_error_message());
                }
                delete_option('WM_image_watermark_options');
            }
            return true;
        } catch (Exception $e) {
            return new WP_Error(
                'settings_migration_failed',
                $e->getMessage()
            );
        }
    }

    /**
     * Prepare settings for REST API response
     *
     * @param array $settings
     * @return array
     */
    public static function prepare_for_response(array $settings): array {
        $prepared = $settings;

        // Convert watermark image ID to URL if needed
        if (!empty($prepared['watermarkImage']) && is_numeric($prepared['watermarkImage'])) {
            $image_url = wp_get_attachment_url($prepared['watermarkImage']);
            if ($image_url) {
                $prepared['watermarkImage'] = $image_url;
            }
        }

        // Ensure all fields exist with proper types
        $defaults = self::get_defaults();
        foreach ($defaults as $key => $default_value) {
            if (!isset($prepared[$key])) {
                $prepared[$key] = $default_value;
            }

            // Type casting
            if (is_bool($default_value)) {
                $prepared[$key] = (bool) $prepared[$key];
            } elseif (is_int($default_value)) {
                $prepared[$key] = (int) $prepared[$key];
            }
        }

        return $prepared;
    }

    /**
     * Reset settings to defaults
     *
     * @return bool|WP_Error
     */
    public static function reset_to_defaults() {
        try {
            return self::update_settings(self::get_defaults());
        } catch (Exception $e) {
            return new WP_Error(
                'settings_reset_failed',
                $e->getMessage()
            );
        }
    }
}