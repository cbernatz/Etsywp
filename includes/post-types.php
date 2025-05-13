<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register custom post types for Etsy shop and listings
 */
function etsy_register_post_types() {
    // Register Etsy Shop post type
    register_post_type('etsy_shop', array(
        'labels' => array(
            'name' => 'Etsy Shops',
            'singular_name' => 'Etsy Shop',
        ),
        'public' => false,
        'show_ui' => false,
        'show_in_menu' => false,
        'supports' => array('title', 'custom-fields'),
        'has_archive' => false,
        'rewrite' => false,
        'query_var' => false,
        'capability_type' => 'post',
        'capabilities' => array(
            'create_posts' => 'do_not_allow',
        ),
        'map_meta_cap' => true,
    ));

    // Register Etsy Listing post type
    register_post_type('etsy_listing', array(
        'labels' => array(
            'name' => 'Etsy Listings',
            'singular_name' => 'Etsy Listing',
            'all_items' => 'All Etsy Listings',
            'add_new' => 'Add New Listing',
            'add_new_item' => 'Add New Etsy Listing',
            'edit_item' => 'Edit Etsy Listing',
            'view_item' => 'View Etsy Listing',
            'search_items' => 'Search Etsy Listings',
        ),
        'public' => true,
        'show_ui' => true,
        'show_in_menu' => 'etsy-shop',
        'supports' => array('title', 'editor', 'thumbnail', 'custom-fields'),
        'has_archive' => true,
        'rewrite' => array('slug' => 'etsy-products'),
        'menu_icon' => 'dashicons-store',
    ));
}
add_action('init', 'etsy_register_post_types');

/**
 * Save Etsy shop data as a custom post type
 * 
 * @param array $shop_data Shop data to save
 * @return int|WP_Error Post ID on success, WP_Error on failure
 */
function etsy_save_shop_data($shop_data) {
    // Check if we already have a shop saved
    $existing_shops = get_posts(array(
        'post_type' => 'etsy_shop',
        'posts_per_page' => 1,
        'post_status' => 'publish',
    ));

    $post_data = array(
        'post_title' => !empty($shop_data['shop_name']) ? $shop_data['shop_name'] : 'Etsy Shop',
        'post_status' => 'publish',
        'post_type' => 'etsy_shop',
    );

    // Update existing or create new
    if (!empty($existing_shops)) {
        $post_data['ID'] = $existing_shops[0]->ID;
        $shop_id = wp_update_post($post_data);
    } else {
        $shop_id = wp_insert_post($post_data);
    }

    if (is_wp_error($shop_id)) {
        return $shop_id;
    }

    // Save shop meta data
    if (isset($shop_data['shop_id'])) {
        update_post_meta($shop_id, 'etsy_shop_id', $shop_data['shop_id']);
    }
    
    if (isset($shop_data['shop_name'])) {
        update_post_meta($shop_id, 'etsy_shop_name', $shop_data['shop_name']);
    }
    
    if (isset($shop_data['shop_url'])) {
        update_post_meta($shop_id, 'etsy_shop_url', $shop_data['shop_url']);
    }
    
    if (isset($shop_data['api_key'])) {
        update_post_meta($shop_id, 'etsy_api_key', $shop_data['api_key']);
    }
    
    if (isset($shop_data['create_date'])) {
        update_post_meta($shop_id, 'etsy_create_date', $shop_data['create_date']);
    }
    
    if (isset($shop_data['icon_url_fullxfull'])) {
        update_post_meta($shop_id, 'etsy_icon_url', $shop_data['icon_url_fullxfull']);
    }

    return $shop_id;
}

/**
 * Save Etsy listing data as a custom post type
 * 
 * @param array $listing Listing data to save
 * @return int|WP_Error Post ID on success, WP_Error on failure
 */
function etsy_save_listing($listing) {
    // Check if listing already exists
    $existing_listing = get_posts(array(
        'post_type' => 'etsy_listing',
        'meta_query' => array(
            array(
                'key' => 'etsy_listing_id',
                'value' => $listing['listing_id'],
            ),
        ),
        'posts_per_page' => 1,
        'post_status' => 'any',
    ));

    $post_data = array(
        'post_title' => $listing['title'],
        'post_content' => $listing['description'],
        'post_status' => ($listing['state'] === 'active') ? 'publish' : 'draft',
        'post_type' => 'etsy_listing',
    );

    // Update existing or create new
    if (!empty($existing_listing)) {
        $post_data['ID'] = $existing_listing[0]->ID;
        $listing_id = wp_update_post($post_data);
    } else {
        $listing_id = wp_insert_post($post_data);
    }

    if (is_wp_error($listing_id)) {
        return $listing_id;
    }

    // Save listing meta data
    update_post_meta($listing_id, 'etsy_listing_id', $listing['listing_id']);
    update_post_meta($listing_id, 'etsy_shop_id', $listing['shop_id']);
    update_post_meta($listing_id, 'etsy_state', $listing['state']);
    update_post_meta($listing_id, 'etsy_url', $listing['url']);
    
    // Save number of favorites if available
    if (isset($listing['num_favorers'])) {
        update_post_meta($listing_id, 'etsy_num_favorers', $listing['num_favorers']);
    }
    
    // Save price
    if (isset($listing['price'])) {
        $price_amount = $listing['price']['amount'] / $listing['price']['divisor'];
        update_post_meta($listing_id, 'etsy_price', $price_amount);
        update_post_meta($listing_id, 'etsy_currency_code', $listing['price']['currency_code']);
    }
    
    // Save main image as featured image
    if (!empty($listing['images']) && !empty($listing['images'][0]['url_fullxfull'])) {
        etsy_set_featured_image_from_url($listing_id, $listing['images'][0]['url_fullxfull'], $listing['title']);
        
        // Save all images as meta
        $images = array();
        foreach ($listing['images'] as $image) {
            $images[] = array(
                'url_fullxfull' => $image['url_fullxfull'],
                'url_170x135' => $image['url_170x135'],
            );
        }
        update_post_meta($listing_id, 'etsy_images', $images);
    }

    return $listing_id;
}

