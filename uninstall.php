<?php

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'includes/class-database.php';

// Drop the custom table
WatermarkManager\Includes\Database::drop_table();

// Remove any options from wp_options table
delete_option('WM_image_watermark_options');
delete_option('WM_content_watermark_options');

