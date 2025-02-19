<?php
namespace WatermarkManager;

/**
 * The admin-specific functionality of the plugin.
 *
 * @since      1.0.0
 * @package    WatermarkManager
 * @subpackage WatermarkManager/admin
 */
class WatermarkManager_Admin {

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        // Constructor code...
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {
        wp_enqueue_style( 'watermark-manager-admin', WM_PLUGIN_URL . 'admin/css/watermark-manager-admin.css', array(), WM_VERSION, 'all' );
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {
        wp_enqueue_script( 'watermark-manager-admin', WM_PLUGIN_URL . 'admin/js/watermark-manager-admin.js', array( 'jquery' ), WM_VERSION, false );
    }

    /**
     * Add plugin admin menu.
     *
     * @since    1.0.0
     */
    public function add_plugin_admin_menu() {
        add_menu_page(
            ' Watermark Manager Settings',
            'Watermark Manager',
            'manage_options',
            'watermark-manager',
            array( $this, 'display_plugin_setup_page' )
        );
    }

    /**
     * Render the settings page for this plugin.
     *
     * @since    1.0.0
     */
    public function display_plugin_setup_page() {
        include_once 'partials/watermark-manager-admin-display.php';
    }

    /**
     * Add settings action link to the plugins page.
     *
     * @since    1.0.0
     */
    public function add_action_links( $links ) {
        $settings_link = array(
            '<a href="' . admin_url( 'admin.php?page=watermark-manager' ) . '">' . __( 'Settings', 'watermark-manager' ) . '</a>',
        );
        return array_merge( $settings_link, $links );
    }

    /**
     * Validate options
     *
     * @param array $input
     * @return array
     */
    public function validate_options( $input ) {
        // Validation logic here
        return $input;
    }
    
}

