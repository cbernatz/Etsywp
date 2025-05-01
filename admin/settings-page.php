<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main admin page for Etsy plugin
 */
function etsy_admin_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1> 
        <div class="etsy-admin-container">
            <div class="etsy-admin-card">
                <h2>Etsy Shop Status</h2>
                <?php 
                // This would check if the shop is connected in a real implementation
                $is_connected = false;
                
                if ($is_connected) {
                    echo '<div class="etsy-status etsy-status-connected">Connected</div>';
                    echo '<p>Your Etsy shop is successfully connected.</p>';
                } else {
                    echo '<div class="etsy-status etsy-status-disconnected">Not Connected</div>';
                    echo '<p>Connect your Etsy shop to start syncing products.</p>';
                    echo '<a href="' . admin_url('admin.php?page=etsy-shop-connect') . '" class="button button-primary">Connect Shop</a>';
                }
                ?>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Connect shop admin page
 */
function etsy_connect_shop_page() {
    // Process form submission (would handle authentication in real implementation)
    $form_submitted = isset($_POST['etsy_connect_submit']);
    $connection_success = false;
    
    if ($form_submitted) {
        // This would handle the actual OAuth process in a real implementation
        $connection_success = true;
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <?php if ($connection_success): ?>
            <div class="notice notice-success">
                <p>Your Etsy shop has been successfully connected!</p>
            </div>
            <p><a href="<?php echo admin_url('admin.php?page=etsy-shop'); ?>" class="button button-primary">Return to Dashboard</a></p>
        <?php else: ?>
            <div class="etsy-connect-container">
                <div class="etsy-connect-card">
                    <h2>Connect Your Etsy Shop</h2>
                    <p>To connect your Etsy shop, you'll need to authorize this plugin to access your Etsy account.</p>
                    
                    <form method="post" action="">
                        <p class="submit">
                            <input type="submit" name="etsy_connect_submit" class="button button-primary" value="Connect to Etsy">
                        </p>
                    </form>
                    
                    <div class="etsy-connection-details">
                        <h3>Connection Process</h3>
                        <ol>
                            <li>Click "Connect to Etsy"</li>
                            <li>You'll be redirected to Etsy to authorize access</li>
                            <li>After authorizing, you'll be returned to this site</li>
                        </ol>
                    </div>
                </div>
            </div>
        <?php endif; ?>
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
