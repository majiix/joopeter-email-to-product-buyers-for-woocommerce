# Changelog

## [1.3.4] - 2026-07-02
### Changed
- Hardened user input sanitization in $_POST AJAX handlers (statuses, emails, and roles) using `map_deep()` with `sanitize_key()` and `sanitize_email()` to satisfy WordPress coding standards and prevent security warnings.

## [1.3.3] - 2026-07-02
### Changed
- Refactored bootstrap process to hook plugin loading to `plugins_loaded`.
- Added check for WooCommerce class existence during initialization to prevent errors if WooCommerce is deactivated.
- Hardened PHP 8+ compatibility in all AJAX handlers by ensuring elements in `$_POST` array are scalar and unslashed (`wp_unslash()`) before passing to WordPress sanitization functions (`sanitize_key`, `sanitize_text_field`, `wp_kses_post`, `sanitize_email`), preventing TypeError Fatal Errors and fixing WordPress security warnings.
- Added verification of `$post` variable instance to be `WP_Post` in `enqueue_assets` and `render_meta_box` to prevent property notices.
- Hardened order change callback `clear_cache_on_order_change` and variation helper `get_product_ids_including_variations` with function checks (`function_exists`) to prevent fatal errors when WooCommerce functions are unavailable.
- Integrated `is_email` filter into database email retrieval to remove malformed or empty database values.
- Replaced deprecated `current_time( 'timestamp' )` call in log pruning with warning-free, standard timezone calculation.


Step 3:
1. Redesigned assets/css/admin.css with modern design variables, slate text colors, glowing circular stats card icons, active checklist role cards, smooth transitions, and drawing checkmark keyframe animations.
2. Refactored assets/js/admin.js to animate numerical counters on page load, transition a linear progress countdown bar from 100% to 0% during the 10-second delay, close message modals on pressing the Escape key, and render a custom drawing checkmark SVG celebration upon batch email completion.
3. Modified includes/class-joopeter-email-to-product-buyers.php to render a dedicated, beautiful empty state layout with a custom paper plane SVG illustration when email logs are empty.
Commit message: style(admin): upgrade dashboard and metabox UI/UX with premium styles and interactive delights
