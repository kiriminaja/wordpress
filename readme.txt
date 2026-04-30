=== KiriminAja Official ===
Contributors: kiriminaja
Donate link: https://developer.kiriminaja.com/kopi
Tags: shipping, ecommerce, WooCommerce, logistics
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.0
Stable tag: 2.1.15
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Easily integrate with multiple couriers across Indonesia

== Description ==

KiriminAja is a platform that makes it easy to send packages and find expeditions according to people's needs, with COD and Non-COD delivery methods developed by PT Selalu Siap Solusi.

**Key Features:**
* Ease of sending packages with various expedition options.
* COD (Cash On Delivery) delivery service with daily fund disbursement and package pickup system at home by the expedition.
* Non-COD package delivery service with package pickup system at home by the expedition.
* Platform that can help online business people control and manage their business better.
* With the services and innovations offered, KiriminAja is committed to contributing to the Indonesian economy, by providing solutions and convenience for online business people so that their business continues to grow.

This plugin is perfect for eCommerce store owners looking for a hassle-free shipping solution.

== External Services ==

This plugin connects to KiriminAja API services to provide shipping functionality for your WooCommerce store.

**KiriminAja API**
* Service: https://client.kiriminaja.com
* Purpose: Process shipping rates, create shipments, track packages, and manage pickup requests
* Data sent: Shipping addresses, package dimensions and weight, order details, customer information
* When: Every time shipping rates are calculated, when shipments are created, and when tracking packages
* Terms of Service: https://kiriminaja.com/syarat-ketentuan
* Privacy Policy: https://kiriminaja.com/privacy-policy

**KiriminAja Callback (Webhook)**
* Endpoint (on your site): `/?feed=kiriminaja-callback`
* Purpose: Receive shipment/pickup status updates from KiriminAja
* Data received: Package status events including order IDs, AWB, and timestamps
* Authentication: Requires an `Authorization` header; the token is validated against the API key configured in the plugin

