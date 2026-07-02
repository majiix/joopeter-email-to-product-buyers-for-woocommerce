<?php
/**
 * Class Joopeterewb_Email_To_Product_Buyers
 *
 * Handles the logic for sending emails to product buyers.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Joopeterewb_Email_To_Product_Buyers' ) ) {

	/**
	 * Main Plugin Class.
	 */
	class Joopeterewb_Email_To_Product_Buyers {

		/**
		 * Cache Group Name.
		 * Using v1 for the rebranded plugin.
		 */
		const CACHE_GROUP = 'JOOPETEREWB_cache_v1';

		/**
		 * Constructor.
		 */
		public function __construct() {
			// Initialize the plugin on admin init.
			add_action( 'admin_init', array( $this, 'init' ) );

			// Add the meta box.
			add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );

			// Enqueue scripts and styles.
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

			// Handle the AJAX request to send email.
			add_action( 'wp_ajax_joopeterewb_send_product_email', array( $this, 'handle_email_sending' ) );

			// Handle AJAX request to refresh count.
			add_action( 'wp_ajax_joopeterewb_refresh_customer_count', array( $this, 'handle_refresh_count' ) );

			// Clear cache when order status changes to keep counts accurate.
			add_action( 'woocommerce_order_status_changed', array( $this, 'clear_cache_on_order_change' ), 10, 4 );

			// Register the Tools submenu page.
			add_action( 'admin_menu', array( $this, 'register_tools_page' ) );

			// Handle AJAX request to clear logs.
			add_action( 'wp_ajax_joopeterewb_clear_email_logs', array( $this, 'handle_clear_logs' ) );

			// Handle AJAX request to clear success logs.
			add_action( 'wp_ajax_joopeterewb_clear_success_logs', array( $this, 'handle_clear_success_logs' ) );

			// Handle AJAX request to retry failed emails.
			add_action( 'wp_ajax_joopeterewb_retry_failed_emails', array( $this, 'handle_retry_failed_emails' ) );

			// Handle AJAX request to save settings.
			add_action( 'wp_ajax_joopeterewb_save_settings', array( $this, 'handle_save_settings' ) );
		}

		/**
		 * Check for dependencies.
		 */
		public function init() {
			if ( ! class_exists( 'WooCommerce' ) ) {
				add_action( 'admin_notices', array( $this, 'admin_notice_missing_wc' ) );
			}
		}

		/**
		 * Display notice if WooCommerce is missing.
		 */
		public function admin_notice_missing_wc() {
			?>
			<div class="notice notice-error">
				<p>
					<?php
					/* translators: %s: Plugin Name */
					esc_html_e( 'JooPeter Email to Product Buyers requires WooCommerce to be installed and active.', 'joopeter-email-to-product-buyers-for-woocommerce' );
					?>
				</p>
			</div>
			<?php
		}

		/**
		 * Enqueue Scripts and Styles.
		 *
		 * @param string $hook The current admin page hook.
		 */
		public function enqueue_assets( $hook ) {
			global $post;

			// Load on tools panel page.
			if ( 'tools_page_joopeterewb-email-panel' === $hook ) {
				// Only allow administrators to manage logs & settings.
				if ( ! current_user_can( 'manage_options' ) ) {
					return;
				}

				wp_enqueue_style(
					'joopeterewb-admin-css',
					JOOPETEREWB_PLUGIN_URL . 'assets/css/admin.css',
					array(),
					JOOPETEREWB_VERSION
				);

				wp_enqueue_script(
					'joopeterewb-admin-js',
					JOOPETEREWB_PLUGIN_URL . 'assets/js/admin.js',
					array( 'jquery' ),
					JOOPETEREWB_VERSION,
					true
				);

				wp_localize_script(
					'joopeterewb-admin-js',
					'joopeterewb_params',
					array(
						'ajaxurl' => admin_url( 'admin-ajax.php' ),
						'nonce'   => wp_create_nonce( 'joopeterewb_email_action' ),
						'strings' => array(
							'confirm_clear'         => __( 'Are you sure you want to clear all email logs?', 'joopeter-email-to-product-buyers-for-woocommerce' ),
							'confirm_clear_success' => __( 'Are you sure you want to clear all successful email logs?', 'joopeter-email-to-product-buyers-for-woocommerce' ),
							'confirm_retry'         => __( 'Are you sure you want to retry sending all failed emails?', 'joopeter-email-to-product-buyers-for-woocommerce' ),
							'confirm_save'          => __( 'Save settings?', 'joopeter-email-to-product-buyers-for-woocommerce' ),
							'server_error'          => __( 'Server error. Please try again.', 'joopeter-email-to-product-buyers-for-woocommerce' ),
							'settings_saved'        => __( 'Settings saved successfully.', 'joopeter-email-to-product-buyers-for-woocommerce' ),
						),
					)
				);
				return;
			}

			// Only load on product edit pages.
			if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
				return;
			}

			if ( ! $post instanceof WP_Post || 'product' !== $post->post_type ) {
				return;
			}

			// Check role permissions before enqueuing assets on product edit page.
			if ( ! $this->current_user_has_access() ) {
				return;
			}

			// Enqueue CSS with unique handle.
			wp_enqueue_style(
				'joopeterewb-admin-css',
				JOOPETEREWB_PLUGIN_URL . 'assets/css/admin.css',
				array(),
				JOOPETEREWB_VERSION
			);

			// Enqueue JS with unique handle.
			wp_enqueue_script(
				'joopeterewb-admin-js',
				JOOPETEREWB_PLUGIN_URL . 'assets/js/admin.js',
				array( 'jquery' ),
				JOOPETEREWB_VERSION,
				true
			);

			// Localize script to pass data to JS with unique object name.
			wp_localize_script(
				'joopeterewb-admin-js',
				'joopeterewb_params',
				array(
					'ajaxurl'    => admin_url( 'admin-ajax.php' ),
					'product_id' => $post->ID,
					'nonce'      => wp_create_nonce( 'joopeterewb_email_action' ),
					'strings'    => array(
						'empty_fields' => __( 'Please fill in both Subject and Message fields.', 'joopeter-email-to-product-buyers-for-woocommerce' ),
						'confirm_send' => __( 'Are you sure you want to send this email to all buyers?', 'joopeter-email-to-product-buyers-for-woocommerce' ),
						'server_error' => __( 'Server error. Please try again.', 'joopeter-email-to-product-buyers-for-woocommerce' ),
					),
				)
			);
		}

		public function add_meta_box() {
			if ( $this->current_user_has_access() ) {
				add_meta_box(
					'joopeterewb_email_buyers_box',
					__( 'Email Customers Who Bought This Product', 'joopeter-email-to-product-buyers-for-woocommerce' ),
					array( $this, 'render_meta_box' ),
					'product',
					'side',
					'low'
				);
			}
		}

		/**
		 * Render the Meta Box content.
		 *
		 * @param WP_Post $post The current post object.
		 */
		public function render_meta_box( $post ) {
			// Verify we are on a product page.
			if ( ! $post instanceof WP_Post || 'product' !== $post->post_type ) {
				return;
			}

			// Get the number of unique buyers.
			$buyer_count = $this->get_buyer_count( $post->ID );
			?>
			<div class="joopeterewb-email-box-container">
				<p class="description">
					<?php esc_html_e( 'Send a custom email to all customers with "Processing", "On Hold", or "Completed" orders for this product.', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?>
				</p>

				<div class="joopeterewb-chips-wrapper">
					<label><?php esc_html_e( 'Select Filter Order Status:', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?></label>
					<div class="joopeterewb-status-chips">
						<span class="joopeterewb-chip active" data-status="processing"><?php esc_html_e( 'Processing', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?></span>
						<span class="joopeterewb-chip active" data-status="on-hold"><?php esc_html_e( 'On Hold', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?></span>
						<span class="joopeterewb-chip active" data-status="completed"><?php esc_html_e( 'Completed', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?></span>
					</div>
				</div>

				<div class="joopeterewb-recipient-count">
					<div class="joopeterewb-count-wrapper">
						<span class="dashicons dashicons-groups"></span>
						<strong id="joopeterewb-count-value"><?php echo esc_html( $buyer_count ); ?></strong>
						<span id="joopeterewb-count-label">
						<?php
						/* translators: %s: Number of customers */
						echo esc_html( _n( 'customer found', 'customers found', $buyer_count, 'joopeter-email-to-product-buyers-for-woocommerce' ) );
						?>
						</span>
					</div>
					<button type="button" id="joopeterewb_refresh_count_btn" class="button button-small joopeterewb-refresh-btn" title="<?php esc_attr_e( 'Refresh count', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?>">
						<span class="dashicons dashicons-update"></span>
					</button>
				</div>

				<p>
					<label for="joopeterewb_email_subject">
						<?php esc_html_e( 'Subject:', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?>
					</label>
					<input type="text" id="joopeterewb_email_subject" name="joopeterewb_email_subject" class="widefat" value="" placeholder="<?php esc_attr_e( 'Enter email subject', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?>">
				</p>

				<div class="joopeterewb-editor-wrapper">
					<label for="joopeterewb_email_message">
						<?php esc_html_e( 'Message:', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?>
					</label>
					<?php
					// Settings for a compact editor suitable for a side meta box.
					$editor_settings = array(
						'media_buttons' => false,
						'textarea_rows' => 8,
						'teeny'         => true,
						'quicktags'     => false,
					);
					wp_editor( '', 'joopeterewb_email_message', $editor_settings );
					?>
				</div>

				<div id="joopeterewb-email-feedback"></div>

				<button type="button" id="joopeterewb_send_email_btn" class="button button-primary">
					<?php esc_html_e( 'Send Email', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?>
				</button>
				<button type="button" id="joopeterewb_retry_failed_meta_btn" class="button button-secondary joopeterewb-meta-retry-btn" style="display:none;">
					<?php esc_html_e( 'Retry Failed', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?>
				</button>
				<span class="spinner" id="joopeterewb_email_spinner"></span>

				<div class="joopeterewb-metabox-footer">
					<a href="<?php echo esc_url( admin_url( 'tools.php?page=joopeterewb-email-panel' ) ); ?>" target="_blank">
						<span class="dashicons dashicons-list-view"></span>
						<?php esc_html_e( 'View Email Logs', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?>
					</a>
				</div>
			</div>
			<?php
		}

		/**
		 * Handle AJAX request to refresh customer count.
		 */
		public function handle_refresh_count() {
			check_ajax_referer( 'joopeterewb_email_action', 'security' );

			if ( ! $this->current_user_has_access() ) {
				wp_send_json_error( __( 'Permission denied.', 'joopeter-email-to-product-buyers-for-woocommerce' ) );
			}

			$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
			$raw_statuses = isset( $_POST['statuses'] ) ? (array) $_POST['statuses'] : array( 'processing', 'completed', 'on-hold' );
			$statuses     = array();
			foreach ( $raw_statuses as $status ) {
				if ( is_scalar( $status ) ) {
					$statuses[] = sanitize_key( $status );
				}
			}
			if ( empty( $statuses ) ) {
				$statuses = array( 'processing', 'completed', 'on-hold' );
			}

			if ( ! $product_id ) {
				wp_send_json_error( __( 'Invalid product ID.', 'joopeter-email-to-product-buyers-for-woocommerce' ) );
			}

			// Force clear cache for this product.
			wp_cache_delete( 'JOOPETEREWB_buyer_emails_' . $product_id, self::CACHE_GROUP );

			// Re-calculate counts (this will trigger fresh DB query and cache set).
			$emails = $this->get_buyer_emails( $product_id, $statuses );
			$count  = count( $emails );

			/* translators: %s: Number of customers */
			$label = _n( 'customer found', 'customers found', $count, 'joopeter-email-to-product-buyers-for-woocommerce' );

			wp_send_json_success( array(
				'count' => $count,
				'label' => $label,
			) );
		}

		/**
		 * Handle AJAX request to send emails in chunked batches.
		 */
		public function handle_email_sending() {
			check_ajax_referer( 'joopeterewb_email_action', 'security' );

			if ( ! $this->current_user_has_access() ) {
				wp_send_json_error( __( 'You do not have permission to perform this action.', 'joopeter-email-to-product-buyers-for-woocommerce' ) );
			}

			$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
			$subject    = isset( $_POST['subject'] ) && is_scalar( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
			$message    = isset( $_POST['message'] ) && is_scalar( $_POST['message'] ) ? wp_kses_post( wp_unslash( $_POST['message'] ) ) : '';
			$raw_statuses = isset( $_POST['statuses'] ) ? (array) $_POST['statuses'] : array( 'processing', 'completed', 'on-hold' );
			$statuses     = array();
			foreach ( $raw_statuses as $status ) {
				if ( is_scalar( $status ) ) {
					$statuses[] = sanitize_key( $status );
				}
			}
			if ( empty( $statuses ) ) {
				$statuses = array( 'processing', 'completed', 'on-hold' );
			}
			$offset     = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
			$limit      = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 10;
			if ( $limit <= 0 ) {
				$limit = 10;
			}

			// Convert newlines to HTML line breaks/paragraphs for email.
			$message = wpautop( $message );

			if ( ! $product_id || empty( $subject ) || empty( $message ) ) {
				wp_send_json_error( __( 'Missing required fields.', 'joopeter-email-to-product-buyers-for-woocommerce' ) );
			}

			// Fetch emails directly using robust SQL, or use custom passed list.
			$raw_emails = isset( $_POST['emails'] ) ? (array) $_POST['emails'] : array();
			$custom_emails = array();
			foreach ( $raw_emails as $email ) {
				if ( is_scalar( $email ) ) {
					$custom_emails[] = sanitize_email( wp_unslash( $email ) );
				}
			}
			if ( ! empty( $custom_emails ) ) {
				$emails = $custom_emails;
			} else {
				$emails = $this->get_buyer_emails( $product_id, $statuses );
			}
			$total  = count( $emails );

			if ( empty( $emails ) ) {
				wp_send_json_error( __( 'No valid customer emails found.', 'joopeter-email-to-product-buyers-for-woocommerce' ) );
			}

			$batch_emails = array_slice( $emails, $offset, $limit );

			if ( empty( $batch_emails ) ) {
				wp_send_json_success( array(
					'done'          => true,
					'sent'          => 0,
					'failed'        => 0,
					'failed_emails' => array(),
					'total'         => $total,
					'next_offset'   => $offset,
					'message'       => __( 'All emails have been processed.', 'joopeter-email-to-product-buyers-for-woocommerce' ),
				) );
			}

			// Add basic header info.
			$site_title = wp_strip_all_tags( get_bloginfo( 'name' ) );
			$headers    = array( 'Content-Type: text/html; charset=UTF-8' );
			/* translators: %s: Site title */
			$from_name  = sprintf( __( '%s Shop', 'joopeter-email-to-product-buyers-for-woocommerce' ), $site_title );
			$admin_email = get_option( 'admin_email' );
			$headers[]  = 'From: ' . $from_name . ' <' . $admin_email . '>';

			$emails_sent = 0;
			$emails_failed = 0;
			$failed_recipients = array();

			// Send the emails.
			foreach ( $batch_emails as $recipient ) {
				$sent = wp_mail( $recipient, $subject, $message, $headers );
				if ( $sent ) {
					$emails_sent++;
					$this->add_email_log( $product_id, $recipient, $subject, $message, 'success' );
				} else {
					$emails_failed++;
					$failed_recipients[] = $recipient;
					$this->add_email_log( $product_id, $recipient, $subject, $message, 'failed' );
				}
			}

			$next_offset = $offset + count( $batch_emails );
			$done = $next_offset >= $total;

			if ( $done ) {
				/* translators: %d: Number of emails successfully sent */
				$msg = sprintf( __( 'Successfully finished sending. Total processed: %d.', 'joopeter-email-to-product-buyers-for-woocommerce' ), $total );
			} else {
				/* translators: 1: Current sent progress, 2: Total recipient count */
				$msg = sprintf( __( 'Sending progress: %1$d of %2$d emails processed...', 'joopeter-email-to-product-buyers-for-woocommerce' ), $next_offset, $total );
			}

			wp_send_json_success( array(
				'done'          => $done,
				'sent'          => $emails_sent,
				'failed'        => $emails_failed,
				'failed_emails' => $failed_recipients,
				'total'         => $total,
				'next_offset'   => $next_offset,
				'message'       => $msg,
			) );
		}

		/**
		 * Clear cache when an order is updated.
		 *
		 * @param int      $order_id Order ID.
		 * @param string   $from     Old status.
		 * @param string   $to       New status.
		 * @param WC_Order $order    Order object.
		 */
		public function clear_cache_on_order_change( $order_id, $from, $to, $order ) {
			if ( ! function_exists( 'wc_get_order' ) ) {
				return;
			}

			// We only care if an order enters or leaves processing/completed/on-hold.
			$relevant_statuses = array( 'processing', 'completed', 'on-hold', 'wc-processing', 'wc-completed', 'wc-on-hold' );

			if ( ! in_array( $to, $relevant_statuses, true ) && ! in_array( $from, $relevant_statuses, true ) ) {
				return;
			}

			if ( ! $order ) {
				$order = wc_get_order( $order_id );
			}

			if ( ! $order ) {
				return;
			}

			// Loop through order items to clear cache for specific products.
			foreach ( $order->get_items() as $item ) {
				$product_id = $item->get_product_id();
				$variation_id = $item->get_variation_id();

				// Clear cache for main product ID.
				if ( $product_id ) {
					wp_cache_delete( 'JOOPETEREWB_buyer_emails_' . $product_id, self::CACHE_GROUP );
				}

				// If variation, also clear cache (though we usually query by parent).
				if ( $variation_id ) {
					wp_cache_delete( 'JOOPETEREWB_buyer_emails_' . $variation_id, self::CACHE_GROUP );
				}
			}
		}

		/**
		 * Helper to get count of unique buyers for a product.
		 *
		 * @param int   $product_id
		 * @param array $statuses
		 * @return int
		 */
		private function get_buyer_count( $product_id, $statuses = array( 'processing', 'completed', 'on-hold' ) ) {
			// We can now just count the array from the main function, which is cached.
			$emails = $this->get_buyer_emails( $product_id, $statuses );
			return count( $emails );
		}

		/**
		 * Helper to get unique buyer emails using Direct SQL.
		 * Bypasses WC abstract functions to ensure we check the correct tables.
		 * Includes caching to satisfy linter and improve performance.
		 *
		 * @param int   $product_id
		 * @param array $statuses
		 * @return array
		 */
		private function get_buyer_emails( $product_id, $statuses = array( 'processing', 'completed', 'on-hold' ) ) {
			if ( ! class_exists( 'WooCommerce' ) ) {
				return array();
			}

			global $wpdb;

			$cache_key = 'JOOPETEREWB_buyer_emails_' . $product_id;
			$cache_data = wp_cache_get( $cache_key, self::CACHE_GROUP );
			if ( ! is_array( $cache_data ) ) {
				$cache_data = array();
			}

			// Generate a hash based on the sorted statuses
			sort( $statuses );
			$statuses_hash = md5( implode( '_', $statuses ) );

			if ( isset( $cache_data[ $statuses_hash ] ) ) {
				return $cache_data[ $statuses_hash ];
			}

			// 1. Get Product IDs (Main + Variations)
			$product_ids = $this->get_product_ids_including_variations( $product_id );
			$product_ids = array_values( array_filter( array_map( 'absint', $product_ids ) ) );

			if ( empty( $product_ids ) ) {
				$cache_data[ $statuses_hash ] = array();
				wp_cache_set( $cache_key, $cache_data, self::CACHE_GROUP, 300 );
				return array();
			}

			// 2. Step 1: Find candidate Order IDs from items.
			// Construct dynamic placeholders for the IN clause.
			$ids_placeholder = implode( ',', array_fill( 0, count( $product_ids ), '%s' ) );

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
			// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$order_ids = $wpdb->get_col( $wpdb->prepare( "
				SELECT DISTINCT order_items.order_id
				FROM {$wpdb->prefix}woocommerce_order_items as order_items
				LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta ON order_items.order_item_id = order_item_meta.order_item_id
				WHERE order_items.order_item_type = 'line_item'
				AND order_item_meta.meta_key IN ( '_product_id', '_variation_id' )
				AND order_item_meta.meta_value IN ($ids_placeholder)
			", $product_ids ) );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter
			// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

			if ( empty( $order_ids ) ) {
				$cache_data[ $statuses_hash ] = array();
				wp_cache_set( $cache_key, $cache_data, self::CACHE_GROUP, 300 );
				return array();
			}

			// Clean IDs and Prepare Placeholders for Order IDs
			$order_ids = array_map( 'absint', $order_ids );
			// Create string of %d placeholders for the IN clause
			$order_ids_placeholder = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );

			// 3. Step 2: Check Status AND Fetch Email.
			
			// Map statuses to both clean and wc- prefixed for backwards compatibility.
			$all_possible_statuses = array();
			foreach ( $statuses as $status ) {
				$clean = str_replace( 'wc-', '', $status );
				$all_possible_statuses[] = $clean;
				$all_possible_statuses[] = 'wc-' . $clean;
			}
			$all_possible_statuses = array_unique( $all_possible_statuses );
			$status_placeholders = implode( ',', array_fill( 0, count( $all_possible_statuses ), '%s' ) );

			// Legacy Query (wp_posts + wp_postmeta)
			$legacy_args = array_merge( $order_ids, $all_possible_statuses );

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
			// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$legacy_emails = $wpdb->get_col( $wpdb->prepare( "
				SELECT DISTINCT pm.meta_value
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
				WHERE pm.post_id IN ($order_ids_placeholder)
				AND pm.meta_key = '_billing_email'
				AND pm.meta_value != ''
				AND p.post_type = 'shop_order'
				AND p.post_status IN ($status_placeholders)
			", $legacy_args ) );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter
			// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

			// HPOS Query (wp_wc_orders) - Only if table exists.
			$hpos_table = $wpdb->prefix . 'wc_orders';
			$hpos_emails = array();

			// Use prepare for the table check
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
			$hpos_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $hpos_table ) ) === $hpos_table;
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

			if ( $hpos_exists ) {
				$hpos_args = array_merge( $order_ids, $all_possible_statuses );

				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
				// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				$hpos_emails = $wpdb->get_col( $wpdb->prepare( "
					SELECT DISTINCT billing_email
					FROM {$hpos_table}
					WHERE id IN ($order_ids_placeholder)
					AND billing_email != ''
					AND status IN ($status_placeholders)
				", $hpos_args ) );
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
				// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				// phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter
				// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			}

			// Merge and Unique
			$all_emails = array_merge(
				is_array( $legacy_emails ) ? $legacy_emails : array(),
				is_array( $hpos_emails ) ? $hpos_emails : array()
			);

			$final_emails = array_filter( array_unique( $all_emails ), 'is_email' );

			// Store in cache
			$cache_data[ $statuses_hash ] = $final_emails;
			wp_cache_set( $cache_key, $cache_data, self::CACHE_GROUP, 300 );

			return $final_emails;
		}

		/**
		 * Helper to get array of Product ID + Variation IDs if it's a variable product.
		 *
		 * @param int $product_id The main product ID.
		 * @return array
		 */
		private function get_product_ids_including_variations( $product_id ) {
			$product_ids = array( $product_id );
			if ( ! function_exists( 'wc_get_product' ) ) {
				return $product_ids;
			}
			$product     = wc_get_product( $product_id );

			if ( $product && $product->is_type( 'variable' ) ) {
				$children = $product->get_children();
				if ( ! empty( $children ) ) {
					$product_ids = array_merge( $product_ids, $children );
				}
			}

			return array_map( 'absint', $product_ids );
		}

		/**
		 * Register the Tools submenu page.
		 */
		public function register_tools_page() {
			add_management_page(
				__( 'JooPeter Email Panel', 'joopeter-email-to-product-buyers-for-woocommerce' ),
				__( 'JooPeter Email Panel', 'joopeter-email-to-product-buyers-for-woocommerce' ),
				'manage_options',
				'joopeterewb-email-panel',
				array( $this, 'render_tools_page' )
			);
		}

		/**
		 * Handle AJAX request to clear logs.
		 */
		public function handle_clear_logs() {
			check_ajax_referer( 'joopeterewb_email_action', 'security' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( __( 'You do not have permission to perform this action.', 'joopeter-email-to-product-buyers-for-woocommerce' ) );
			}

			update_option( 'joopeterewb_email_logs', array() );

			wp_send_json_success( __( 'All logs have been cleared successfully.', 'joopeter-email-to-product-buyers-for-woocommerce' ) );
		}

		/**
		 * Handle AJAX request to clear successful logs.
		 */
		public function handle_clear_success_logs() {
			check_ajax_referer( 'joopeterewb_email_action', 'security' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( __( 'You do not have permission to perform this action.', 'joopeter-email-to-product-buyers-for-woocommerce' ) );
			}

			$this->prune_expired_logs();

			$logs = get_option( 'joopeterewb_email_logs', array() );
			if ( ! is_array( $logs ) ) {
				$logs = array();
			}

			$filtered_logs = array();
			foreach ( $logs as $log ) {
				if ( ! isset( $log['status'] ) || 'success' !== $log['status'] ) {
					$filtered_logs[] = $log;
				}
			}

			update_option( 'joopeterewb_email_logs', $filtered_logs );

			wp_send_json_success( __( 'Successful logs have been cleared successfully.', 'joopeter-email-to-product-buyers-for-woocommerce' ) );
		}

		/**
		 * Handle AJAX request to retry failed emails.
		 */
		public function handle_retry_failed_emails() {
			check_ajax_referer( 'joopeterewb_email_action', 'security' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( __( 'You do not have permission to perform this action.', 'joopeter-email-to-product-buyers-for-woocommerce' ) );
			}

			$this->prune_expired_logs();

			$logs = get_option( 'joopeterewb_email_logs', array() );
			if ( ! is_array( $logs ) || empty( $logs ) ) {
				wp_send_json_error( __( 'No logs available to retry.', 'joopeter-email-to-product-buyers-for-woocommerce' ) );
			}

			$site_title = wp_strip_all_tags( get_bloginfo( 'name' ) );
			$headers    = array( 'Content-Type: text/html; charset=UTF-8' );
			/* translators: %s: Site title */
			$from_name  = sprintf( __( '%s Shop', 'joopeter-email-to-product-buyers-for-woocommerce' ), $site_title );
			$admin_email = get_option( 'admin_email' );
			$headers[]  = 'From: ' . $from_name . ' <' . $admin_email . '>';

			$retry_count = 0;
			$success_count = 0;

			foreach ( $logs as $key => $log ) {
				if ( isset( $log['status'] ) && 'failed' === $log['status'] ) {
					$retry_count++;
					$recipient = $log['recipient'];
					$subject   = $log['subject'];
					$message   = $log['message'];

					$sent = wp_mail( $recipient, $subject, $message, $headers );
					if ( $sent ) {
						$success_count++;
						$logs[ $key ]['status']    = 'success';
						$logs[ $key ]['timestamp'] = current_time( 'mysql' );
						
						// Update/set the sender to current logged in admin who clicked retry.
						$current_user = wp_get_current_user();
						$logs[ $key ]['sender'] = $current_user->exists() ? $current_user->display_name : __( 'System', 'joopeter-email-to-product-buyers-for-woocommerce' );
					}
				}
			}

			if ( $retry_count === 0 ) {
				wp_send_json_error( __( 'No failed emails found to retry.', 'joopeter-email-to-product-buyers-for-woocommerce' ) );
			}

			update_option( 'joopeterewb_email_logs', $logs );

			/* translators: 1: Succeeded retries count, 2: Total retries count */
			$message = sprintf( __( 'Retried %2$d failed emails. %1$d succeeded.', 'joopeter-email-to-product-buyers-for-woocommerce' ), $success_count, $retry_count );
			wp_send_json_success( $message );
		}

		/**
		 * Helper to log sent emails.
		 *
		 * @param int    $product_id The product ID.
		 * @param string $recipient  The recipient email address.
		 * @param string $subject    The email subject.
		 * @param string $message    The email body.
		 * @param string $status     The status ('success' or 'failed').
		 */
		private function add_email_log( $product_id, $recipient, $subject, $message, $status ) {
			$this->prune_expired_logs();

			$logs = get_option( 'joopeterewb_email_logs', array() );
			if ( ! is_array( $logs ) ) {
				$logs = array();
			}

			$product_title = get_the_title( $product_id );
			if ( ! $product_title ) {
				/* translators: %d: Product ID */
				$product_title = sprintf( __( 'Product #%d', 'joopeter-email-to-product-buyers-for-woocommerce' ), $product_id );
			}

			$current_user = wp_get_current_user();
			$sender = $current_user->exists() ? $current_user->display_name : __( 'System', 'joopeter-email-to-product-buyers-for-woocommerce' );

			// Add new entry at the beginning.
			array_unshift( $logs, array(
				'timestamp'    => current_time( 'mysql' ),
				'product_id'   => $product_id,
				'product_name' => $product_title,
				'recipient'    => $recipient,
				'subject'      => $subject,
				'message'      => $message,
				'status'       => $status,
				'sender'       => $sender,
			) );

			// Limit to 500 entries.
			if ( count( $logs ) > 500 ) {
				$logs = array_slice( $logs, 0, 500 );
			}

			update_option( 'joopeterewb_email_logs', $logs );
		}

		/**
		 * Prune expired log entries based on settings.
		 */
		private function prune_expired_logs() {
			$expiry_days = (int) get_option( 'joopeterewb_log_expiry_days', 30 );
			if ( 0 === $expiry_days ) {
				return;
			}

			$logs = get_option( 'joopeterewb_email_logs', array() );
			if ( ! is_array( $logs ) || empty( $logs ) ) {
				return;
			}

			$cutoff_time = time() + (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) - ( $expiry_days * DAY_IN_SECONDS );
			$updated = false;

			foreach ( $logs as $key => $log ) {
				if ( ! isset( $log['timestamp'] ) ) {
					continue;
				}
				$log_time = mysql2date( 'U', $log['timestamp'] );
				if ( $log_time < $cutoff_time ) {
					unset( $logs[ $key ] );
					$updated = true;
				}
			}

			if ( $updated ) {
				$logs = array_values( $logs ); // Re-index array
				update_option( 'joopeterewb_email_logs', $logs );
			}
		}


		/**
		 * Render the Tools submenu page.
		 */
		public function render_tools_page() {
			$this->prune_expired_logs();

			$logs = get_option( 'joopeterewb_email_logs', array() );
			if ( ! is_array( $logs ) ) {
				$logs = array();
			}

			$total_sent = count( $logs );
			$success_count = 0;
			$failed_count = 0;
			foreach ( $logs as $log ) {
				if ( isset( $log['status'] ) && 'success' === $log['status'] ) {
					$success_count++;
				} else {
					$failed_count++;
				}
			}

			// Settings variables.
			$all_roles = wp_roles()->get_names();
			$allowed_roles = get_option( 'joopeterewb_allowed_roles', array( 'administrator', 'shop_manager' ) );
			if ( ! is_array( $allowed_roles ) ) {
				$allowed_roles = array( 'administrator', 'shop_manager' );
			}
			// Always ensure administrator is in the list
			if ( ! in_array( 'administrator', $allowed_roles, true ) ) {
				$allowed_roles[] = 'administrator';
			}
			?>
			<div class="wrap joopeterewb-logs-page-wrapper">
				<div class="joopeterewb-header-banner">
					<div class="joopeterewb-header-content">
						<h1><?php esc_html_e( 'JooPeter Email Control Center', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?></h1>
						<p><?php esc_html_e( 'Manage settings and review sent email logs for your WooCommerce product buyers.', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?></p>
					</div>
				</div>

				<!-- Tab Navigation -->
				<div class="joopeterewb-nav-tabs">
					<button type="button" class="joopeterewb-tab-nav-btn active" data-tab="logs-panel">
						<span class="dashicons dashicons-list-view"></span> <?php esc_html_e( 'Sent Email Logs', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?>
					</button>
					<button type="button" class="joopeterewb-tab-nav-btn" data-tab="settings-panel">
						<span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e( 'Permissions & Settings', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?>
					</button>
				</div>

				<!-- Tab 1: Logs Panel -->
				<div class="joopeterewb-tab-panel active" id="joopeterewb-tab-logs-panel">
					<div class="joopeterewb-panel-header-actions">
						<h2><?php esc_html_e( 'Sent Email Logs History', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?></h2>
						<div class="joopeterewb-action-buttons">
							<button type="button" id="joopeterewb_retry_failed_btn" class="button button-primary joopeterewb-retry-btn" <?php echo ( $failed_count === 0 ) ? 'disabled' : ''; ?>>
								<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Retry Failed Emails', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?>
							</button>
							<button type="button" id="joopeterewb_clear_success_btn" class="button joopeterewb-clear-success-btn" <?php echo ( $success_count === 0 ) ? 'disabled' : ''; ?>>
								<span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Clear Success Logs', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?>
							</button>
							<button type="button" id="joopeterewb_clear_logs_btn" class="button joopeterewb-clear-btn" <?php echo empty( $logs ) ? 'disabled' : ''; ?>>
								<span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Clear All Logs', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?>
							</button>
						</div>
					</div>

					<div class="joopeterewb-stats-container">
						<div class="joopeterewb-stat-card joopeterewb-stat-total">
							<div class="joopeterewb-stat-icon">
								<span class="dashicons dashicons-email"></span>
							</div>
							<div class="joopeterewb-stat-info">
								<h3><?php echo esc_html( $total_sent ); ?></h3>
								<p><?php esc_html_e( 'Total Sent', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?></p>
							</div>
						</div>

						<div class="joopeterewb-stat-card joopeterewb-stat-success">
							<div class="joopeterewb-stat-icon">
								<span class="dashicons dashicons-yes-alt"></span>
							</div>
							<div class="joopeterewb-stat-info">
								<h3><?php echo esc_html( $success_count ); ?></h3>
								<p><?php esc_html_e( 'Successful', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?></p>
							</div>
						</div>

						<div class="joopeterewb-stat-card joopeterewb-stat-failed">
							<div class="joopeterewb-stat-icon">
								<span class="dashicons dashicons-warning"></span>
							</div>
							<div class="joopeterewb-stat-info">
								<h3><?php echo esc_html( $failed_count ); ?></h3>
								<p><?php esc_html_e( 'Failed', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?></p>
							</div>
						</div>
					</div>

					<div class="joopeterewb-filter-bar">
						<div class="joopeterewb-search-box-wrapper">
							<span class="dashicons dashicons-search"></span>
							<input type="text" id="joopeterewb_log_search" placeholder="<?php esc_attr_e( 'Search by email, sender or subject...', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?>">
						</div>
						<div class="joopeterewb-filter-status-wrapper">
							<label for="joopeterewb_log_status_filter"><?php esc_html_e( 'Filter Status:', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?></label>
							<select id="joopeterewb_log_status_filter">
								<option value="all"><?php esc_html_e( 'All', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?></option>
								<option value="success"><?php esc_html_e( 'Success', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?></option>
								<option value="failed"><?php esc_html_e( 'Failed', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?></option>
							</select>
						</div>
					</div>

					<div class="joopeterewb-table-container">
						<table class="wp-list-table widefat fixed striped table-view-list joopeterewb-logs-table">
							<thead>
								<tr>
									<th scope="col" class="manage-column column-date"><?php esc_html_e( 'Date & Time', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?></th>
									<th scope="col" class="manage-column column-sender"><?php esc_html_e( 'Sent By', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?></th>
									<th scope="col" class="manage-column column-recipient"><?php esc_html_e( 'Recipient', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?></th>
									<th scope="col" class="manage-column column-product"><?php esc_html_e( 'Product', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?></th>
									<th scope="col" class="manage-column column-subject"><?php esc_html_e( 'Subject', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?></th>
									<th scope="col" class="manage-column column-status"><?php esc_html_e( 'Status', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?></th>
									<th scope="col" class="manage-column column-actions"><?php esc_html_e( 'Action', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?></th>
								</tr>
							</thead>
							<tbody id="joopeterewb-logs-tbody">
								<?php if ( empty( $logs ) ) : ?>
									<tr class="no-items">
										<td colspan="7" class="colspanchange">
											<?php esc_html_e( 'No email logs found.', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?>
										</td>
									</tr>
								<?php else : ?>
									<?php foreach ( $logs as $index => $log ) : ?>
										<tr class="joopeterewb-log-row" data-status="<?php echo esc_attr( $log['status'] ); ?>" data-search="<?php echo esc_attr( strtolower( $log['recipient'] . ' ' . $log['subject'] . ' ' . ( isset( $log['sender'] ) ? $log['sender'] : 'system' ) . ' ' . $log['product_name'] ) ); ?>">
											<td class="column-date">
												<?php echo esc_html( $log['timestamp'] ); ?>
											</td>
											<td class="column-sender">
												<?php echo esc_html( isset( $log['sender'] ) ? $log['sender'] : __( 'System', 'joopeter-email-to-product-buyers-for-woocommerce' ) ); ?>
											</td>
											<td class="column-recipient">
												<strong><?php echo esc_html( $log['recipient'] ); ?></strong>
											</td>
											<td class="column-product">
												<a href="<?php echo esc_url( get_edit_post_link( $log['product_id'] ) ); ?>" target="_blank">
													<?php echo esc_html( $log['product_name'] ); ?>
												</a>
											</td>
											<td class="column-subject">
												<?php echo esc_html( $log['subject'] ); ?>
											</td>
											<td class="column-status">
												<span class="joopeterewb-status-badge joopeterewb-status-<?php echo esc_attr( $log['status'] ); ?>">
													<?php echo esc_html( ucfirst( $log['status'] ) ); ?>
												</span>
											</td>
											<td class="column-actions">
												<button type="button" class="button button-small joopeterewb-view-msg-btn" data-index="<?php echo esc_attr( $index ); ?>">
													<?php esc_html_e( 'View Message', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?>
												</button>
												<div class="joopeterewb-hidden-message" id="joopeterewb-msg-<?php echo esc_attr( $index ); ?>" style="display:none;">
													<?php echo wp_kses_post( $log['message'] ); ?>
												</div>
											</td>
										</tr>
									<?php endforeach; ?>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>

				<!-- Tab 2: Settings Panel -->
				<div class="joopeterewb-tab-panel" id="joopeterewb-tab-settings-panel" style="display:none;">
					<div class="joopeterewb-settings-card">
						<h2><?php esc_html_e( 'Role Access Configuration', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?></h2>
						<p class="description">
							<?php esc_html_e( 'Select which user roles are permitted to see the product editor meta box and send custom emails to product buyers.', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?>
						</p>

						<form id="joopeterewb-settings-form">
							<div class="joopeterewb-roles-grid">
								<?php foreach ( $all_roles as $role_slug => $role_name ) : ?>
									<?php
									$is_admin = 'administrator' === $role_slug;
									$is_checked = in_array( $role_slug, $allowed_roles, true ) || $is_admin;
									?>
									<label class="joopeterewb-role-checkbox-label">
										<input type="checkbox" name="allowed_roles[]" value="<?php echo esc_attr( $role_slug ); ?>" <?php checked( $is_checked ); ?> <?php disabled( $is_admin ); ?>>
										<span><?php echo esc_html( $role_name ); ?></span>
										<?php if ( $is_admin ) : ?>
											<span class="joopeterewb-badge-required"><?php esc_html_e( 'Always Allowed', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?></span>
										<?php endif; ?>
									</label>
								<?php endforeach; ?>
							</div>

							<div class="joopeterewb-settings-section" style="margin-top: 30px; border-top: 1px solid #dcdcde; padding-top: 24px;">
								<h2><?php esc_html_e( 'Log Retention Settings', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?></h2>
								<p class="description">
									<?php esc_html_e( 'Configure how many days email logs should be retained before they are automatically deleted.', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?>
								</p>
								<div class="joopeterewb-settings-field" style="margin-bottom: 20px;">
									<label for="joopeterewb_log_expiry_days" style="font-weight:600; display:block; margin-bottom:8px;"><?php esc_html_e( 'Keep Logs For:', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?></label>
									<?php $expiry_days = (int) get_option( 'joopeterewb_log_expiry_days', 30 ); ?>
									<select id="joopeterewb_log_expiry_days" name="log_expiry_days" style="min-width: 200px; padding: 6px; border-radius: 4px; border: 1px solid #8c8f94;">
										<option value="0" <?php selected( 0 === $expiry_days ); ?>><?php esc_html_e( 'Keep Forever', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?></option>
										<option value="7" <?php selected( 7 === $expiry_days ); ?>><?php esc_html_e( '7 Days', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?></option>
										<option value="14" <?php selected( 14 === $expiry_days ); ?>><?php esc_html_e( '14 Days', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?></option>
										<option value="30" <?php selected( 30 === $expiry_days ); ?>><?php esc_html_e( '30 Days', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?></option>
										<option value="90" <?php selected( 90 === $expiry_days ); ?>><?php esc_html_e( '90 Days', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?></option>
										<option value="180" <?php selected( 180 === $expiry_days ); ?>><?php esc_html_e( '180 Days', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?></option>
									</select>
								</div>
							</div>

							<div id="joopeterewb-settings-feedback"></div>

							<div class="joopeterewb-settings-footer">
								<button type="submit" id="joopeterewb_save_settings_btn" class="button button-primary button-hero">
									<span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save Settings', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?>
								</button>
								<span class="spinner" id="joopeterewb_settings_spinner"></span>
							</div>
						</form>
					</div>
				</div>
			</div>

			<!-- Modern Modal Dialog for message preview -->
			<div id="joopeterewb-preview-modal" class="joopeterewb-modal">
				<div class="joopeterewb-modal-content">
					<div class="joopeterewb-modal-header">
						<h2><?php esc_html_e( 'Email Message Content', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?></h2>
						<span class="joopeterewb-modal-close">&times;</span>
					</div>
					<div class="joopeterewb-modal-meta">
						<p><strong><?php esc_html_e( 'Recipient:', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?></strong> <span id="joopeterewb-modal-recipient"></span></p>
						<p><strong><?php esc_html_e( 'Subject:', 'joopeter-email-to-product-buyers-for-woocommerce' ); ?></strong> <span id="joopeterewb-modal-subject"></span></p>
					</div>
					<div class="joopeterewb-modal-body" id="joopeterewb-modal-message-body">
						<!-- Message content will be loaded here -->
					</div>
				</div>
			</div>
			<?php
		}

		/**
		 * Check if the current user has access to plugin features based on their role.
		 *
		 * @return bool
		 */
		private function current_user_has_access() {
			if ( ! is_user_logged_in() ) {
				return false;
			}

			$allowed_roles = get_option( 'joopeterewb_allowed_roles', array( 'administrator', 'shop_manager' ) );
			if ( ! is_array( $allowed_roles ) ) {
				$allowed_roles = array( 'administrator', 'shop_manager' );
			}

			// Administrators must always have access.
			if ( ! in_array( 'administrator', $allowed_roles, true ) ) {
				$allowed_roles[] = 'administrator';
			}

			$user = wp_get_current_user();
			$user_roles = (array) $user->roles;

			$intersect = array_intersect( $user_roles, $allowed_roles );
			return ! empty( $intersect );
		}

		/**
		 * Handle AJAX request to save settings.
		 */
		public function handle_save_settings() {
			check_ajax_referer( 'joopeterewb_email_action', 'security' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( __( 'You do not have permission to perform this action.', 'joopeter-email-to-product-buyers-for-woocommerce' ) );
			}

			$raw_roles = isset( $_POST['roles'] ) ? (array) $_POST['roles'] : array( 'administrator' );
			$roles     = array();
			foreach ( $raw_roles as $role ) {
				if ( is_scalar( $role ) ) {
					$roles[] = sanitize_key( $role );
				}
			}
			
			// Always enforce administrator.
			if ( ! in_array( 'administrator', $roles, true ) ) {
				$roles[] = 'administrator';
			}

			update_option( 'joopeterewb_allowed_roles', $roles );

			// Handle log expiry setting.
			if ( isset( $_POST['log_expiry_days'] ) && is_scalar( $_POST['log_expiry_days'] ) ) {
				$expiry_days = absint( $_POST['log_expiry_days'] );
				update_option( 'joopeterewb_log_expiry_days', $expiry_days );
			}

			$this->prune_expired_logs();

			wp_send_json_success( __( 'Settings saved successfully.', 'joopeter-email-to-product-buyers-for-woocommerce' ) );
		}

	}
}

