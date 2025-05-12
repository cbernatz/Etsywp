=== Etsy ===
Contributors: yourname
Tags: etsy, shop, listings, ecommerce, api, inventory
Requires at least: 5.0
Tested up to: 6.5
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Websites for Etsy sellers. Connect your Etsy shop, sync your listings, and display them in your WordPress admin.

== Description ==
This plugin allows Etsy sellers to connect their Etsy shop to WordPress, sync their shop and product listings, and manage/display them in the WordPress admin. It uses the Etsy API to fetch shop details and listings, storing them as custom post types for easy management and future extensibility.

**Features:**
* Connect your Etsy shop using your shop URL and API key
* Sync shop details and product listings from Etsy
* View and manage listings in the WordPress admin
* Listings and shop data stored as custom post types
* Uses Etsy colorways for a familiar, branded admin experience
* AJAX-powered refresh for up-to-date listings
* Secure connection with nonce and permission checks

== Installation ==
1. Upload the plugin files to the `/wp-content/plugins/etsy-plugin` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to the new 'Etsy' menu in your WordPress admin sidebar.
4. Enter your Etsy shop URL (e.g., https://www.etsy.com/shop/YourShopName) and your Etsy API key (get one at https://www.etsy.com/developers/register).
5. Click 'Connect Shop' to sync your shop and listings.

== Frequently Asked Questions ==
= Where do I get an Etsy API key? =
Register as a developer at https://www.etsy.com/developers/register to obtain your API key.

= How do I refresh my listings? =
Click the 'Refresh' button in the Etsy admin dashboard to fetch the latest listings from your Etsy shop.

= Where are my shop and listings stored? =
Shop data is stored as a custom post type (`etsy_shop`), and listings as `etsy_listing` posts. Images are saved as WordPress media attachments.
