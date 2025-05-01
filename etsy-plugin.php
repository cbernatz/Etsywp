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

/**
 * Add shortcode for displaying "Hello world!"
 */
function etsy_inventory_shortcode($atts) {
    return '<div class="etsy-inventory-container">Hello world!</div>';
}
add_shortcode('etsy_inventory', 'etsy_inventory_shortcode'); 