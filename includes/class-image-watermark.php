<?php

namespace WatermarkManager\Includes;

use WP_Error;

/**
 * Handles image watermarking functionality.
 *
 * @since      1.0.0
 * @package    WatermarkManager
 * @subpackage WatermarkManager/includes
 */
class ImageWatermark
{

    /**
     * Apply watermark to an image.
     *
     * @param string $image_path Path to the image file.
     * @param array  $options    Watermark options.
     * @return string|WP_Error Path to watermarked image or WP_Error on failure.
     */
    public function apply_watermark($input, array $options)
    {

        // Validate required options
        if (
            !isset(
            $options['watermarkImage'],
            $options['position'],
            $options['opacity'],
            $options['size'],
            $options['rotation']
        )
        ) {
            return false;
        }

        // Handle preview images
        if (is_resource($input) || $input instanceof \GdImage) {
            return $this->apply_watermark_to_resource($input, $options);
        }

        // Ensure we have a numeric ID
        $attachment_id = absint($input);
        if (!$attachment_id) {
            return false;
        }

        // Get attachment metadata
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (!$metadata) {

            return false;
        }

        // Get upload directory info
        $upload_dir = wp_upload_dir();
        $base_dir = dirname(path_join($upload_dir['basedir'], $metadata['file']));

        // Store original file paths
        $original_paths = [];

        // Add full size path
        $full_path = path_join($upload_dir['basedir'], $metadata['file']);
        if (file_exists($full_path)) {
            $original_paths['full'] = $full_path;
        }

        // Add thumbnail paths
        if (!empty($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $size => $size_data) {
                $sized_path = path_join($base_dir, $size_data['file']);
                if (file_exists($sized_path)) {
                    $original_paths[$size] = $sized_path;
                }
            }
        }

        // Backup original images before applying watermark
        $backup_result = $this->backup_original_image($attachment_id, $original_paths);
        if (!$backup_result) {

            return false;
        }
        // Process full size image first
        if (isset($original_paths['full'])) {

            if (!$this->apply_watermark_to_file($original_paths['full'], $options)) {
                return false;
            }
        }

        // Process all registered image sizes
        foreach ($original_paths as $size => $path) {
            if ($size !== 'full') {

                if (!$this->apply_watermark_to_file($path, $options)) {
                    return false;
                }
            }
        }

        // Update metadata to indicate the image has been watermarked
        update_post_meta($attachment_id, '_WM_watermarked', '1');

        return true;
    }

    /**
     * Apply image watermark to image.
     *
     * @param WP_Image_Editor $image   Image editor instance.
     * @param array           $options Watermark options.
     * @param array           $size    Image size.
     * @return true|WP_Error True on success, WP_Error on failure.
     */
    private function apply_watermark_to_file($file, array $options)
    {
        if (!file_exists($file)) {

            return false;
        }

        // Get image info
        $image_info = getimagesize($file);
        if ($image_info === false) {

            return false;
        }

        // Create image resource based on file type
        $image = null;
        switch ($image_info[2]) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($file);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($file);
                break;
            case IMAGETYPE_GIF:
                $image = imagecreatefromgif($file);
                break;
            case IMAGETYPE_WEBP:
                $image = imagecreatefromwebp($file);
                break;
            default:
                return false;
        }

        if (!$image) {
            return false;
        }

        // Enable alpha blending
        imagealphablending($image, true);
        imagesavealpha($image, true);

        // Apply watermark
        $watermarked_image = $this->apply_watermark_to_resource($image, $options);

        if ($watermarked_image === false) {
            imagedestroy($image);
            return false;
        }

        // Save the watermarked image
        $save_result = false;
        switch ($image_info[2]) {
            case IMAGETYPE_JPEG:
                $save_result = imagejpeg($watermarked_image, $file, 90);
                break;
            case IMAGETYPE_PNG:
                $save_result = imagepng($watermarked_image, $file, 9);
                break;
            case IMAGETYPE_GIF:
                $save_result = imagegif($watermarked_image, $file);
                break;
            case IMAGETYPE_WEBP:
                $save_result = imagewebp($watermarked_image, $file, 90);
                break;
        }

