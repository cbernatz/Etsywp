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

    // Include shortcodes
    require_once ETSY_PLUGIN_DIR . 'includes/shortcodes.php';
    
    // Include admin files if in admin area
    if (is_admin()) {
        require_once ETSY_PLUGIN_DIR . 'admin/main.php';
    }
}
add_action('init', 'etsy_plugin_init');

/**
 * Register styles and scripts
 */
function etsy_enqueue_scripts() {
    // Enqueue frontend styles
    wp_enqueue_style(
        'etsywp-frontend-styles',
        ETSY_PLUGIN_URL . 'assets/css/frontend-styles.css',
        array(),
        filemtime(ETSY_PLUGIN_DIR . 'assets/css/frontend-styles.css')
    );
}
add_action('wp_enqueue_scripts', 'etsy_enqueue_scripts');

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

/**
 * Create 'Best Sellers' page on plugin activation
 */
function etsy_create_best_sellers_page() {
    $page_title = 'Best Sellers';
    $page_content = '[etsywp_best_sellers]'; // Placeholder for shortcode or content
    $page_check = get_page_by_title($page_title);

    if (!isset($page_check->ID)) {
        $new_page = array(
            'post_title'    => $page_title,
            'post_content'  => $page_content,
            'post_status'   => 'publish',
            'post_type'     => 'page',
        );
        wp_insert_post($new_page);
    }
}

function etsy_create_shop_all_page() {
    $page_title = 'Shop All';
    $page_content = '[etsywp_shop_all]'; // Placeholder for shortcode or content
    $page_check = get_page_by_title($page_title);
    
    if (!isset($page_check->ID)) {
        $new_page = array(
            'post_title'    => $page_title,
            'post_content'  => $page_content,
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'meta_input'    => [
                '_hide_from_nav' => true // custom meta for theme logic
            ],
        );
        wp_insert_post($new_page);
    }
}
register_activation_hook(__FILE__, 'etsy_create_best_sellers_page'); 
register_activation_hook(__FILE__, 'etsy_create_shop_all_page');

add_filter('wp_get_nav_menu_items', function($items) {
    foreach ($items as $key => $item) {
        if (get_post_meta($item->object_id, '_hide_from_nav', true)) {
            unset($items[$key]);
        }
    }
    return $items;
});
