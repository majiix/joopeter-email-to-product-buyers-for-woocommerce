# JooPeter Email to Product Buyers for WooCommerce

## Overview
JooPeter Email to Product Buyers for WooCommerce is a WordPress plugin that adds a meta box to the WooCommerce product edit screen. This enables store administrators and other permitted roles to send customized emails to customers who bought that specific product.

## Tech Stack
- **PHP**: 7.4+
- **WordPress**: 6.0+
- **WooCommerce**: 6.0+ (supports HPOS / Custom Order Tables)
- **JavaScript**: jQuery (standard WordPress bundle)
- **CSS**: Vanilla CSS

## Architecture
1. **Bootstrap File ([joopeter-email-to-product-buyers-for-woocommerce.php](../joopeter-email-to-product-buyers-for-woocommerce.php))**: Initializes the plugin and handles basic checks (e.g., verifying if WooCommerce is active) before instantiating the main class.
2. **Main Class ([includes/class-joopeter-email-to-product-buyers.php](../includes/class-joopeter-email-to-product-buyers.php))**: Registers action/filter hooks, processes AJAX endpoints (sending emails, saving settings, clearing/retrying logs), renders metaboxes, and queries order/customer data.
3. **Admin Assets**: `assets/js/admin.js` and `assets/css/admin.css` handle the tabbed dashboard interface, modals, batch sending logic, and styling.

## Current Features
- **Product-Specific Emails**: Send custom emails targeting customers of a specific WooCommerce product.
- **Order Status Filters**: Select recipients based on their order statuses (Processing, On Hold, Completed).
- **Batch Sending**: Process emails in chunks to prevent server timeouts and resource limits.
- **Sending Delay**: 10-second cancelable delay before starting the batch processing.
- **Email History Logs**: Track sent emails, timestamps, senders, and delivery status (Success / Failed).
- **Retry Mechanism**: Re-send failed emails directly from the logs.
- **Log Retention Config**: Set logs to auto-expire after a certain number of days (7, 14, 30, 90, 180, or keep forever).
- **Access Controls**: Select user roles (beyond administrator) allowed to send emails and view logs.
- **Search & Filter**: Search logs by email/sender/subject and filter by delivery status.
- **Optimized Caching & DB Queries**:
  - Non-autoloading logs (`joopeterewb_email_logs` has autoload = 'no') for site speed.
  - SQL query optimized with `INNER JOIN` instead of `LEFT JOIN` for meta lookup.
  - Cache invalidation on both order status changes and order updates (`woocommerce_update_order`).

## Verification Commands
- PHP Syntax Check:
  ```bash
  php -l joopeter-email-to-product-buyers-for-woocommerce.php
  php -l includes/class-joopeter-email-to-product-buyers.php
  ```