        imagedestroy($watermarked_image);

        if ($save_result === false) {

            return false;
        }

        return true;
    }

    /**
     * Apply watermark to an image resource
     *
     * @param resource|\GdImage $image Image resource
     * @param array $options Watermark options
     * @return resource|\GdImage|false Watermarked image resource or false on failure
     */
    private function apply_watermark_to_resource($image, array $options)
    {
        // Use the new path resolution method
        $watermark_path = $this->get_watermark_image_path($options['watermarkImage']);

        if (!$watermark_path || !file_exists($watermark_path)) {

            return false;
        }

        // Get image dimensions
        $width = imagesx($image);
        $height = imagesy($image);

        // Calculate watermark size
        $watermark_size = (int) (min($width, $height) * ($options['size'] / 100));

        $watermark_info = @getimagesize($watermark_path);
        if ($watermark_info === false) {

            return false;
        }

        // Create watermark resource
        switch ($watermark_info[2]) {
            case IMAGETYPE_PNG:
                $watermark = @imagecreatefrompng($watermark_path);
                break;
            case IMAGETYPE_JPEG:
                $watermark = @imagecreatefromjpeg($watermark_path);
                break;
            case IMAGETYPE_GIF:
                $watermark = @imagecreatefromgif($watermark_path);
                break;
            default:

                return false;
        }

        if ($watermark === false) {

            return false;
        }

        // Resize watermark
        $watermark_width = imagesx($watermark);
        $watermark_height = imagesy($watermark);
        $scale = $watermark_size / max($watermark_width, $watermark_height);
        $new_width = (int) ($watermark_width * $scale);
        $new_height = (int) ($watermark_height * $scale);

        $resized_watermark = @imagecreatetruecolor($new_width, $new_height);
        if ($resized_watermark === false) {

            imagedestroy($watermark);
            return false;
        }
        imagealphablending($resized_watermark, false);
        imagesavealpha($resized_watermark, true);

        $result = @imagecopyresampled(
            $resized_watermark,
            $watermark,
            0,
            0,
            0,
            0,
            $new_width,
            $new_height,
            $watermark_width,
            $watermark_height
        );

        if ($result === false) {

            imagedestroy($watermark);
            imagedestroy($resized_watermark);
            return false;
        }

        // Calculate position
        list($dest_x, $dest_y) = $this->calculate_position(
            $options['position'],
            $width,
            $height,
            $new_width,
            $new_height
        );

        // Apply rotation if needed
        if ($options['rotation'] != 0) {
            $resized_watermark = @imagerotate(
                $resized_watermark,
                -$options['rotation'],
                imagecolorallocatealpha($resized_watermark, 0, 0, 0, 127)
            );
            if ($resized_watermark === false) {

                imagedestroy($watermark);
                return false;
            }
        }

        // Merge watermark with original image
        $result = @imagecopymerge(
            $image,
            $resized_watermark,
            $dest_x,
            $dest_y,
            0,
            0,
            imagesx($resized_watermark),
            imagesy($resized_watermark),
            $options['opacity']
        );

        if ($result === false) {

            imagedestroy($watermark);
            imagedestroy($resized_watermark);
            return false;
        }

        imagedestroy($watermark);
        imagedestroy($resized_watermark);


        return $image;
    }

    private function clear_image_cache($attachment_id)
    {
        // Clear WordPress image cache
        clean_post_cache($attachment_id);
        clean_attachment_cache($attachment_id);

        // Force regeneration of thumbnails
        $metadata = wp_get_attachment_metadata($attachment_id);
        if ($metadata) {
            wp_update_attachment_metadata($attachment_id, $metadata);
        }

        // Clear any plugin caches (e.g., W3 Total Cache, WP Super Cache)
        if (function_exists('\w3tc_flush_post')) {
            \w3tc_flush_post($attachment_id);
        }
        if (function_exists('\wp_cache_post_change')) {
            \wp_cache_post_change($attachment_id);
        }
    }

    /**
     * Calculate position for watermark placement.
     *
     * @param string $position Position option (e.g., 'top-left', 'center', 'bottom-right')
     * @param int $img_width Width of the main image
     * @param int $img_height Height of the main image
     * @param int $mark_width Width of the watermark
     * @param int $mark_height Height of the watermark
     * @return array<int, int> Array containing [x, y] coordinates
     */
    public function calculate_position(string $position, int $img_width, int $img_height, int $mark_width, int $mark_height): array
    {
        switch ($position) {
            case 'top-left':
                return [10, 10];

            case 'top-right':
                return [$img_width - $mark_width - 10, 10];

            case 'bottom-left':
                return [10, $img_height - $mark_height - 10];

            case 'bottom-right':
                return [$img_width - $mark_width - 10, $img_height - $mark_height - 10];

            case 'center':
                return [
                    (int) (($img_width - $mark_width) / 2),
                    (int) (($img_height - $mark_height) / 2)
                ];

            default:
                return [10, 10];
        }
    }

    /**
     * Resolve and cache watermark image path
     *
     * @param string $url_or_path URL or path to the watermark image
     * @return string|false Resolved absolute path or false if not found
     */
    public function get_watermark_image_path($url_or_path)
    {
        // Generate a unique cache key based on the input
        $cache_key = 'WM_watermark_path_' . md5($url_or_path);

        // Try to get cached path
        $cached_path = get_transient($cache_key);
        if ($cached_path !== false) {
            // Normalize the cached path
            $cached_path = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $cached_path);

            if (file_exists($cached_path)) {

                return $cached_path;
            } else {

                delete_transient($cache_key);
            }
        }

        // If no cached path, resolve the path
        $resolved_path = $this->resolve_watermark_path($url_or_path);

        // Cache the resolved path if valid
        if ($resolved_path && file_exists($resolved_path)) {

            set_transient($cache_key, $resolved_path, DAY_IN_SECONDS);
            return $resolved_path;
        }


        return false;
    }

    /**
     * Creates backup of original image files with improved metadata handling
     * 
     * @param int $attachment_id The attachment ID
     * @param array $original_paths Array of original file paths
     * @return bool True if backup successful, false otherwise
     */
    private function backup_original_image($attachment_id, $original_paths)
    {
        // Initialize WP_Filesystem
        global $wp_filesystem;
        if (!is_object($wp_filesystem)) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            WP_Filesystem();
        }

        $upload_dir = wp_upload_dir();
        $backup_base_dir = path_join($upload_dir['basedir'], 'WM_backups');
        $backup_dir = path_join($backup_base_dir, (string) $attachment_id);

        // Normalize backup directory path
        $backup_dir = wp_normalize_path($backup_dir);

        // Create backup directory with proper permissions
        if (!$wp_filesystem->exists($backup_base_dir)) {
            if (!wp_mkdir_p($backup_base_dir)) {
                return false;
            }
            $wp_filesystem->chmod($backup_base_dir, FS_CHMOD_DIR);
        }

        if (!$wp_filesystem->exists($backup_dir)) {
            if (!wp_mkdir_p($backup_dir)) {
                return false;
            }
            $wp_filesystem->chmod($backup_dir, FS_CHMOD_DIR);
        }

        $backup_paths = [];
        $all_copies_successful = true;

        // Get existing metadata first
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (!is_array($metadata)) {
            $metadata = array();
        }

        foreach ($original_paths as $size => $original_path) {
            // Sanitize filename and create backup path
            $safe_filename = sanitize_file_name(basename($original_path));
            $backup_path = path_join($backup_dir, $safe_filename);

            // Normalize backup path
            $backup_path = wp_normalize_path($backup_path);

            // Ensure original file exists
            if (!$wp_filesystem->exists($original_path)) {
                $all_copies_successful = false;
                break;
            }

            // Create backup with proper permissions
            if ($wp_filesystem->copy($original_path, $backup_path, true)) {
                $wp_filesystem->chmod($backup_path, FS_CHMOD_FILE);
                $backup_paths[$size] = $backup_path;
            } else {
                $all_copies_successful = false;
                break;
            }
        }

        if (!empty($backup_paths) && $all_copies_successful) {
            // Store backup paths in metadata
            $metadata['WM_backups'] = $backup_paths;

            // Update metadata with error checking
            $update_result = wp_update_attachment_metadata($attachment_id, $metadata);
            if ($update_result === false) {
                // Try alternative update method
                $direct_update = update_post_meta($attachment_id, '_wp_attachment_metadata', $metadata);
                if ($direct_update === false) {
                    // Clean up backup files
                    foreach ($backup_paths as $backup_path) {
                        if ($wp_filesystem->exists($backup_path)) {
                            wp_delete_file($backup_path);
                        }
                    }
                    if ($wp_filesystem->exists($backup_dir) && count($wp_filesystem->dirlist($backup_dir)) <= 2) {
                        $wp_filesystem->rmdir($backup_dir);
                    }
                    return false;
                } else {
                    return true;
                }
            }
            return true;
        }

        // Clean up on failure
        foreach ($backup_paths as $backup_path) {
            if ($wp_filesystem->exists($backup_path)) {
                wp_delete_file($backup_path);
            }
        }

        // Only remove directory if empty
        if ($wp_filesystem->exists($backup_dir) && count($wp_filesystem->dirlist($backup_dir)) <= 2) {
            $wp_filesystem->rmdir($backup_dir);
        }

        return false;
    }

    /**
     * Restore original image from backup with enhanced error handling
     * 
     * @param int $attachment_id The attachment ID
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function restore_original($attachment_id)
    {
        // Initialize WP_Filesystem
        global $wp_filesystem;
        if (!is_object($wp_filesystem)) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            WP_Filesystem();
        }

        // Verify attachment exists
        if (!get_post($attachment_id)) {
            return new WP_Error('invalid_attachment', 'Invalid attachment ID');
        }

        $metadata = wp_get_attachment_metadata($attachment_id);
        if (empty($metadata) || empty($metadata['WM_backups'])) {
            return new WP_Error('no_backup', 'No backup found for this image');
        }

        $upload_dir = wp_upload_dir();
        $backup_paths = $metadata['WM_backups'];
        $restored_sizes = [];
        $failed_sizes = [];

        foreach ($backup_paths as $size => $backup_path) {
            // Normalize the backup path
            $backup_path = wp_normalize_path($backup_path);

            // Ensure backup exists
            if (!$wp_filesystem->exists($backup_path)) {
                $failed_sizes[] = $size;
                continue;
            }

            // Determine destination path
            $dest_path = '';
            if ($size === 'full') {
                $dest_path = get_attached_file($attachment_id);
            } else if (isset($metadata['sizes'][$size]['file'])) {
                $base_dir = dirname(path_join($upload_dir['basedir'], $metadata['file']));
                $dest_path = path_join($base_dir, $metadata['sizes'][$size]['file']);
            }

            if (empty($dest_path)) {
                $failed_sizes[] = $size;
                continue;
            }

            // Normalize destination path
            $dest_path = wp_normalize_path($dest_path);

            // Create destination directory if it doesn't exist
            $dest_dir = dirname($dest_path);
            if (!$wp_filesystem->exists($dest_dir)) {
                if (!wp_mkdir_p($dest_dir)) {
                    $failed_sizes[] = $size;
                    continue;
                }
            }

            // Restore the file
            if ($wp_filesystem->copy($backup_path, $dest_path, true)) {
                $wp_filesystem->chmod($dest_path, FS_CHMOD_FILE);
                $restored_sizes[] = $size;
            } else {
                $failed_sizes[] = $size;
            }
        }

        // Update metadata based on results
        if (empty($failed_sizes)) {
            // Remove backup paths and watermark flag from metadata
            unset($metadata['WM_backups']);
            delete_post_meta($attachment_id, '_WM_watermarked');
            wp_update_attachment_metadata($attachment_id, $metadata);

            // Clear image cache
            $this->clear_image_cache($attachment_id);

            // Clean up backup files
            $this->cleanup_backup_files($attachment_id);

            return true;
        }

        // Return detailed error information
        return new WP_Error(
            'restore_partial_failure',
            'Some image sizes failed to restore',
            [
                'restored_sizes' => $restored_sizes,
                'failed_sizes' => $failed_sizes
            ]
        );
    }

    /**
     * Clean up backup files after successful restore
     * 
     * @param int $attachment_id The attachment ID
     */
    private function cleanup_backup_files($attachment_id)
    {
        // Initialize WP_Filesystem
        global $wp_filesystem;
        if (!is_object($wp_filesystem)) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            WP_Filesystem();
        }

        $upload_dir = wp_upload_dir();
        $backup_dir = path_join($upload_dir['basedir'], 'WM_backups', (string) $attachment_id);

        if ($wp_filesystem->exists($backup_dir)) {
            $files = $wp_filesystem->dirlist($backup_dir);
            foreach ($files as $file) {
                if ($file['name'] !== '.' && $file['name'] !== '..') {
                    $file_path = path_join($backup_dir, $file['name']);
                    if ($file['type'] === 'f') {
                        wp_delete_file($file_path);
                    }
                }
            }
            // Only remove directory if empty
            if (count($wp_filesystem->dirlist($backup_dir)) <= 2) { // . and ..
                $wp_filesystem->rmdir($backup_dir);
            }
        }
    }

    /**
     * Get all watermarked images
     * 
     * @return array Array of watermarked image data
     */
    public function get_watermarked_images()
    {
        $args = [
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_WM_watermarked',
                    'value' => '1',
                    'compare' => '='
                ]
            ]
        ];

        $query = new \WP_Query($args);
        $images = [];

        foreach ($query->posts as $image) {
            $full_image_url = wp_get_attachment_image_src($image->ID, 'full');
            $thumbnail_url = wp_get_attachment_image_src($image->ID, 'thumbnail');
            $metadata = wp_get_attachment_metadata($image->ID);

            $images[] = [
                'id' => $image->ID,
                'title' => $image->post_title,
                'thumbnail' => $thumbnail_url ? $thumbnail_url[0] : '',
                'full' => $full_image_url ? $full_image_url[0] : '',
                'has_backup' => isset($metadata['WM_backups']),
                'sizes' => isset($metadata['sizes']) ? array_keys($metadata['sizes']) : []
            ];
        }

        return $images;
    }

    /**
     * Bulk watermark images
     * 
     * @param array $attachment_ids Array of attachment IDs
     * @return array Results of the bulk watermarking operation
     */
    public function bulk_watermark($attachment_ids, array $watermark_options)
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];

        if (empty($watermark_options)) {
            $results['failed'] = count($attachment_ids);
            $results['errors'][] = 'No watermark options provided';
            return $results;
        }

        foreach ($attachment_ids as $id) {
            if (!wp_attachment_is_image($id)) {
                $results['failed']++;
                $results['errors'][] = "ID {$id} is not a valid image";
                continue;
            }

            if (get_post_meta($id, '_WM_watermarked', true) === '1') {
                $results['failed']++;
                $results['errors'][] = "Image {$id} is already watermarked";
                continue;
            }

            try {
                if ($this->apply_watermark($id, $watermark_options)) {
                    update_post_meta($id, '_WM_watermarked', '1');
                    $results['success']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = "Failed to watermark image {$id}";
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Error processing image {$id}: " . $e->getMessage();
            }
        }

        return $results;
    }

    public function watermark_all_images($watermark_options = [])
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
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($query->posts as $image) {
            try {
                if ($this->apply_watermark($image->ID, $watermark_options)) {
                    update_post_meta($image->ID, '_WM_watermarked', '1');
                    $results['success']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = "Failed to watermark image {$image->ID}";
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Error processing image {$image->ID}: " . $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Generates a preview with watermark
     * 
     * @param array $options Watermark options
     * @param string|null $image_data Base64 or binary image data
     * @return array Response with preview data or error
     */
    public function generate_preview_with_image($options, $image_data = null)
    {
        try {
            // Get the preview image
            $preview_image = $this->get_preview_image_resource($image_data, $options);
            if (!$preview_image || !($preview_image instanceof \GdImage)) {
                throw new \Exception('Failed to create preview image');
            }

            // Apply watermark
            $watermarked_image = $this->apply_watermark($preview_image, $options);
            if (!$watermarked_image || !($watermarked_image instanceof \GdImage)) {
                if ($preview_image instanceof \GdImage) {
                    imagedestroy($preview_image);
                }
                throw new \Exception('Failed to apply watermark');
            }

            // Convert to base64
            ob_start();
            imagepng($watermarked_image);
            $image_data = ob_get_clean();

            // Clean up resources
            if ($watermarked_image instanceof \GdImage) {
                imagedestroy($watermarked_image);
            }
            if ($preview_image instanceof \GdImage) {
                imagedestroy($preview_image);
            }

            if ($image_data === false) {
                throw new \Exception('Failed to generate image data');
            }

            $base64 = base64_encode($image_data);
            return [
                'success' => true,
                'preview' => 'data:image/png;base64,' . $base64
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Creates an image resource from various sources
     * 
     * @param string|null $image_data Binary image data
     * @param array $options Options including preview_image_id
     * @return \GdImage|false
     */
    private function get_preview_image_resource($image_data = null, $options = [])
    {
        // Initialize WP_Filesystem for file checks
        global $wp_filesystem;
        if (!is_object($wp_filesystem)) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            WP_Filesystem();
        }

        // If image data is provided, create from it
        if ($image_data) {
            $image = @imagecreatefromstring($image_data);
            if ($image instanceof \GdImage) {
                return $image;
            }
        }

        // Check for preview image ID
        $preview_image_id = isset($options['preview_image_id']) ? intval($options['preview_image_id']) : 0;
        if ($preview_image_id > 0) {
            $image_path = get_attached_file($preview_image_id);
            if ($image_path && $wp_filesystem->exists($image_path)) {
                $image = $this->create_image_resource($image_path);
                if ($image instanceof \GdImage) {
                    return $image;
                }
            }
        }

        // Fallback to most recent media library image
        $args = [
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'posts_per_page' => 1,
            'orderby' => 'date',
            'order' => 'DESC'
        ];

        $media_query = get_posts($args);
        if (!empty($media_query)) {
            $image_path = get_attached_file($media_query[0]->ID);
            if ($image_path && $wp_filesystem->exists($image_path)) {
                $image = $this->create_image_resource($image_path);
                if ($image instanceof \GdImage) {
                    return $image;
                }
            }
        }

        // Create blank canvas as last resort
        $width = 800;
        $height = 600;
        $image = imagecreatetruecolor($width, $height);
        if (!$image) {
            return false;
        }

        $bg_color = imagecolorallocate($image, 255, 255, 255);
        if ($bg_color === false) {
            imagedestroy($image);
            return false;
        }

        if (!imagefill($image, 0, 0, $bg_color)) {
            imagedestroy($image);
            return false;
        }

        return $image;
    }

    /**
     * Creates an image resource from a file path
     * 
     * @param string $image_path Path to the image file
     * @return \GdImage|false
     */
    private function create_image_resource($image_path)
    {
        $image_info = @getimagesize($image_path);
        if ($image_info === false) {
            return false;
        }

        switch ($image_info[2]) {
            case IMAGETYPE_JPEG:
                return @imagecreatefromjpeg($image_path);
            case IMAGETYPE_PNG:
                return @imagecreatefrompng($image_path);
            case IMAGETYPE_GIF:
                return @imagecreatefromgif($image_path);
            default:
                return false;
        }
    }

    private function url_to_path($url)
    {
        // Remove the protocol and domain
        $site_url = get_site_url();
        $path = str_replace($site_url, '', $url);

        // Convert to absolute path
        $path = ABSPATH . ltrim($path, '/');

        return $path;
    }

    /**
     * Resolve watermark path from various input formats
     *
     * @param string $url_or_path URL or path to the watermark image
     * @return string|false Resolved absolute path or false if not found
     */
    private function resolve_watermark_path($url_or_path)
    {
        // Initialize WP_Filesystem
        global $wp_filesystem;
        if (!is_object($wp_filesystem)) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            WP_Filesystem();
        }

        // If it's already an absolute server path, return it
        if ($wp_filesystem->exists($url_or_path)) {
            return $url_or_path;
        }

        // Get WordPress site and upload directory
        $upload_dir = wp_upload_dir();
        $uploads_dir = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $upload_dir['basedir']);
        $site_url = get_site_url();

        // Convert URL to a path
        $resolved_path = $this->url_to_path($url_or_path);
        if ($wp_filesystem->exists($resolved_path)) {
            return $resolved_path;
        }

        // Extract path relative to /wp-content/uploads/
        if (preg_match('#/wp-content/uploads/(.*)$#', $url_or_path, $matches)) {
            $relative_path = $matches[1];
            $full_path = $uploads_dir . DIRECTORY_SEPARATOR . $relative_path;
            $full_path = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $full_path);
        }

        // Fallback: Try resolving by only the filename inside uploads
        $basename_path = path_join($uploads_dir, basename($url_or_path));
        if ($wp_filesystem->exists($basename_path)) {
            return $basename_path;
        }

        // Final fallback to a default watermark image
        $default_watermark_path = path_join($uploads_dir, 'default-watermark.png');
        if ($wp_filesystem->exists($default_watermark_path)) {
            return $default_watermark_path;
        }

        return false;
    }

    /**
     * Clear cached watermark paths when needed
     *
     * @param string|null $url_or_path Optional specific path to clear
     */
    public function clear_watermark_path_cache($url_or_path = null)
    {
        if ($url_or_path) {
            // Clear specific cache
            $cache_key = 'WM_watermark_path_' . md5($url_or_path);
            delete_transient($cache_key);
        } else {
            // Use wp_cache functions instead of direct database calls
            $transients = wp_cache_get('WM_watermark_paths', 'watermark_plugin');
            if ($transients && is_array($transients)) {
                foreach ($transients as $transient) {
                    delete_transient($transient);
                }
            }

            // Clear the cache group
            wp_cache_delete('WM_watermark_paths', 'watermark_plugin');
        }
    }

    /**
     * Clean up watermark-related data for an attachment
     *
     * @param int $attachment_id The ID of the attachment
     */
    public function cleanup(int $attachment_id): void
    {
        // Initialize WP_Filesystem
        global $wp_filesystem;
        if (!is_object($wp_filesystem)) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            WP_Filesystem();
        }

        // Remove watermark-related metadata
        delete_post_meta($attachment_id, '_WM_watermarked');

        // If you're storing backup images, you might want to remove them here
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (isset($metadata['WM_backup'])) {
            $backup_path = $metadata['WM_backup'];
            if ($wp_filesystem->exists($backup_path)) {
                wp_delete_file($backup_path);
            }
            unset($metadata['WM_backup']);
            wp_update_attachment_metadata($attachment_id, $metadata);
        }

        // Clear any cached watermark paths
        $this->clear_watermark_path_cache();
    }
}