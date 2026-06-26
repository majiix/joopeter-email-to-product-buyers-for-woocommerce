<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Joopeterewb_Email_To_Product_Buyers
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/*
 * Clean up plugin settings and logs from the options database table.
 */
delete_option( 'joopeterewb_email_logs' );
delete_option( 'joopeterewb_allowed_roles' );
delete_option( 'joopeterewb_log_expiry_days' );