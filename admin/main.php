<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include post types file
require_once ETSY_PLUGIN_DIR . 'includes/post-types.php';

/**
 * Main admin page for Etsy plugin
 */
function etsy_admin_page() {
    // Check if we're processing a connection form submission
    $form_submitted = isset($_POST['etsy_connect_submit']);
    $error_message = '';
    
    // Get shop data from CPT
    $shop_data = etsy_get_shop_data();
    
    // If no shop data in CPT, try options (backward compatibility)
    if (!$shop_data) {
        $shop_data = get_option('etsy_shop_data', array());
        $shop_data['shop_url'] = get_option('etsy_shop_url', '');
        $shop_data['api_key'] = get_option('etsy_api_key', '');
    }
    
    // Get listings from CPT
    $listings = etsy_get_listings();
    
    // If no listings in CPT, try options (backward compatibility)
    if (empty($listings)) {
        $listings = get_option('etsy_shop_listings', array());
    }
    
    // Initialize variables from shop data or options
    if ($shop_data) {
        $shop_url = $shop_data['shop_url'];
        $api_key = $shop_data['api_key'];
    } else {
        $shop_url = get_option('etsy_shop_url', '');
        $api_key = get_option('etsy_api_key', '');
    }
    
    $is_connected = !empty($shop_data) && !empty($shop_data['shop_url']) && !empty($shop_data['api_key']);
    
    // Process connection form submission
    if ($form_submitted) {
        $shop_url = isset($_POST['etsy_shop_url']) ? sanitize_text_field($_POST['etsy_shop_url']) : '';
        $api_key = isset($_POST['etsy_api_key']) ? sanitize_text_field($_POST['etsy_api_key']) : '';
        
        // Validate the inputs
        if (empty($shop_url)) {
            $error_message = 'Please enter your Etsy shop URL.';
        } elseif (!preg_match('/^https:\/\/www\.etsy\.com\/shop\/[a-zA-Z0-9][\w-]*$/', $shop_url)) {
            $error_message = 'Please enter a valid Etsy shop URL (e.g., https://www.etsy.com/shop/MyShop).';
        } elseif (empty($api_key)) {
            $error_message = 'Please enter your Etsy API key.';
        } elseif (strlen($api_key) < 24) { // Simple validation for API key length
            $error_message = 'Please enter a valid Etsy API key.';
        } else {
            // Save the values first (for backward compatibility)
            update_option('etsy_shop_url', $shop_url);
            update_option('etsy_api_key', $api_key);
            
            // Now try to fetch shop data
            require_once ETSY_PLUGIN_DIR . 'includes/api-client.php';
            $api_client = new Etsy_API_Client();
            
            // Fetch shop details
            $shop_result = $api_client->get_shop_details();
            
            if (is_wp_error($shop_result)) {
                $error_message = 'Error connecting to Etsy: ' . $shop_result->get_error_message();
                // Delete saved values on error
                delete_option('etsy_shop_url');
                delete_option('etsy_api_key');
            } else {
                // Get the saved shop data
                $shop_data = etsy_get_shop_data();
                
                // Fetch shop listings
                $listings_result = $api_client->get_shop_listings_with_details();
                if (!is_wp_error($listings_result)) {
                    $listings = $listings_result;
                }
                
                $is_connected = true;
            }
        }
    }
    
    // Process listing refresh if requested
    if ($is_connected && isset($_GET['refresh_listings']) && $_GET['refresh_listings'] == '1') {
        require_once ETSY_PLUGIN_DIR . 'includes/api-client.php';
        $api_client = new Etsy_API_Client();
        $api_client->get_shop_listings_with_details();
    }
    ?>
    <div class="wrap">
        <h1>Etsy</h1>
        
        <div class="etsy-admin-container">
            <?php if ($is_connected): ?>
                <!-- Connected Shop Dashboard -->
                <div class="etsy-admin-card">                    
                    <?php
                    // Display shop icon if available
                    if (!empty($shop_data['icon_url_fullxfull'])) {
                        echo '<div class="etsy-shop-icon"><img src="' . esc_url($shop_data['icon_url_fullxfull']) . '" alt="Shop Icon"></div>';
                    }
                    
                    echo '<p><strong>Shop Name:</strong> ' . esc_html($shop_data['shop_name']) . '</p>';
                    echo '<p><strong>Shop URL:</strong> ' . esc_url($shop_data['shop_url']) . '</p>';
                    
                    // Format and display create date
                    $create_date = isset($shop_data['create_date']) ? $shop_data['create_date'] : '';
                    if (!empty($create_date)) {
                        // Handle Unix timestamp format
                        if (is_numeric($create_date)) {
                            $formatted_date = date('F j, Y', intval($create_date));
                            echo '<p><strong>Created:</strong> ' . esc_html($formatted_date) . '</p>';
                        } else {
                            // Try to parse as a formatted date
                            try {
                                $date = new DateTime($create_date);
                                echo '<p><strong>Created:</strong> ' . esc_html($date->format('F j, Y')) . '</p>';
                            } catch (Exception $e) {
                                // If failed to parse, just show the raw date
                                echo '<p><strong>Created:</strong> ' . esc_html($create_date) . '</p>';
                            }
                        }
                    }
                    ?>
                    
                    <div class="etsy-admin-actions">
                        <button type="button" class="button etsy-secondary-button" id="etsy-update-connection">Update Connection</button>
                        <button type="button" class="button button-primary etsy-button" id="etsy-refresh-listings">Refresh</button>
                    </div>
                </div>
                
                <?php if (!empty($listings)): ?>
                    <!-- Listings Display -->
                    <div class="etsy-admin-card etsy-listings-card">
                        <h2>Etsy Shop Listings</h2>
                        <p>Showing <?php echo count($listings); ?> products from your Etsy shop.</p>
                        
                        <table class="widefat etsy-listings-table">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Title</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($listings as $listing): ?>
                                    <tr>
                                        <td class="etsy-listing-image">
                                            <?php
                                            $image_url = '';
                                            if (!empty($listing['images'][0]['url_170x135'])) {
                                                $image_url = $listing['images'][0]['url_170x135'];
                                            }
                                            if (!empty($image_url)) {
                                                echo '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($listing['title']) . '">';
                                            } else {
                                                echo '<div class="etsy-no-image">No Image</div>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <strong><?php echo esc_html($listing['title']); ?></strong>
                                            <div class="etsy-listing-description">
                                                <?php echo wp_trim_words(wp_strip_all_tags($listing['description']), 15, '...'); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            if (isset($listing['price'])) {
                                                echo esc_html($listing['price']['currency_code'] . ' ' . $listing['price']['amount'] / $listing['price']['divisor']);
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <span class="etsy-listing-state"><?php echo esc_html(ucfirst($listing['state'])); ?></span>
                                        </td>
                                        <td>
                                            <a href="<?php echo esc_url($listing['url']); ?>" target="_blank" class="button etsy-secondary-button">View on Etsy</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- Connection Form (hidden when connected) -->
            <div id="etsy-connect-form" class="etsy-connect-container" <?php echo $is_connected ? 'style="display:none;"' : ''; ?>>
                <div class="etsy-connect-card">
                    <h2>Connect Your Etsy Shop</h2>
                    <p>Please enter your Etsy shop URL and API key to connect your shop.</p>
                    
                    <?php if (!empty($error_message)): ?>
                        <div class="notice notice-error">
                            <p><?php echo esc_html($error_message); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="etsy_shop_url">Etsy Shop URL</label></th>
                                <td>
                                    <input type="text" name="etsy_shop_url" id="etsy_shop_url" class="regular-text" 
                                           value="<?php echo esc_attr($shop_url); ?>" 
                                           placeholder="https://www.etsy.com/shop/YourShopName" />
                                    <p class="description">Example: https://www.etsy.com/shop/BrooklynGreetingCo</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="etsy_api_key">Etsy API Key</label></th>
                                <td>
                                    <input type="password" name="etsy_api_key" id="etsy_api_key" class="regular-text" 
                                           value="<?php echo esc_attr($api_key); ?>" />
                                    <p class="description">Enter your Etsy API key from your Etsy developer account.</p>
                                    <p class="description"><a href="https://www.etsy.com/developers/register" target="_blank">Don't have an API key? Register as a developer.</a></p>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <input type="submit" name="etsy_connect_submit" class="button button-primary etsy-button" value="Connect Shop">
                            <?php if ($is_connected): ?>
                                <button type="button" class="button" id="etsy-cancel-update">Cancel</button>
                            <?php endif; ?>
                        </p>
                    </form>
                </div>
            </div>
        </div>
        
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Toggle connection form
                $('#etsy-update-connection').on('click', function() {
                    $('#etsy-connect-form').show();
                });
                
                $('#etsy-cancel-update').on('click', function(e) {
                    e.preventDefault();
                    $('#etsy-connect-form').hide();
                });
                
                // Handle refresh listings button click
                $('#etsy-refresh-listings').on('click', function() {
                    var $button = $(this);
                    $button.prop('disabled', true).html('<span class="spinner is-active" style="float:none;margin-right:5px;"></span> Refreshing...');
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'etsy_refresh_listings',
                            security: '<?php echo wp_create_nonce('etsy_refresh_listings_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                $button.prop('disabled', false).text('Refresh');
                            }
                        },
                        error: function() {
                            $button.prop('disabled', false).text('Refresh');
                        }
                    });
                });
            });
        </script>
    </div>
    <?php
}

