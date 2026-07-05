<?php
/**
 * Plugin Name: JooPeter Email to Product Buyers for WooCommerce
 * Plugin URI:  https://wordpress.org/plugins/joopeter-email-to-product-buyers-for-woocommerce/
 * Description: Adds a meta box to the product edit page to send custom emails to customers who bought that product.
 * Version:     1.3.5
 * Author:      micromax
 * Text Domain: joopeter-email-to-product-buyers-for-woocommerce
 * Domain Path: /languages
 * License:     GPL-2.0+
 * Requires Plugins: woocommerce
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants with unique prefixes (at least 4 chars).
if ( ! defined( 'JOOPETEREWB_VERSION' ) ) {
	define( 'JOOPETEREWB_VERSION', '1.3.5' );
}

if ( ! defined( 'JOOPETEREWB_PLUGIN_DIR' ) ) {
	define( 'JOOPETEREWB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'JOOPETEREWB_PLUGIN_URL' ) ) {
	define( 'JOOPETEREWB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

/**
 * Declare HPOS Compatibility.
 */
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

// Include and initialize the plugin on plugins_loaded.
add_action( 'plugins_loaded', 'joopeterewb_init_plugin' );
function joopeterewb_init_plugin() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'joopeterewb_admin_notice_missing_wc' );
		return;
	}

	// Include the main class.
	if ( file_exists( JOOPETEREWB_PLUGIN_DIR . 'includes/class-joopeter-email-to-product-buyers.php' ) ) {
		require_once JOOPETEREWB_PLUGIN_DIR . 'includes/class-joopeter-email-to-product-buyers.php';
	}

	// Initialize the plugin.
	if ( class_exists( 'Joopeterewb_Email_To_Product_Buyers' ) ) {
		new Joopeterewb_Email_To_Product_Buyers();
	}
}

/**
 * Display notice if WooCommerce is missing.
 */
function joopeterewb_admin_notice_missing_wc() {
	?>
	<div class="notice notice-error is-dismissible">
		<p>
			<?php
			esc_html_e( 'JooPeter Email to Product Buyers requires WooCommerce to be installed and active.', 'joopeter-email-to-product-buyers-for-woocommerce' );
			?>
		</p>
	</div>
	<?php
}

// Register activation hook to initialize email logs option.
register_activation_hook( __FILE__, 'joopeterewb_activate_plugin' );
function joopeterewb_activate_plugin() {
	if ( false === get_option( 'joopeterewb_email_logs' ) ) {
		add_option( 'joopeterewb_email_logs', array(), '', 'no' );
	}
}

