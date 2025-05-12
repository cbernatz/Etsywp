<?php
/**
 * Plugin Name: Etsy
 * Plugin URI: https://plugindevsite.local
 * Description: Websites for Etsy sellers
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: etsy-shop-inventory
 * License: GPL v2 or later
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ETSY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ETSY_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Initialize the plugin
 */
function etsy_plugin_init() {
    // Include post types
    require_once ETSY_PLUGIN_DIR . 'includes/post-types.php';
    
    // Include admin files if in admin area
    if (is_admin()) {
        require_once ETSY_PLUGIN_DIR . 'admin/main.php';
    }
}
add_action('init', 'etsy_plugin_init');

/**
 * Register admin menu
 */
function etsy_register_admin_menu() {
    // Add a single Etsy menu item
    add_menu_page(
        'Etsy',
        'Etsy',
        'manage_options',
        'etsy-shop',
        'etsy_admin_page',
        'dashicons-store',
        30
    );
}
add_action('admin_menu', 'etsy_register_admin_menu'); 