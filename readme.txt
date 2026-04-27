=== KiriminAja Official ===
Contributors: kiriminaja
Donate link: https://developer.kiriminaja.com/kopi
Tags: shipping, ecommerce, WooCommerce, logistics
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.0
Stable tag: 2.1.10
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

1. Upload the plugin files to the `/wp-content/plugins/kiriminaja-official` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to `KiriminAja > Integration` to configure your integration and shipping options.
4. Navigate to `KiriminAja > Shipping` to configure your shipping preferences.
5. Set up your shipping whitelist and preferences.

== Frequently Asked Questions ==

= What expeditions does KiriminAja support? =
KiriminAja is a package delivery management platform with COD and non-COD delivery methods on various expeditions. You are a platform user who accesses KiriminAja services, whether registered as an account owner or not.

= Can COD be done without a marketplace? =
Yes, you can use J&T Express COD service to send goods without having to go through online buying and selling platforms such as marketplaces.

= Does this plugin support Indonesia Domestic shipping? =
Yes, the plugin allows you to set up shipping options for Indonesia domestic.

= Is it compatible with all WooCommerce themes? =
Yes, it works with any WooCommerce-compatible theme.

= Does it support live shipping rates? =
Yes, you can integrate with carriers that provide API access for live rates.

= Can I add custom shipping rules? =
Yes, you can define rules based on weight, destination, or order total.

= Does this plugin support international shipping? =
Currently, this plugin only supports domestic shipping within Indonesia.

== Screenshots ==

1. KiriminAja integration settings on the dashboard
2. Setup key configuration for connecting your store
3. Plugin successfully installed on WordPress
4. Store address and shipping configuration
5. WooCommerce payment method settings
6. Product shipping data setup

== Changelog ==
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