/**
 * Set featured image from URL
 * 
 * @param int $post_id Post ID
 * @param string $image_url Image URL
 * @param string $title Image title
 * @return int|false Attachment ID on success, false on failure
 */
function etsy_set_featured_image_from_url($post_id, $image_url, $title) {
    // Check if this image has already been uploaded
    $existing_attachment = get_posts(array(
        'post_type' => 'attachment',
        'meta_query' => array(
            array(
                'key' => 'etsy_image_url',
                'value' => $image_url,
            ),
        ),
        'posts_per_page' => 1,
    ));

    if (!empty($existing_attachment)) {
        set_post_thumbnail($post_id, $existing_attachment[0]->ID);
        return $existing_attachment[0]->ID;
    }

    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    // Download file to temp location
    $tmp = download_url($image_url);
    if (is_wp_error($tmp)) {
        return false;
    }

    // Set variables for storage
    $file_array = array();
    $file_array['name'] = sanitize_file_name(basename($image_url));
    $file_array['tmp_name'] = $tmp;

    // Do the validation and storage
    $attachment_id = media_handle_sideload($file_array, $post_id, $title);

    // Remove the temp file
    if (file_exists($tmp)) {
        @unlink($tmp);
    }

    if (is_wp_error($attachment_id)) {
        return false;
    }

    // Save original image URL as meta to avoid duplicate uploads
    update_post_meta($attachment_id, 'etsy_image_url', $image_url);
    
    // Set as featured image
    set_post_thumbnail($post_id, $attachment_id);

    return $attachment_id;
}

/**
 * Get the Etsy shop data from custom post type
 * 
 * @return array|false Shop data or false if not found
 */
function etsy_get_shop_data() {
    $shop = get_posts(array(
        'post_type' => 'etsy_shop',
        'posts_per_page' => 1,
        'post_status' => 'publish',
    ));

    if (empty($shop)) {
        return false;
    }

    $shop_id = $shop[0]->ID;
    
    return array(
        'shop_id' => get_post_meta($shop_id, 'etsy_shop_id', true),
        'shop_name' => get_post_meta($shop_id, 'etsy_shop_name', true),
        'shop_url' => get_post_meta($shop_id, 'etsy_shop_url', true),
        'api_key' => get_post_meta($shop_id, 'etsy_api_key', true),
        'create_date' => get_post_meta($shop_id, 'etsy_create_date', true),
        'icon_url_fullxfull' => get_post_meta($shop_id, 'etsy_icon_url', true),
    );
}

/**
 * Get Etsy listings from custom post type
 * 
 * @param int $limit Number of listings to get
 * @param int $offset Offset to start from
 * @param array $extra_args Additional arguments to pass to get_posts
 * @return array Array of listings
 */
function etsy_get_listings($limit = -1, $offset = 0, $extra_args = array()) {
    $args = array(
        'post_type' => 'etsy_listing',
        'posts_per_page' => $limit,
        'offset' => $offset,
        'post_status' => 'publish',
    );
    
    // Merge any additional arguments
    if (!empty($extra_args)) {
        $args = array_merge($args, $extra_args);
    }

    $posts = get_posts($args);
    $listings = array();

    foreach ($posts as $post) {
        $listing_id = $post->ID;
        $images = get_post_meta($listing_id, 'etsy_images', true);
        
        $listing = array(
            'listing_id' => get_post_meta($listing_id, 'etsy_listing_id', true),
            'shop_id' => get_post_meta($listing_id, 'etsy_shop_id', true),
            'title' => $post->post_title,
            'description' => $post->post_content,
            'state' => get_post_meta($listing_id, 'etsy_state', true),
            'url' => get_post_meta($listing_id, 'etsy_url', true),
            'num_favorers' => get_post_meta($listing_id, 'etsy_num_favorers', true),
            'price' => array(
                'amount' => get_post_meta($listing_id, 'etsy_price', true) * 100, // Convert to cents
                'divisor' => 100,
                'currency_code' => get_post_meta($listing_id, 'etsy_currency_code', true),
            ),
            'images' => $images ? $images : array(),
        );

        $listings[] = $listing;
    }

    return $listings;
} 