**Print.js Library**
* This plugin includes Print.js (https://github.com/crabbly/Print.js) for printing shipping labels
* Source code: https://github.com/crabbly/Print.js
* License: MIT

**Select2 Library**
* This plugin includes Select2 (https://github.com/select2/select2) for searchable select fields
* Source code: https://github.com/select2/select2
* License: MIT

By using this plugin, you acknowledge that your store will communicate with KiriminAja's servers to provide shipping services. Please review KiriminAja's terms of service and privacy policy before using this plugin.

== Installation ==

1. Make sure WooCommerce is installed and activated.
2. Upload the plugin files to the `/wp-content/plugins/kiriminaja-official` directory, or install the plugin through the WordPress plugins screen directly.
3. Activate the plugin through the 'Plugins' screen in WordPress.
4. Go to [app.kiriminaja.com/settings/api-request](https://app.kiriminaja.com/settings/api-request), request integration with WordPress, and copy your Setup Key.
5. Navigate to `KiriminAja > Integration` and enter your Setup Key.
6. Navigate to `KiriminAja > Shipping`, fill in your store address, pin your location on the map, select your area, and save.
7. Optionally, configure your courier whitelist under the Shipping tab to limit which couriers appear at checkout.

== Frequently Asked Questions ==

= How do I set up the plugin? =
1. Install and activate WooCommerce (required).
2. Go to **KiriminAja > Integration** and enter your Setup Key from your KiriminAja dashboard.
3. Go to **KiriminAja > Shipping** and fill in your store address, pin your location on the map, choose your area, and save.
4. Optionally whitelist specific couriers under the Shipping tab.

= Where do I get a Setup Key? =
Register or log in at [kiriminaja.com](https://kiriminaja.com), then go to [app.kiriminaja.com/settings/api-request](https://app.kiriminaja.com/settings/api-request) and request integration with WordPress. Once approved, copy the Setup Key and paste it into the Integration tab inside the plugin.

= Which couriers are supported? =
KiriminAja connects to a wide range of Indonesian couriers — the available options depend on your KiriminAja account. Rates are fetched in real time during checkout, so customers always see up-to-date pricing.

= Can I limit which couriers appear at checkout? =
Yes. In **KiriminAja > Shipping**, use the **Whitelist Expedition** selector to choose only the couriers you want to offer.

= Does the plugin support COD (Cash on Delivery)? =
Yes. When a customer selects COD at checkout, only couriers that support COD are shown. COD orders have a minimum of Rp10,000 and a maximum of Rp3,000,000.

= How do I request a package pickup? =
Go to **KiriminAja > Request Pickup** in your WordPress admin. Select the orders you want picked up, choose a pickup schedule, and confirm. The courier will collect the packages from your store address.

= Can I print shipping labels (resi)? =
Yes. After a pickup is scheduled and an AWB is assigned, you can print shipping labels directly from the Shipping Process page in the plugin.

= How does package tracking work? =
Customers can track their orders through a tracking link that appears on the order confirmation page. KiriminAja also sends status updates back to your store via webhook, automatically updating order statuses.

= Does this plugin support international shipping? =
No. Currently the plugin only supports domestic shipping within Indonesia.

= Is it compatible with WooCommerce HPOS (High-Performance Order Storage)? =
Yes. The plugin fully supports WooCommerce HPOS / Custom Order Tables.

= My shipping rates are not showing at checkout. What should I do? =
Make sure you have completed the Shipping setup (store address and area). Also verify that your products have weight and dimensions set — both are required for rate calculation.

= The plugin says "WooCommerce is not yet installed or activated". =
This plugin requires WooCommerce. Install and activate WooCommerce first, then activate KiriminAja Official.

== Screenshots ==

1. Manage all your transactions in one place — view order details, statuses, and shipping info at a glance
2. Handle shipments effortlessly — monitor delivery progress and track every package
3. Schedule and manage pickups — request courier pickups with just a few clicks
4. AWB generated automatically — no manual input, shipping labels ready to print instantly
5. Simple configuration — connect your store, set your address, and start shipping in minutes

== Links ==

* [View Details](https://kiriminaja.com/solusi/plugin-woocommerce)
* [Support](https://kiriminaja.com/kontak-kami)
* [Developer](https://developer.kiriminaja.com)

== Changelog ==
= 2.1.15 =
* Fix(release.yml): update force_message condition to ensure proper handling of empty values

= 2.1.14 =
* Fix(development.yml): standardize quotes and update exclusions for ZIP archive fix(Makefile): add exclusion for .wordpress-org directory in RSYNC
* Fix(index.php): simplify checkbox attributes for order processing status

= 2.1.13 =
* Feat(release.yml): add deployment step to WordPress.org SVN for plugin distribution
* Feat(gitattributes): add build and dist directories to export-ignore list
* Feat(Map): update location button styling for improved visibility and user experience
* Feat(Map): defer map initialization for improved tab switching experience and enhance location button styling
* Feat(Map): implement lazy initialization for Leaflet map picker to enhance performance and user experience
* Feat(Map): enhance map picker with fixed center pin and geolocation button for improved user experience
* Rename duplicate submenu item to "Settings" for better accessibility
* Update date formatting to include time for order creation and transaction histories
* Replace wp_date with gmdate for consistent timezone formatting in shipping and pickup schedules
* Feat(Makefile): add build directory to rsync excludes for cleaner packaging
* Feat(gmdate): replace gmdate with wp_date for consistent date formatting across services and templates
* Add new icon files for WordPress.org
* Feat(Heartbeat): implement nonce auto-refresh for AJAX requests to maintain session validity
* Feat(Validation): improve data validation by replacing error suppression with empty checks for shipping and setup key values
* Feat(AJAX Handling): enhance error handling and response parsing for integration and callback data
* Feat(Webhooks): update button functionality to save callback URL instead of disconnecting integration
* Style(container): implement feature X to enhance user experience and optimize performance
* Feat(Templates): refactor footer display by creating a shared footer partial for consistent versioning
* Feat(Transaction Process): update order status handling to reflect WooCommerce visibility and prevent pickup of non-processable orders
* Feat(Admin): update sub-page registration logic for WooCommerce compatibility and prevent duplicate menu entries
* Feat(PaymentRepository, TransactionRepository): add methods for counting payments and transactions by status feat(RequestPickupIndex, TransactionProcessIndex): implement status filter counts for improved UX in views
* Feat(Enqueue, TrackingFrontPageController): add legacy shortcode alias for backward compatibility
* Feat(Enqueue, Tracking): add dedicated stylesheet for tracking shortcode to ensure proper styling
* Feat(PaymentRepository, Admin, Helper): add shipment unpaid count and update labels for consistency
* Fix(PageGenerator.php): ensure removal of auto-generated submenu duplicates for better menu management
* Fix(Admin.php): update sub-page registration logic to prevent duplicate entries and ensure proper settings access
* Fix(index.php): trim service name to ensure consistent formatting
* Fix(index.php): adjust order date handling to prevent double timezone conversion
* Fix(TransactionRepository, TransactionProcessIndex): update queries to use wp_posts for accurate transaction counts
* Fix(TransactionRepository): improve transaction count query for accuracy and consistency

= 2.1.12 =
* Fix(Order): update shipping method prefix handling for consistency in CheckoutController and GeneralAjaxController

= 2.1.11 =
* Fix(checkout): enhance order handling and session management in afterCheckoutAfterCreated

= 2.1.10 =
* Feat(make): update makefile to handle composer lock
* Add default pickup action assigned inc/Repositories/KiriminajaApiRepository.php
* Feat(make): update scripts/github-release.php
* Update dimension unit templates/product/general-wc-tab-setting.php
* Handler on kiriofAjax assets/wp/js/kj-wp-script.js
* Enhance release workflow to include official and legacy ZIP builds; update changelog with asset details
* Fix(prefix): correct variable name for admin URL and billing/shipping address consistency
* Update variable names for month options consistency
* Inc/Base/BaseInit.php

= 2.1.9 =
* Enhance asset loading by conditionally enqueuing scripts/styles for WooCommerce pages; improve option retrieval for shipping settings
* Update release workflow to resolve version from tag and create GitHub release; add WooCommerce requirement to plugin metadata
* Simplify WordPress integration workflow by removing matrix strategy and hardcoding versions
* Inject WooCommerce dependency for wp-env in testing workflow
* Update static tests workflow to use PHP 8.1 and streamline plugin check process
* Add WordPress Plugin Check job for compliance validation
* Add direct access restriction to UpdaterController
* Implement forced update mechanism with admin notice and auto-update support in UpdaterController
* Add legacy release variant with UpdaterController support and update workflows accordingly
* Enhance changelog script with version validation and sanitization improvements
* Enhance Makefile with automated release process and usage instructions in README
* Feat(AB#25922): enhance data sanitization by replacing recursive sanitization with map_deep for improved security
* Update version to 2.1.8 and enhance security by hardening inline onclick handlers and re-auditing for compliance

= 2.1.8 =
* Harden inline onclick handlers in templates/request-pickup/view/index.php: switch from JS template literals (backticks) to single-quoted JS strings so esc_js() fully neutralises the interpolated pickup_number value (esc_js does not escape backticks or `${}` template-literal expressions).
* Re-audit pass against the WordPress.org Plugin Directory automated reviewer feedback — confirmed compliance for: privacy/terms URLs (use of /privacy-policy and /syarat-ketentuan), composer.json present in the distributed archive, recursive sanitisation of $_POST['data'] arrays in SettingController, recursive sanitisation of decoded webhook JSON and request headers in CallbackController, nonce + capability check on ShippingProcessController::resiPrint(), wp_kses_post() of wc_cart_totals_shipping_method_label() in templates/woocommerce/cart/cart-shipping.php, no remote CDN assets, no session_start() / ini_set() / date_default_timezone_set() in plugin code, and no raw <script>/<style> tags in templates (all inline assets registered via wp_add_inline_script() / wp_add_inline_style()).

= 2.1.7 =
* Apply WordPress Escaping Data API across templates and controllers — escape late at the point of output:
  - Wrap the order thank-you HTML block with wp_kses_post() in CheckoutController::custom_content_thankyou().
  - Use esc_attr() (instead of esc_html) for HTML attribute values in templates/front/form-billing-address.php.
  - Use esc_js() for values interpolated into inline JavaScript strings/template literals (form-billing-address.php, setting/setuped/index.php).
  - Use absint() for numeric output (item count) in templates/request-pickup/view/index.php.
* Remove unused/debug functions (custom_shipping_content, ts_add_order) from CheckoutController.
* Move the inline <style> block in templates/front/tracking.php into wp_add_inline_style() per the Plugin Handbook (no inline <script>/<style> tags in output).
* Prefix all enqueued script/style handles and JavaScript globals with the kiriof- / kiriof prefix:
  - 'select2', 'kiriminPluginScript', 'kiriminPluginStyle', 'BSGridStyle', 'printCss', 'printJs', 'kj' . 'wc_*' → 'kiriof-script', 'kiriof-style', 'kiriof-select2-script', 'kiriof-select2-style', 'kiriof-grid-style', 'kiriof-print-script', 'kiriof-print-style', 'kiriof-wc-*'.
  - 'kirioAjax' → 'kiriofAjax', 'kjTransactionData' → 'kiriofTransactionData' (in localized data and in the corresponding JS files).
* Prefix unprefixed PHP classes that lived in the global namespace:
  - requestPickupIndex → Kiriof_RequestPickupIndex.
  - kiriof_settingIndex → Kiriof_SettingIndex.
  - AdminWoocommerceSettings → Kiriof_AdminWoocommerceSettings.
* Stop polluting the WP global variable namespace from template index files (request-pickup, transaction-process, setting): drop `global $results / $page / $monthOptions / $approvedSetupKey / $activeTab / $locale / ...` and use ordinary local variables passed into the included view via include scope.

= 2.1.6 =
* Apply context-specific sanitization functions across the plugin per the WordPress Sanitizing Data API:
  - sanitize_textarea_field() for the multi-line origin address.
  - sanitize_key() for whitelist expedition IDs.
  - esc_url_raw() for callback URLs.
  - Numeric coercion (float, non-negative) for product weight/length/width/height before update_post_meta().
* Replace array_map('sanitize_text_field', $_POST['data']) with kiriof_sanitize_recursive(wp_unslash(...)) in GeneralAjaxController::kiriminajaSubdistrictSearch().
* Refactor ProductController::kiriof_save_product_custom_fields() to stop mutating $_POST and add an edit_post capability check.

= 2.1.5 =
* Sanitize all $_POST['data'] arrays in SettingController via kiriof_sanitize_recursive() before passing to services.
* Sanitize webhook JSON payload and headers in CallbackController before handing off to CallbackHandlerService.
* Validate callback URL in storeCallbackData with esc_url_raw() prior to API call and database storage.
* Add nonce verification (kiriof_resi_print) to the transaction-resi-print feed endpoint to prevent CSRF; resi print URLs in templates now include _wpnonce.
* Properly esc_js() pickup_number when interpolated into onclick handlers in request-pickup view template.
* Wrap wc_cart_totals_shipping_method_label() output with wp_kses_post() in cart-shipping.php template.
* Keep composer.json in distributed build (Makefile no longer strips it).

= 2.1.4 =
* Address Copilot code review feedback

= 2.1.3 =
* Fix(ci): correct shipping method ID in WooCommerce integration test
* Fix(ci): use GitHub releases for WooCommerce downloads
* Fix(ci): use vendor/bin/phpunit in Makefile test target
* Exclude dev deps from build, fix test type errors

= 2.1.2 =
* Security improvements: Fixed nonce verification patterns to fail early
* Security improvements: Fixed SQL injection vulnerability in SettingRepository
* Security improvements: Improved input sanitization and output escaping
* Bundled Select2 library locally (no longer loads from CDN)
* Fixed AJAX endpoint URL generation to use WordPress functions
* Replaced file_get_contents with WordPress HTTP API
* Removed PHP session usage
* Added proper documentation for external services
* Updated plugin prefix to meet WordPress.org requirements
* General code quality improvements and WordPress coding standards compliance

= 2.1.0 =
* Comply wordpress.org namespace
= 2.0.16 =
* Fix AJAX endpoint resolution for WordPress installations running in subdirectories.
* Ensure Connect Now and other admin flows use the correct `admin-ajax.php` URL.
* Improve script localization binding so AJAX URL is properly available in plugin JavaScript.
= 2.0.15 =
* Fixing critical issues request pickup for JNE.
= 2.0.13 =
* Fixing critical issues on plugin update process.
= 2.0.12 =
* Fixing critical issues on generating qr content
= 2.0.11 =
* Implement discount on request pickup.
= 2.0.10 =
* Fix request pickup date issue.
= 2.0.9 =
* Fix query tracking on buyer page
= 2.0.8 =
* Fix issue on webhook handler.
* Update to support latest WooCommerce version.
= 2.0.7 =
* Fix issue request pickup Error validation package items.

= 2.0.6 =
* Fix issue on webhook handler.

= 2.0.5 =
* Add capability to create package with multiple items.
* Change the print AWB to use the new API.

= 2.0.4 =
* Bug fixes and performance improvements.

= 2.0.3 =
* Bug fixes and performance improvements.

= 2.0.2 =
* Improved integration with WooCommerce.

= 2.0.1 =
* First stable release. Please report any issues.

== Upgrade Notice ==

= 2.0.3 =
This update includes bug fixes and improvements for better performance. Please ensure to update to this version for a smoother experience.