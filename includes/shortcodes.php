<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Listing Tile shortcode - displays a single Etsy listing
 * @param array $atts Shortcode attributes
 * @return string Shortcode output
 */
function etsy_listing_tile_shortcode($atts)
{
    // Parse attributes
    $atts = shortcode_atts(
        array(
            'listing_id' => 0,  // Can be used to fetch specific listing
            'title' => '',      // Or pass in listing details directly
            'price' => '',
            'currency' => 'USD',
            'image_url' => '',
            'url' => '',
        ),
        $atts,
        'etsywp_listing_tile'
    );

    ob_start();
    // If we have a listing_id but no details, try to fetch the listing
    if (!empty($atts['listing_id']) && empty($atts['title'])) {
        // We need to update etsy_get_listings first to accept additional parameters
        // For now, let's use a simple custom query
        $posts = get_posts(array(
            'post_type' => 'etsy_listing',
            'posts_per_page' => 1,
            'meta_query' => array(
                array(
                    'key' => 'etsy_listing_id',
                    'value' => $atts['listing_id'],
                ),
            ),
        ));

        if (!empty($posts)) {
            $post = $posts[0];
            $listing_id = $post->ID;
            $images = get_post_meta($listing_id, 'etsy_images', true);

            $atts['title'] = $post->post_title;
            $atts['url'] = get_post_meta($listing_id, 'etsy_url', true);

            $price = get_post_meta($listing_id, 'etsy_price', true);
            if (!empty($price)) {
                $atts['price'] = number_format($price, 2);
                $atts['currency'] = get_post_meta($listing_id, 'etsy_currency_code', true);
            }

            if (!empty($images[0]['url_170x135'])) {
                $atts['image_url'] = $images[0]['url_170x135'];
            }
        }
    }

    // Only render if we have at least a title
    if (!empty($atts['title'])) {
        // Set CSS classes
        $card_class = 'etsywp-product-card';
?>
        <a class="<?php echo esc_attr($card_class); ?>" href="/<?php echo $atts['listing_id'] ?>">
            <?php if (!empty($atts['image_url'])) : ?>
                <div class="etsywp-product-image">
                    <img class="image-fit-cover" src="<?php echo esc_url($atts['image_url']); ?>" alt="<?php echo esc_attr($atts['title']); ?>">
                </div>
            <?php endif; ?>

            <div class="etsywp-product-details">
                <h3><?php echo esc_html($atts['title']); ?></h3>

                <?php if (!empty($atts['price'])) : ?>
                    <div class="etsywp-product-price">
                        <?php echo esc_html($atts['currency'] . ' ' . $atts['price']); ?>
                    </div>
                <?php endif; ?>
            </div>
        </a>
    <?php
    }

    return ob_get_clean();
}

/**
 * Listing Grid shortcode - displays a grid of listings
 * @param array $atts Shortcode attributes
 * @param string $content The shortcode content (to place inside the grid)
 * @return string Shortcode output
 */
function etsy_listing_grid_shortcode($atts, $content = null)
{
    // Parse attributes
    $atts = shortcode_atts(
        array(
            'columns' => 3, // Default number of columns (changed to 2)
            'fullwidth' => 'no', // Whether to use fullwidth display
        ),
        $atts,
        'etsywp_listing_grid'
    );

    ob_start();

    // Set grid class
    $grid_class = 'etsywp-grid';

    // Full-width container if enabled
    if ($atts['fullwidth'] === 'yes') {
        echo '<div class="etsywp-grid-container">';
    }

    // No need for inline column styles - we'll handle this with CSS and media queries
    ?>
    <div class="<?php echo esc_attr($grid_class); ?>">
        <?php echo do_shortcode($content); ?>
    </div>
    <?php

    if ($atts['fullwidth'] === 'yes') {
        echo '</div>';
    }

    return ob_get_clean();
}

/**
 * Best Sellers shortcode
 *
 * @param array $atts Shortcode attributes
 * @return string Shortcode output
 */
function etsy_best_sellers_shortcode($atts)
{
    // Parse attributes
    $atts = shortcode_atts(
        array(
            'limit' => 30, // Changed from 12 to 30 items by default
            'columns' => 3, // Default number of columns (changed to 2)
            'fullwidth' => 'yes', // Default to full width
        ),
        $atts,
        'etsywp_best_sellers'
    );

    // Start output buffer
    ob_start();

    // Get the listings sorted by favorites
    $extra_args = array(
        'meta_key' => 'etsy_num_favorers',
        'orderby' => 'meta_value_num',
        'order' => 'DESC',
        'meta_query' => array(
            array(
                'key' => 'etsy_num_favorers',
                'value' => '0',
                'compare' => '>',
                'type' => 'NUMERIC'
            )
        )
    );

    $listings = etsy_get_listings($atts['limit'], 0, $extra_args);

    if (!empty($listings)) {
        // Start grid shortcode - pass the columns parameter from best_sellers
        $grid_shortcode = '[etsywp_listing_grid columns="' . esc_attr($atts['columns']) . '" fullwidth="' . esc_attr($atts['fullwidth']) . '"]';

        // Add tile shortcodes for each listing
        foreach ($listings as $listing) {
            $image_url = !empty($listing['images'][0]['url_170x135']) ? $listing['images'][0]['url_170x135'] : '';
            $price = '';
            $currency = 'USD';

            if (isset($listing['price'])) {
                $price = number_format($listing['price']['amount'] / $listing['price']['divisor'], 2);
                $currency = $listing['price']['currency_code'];
            }

            $grid_shortcode .= '[etsywp_listing_tile ';
            $grid_shortcode .= 'listing_id="' . esc_attr($listing['listing_id']) . '" ';
            $grid_shortcode .= 'title="' . esc_attr($listing['title']) . '" ';
            $grid_shortcode .= 'url="' . esc_url($listing['url']) . '" ';
            $grid_shortcode .= 'price="' . esc_attr($price) . '" ';
            $grid_shortcode .= 'currency="' . esc_attr($currency) . '" ';
            $grid_shortcode .= 'image_url="' . esc_url($image_url) . '" ';
            $grid_shortcode .= ']';
        }

        // Close grid shortcode
        $grid_shortcode .= '[/etsywp_listing_grid]';

        // Process the shortcodes
        echo do_shortcode($grid_shortcode);
    } else {
        echo '<p>No listings found.</p>';
    }

    // Return the buffered content
    return ob_get_clean();
}

