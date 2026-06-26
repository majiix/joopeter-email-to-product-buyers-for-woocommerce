=== JooPeter Email to Product Buyers for WooCommerce ===
Contributors: micromax2
Tags: woocommerce, email, product, customers, marketing
Requires at least: 5.8
Tested up to: 7.0
Stable tag: 1.3.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Send custom emails to customers who bought a WooCommerce product, with logs tracking, user role permissions, and an email delivery control center.

== Description ==

JooPeter Email to Product Buyers for WooCommerce is a lightweight and efficient plugin that helps store owners communicate directly with their customers. It adds a convenient meta box to the WooCommerce product edit screen, allowing you to send custom emails specifically to users who have purchased that product.

Key Features:

* **Targeted Emails**: Automatically fetches customers who have purchased the current product.
* **Dynamic Order Status Chips**: Toggle order status filters (Processing, On Hold, Completed) with real-time customer counts.
* **Chunked AJAX Batch Sending**: Dispatches emails in batches (e.g. 10 at a time) to prevent server timeout errors and spikes.
* **Control Center (JooPeter Email Panel)**: Centralized admin dashboard under Tools menu to manage logs and settings.
* **Email Logs History**: Tracks total, successful, and failed email dispatches with statistical cards.
* **Send Countdown Delay**: Prevent accidental sends with a cancelable 10-second countdown before dispatches.
* **Bulk Retry**: Bulk retry sending all failed emails with a single click.
* **Selective Clearing**: Clear only successful email logs, keeping failed logs for troubleshooting.
* **Sender Tracking**: Captures and logs the display name of the WordPress user who sent each email.
* **Log Retention Settings**: Automatically prune expired logs after 7, 14, 30, 90, or 180 days, or keep them forever.
* **Access Permissions**: Configure which user roles are permitted to see the product editor meta box and send emails.
* **Duplicate Protection**: Ensures each customer receives the email only once, even if they bought the product multiple times.
* **Performance**: Uses object caching to minimize database load on large stores.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/joopeter-email-to-product-buyers-for-woocommerce` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Configure role permissions under **Tools > JooPeter Email Panel > Permissions & Settings**.
4. Navigate to any WooCommerce Product edit page to find the "Email Customers Who Bought This Product" meta box in the sidebar.

== Frequently Asked Questions ==

= Does this save a log of sent emails? =
Yes, the plugin saves a history of sent emails inside the **JooPeter Email Panel** (accessible under the **Tools** menu). It logs the date/time, recipient, product, subject, sender's name, and delivery status (success/failed).

= Can I retry failed emails? =
Yes! The Control Center provides a **Retry Failed Emails** bulk action button that will resend all emails marked as failed.

= Can I control who can send emails? =
Yes, under the Permissions & Settings tab in the Control Center, administrators can select which user roles have access to the product editor email composer meta box.

= How long are logs saved? =
You can customize log retention settings under the Permissions & Settings tab to automatically prune logs older than 7, 14, 30, 90, or 180 days. You can also disable pruning by selecting "Keep Forever".

= Can I cancel a send after clicking the button? =
Yes, the plugin features a cancelable 10-second countdown. If you click send and realize there is an issue, you can click the red "Cancel Send" button before the timer hits zero to safely abort the send.

== Changelog ==

= 1.3.1 =
* Fixed ReferenceError: joopeterewb_params is not defined in administrative scripts.

= 1.3.0 =
* Fixed short description length to satisfy WordPress.org requirements (under 150 characters).
* Refactored email dispatcher to use chunked AJAX batch sending (10 emails per request) to prevent PHP timeout errors and mail server congestion.

= 1.2.0 =
* Added a 10-second cancelable countdown before sending emails to prevent accidental sends.
* Added a bulk "Retry Failed Emails" action button in the Tools control center.
* Added a selective "Clear Success Logs" button to retain failed dispatches.
* Added sender tracking to record the name of the user who sent each email.
* Added automatic log expiration and pruning settings (7, 14, 30, 90, 180 days, or Forever).
* Enhanced Control Center UI with flex button layouts and responsive columns.

= 1.1.0 =
* Rebranded and renamed the plugin to JooPeter Email to Product Buyers for WooCommerce.
* Refactored all classes, functions, constants, translation text domains, CSS classes, and JS parameters to prevent naming collisions.

= 1.0.1 =
* Refactored code into a modular structure (separate CSS/JS/PHP).
* Added object caching for database queries to improve performance.
* Added customer count display in the meta box.
* Replaced text area with WP Editor.

= 1.0.0 =
* Initial release.
