# JooPeter Email to Product Buyers for WooCommerce

## Overview
JooPeter Email to Product Buyers for WooCommerce is a WordPress plugin that adds a meta box to the WooCommerce product edit screen. This enables store administrators and other permitted roles to send customized emails to customers who bought that specific product.

## Tech Stack
- **PHP**: 7.4+ (Compatible with PHP 8.1 - 8.3+)
- **WordPress**: 6.0+ (Tested up to 7.1)
- **WooCommerce**: 6.0+ (Tested up to 11.0, supports HPOS / Custom Order Tables)
- **Static Analysis**: PHPStan Level 6 with `szepeviktor/phpstan-wordpress` and `php-stubs/woocommerce-stubs`
- **JavaScript**: jQuery (standard WordPress bundle)
- **CSS**: Vanilla CSS

## Architecture
1. **Bootstrap File ([joopeter-email-to-product-buyers-for-woocommerce.php](../joopeter-email-to-product-buyers-for-woocommerce.php))**: Initializes the plugin, registers `plugin_action_links_` shortcuts, and handles basic checks (e.g., verifying if WooCommerce is active) before instantiating the main class.
2. **Main Class ([includes/class-joopeter-email-to-product-buyers.php](../includes/class-joopeter-email-to-product-buyers.php))**: Scopes admin/AJAX hooks inside `is_admin()`, processes AJAX endpoints (sending emails, saving settings, clearing/retrying logs), renders metaboxes, and runs optimized direct JOIN customer queries.
3. **Admin Assets**: `assets/js/admin.js` and `assets/css/admin.css` handle the tabbed dashboard interface, modals, batch sending logic, and styling.

## Current Features
- **Product-Specific Emails**: Send custom emails targeting customers of a specific WooCommerce product.
- **Order Status Filters**: Select recipients based on their order statuses (Processing, On Hold, Completed).
- **Batch Sending**: Process emails in chunks to prevent server timeouts and resource limits.
- **Sending Delay**: 10-second cancelable delay before starting the batch processing.
- **Email History Logs**: Track sent emails, timestamps, senders, and delivery status (Success / Failed).
- **Multibyte UTF-8 Search**: Log search supporting internationalized scripts via `mb_strtolower`.
- **Retry Mechanism**: Re-send failed emails directly from the logs.
- **Log Retention Config**: Set logs to auto-expire after a certain number of days (7, 14, 30, 90, 180, or keep forever).
- **Access Controls & Security**:
  - Role-based permissions configuration.
  - Per-product authorization check (`current_user_can('edit_post', $product_id)`) on AJAX actions.
  - Nonces and user capability validation on all endpoints.
- **Admin Action Links**: Direct "Settings" shortcut link on `plugins.php` for quick navigation to the control center.
- **High-Performance Caching & Optimized DB Architecture**:
  - Single-pass direct indexed `JOIN` query for buyer retrieval (eliminating multi-stage PHP array allocations).
  - Single-write batch persistence for email logs (`add_email_logs_batch`), eliminating N+1 database option writes.
  - Scoped admin hook registrations behind `is_admin()` to keep frontend page views lean.
  - Cache deduplication in order updates (`clear_product_cache_for_order`).
  - Direct integration with WooCommerce `OrderUtil::custom_orders_table_usage_is_enabled()`.
  - Non-autoloading logs (`joopeterewb_email_logs` has autoload = 'no') for site speed.
  - Object caching for customer email queries (`wp_cache_get` / `wp_cache_set`).
  - Cache invalidation on order status changes and updates (`woocommerce_update_order`).

## Verification Commands
- PHP Syntax Check:
  ```bash
  rtk php -l joopeter-email-to-product-buyers-for-woocommerce.php
  rtk php -l includes/class-joopeter-email-to-product-buyers.php
  rtk php -l uninstall.php
  ```

- Static Analysis (when dev dependencies are installed):
  ```bash
  rtk composer install
  vendor/bin/phpstan analyse
  ```
