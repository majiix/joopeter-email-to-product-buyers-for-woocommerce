<?php
/**
 * Plugin Name: JooPeter Email to Product Buyers for WooCommerce
 * Plugin URI:  https://wordpress.org/plugins/joopeter-email-to-product-buyers-for-woocommerce/
 * Description: Adds a meta box to the product edit page to send custom emails to customers who bought that product.
 * Version:     1.3.9
 * Author:      micromax
 * Text Domain: joopeter-email-to-product-buyers-for-woocommerce
 * Domain Path: /languages
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires Plugins: woocommerce
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 11.3.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants with unique prefixes (at least 4 chars).
if ( ! defined( 'JOOPETEREWB_VERSION' ) ) {
	define( 'JOOPETEREWB_VERSION', '1.3.9' );
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

/**
 * Add settings action link to the plugins page.
 *
 * @param array $links Array of plugin action links.
 * @return array Modified array of action links.
 */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'joopeterewb_add_action_links' );
function joopeterewb_add_action_links( $links ) {
	if ( current_user_can( 'manage_options' ) ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'tools.php?page=joopeterewb-email-panel' ) ),
			esc_html__( 'Settings', 'joopeter-email-to-product-buyers-for-woocommerce' )
		);
		array_unshift( $links, $settings_link );
	}
	return $links;
}