/**
 * Register admin styles
 */
function etsy_admin_styles() {
    wp_enqueue_style(
        'etsy-admin-styles',
        ETSY_PLUGIN_URL . 'assets/css/admin-styles.css',
        array(),
        '1.0.0'
    );
}
add_action('admin_enqueue_scripts', 'etsy_admin_styles');

/**
 * AJAX handler for refreshing Etsy listings
 */
function etsy_ajax_refresh_listings() {
    // Verify nonce
    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'etsy_refresh_listings_nonce')) {
        wp_send_json_error();
    }
    
    // Check user permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error();
    }
    
    // Get shop data
    $shop_data = etsy_get_shop_data();
    
    // Check if shop is connected
    if (empty($shop_data) || empty($shop_data['shop_url']) || empty($shop_data['api_key'])) {
        wp_send_json_error();
    }
    
    // Include API client
    require_once ETSY_PLUGIN_DIR . 'includes/api-client.php';
    $api_client = new Etsy_API_Client();
    
    // Refresh listings
    $result = $api_client->get_shop_listings_with_details();
    
    if (is_wp_error($result)) {
        wp_send_json_error();
    } else {
        wp_send_json_success();
    }
}
add_action('wp_ajax_etsy_refresh_listings', 'etsy_ajax_refresh_listings');