/**
 * Shop All shortcode
 *
 * @param array $atts Shortcode attributes
 * @return string Shortcode output
 */

function etsy_shop_all_shortcode($atts)
{
    // Parse attributes
    $atts = shortcode_atts(
        array(
            'columns' => 2, // Default number of columns (changed to 2)
            'fullwidth' => 'yes', // Default to full width
        ),
        $atts,
        'etsywp_shop_all'
    );

    // Start output buffer
    ob_start();

    // Get all listings
    $listings = etsy_get_listings();

    if (!empty($listings)) {
        // Start grid shortcode - pass the columns parameter
        $grid_shortcode = '[etsywp_listing_grid columns="' . esc_attr($atts['columns']) . '" fullwidth="' . esc_attr($atts['fullwidth']) . '"]';

        // Add tile shortcodes for each listing
        foreach ($listings as $listing) {
            $image_url = !empty($listing['images'][0]['url_170x135']) ? $listing['images'][0]['url_170x135'] : '';
            $price = '';
            $currency = 'USD';

            if (isset($listing['price'])) {
                $price = number_format($listing['price']['amount'] / $listing['price']['divisor'], 2);
                $currency = $listing['price']['currency_code'];
            }

            $grid_shortcode .= '[etsywp_listing_tile ';
            $grid_shortcode .= 'listing_id="' . esc_attr($listing['listing_id']) . '" ';
            $grid_shortcode .= 'title="' . esc_attr($listing['title']) . '" ';
            $grid_shortcode .= 'url="' . esc_url($listing['url']) . '" ';
            $grid_shortcode .= 'price="' . esc_attr($price) . '" ';
            $grid_shortcode .= 'currency="' . esc_attr($currency) . '" ';
            $grid_shortcode .= 'image_url="' . esc_url($image_url) . '" ';
            $grid_shortcode .= ']';
        }

        // Close grid shortcode
        $grid_shortcode .= '[/etsywp_listing_grid]';

        // Process the shortcodes
        echo do_shortcode($grid_shortcode);
    } else {
        echo '<p>No listings found.</p>';
    }

    // Return the buffered content
    return ob_get_clean();
}


/**
 * Full Width Container shortcode
 * @param array $atts Shortcode attributes
 * @param string $content The shortcode content
 * @return string Shortcode output
 */
function etsy_fullwidth_container_shortcode($atts, $content = null)
{
    // Parse attributes
    $atts = shortcode_atts(
        array(
            'max_width' => '1200px', // Maximum width for large screens
            'padding' => '1rem',     // Padding
            'bg_color' => '',        // Optional background color
        ),
        $atts,
        'etsywp_fullwidth'
    );

    ob_start();

    // Generate a unique ID for this container
    $container_id = 'etsywp-fullwidth-' . mt_rand(1000, 9999);

    // Add custom style for this specific container
    ?>
    <style>
        #<?php echo esc_attr($container_id); ?> {
            padding: <?php echo esc_attr($atts['padding']); ?>;
            <?php if (!empty($atts['bg_color'])) : ?>background-color: <?php echo esc_attr($atts['bg_color']); ?>;
            <?php endif; ?>
        }

        #<?php echo esc_attr($container_id); ?>.etsywp-fullwidth-inner {
            max-width: <?php echo esc_attr($atts['max_width']); ?>;
        }
    </style>

    <div id="<?php echo esc_attr($container_id); ?>" class="etsywp-fullwidth-container">
        <div class="etsywp-fullwidth-inner">
            <?php echo do_shortcode($content); ?>
        </div>
    </div>
<?php

    return ob_get_clean();
}

// Register shortcodes
add_shortcode('etsywp_listing_tile', 'etsy_listing_tile_shortcode');
add_shortcode('etsywp_listing_grid', 'etsy_listing_grid_shortcode');
add_shortcode('etsywp_best_sellers', 'etsy_best_sellers_shortcode');
add_shortcode('etsywp_fullwidth', 'etsy_fullwidth_container_shortcode');
add_shortcode('etsywp_shop_all', 'etsy_shop_all_shortcode');

