<?php
namespace WatermarkManager\Admin;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WatermarkManager\Includes\ImageWatermark;

use WatermarkManager\Includes\SettingsHandler;
use Exception;
class RestController
{
    private const API_NAMESPACE = 'WM/v1';
    private $image_watermark;

    public function __construct()
    {
        $this->image_watermark = new ImageWatermark();
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/settings', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_settings'],
                'permission_callback' => [$this, 'check_admin_permissions'],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_settings'],
                'permission_callback' => [$this, 'check_admin_permissions'],
                'args' => [
                    'watermarkImage' => [
                        'type' => 'string',
                        'required' => false,
                        'sanitize_callback' => 'esc_url_raw',
                    ],
                    'position' => [
                        'type' => 'string',
                        'required' => true,
                        'enum' => ['top-left', 'top-right', 'bottom-left', 'bottom-right', 'center'],
                    ],
                    'opacity' => [
                        'type' => 'integer',
                        'required' => true,
                        'minimum' => 0,
                        'maximum' => 100,
                    ],
                    'size' => [
                        'type' => 'integer',
                        'required' => true,
                        'minimum' => 1,
                        'maximum' => 100,
                    ],
                    'rotation' => [
                        'type' => 'integer',
                        'required' => true,
                        'minimum' => 0,
                        'maximum' => 360,
                    ],
                    'autoWatermark' => [
                        'type' => 'boolean',
                        'required' => false,
                    ],
                    'backupOriginals' => [
                        'type' => 'boolean',
                        'required' => false,
                    ],
                ],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/preview', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'generate_preview'],
            'permission_callback' => [$this, 'check_admin_permissions'],
            'args' => [
                'imageData' => [
                    'required' => false,
                    'type' => 'string',
                ],
                'settings' => [
                    'required' => true,
                    'type' => 'object',
                ],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/bulk-watermark', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'bulk_watermark'],
            'permission_callback' => [$this, 'check_admin_permissions'],
        ]);

        register_rest_route(self::API_NAMESPACE, '/watermarked-images', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_watermarked_images'],
            'permission_callback' => [$this, 'check_admin_permissions'],
        ]);

        register_rest_route(self::API_NAMESPACE, '/restore/(?P<id>\d+)', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'restore_original'],
            'permission_callback' => [$this, 'check_admin_permissions'],
        ]);

        register_rest_route(self::API_NAMESPACE, '/images', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_non_watermarked_images'],
            'permission_callback' => [$this, 'check_admin_permissions'],
        ]);

        register_rest_route(self::API_NAMESPACE, '/watermark-all', [
            'methods' => 'POST',
            'callback' => [$this, 'watermark_all_images'],
            'permission_callback' => [$this, 'permissions_check'],
        ]);
    }

    public function check_admin_permissions(): bool
    {
        return current_user_can('manage_options');
    }

    public function get_settings(): WP_REST_Response {
        $settings = SettingsHandler::get_settings();
        $prepared_settings = SettingsHandler::prepare_for_response($settings);
        return new WP_REST_Response($prepared_settings, 200);
    }
    
    public function update_settings(WP_REST_Request $request): WP_REST_Response {
        $settings = $request->get_params();
        $result = SettingsHandler::update_settings($settings);
    
        if (is_wp_error($result)) {
            return new WP_REST_Response([
                'error' => $result->get_error_message(),
                'details' => $result->get_error_data()
            ], 400);
        }
    
        $updated_settings = SettingsHandler::get_settings();
        $prepared_settings = SettingsHandler::prepare_for_response($updated_settings);
    
        return new WP_REST_Response([
            'message' => 'Settings updated successfully',
            'settings' => $prepared_settings
        ], 200);
    }
    private function validate_watermark_settings(array $settings): array
    {
        $errors = [];

        // Validate watermarkImage
        if (isset($settings['watermarkImage'])) {
            if (is_numeric($settings['watermarkImage'])) {
                // Check if attachment exists
                if (!wp_get_attachment_url($settings['watermarkImage'])) {
                    $errors[] = 'Invalid watermark image attachment ID';
                }
            } elseif (!empty($settings['watermarkImage']) && !filter_var($settings['watermarkImage'], FILTER_VALIDATE_URL)) {
                $errors[] = 'Invalid watermark image URL format';
            }
        }

        // Validate position
        if (
            !isset($settings['position']) || !in_array(
                $settings['position'],
                ['top-left', 'top-right', 'bottom-left', 'bottom-right', 'center']
            )
        ) {
            $errors[] = 'Invalid position value';
        }

        // Validate numeric ranges
        $numeric_validations = [
            'opacity' => ['min' => 0, 'max' => 100],
            'size' => ['min' => 1, 'max' => 100],
            'rotation' => ['min' => 0, 'max' => 360]
        ];

        foreach ($numeric_validations as $field => $range) {
            if (
                !isset($settings[$field]) ||
                !is_numeric($settings[$field]) ||
                $settings[$field] < $range['min'] ||
                $settings[$field] > $range['max']
            ) {
                $errors[] = sprintf(
                    'Invalid %s value. Must be between %d and %d',
                    $field,
                    $range['min'],
                    $range['max']
                );
            }
        }

        return $errors;
    }

    private function prepare_settings_for_response(array $settings): array
    {
        try {
            $response_settings = $settings;

            // Handle watermarkImage
            if (!empty($response_settings['watermarkImage'])) {
                if (is_numeric($response_settings['watermarkImage'])) {
                    $image_url = wp_get_attachment_url($response_settings['watermarkImage']);
                    if (!$image_url) {
                        throw new Exception('Failed to get watermark image URL');
                    }
                    $response_settings['watermarkImage'] = $image_url;
                }
            }

            // Ensure all required fields exist with proper types
            $defaults = [
                'position' => 'bottom-right',
                'opacity' => 50,
                'size' => 50,
                'rotation' => 0,
                'autoWatermark' => false,
                'backupOriginals' => true
            ];

            foreach ($defaults as $key => $default_value) {
                if (!isset($response_settings[$key])) {
                    $response_settings[$key] = $default_value;
                }

                // Type casting for boolean values
                if (in_array($key, ['autoWatermark', 'backupOriginals'])) {
                    $response_settings[$key] = (bool) $response_settings[$key];
                }

                // Type casting for numeric values
                if (in_array($key, ['opacity', 'size', 'rotation'])) {
                    $response_settings[$key] = (int) $response_settings[$key];
                }
            }

            return $response_settings;

        } catch (Exception $e) {
            error_log('Error preparing settings response: ' . $e->getMessage());
            throw $e;
        }
    }

    private function sanitize_settings(array $settings): array
    {
        $sanitized = [];

        // Handle watermarkImage - support both URL and attachment ID
        if (isset($settings['watermarkImage'])) {
            if (is_numeric($settings['watermarkImage'])) {
                $sanitized['watermarkImage'] = absint($settings['watermarkImage']);
            } else {
                $sanitized['watermarkImage'] = esc_url_raw($settings['watermarkImage']);
            }
        }

        if (isset($settings['position']) && in_array($settings['position'], ['top-left', 'top-right', 'bottom-left', 'bottom-right', 'center'])) {
            $sanitized['position'] = sanitize_text_field($settings['position']);
        }

        if (isset($settings['opacity']) && is_numeric($settings['opacity'])) {
            $sanitized['opacity'] = max(0, min(100, intval($settings['opacity'])));
        }

        if (isset($settings['size']) && is_numeric($settings['size'])) {
            $sanitized['size'] = max(1, min(100, intval($settings['size'])));
        }

        if (isset($settings['rotation']) && is_numeric($settings['rotation'])) {
            $sanitized['rotation'] = max(0, min(360, intval($settings['rotation'])));
        }

        // Handle boolean values
        if (isset($settings['autoWatermark'])) {
            $sanitized['autoWatermark'] = (bool) $settings['autoWatermark'];
        }

        if (isset($settings['backupOriginals'])) {
            $sanitized['backupOriginals'] = (bool) $settings['backupOriginals'];
        }

        return $sanitized;
    }

    public function generate_preview(WP_REST_Request $request): WP_REST_Response
    {
        try {
            // Get and validate the request parameters
            $params = $request->get_params();

            // Check if we have the required parameters
            if (!isset($params['settings']) || !is_array($params['settings'])) {
                return new WP_REST_Response([
                    'error' => 'Invalid or missing settings parameter'
                ], 400);
            }

            // Get the media ID
            $media_id = isset($params['mediaId']) ? absint($params['mediaId']) : null;

            if (!$media_id) {
                return new WP_REST_Response([
                    'error' => 'Missing or invalid mediaId parameter'
                ], 400);
            }

            // Get the image path from media library
            $image_path = get_attached_file($media_id);
            if (!$image_path || !file_exists($image_path)) {
                return new WP_REST_Response([
                    'error' => 'Image file not found'
                ], 404);
            }

            // Get image data
            $image_data = file_get_contents($image_path);
            if ($image_data === false) {
                return new WP_REST_Response([
                    'error' => 'Failed to read image file'
                ], 500);
            }

            // Validate settings
            $validation_errors = $this->validate_watermark_settings($params['settings']);
            if (!empty($validation_errors)) {
                return new WP_REST_Response([
                    'error' => 'Invalid settings',
                    'details' => $validation_errors
                ], 400);
            }

            // Generate the preview
            $result = $this->image_watermark->generate_preview_with_image($params['settings'], $image_data);

            if (!$result['success']) {
                return new WP_REST_Response([
                    'error' => $result['error']
                ], 500);
            }

            return new WP_REST_Response([
                'previewUrl' => $result['preview'],
                'originalUrl' => wp_get_attachment_url($media_id)
            ], 200);

        } catch (Exception $e) {
            error_log('Preview generation error: ' . $e->getMessage());
            return new WP_REST_Response([
                'error' => 'Failed to generate preview: ' . $e->getMessage()
            ], 500);
        }
    }

    public function restore_original($request)
{
    $image_id = $request['id'];
    error_log('Attempting to restore image with ID: ' . $image_id);
    
    $result = $this->image_watermark->restore_original($image_id);
    if (is_wp_error($result)) {
        error_log('Restore failed: ' . $result->get_error_message());
        return new WP_Error('uwm_restore_failed', $result->get_error_message(), ['status' => 400]);
    }
    
    error_log('Image restored successfully');
    return rest_ensure_response(['success' => true]);
}

    public function get_watermarked_images($request)
    {
        $images = $this->image_watermark->get_watermarked_images();
        return rest_ensure_response($images);
    }

    public function get_non_watermarked_images($request)
    {
        $args = [
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_WM_watermarked',
                    'compare' => 'NOT EXISTS'
                ]
            ]
        ];

        $query = new \WP_Query($args);
        $images = [];

        foreach ($query->posts as $image) {
            $full_image_url = wp_get_attachment_image_src($image->ID, 'full');
            $thumbnail_url = wp_get_attachment_image_src($image->ID, 'thumbnail');

            $images[] = [
                'id' => $image->ID,
                'title' => $image->post_title,
                'thumbnail' => $thumbnail_url ? $thumbnail_url[0] : '',
                'full' => $full_image_url ? $full_image_url[0] : '',
            ];
        }

        return rest_ensure_response($images);
    }

    public function bulk_watermark($request)
    {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return new WP_Error('insufficient_permissions', 'You do not have permission to perform this action.', ['status' => 403]);
        }

        // Get and validate parameters
        $params = $request->get_json_params();
        $image_ids = $params['imageIds'] ?? [];
        $watermark_options = $params['watermarkOptions'] ?? [];

        // Validate image IDs
        if (empty($image_ids) || !is_array($image_ids)) {
            return new WP_Error('invalid_image_ids', 'No valid image IDs provided.', ['status' => 400]);
        }

        // Validate watermark options
        $validation_result = $this->validate_watermark_options($watermark_options);
        if (is_wp_error($validation_result)) {
            return $validation_result;
        }

        // Start bulk watermarking process
        try {
            $result = $this->image_watermark->bulk_watermark($image_ids, $watermark_options);

            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }

            // Log the successful operation
            error_log('Bulk watermark operation completed. Success: ' . $result['success'] . ', Failed: ' . $result['failed']);

            return rest_ensure_response([
                'success' => true,
                'message' => sprintf(
                    'Bulk watermarking completed. %d images watermarked successfully, %d failed.',
                    $result['success'],
                    $result['failed']
                ),
                'data' => $result
            ]);

        } catch (Exception $e) {
            error_log('Error in bulk_watermark: ' . $e->getMessage());
            return new WP_Error(
                'uwm_bulk_watermark_failed',
                'An error occurred during bulk watermarking: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }
    public function watermark_all_images($request)
    {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return new WP_Error('insufficient_permissions', 'You do not have permission to perform this action.', ['status' => 403]);
        }

        // Get and validate parameters
        $params = $request->get_json_params();
        $watermark_options = $params['watermarkOptions'] ?? [];

        // Validate watermark options
        $validation_result = $this->validate_watermark_options($watermark_options);
        if (is_wp_error($validation_result)) {
            return $validation_result;
        }

        // Start watermarking process
        try {
            $result = $this->image_watermark->watermark_all_images($watermark_options);

            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }

            // Log the successful operation
            error_log('Watermark all images operation completed. Success: ' . $result['success'] . ', Failed: ' . $result['failed']);

            return rest_ensure_response([
                'success' => true,
                'message' => sprintf(
                    'Watermarking process completed. %d images watermarked successfully, %d failed.',
                    $result['success'],
                    $result['failed']
                ),
                'data' => $result
            ]);

        } catch (Exception $e) {
            error_log('Error in watermark_all_images: ' . $e->getMessage());
            return new WP_Error(
                'uwm_watermark_all_failed',
                'An error occurred while watermarking images: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }
    private function validate_watermark_options($options)
    {
        $required_fields = ['watermarkImage', 'position', 'opacity', 'size', 'rotation'];
        $missing_fields = [];

        foreach ($required_fields as $field) {
            if (!isset($options[$field])) {
                $missing_fields[] = $field;
            }
        }

        if (!empty($missing_fields)) {
            return new WP_Error(
                'invalid_watermark_options',
                'Missing required watermark options: ' . implode(', ', $missing_fields),
                ['status' => 400]
            );
        }

        // Validate specific fields
        if (!in_array($options['position'], ['top-left', 'top-right', 'bottom-left', 'bottom-right', 'center'])) {
            return new WP_Error('invalid_position', 'Invalid watermark position.', ['status' => 400]);
        }

        if (!is_numeric($options['opacity']) || $options['opacity'] < 0 || $options['opacity'] > 100) {
            return new WP_Error('invalid_opacity', 'Opacity must be a number between 0 and 100.', ['status' => 400]);
        }

        if (!is_numeric($options['size']) || $options['size'] < 1 || $options['size'] > 100) {
            return new WP_Error('invalid_size', 'Size must be a number between 1 and 100.', ['status' => 400]);
        }

        if (!is_numeric($options['rotation']) || $options['rotation'] < 0 || $options['rotation'] > 360) {
            return new WP_Error('invalid_rotation', 'Rotation must be a number between 0 and 360.', ['status' => 400]);
        }

        return true;
    }
}