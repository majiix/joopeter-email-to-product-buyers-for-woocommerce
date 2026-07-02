# Project Overview

JooPeter Email to Product Buyers for WooCommerce is a WordPress plugin that adds a meta box to the WooCommerce product edit screen. This enables store administrators and other permitted roles to send customized emails to customers who bought that specific product.

## Tech Stack
- **PHP**: 7.4+
- **WordPress**: 6.0+
- **WooCommerce**: 6.0+ (supports HPOS / Custom Order Tables)
- **JavaScript**: jQuery (standard WordPress bundle)
- **CSS**: Vanilla CSS

## Architecture
1. **Bootstrap File (`joopeter-email-to-product-buyers-for-woocommerce.php`)**: Initializes the plugin and handles basic checks (e.g., verifying if WooCommerce is active) before instantiating the main class.
2. **Main Class (`includes/class-joopeter-email-to-product-buyers.php`)**: Registers all action and filter hooks, processes AJAX endpoints (sending emails, saving settings, clearing/retrying logs), renders metaboxes, and queries order/customer data.
3. **Admin Assets**: `assets/js/admin.js` and `assets/css/admin.css` handle the tabbed dashboard interface, modals, batch sending logic, and styling.

## Verification
- Lint check: `php -l <file>` to verify no syntax errors.
