<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include post types file
require_once ETSY_PLUGIN_DIR . 'includes/post-types.php';

/**
 * Etsy API Client
 * 
 * Handles communication with the Etsy API
 */
class Etsy_API_Client {
    /**
     * API base URL
     */
    private $api_base = 'https://openapi.etsy.com/v3';
    
    /**
     * API key
     */
    private $api_key = '';
    
    /**
     * Shop ID
     */
    private $shop_id = '';
    
    /**
     * Shop name (from URL)
     */
    private $shop_name = '';
    
    /**
     * Constructor
     */
    public function __construct() {
        // Get API credentials from stored shop data or fallback to options
        $shop_data = etsy_get_shop_data();
        
        if ($shop_data) {
            $this->api_key = $shop_data['api_key'];
            $this->shop_id = $shop_data['shop_id'];
            $this->shop_name = $this->extract_shop_name($shop_data['shop_url']);
        } else {
            // Fallback to options (for backward compatibility)
            $this->api_key = get_option('etsy_api_key', '');
            $this->shop_id = get_option('etsy_shop_id', '');
            $this->shop_name = $this->extract_shop_name(get_option('etsy_shop_url', ''));
        }
    }
    
    /**
     * Extract shop name from shop URL
     * 
     * @param string $shop_url The Etsy shop URL
     * @return string The shop name
     */
    private function extract_shop_name($shop_url) {
        // Extract shop name from URL (e.g., https://www.etsy.com/shop/BrooklynGreetingCo)
        if (preg_match('/^https:\/\/www\.etsy\.com\/shop\/([a-zA-Z0-9][\w-]*)$/', $shop_url, $matches)) {
            return $matches[1];
        }
        return '';
    }
    
    /**
     * Check if the API client is properly configured
     * 
     * @return bool True if configured, false otherwise
     */
    public function is_configured() {
        return !empty($this->api_key) && !empty($this->shop_name);
    }
    
    /**
     * Find shop by shop name
     * 
     * @return array|WP_Error Shop data or error
     */
    public function find_shop() {
        if (empty($this->shop_name)) {
            return new WP_Error('invalid_shop_name', 'Invalid shop name');
        }
        
        $endpoint = '/application/shops';
        $params = array(
            'shop_name' => $this->shop_name,
            'limit' => 1
        );
        
        $response = $this->request($endpoint, $params);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        if (empty($response['results'])) {
            return new WP_Error('shop_not_found', 'Shop not found');
        }
        
        // Save the shop ID
        $this->shop_id = $response['results'][0]['shop_id'];
        update_option('etsy_shop_id', $this->shop_id);
        
        return $response['results'][0];
    }
    
    /**
     * Get detailed shop information
     * 
     * @return array|WP_Error Shop data or error
     */
    public function get_shop_details() {
        if (empty($this->shop_id)) {
            // Try to find shop first
            $find_result = $this->find_shop();
            if (is_wp_error($find_result)) {
                return $find_result;
            }
        }
        
        $endpoint = "/application/shops/{$this->shop_id}";
        
        $response = $this->request($endpoint);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Save important shop details
        $shop_data = array(
            'shop_id' => $response['shop_id'],
            'shop_name' => $response['shop_name'],
            'create_date' => $response['create_date'],
            'icon_url_fullxfull' => isset($response['icon_url_fullxfull']) ? $response['icon_url_fullxfull'] : ''
        );
        
        // Save to CPT and also to options for backward compatibility
        etsy_save_shop_data($shop_data);
        update_option('etsy_shop_data', $shop_data);
        
        return $response;
    }
    
    /**
     * Get all active listings for a shop
     * 
     * @param int $limit Number of listings to fetch (max 100)
     * @param int $offset Starting position of results
     * @return array|WP_Error Listings data or error
     */
    public function get_shop_listings($limit = 25, $offset = 0) {
        if (empty($this->shop_id)) {
            // Try to find shop first
            $find_result = $this->find_shop();
            if (is_wp_error($find_result)) {
                return $find_result;
            }
        }
        
        $endpoint = "/application/shops/{$this->shop_id}/listings/active";
        
        $params = array(
            'limit' => min($limit, 100), // Etsy has a max limit of 100
            'offset' => $offset
        );
        
        $response = $this->request($endpoint, $params);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return $response;
    }
    
    /**
     * Get detailed information for a specific listing
     * 
     * @param int $listing_id The listing ID
     * @return array|WP_Error Listing data or error
     */
    public function get_listing_details($listing_id) {
        if (empty($listing_id)) {
            return new WP_Error('invalid_listing_id', 'Invalid listing ID');
        }
        
        $endpoint = "/application/listings/{$listing_id}";
        
        $params = array(
            'includes' => 'images' // Include images in the response
        );
        
        $response = $this->request($endpoint, $params);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return $response;
    }
    
    /**
     * Get all shop listings with detailed information
     * 
     * @param int $limit Number of listings to fetch
     * @return array|WP_Error Array of detailed listings or error
     */
    public function get_shop_listings_with_details($limit = 10) {
        // Get basic listing data
        $listings_response = $this->get_shop_listings($limit);
        
        if (is_wp_error($listings_response)) {
            return $listings_response;
        }
        
        $listings = $listings_response['results'];
        $detailed_listings = array();
        
        // Get detailed information for each listing and save to CPT
        foreach ($listings as $listing) {
            $listing_id = $listing['listing_id'];
            $listing_details = $this->get_listing_details($listing_id);
            
            if (!is_wp_error($listing_details)) {
                $detailed_listings[] = $listing_details;
                
                // Save listing to CPT
                etsy_save_listing($listing_details);
            }
        }
        
        // Save listings to options for backward compatibility
        update_option('etsy_shop_listings', $detailed_listings);
        
        return $detailed_listings;
    }
    
    /**
     * Make a request to the Etsy API
     * 
     * @param string $endpoint API endpoint
     * @param array $params Request parameters
     * @param string $method HTTP method (GET, POST, etc.)
     * @return array|WP_Error Response data or error
     */
    public function request($endpoint, $params = array(), $method = 'GET') {
        $url = $this->api_base . $endpoint;
        
        // Add parameters to URL for GET requests
        if ($method === 'GET' && !empty($params)) {
            $url = add_query_arg($params, $url);
        }
        
        $args = array(
            'method' => $method,
            'headers' => array(
                'x-api-key' => $this->api_key,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ),
            'timeout' => 30,
        );
        
        // Add body for non-GET requests
        if ($method !== 'GET' && !empty($params)) {
            $args['body'] = json_encode($params);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        
        if ($response_code < 200 || $response_code >= 300) {
            $error_message = isset($response_data['error']) ? $response_data['error'] : 'Unknown API error';
            return new WP_Error('api_error', $error_message, array('status' => $response_code));
        }
        
        return $response_data;
    }
}
