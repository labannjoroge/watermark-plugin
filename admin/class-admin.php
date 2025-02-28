<?php

namespace WatermarkManager\Admin;

use WatermarkManager\Includes\Database;
use WatermarkManager\Includes\ImageWatermark;

class Admin
{
    private $plugin_name;
    private $version;
    private $image_watermark;

    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->image_watermark = new ImageWatermark();

        add_action('admin_enqueue_scripts', [$this, 'enqueue_styles']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_menu', [$this, 'add_plugin_admin_menu']);
        add_filter('plugin_action_links_' . WM_PLUGIN_BASENAME, [$this, 'add_action_links']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    public function enqueue_styles()
    {
        wp_enqueue_style($this->plugin_name, WM_PLUGIN_URL . 'admin/build/css/app.css', [], $this->version, 'all');
    }

    public function enqueue_scripts($hook)
    {
        if (
            'toplevel_page_watermark-manager' !== $hook &&
            'watermark-manager_page_watermark-manager-images' !== $hook &&
            'watermark-manager_page_watermark-manager-bulk' !== $hook
        ) {
            return;
        }

        add_action('admin_enqueue_scripts', function() {
            wp_add_inline_script('jquery', '
              console.log("React version:", React.version);
              console.log("ReactDOM version:", ReactDOM.version);
            ');
          });

        wp_deregister_script('react');
        wp_deregister_script('react-dom');

        // Load React from plugin bundled files instead of external CDN
        wp_enqueue_script('react', WM_PLUGIN_URL . 'admin/build/js/react.min.js', [], '18.2.0', true);
        wp_enqueue_script('react-dom', WM_PLUGIN_URL . 'admin/build/js/react-dom.min.js', ['react'], '18.2.0', true);

        // Enqueue your app
        wp_enqueue_script(
            $this->plugin_name,
            WM_PLUGIN_URL . 'admin/build/js/app.js',
            ['react', 'react-dom'],
            $this->version,
            true
        );

        wp_localize_script($this->plugin_name, 'WMData', [
            'restUrl' => esc_url_raw(rest_url('WM/v1/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'pluginUrl' => WM_PLUGIN_URL,
            'currentPage' => $hook
        ]);
    }

    public function display_plugin_admin_page()
    {
        // Properly unslash and sanitize GET parameters
        $current_page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : 'watermark-manager';
        $sub_page = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'settings';

        // Add nonce for any form actions
        $nonce = wp_create_nonce('watermark_manager_page_action');
        
        echo '<div id="WM-admin-app" data-page="' . esc_attr($current_page) . '" data-tab="' . esc_attr($sub_page) . '" data-nonce="' . esc_attr($nonce) . '"></div>';
    }

    public function add_plugin_admin_menu()
    {
        add_menu_page(
            'Watermark Manager',
            'Watermark Manager',
            'manage_options',
            'watermark-manager',
            [$this, 'display_plugin_admin_page'],
            'dashicons-format-image',
            81
        );

        // Add proper nonce to these URLs
        $nonce = wp_create_nonce('watermark_manager_menu_action');
        
        add_submenu_page(
            'watermark-manager',
            'Bulk Watermark',
            'Bulk Watermark',
            'manage_options',
            'watermark-manager&tab=bulk&_wpnonce=' . $nonce,
            [$this, 'display_plugin_admin_page']
        );

        add_submenu_page(
            'watermark-manager',
            'Manage Images',
            'Manage Images',
            'manage_options',
            'watermark-manager&tab=manage&_wpnonce=' . $nonce,
            [$this, 'display_plugin_admin_page']
        );
    }

    public function add_action_links($links)
    {
        $nonce = wp_create_nonce('watermark_manager_action_link');
        $settings_link = [
            '<a href="' . admin_url('admin.php?page=watermark-manager&_wpnonce=' . $nonce) . '">' . __('Settings', 'watermark-manager') . '</a>',
        ];
        return array_merge($settings_link, $links);
    }

    public function register_settings()
    {
        register_setting(
            'WM_options', 
            'WM_options', 
            [
                'sanitize_callback' => [$this, 'validate_options'],
                'default' => [
                    'watermark_image' => '',
                    'watermark_position' => 'bottom-right',
                    'watermark_opacity' => 50,
                    'watermark_size' => 50,
                    'watermark_rotation' => 0,
                    'auto_watermark' => false,
                    'backup_originals' => false,
                ]
            ]
        );
    }

    public function validate_options($input)
    {
        $valid = [];

        // Validate and sanitize each option with improved validation
        $valid['watermark_image'] = isset($input['watermark_image']) ? esc_url_raw($input['watermark_image']) : '';
        
        // Position - restrict to allowed values
        $allowed_positions = ['top-left', 'top-center', 'top-right', 'middle-left', 'middle-center', 
                             'middle-right', 'bottom-left', 'bottom-center', 'bottom-right'];
        $valid['watermark_position'] = isset($input['watermark_position']) && 
                                      in_array($input['watermark_position'], $allowed_positions, true) ? 
                                      $input['watermark_position'] : 'bottom-right';
        
        // Opacity - ensure it's between 0 and 100
        $opacity = isset($input['watermark_opacity']) ? intval($input['watermark_opacity']) : 50;
        $valid['watermark_opacity'] = max(0, min(100, $opacity));
        
        // Size - ensure it's between 1 and 100
        $size = isset($input['watermark_size']) ? intval($input['watermark_size']) : 50;
        $valid['watermark_size'] = max(1, min(100, $size));
        
        // Rotation - ensure it's between 0 and 359
        $rotation = isset($input['watermark_rotation']) ? intval($input['watermark_rotation']) : 0;
        $valid['watermark_rotation'] = $rotation % 360;
        
        // Boolean values
        $valid['auto_watermark'] = isset($input['auto_watermark']) ? (bool) $input['auto_watermark'] : false;
        $valid['backup_originals'] = isset($input['backup_originals']) ? (bool) $input['backup_originals'] : false;

        return $valid;
    }

    public function register_rest_routes()
    {
        $rest_controller = new RestController();
        $rest_controller->register_routes();
    }
}