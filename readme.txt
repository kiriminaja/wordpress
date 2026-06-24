=== KiriminAja Official ===
Contributors: kiriminaja
Donate link: https://developer.kiriminaja.com/kopi
Tags: shipping, ecommerce, WooCommerce, logistics
Requires at least: 6.8
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 2.2.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Easily integrate with multiple couriers across Indonesia

== Description ==

KiriminAja helps WooCommerce store owners streamline shipping operations, reduce manual fulfillment work, and offer more flexible delivery options for customers across Indonesia. Built by PT Selalu Siap Solusi, KiriminAja supports COD and Non-COD shipping workflows so online businesses can manage deliveries, pickups, and tracking from one connected platform.

**Key Business Benefits:**
- Offer customers more courier choices with real-time shipping rates directly at checkout.
- Support COD (Cash On Delivery) orders with pickup handling and faster fund disbursement workflows.
- Process Non-COD shipments more efficiently with courier pickup from your store location.
- Reduce manual order fulfillment by creating shipments, tracking packages, and managing pickup requests from WooCommerce.
- Improve operational visibility so store owners can control shipping activity, delivery status, and customer fulfillment more effectively.
- Help online businesses scale their logistics process with integrated shipping tools designed for Indonesian eCommerce needs.

This plugin is ideal for WooCommerce merchants who want a reliable shipping integration that improves checkout experience, simplifies fulfillment, and supports business growth.

== External Services ==

This plugin connects to KiriminAja API services to provide shipping functionality for your WooCommerce store.

**KiriminAja API**
- Service: https://client.kiriminaja.com
- Purpose: Process shipping rates, create shipments, track packages, and manage pickup requests
- Data sent: Shipping addresses, package dimensions and weight, order details, customer information
- When: Every time shipping rates are calculated, when shipments are created, and when tracking packages
- Terms of Service: https://kiriminaja.com/syarat-ketentuan
- Privacy Policy: https://kiriminaja.com/privacy-policy

**KiriminAja Callback (Webhook)**
- Endpoint (on your site): `/?feed=kiriminaja-callback`
- Purpose: Receive shipment/pickup status updates from KiriminAja
- Data received: Package status events including order IDs, AWB, and timestamps
- Authentication: Requires an `Authorization` header; the token is validated against the API key configured in the plugin

**Print.js Library**
- This plugin includes Print.js (https://github.com/crabbly/Print.js) for printing shipping labels
- Source code: https://github.com/crabbly/Print.js
- License: MIT

**Select2 Library**
- This plugin includes Select2 (https://github.com/select2/select2) for searchable select fields
- Source code: https://github.com/select2/select2
- License: MIT

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

- [Support](https://kiriminaja.com/kontak-kami)
- [Developer](https://developer.kiriminaja.com)

== Changelog ==
= 2.2.4 =
- Fallback transaction process recipients to wc order
- Restore recipient fallbacks

= 2.2.3 =
- Restore missing shipment info

= 2.2.2 =
- - Add margin: 0 auto to center the card wrapper
- Transients kiriof_profile_cache and kiriof_profile_last_success_cache

= 2.2.1 =
- Use order items instead of analytics lookup for shippable check (#227)

= 2.2.0 =
- Expose shipping rate metadata to Store API
- Seamless integration of settings to woocommmerce plugin settings page
- Handle number that lead by zero
- Update coupon validation to allow optional shipping and courier requirements
- Add support for active coupon combination validation and update related tests
- Enhance virtual product handling in volumetric configuration and update related tests
- Skip weight and volumetric requirements for virtual products in shipping validation
- Add validation to prevent combining multiple shipping discount coupons
- Implement logistics session cleanup for virtual carts in checkout process
- Add shipment details display after order table in checkout process
- Enhance shipping method handling in block checkout and improve test coverage
- Handling COD Fee label
- Form billing not render select
- Enhance customer meta handling for shipping and billing addresses in checkout process
- Enhance district management by persisting selected district in customer meta and updating shipping rates on changes
- Implement block district management for shipping address in checkout process
- Enhance block checkout functionality to manage district validation and button behavior
- Implement structured logging across the plugin
- Feat(setup-guide): make step titles clickable links for improved navigation
- Feat(onboarding): reduce size of icons and simplify step navigation in setup guide
- Feat(onboarding): enhance setup guide design for improved user experience
- Ignore the lang generated binary
- Add discounted shipping display and update localization for shipping breakdown
- Add actual shipping display and update localization files
- Add shipping discount calculation and update fee name alias handling
- Discount cod deficit handler (#202)
- Feat(discount-coupon): courier restriction validation and auto-removal
- Feat(block-checkout): disable Place Order button when district not selected
- Feat(checkout): make phone field mandatory when plugin is active
- Feat(admin): use WooCommerce order preview for transactions
- Courier cache management, proper labels, and filter instant/international
- Feat(coupon): improve combinations, individual use, and UI polish
- Feat(coupon-ui): redesign region picker with card grid layout
- Feat(coupon-ui): move Area Restrictions into a full-width metabox postbox
- Feat(coupon-ui): improve region scope radio buttons styling and visibility
- Feat(region-cache): bundle pre-fetched regions.json as offline fallback
- Show strikethrough shipping price in block checkout Order Summary
- Enhance shipping discount and rate metadata handling in checkout process
- Implement shipping discount management feature with AJAX support, including display in checkout and cart, session handling for saved districts, and enhanced error handling
- Implement shipping discount feature with display in cart and checkout, including calculations and metadata handling
- Enhance API integration by adding flexible base URL resolution, improving province and city retrieval methods, and updating error handling in region cache service
- Update API methods to use POST for province and city retrieval, enhance error handling in region cache service, and improve test coverage for AJAX error messages
- Enhance Shipping Discount Features and Error Handling
- Feat(coupon): add shipping coupon combinations UI
- Feat(coupon): apply shipping discount coupons to rates
- Feat(coupon): add shipping discount admin foundations
- Implement discount management feature with WooCommerce integration
- Fix(checkout): clear shipping sync timer
- Fix(checkout): delay block shipping discount sync
- Fix(checkout): prevent block postcode fallback
- Re-apply shipping strikethrough after React DOM re-render
- Use wp.data.useSelect for cart data in order meta fill
- Fallback to global summary when rate-specific discount unavailable
- Hide discount row when selected rate signature is unknown
- Hide original price when equal to current cost and add WP/PHP version headers
- Update shipping discount when rate changes
- Show original price for all discounted rates
- Show original price in block shipping options
- Simplify shipping discount checkout integration
- Comply plugin check on warning notice
- Comply plugin check
- Postal code forced fallback
- Shipping discount not show on top member account. and handling shipping information on cart too
- Fix(checkout): relax Store API shipping coupon validation
- Fix(checkout): keep shipping coupons applied in Store API
- Product comibined with virtual can't use shipping discount
- Sync with development branch fixing
- Fix(i18n): add missing translators comment in _setup-guide.php
- Fix(i18n): add Bahasa translations for 19 courier name strings
- Fix(plugin-check): wrap courier name strings in __() for i18n
- Fix(shipping): stable sort with secondary name comparison
- Fix(shipping): courier name mapping and display formatting
- Plugin check fails
- Transaction process
- Fix(checkout): hide COD for virtual carts
- Update district handling in block checkout to prevent premature field updates and ensure proper order submission
- Preserve typed postcode in block checkout
- Sync block checkout district field before submit
- Adjust shipping rates control visibility for district warning display
- Clear shipping coupon notices when no shipping coupons exist
- Clear stale shipping coupon validation notices
- Classify shipping discount coupons by scope
- Read posted district during shipping coupon validation
- Fix(test): satisfy setup guide validation
- Fix(i18n): localize setup guide strings
- Fix(admin): compact KiriminAja setup guide
- Fix(pickup): use recipient name for destination
- Fix(pickup): omit zero-value discount fields
- Rename variables for consistency in COD adjustment modal and transaction process
- Fix(tracking): restore front page autofill and submit
- Update Makefile for environment-specific ZIP file handling and add .env.example
- Fix(pickup): sanitize origin and destination names
- Fix(pickup): use saved destination area name
- Adjust spacing in user capability checks and update test for order details method length
- Remove unnecessary class from discounted shipping row in metabox
- Add padding to dialog modal
- Shipping discount can't saved
- TrackOrder ReferenceError — output script tag directly from shortcode
- Tracking page autofill and button not working
- Use feed URL for callback registration instead of pretty permalink
- Plugin check errors — translators comment + install from clean build
- Fix(plugin-check): fix text domain mismatch and nonce warning in TransactionProcessController
- Fix(i18n): update Bahasa translations for changed/new strings
- Fix(block-checkout): show district warning message when shipping options blocked
- Fix(block-checkout): block shipping options when postcode is cleared
- Fix(block-checkout): hide Shipment package card when district not selected
- Fix(block-checkout): use native WC styling for district required message
- Fix(block-checkout): show district warning below Shipping options heading
- Fix(block-checkout): fully hide shipping options section when no district selected
- Fix(block-checkout): suppress district warning on fresh non-logged-in load
- Fix(block-checkout): remove whitespace gap under Shipping options on first load
- Fix(block-checkout): eliminate shipping rate jank on initial page load
- Fix(block-checkout): restore district on page refresh for logged-in users
- Fix(i18n): add pickup modal translations
- Fix(admin): size Woo action modals inline
- Fix(cart): read shipping discount meta from session or rate
- Add translators comments to each sprintf/__() branch
- Resolve WP Plugin Check warnings
- Address Copilot PR review feedback (#198)
- Fix(coupon-ui): force inline styles + version bump to bust CSS cache
- Fix(coupon-ui): tree hidden on init, 3-column province grid
- Fix(coupon-ui): use native radio inputs, hide tree in All Regions mode
- Fix(coupon-ui): replace radio inputs with button toggles, fix search disabled bug
- Fix(coupon-ui): stats show selected count, hidden in All Regions mode
- Fix(coupon-ui): replace :has() with JS-driven .is-active class for radio buttons
- Fix(region-cache): seed bundled data in enqueueCouponAdminAssets before localize_script
- Fix(region-cache): run migration before DB upsert to ensure tables exist
- Fix(region-cache): use background cron for region data refresh
- Update fee key checks and improve payment method session handling
- Read shipping discount from session rate meta, not WC_Shipping_Rate meta_data
- Persist district to postcode map on selection and restore
- Re-fetch shipping discount when selected rate changes
- Collapse shipping step whitespace when no district selected
- Use CSS body class for React-proof no-district state hiding
- Prevent infinite loop in block checkout district warning sync
- Hide shipping+payment sections and validate district for logged-in users
- Clear stale shipping rates from Order Summary when district not selected
- Remove shipping injection, fix district autoload & unused vars

= 2.1.44 =
- Fix(callback): register feed query callback URL

= 2.1.43 =
- Fix(tracking): enqueue tracking page script

= 2.1.42 =
- Handling process and shipping logic
- Persistent select courier issue
- Order summary not sync with selected courier extended
- Order summary not sync with selected courier
- Fix(checkout): trust WC-resolved shipping method in block checkout
- Fix(checkout): preserve selected block shipping method
- Fix(tracking): resolve shortcode lookup by shipment identifiers

= 2.1.41 =
- Implement block district management for shipping address in checkout process
- Enhance block checkout functionality to manage district validation and button behavior
- Implement structured logging across the plugin
- Feat(setup-guide): make step titles clickable links for improved navigation
- Feat(onboarding): reduce size of icons and simplify step navigation in setup guide
- Feat(onboarding): enhance setup guide design for improved user experience
- Ignore the lang generated binary
- Add discounted shipping display and update localization for shipping breakdown
- Add actual shipping display and update localization files
- Add shipping discount calculation and update fee name alias handling
- Discount cod deficit handler (#202)
- Feat(discount-coupon): courier restriction validation and auto-removal
- Feat(block-checkout): disable Place Order button when district not selected
- Feat(checkout): make phone field mandatory when plugin is active
- Feat(admin): use WooCommerce order preview for transactions
- Courier cache management, proper labels, and filter instant/international
- Feat(coupon): improve combinations, individual use, and UI polish
- Preserve typed postcode in block checkout
- Sync block checkout district field before submit
- Adjust shipping rates control visibility for district warning display
- Clear shipping coupon notices when no shipping coupons exist
- Clear stale shipping coupon validation notices
- Classify shipping discount coupons by scope
- Read posted district during shipping coupon validation
- Fix(test): satisfy setup guide validation
- Fix(i18n): localize setup guide strings
- Fix(admin): compact KiriminAja setup guide
- Fix(PickupRequest): origin_name and destination_name not sanitized, causing the pickup request failed (#205)
- Fix(pickup): use recipient name for destination
- Fix(pickup): omit zero-value discount fields
- Rename variables for consistency in COD adjustment modal and transaction process
- Fix(tracking): restore front page autofill and submit
- Update Makefile for environment-specific ZIP file handling and add .env.example
- Fix(pickup): sanitize origin and destination names
- Fix(pickup): use saved destination area name
- Adjust spacing in user capability checks and update test for order details method length
- Remove unnecessary class from discounted shipping row in metabox
- Add padding to dialog modal
- Shipping discount can't saved
- TrackOrder ReferenceError — output script tag directly from shortcode
- Tracking page autofill and button not working
- Use feed URL for callback registration instead of pretty permalink
- Plugin check errors — translators comment + install from clean build
- Fix(plugin-check): fix text domain mismatch and nonce warning in TransactionProcessController
- Fix(i18n): update Bahasa translations for changed/new strings
- Fix(block-checkout): show district warning message when shipping options blocked
- Fix(block-checkout): block shipping options when postcode is cleared
- Fix(block-checkout): hide Shipment package card when district not selected
- Fix(block-checkout): use native WC styling for district required message
- Fix(block-checkout): show district warning below Shipping options heading
- Fix(block-checkout): fully hide shipping options section when no district selected
- Fix(block-checkout): suppress district warning on fresh non-logged-in load
- Fix(block-checkout): remove whitespace gap under Shipping options on first load
- Fix(block-checkout): eliminate shipping rate jank on initial page load
- Fix(block-checkout): restore district on page refresh for logged-in users
- Fix(i18n): add pickup modal translations
- Fix(admin): size Woo action modals inline
- Fix(cart): read shipping discount meta from session or rate
- Add translators comments to each sprintf/__() branch
- Resolve WP Plugin Check warnings
- Address Copilot PR review feedback (#198)
- Feat(Onboarding): make the onboarding design more compact (#208)
- Fix(request-pickup): send discount percentage with discounts

= 2.1.40 =
- Fix(PickupRequest): origin_name and destination_name not sanitized, causing the pickup request failed (#205)

= 2.1.39 =
- Migrate plugin translations to WordPress i18n (#191)
- Transaction table UX overhaul — filters, pagination, search, & status badges (#190)
- Address plugin check warnings for PHPCS and i18n (#195)
- Translate request pickup payment strings (#194)
- Localize settings page strings (#193)
- Translate admin menu labels (#192)

= 2.1.38 =
- Fix(request-pickup): preserve auto-open payment trigger attributes (#180)
- Fix(request-pickup): auto-open payment via matching button (#179)

= 2.1.37 =
- Fix(request-pickup): render payment QR reliably (#177)

= 2.1.36 =
- Fix(request-pickup): keep payment modal open without detail handler (#176)

= 2.1.35 =
- Fix(request-pickup): stabilize payment modal auto-open from query params

= 2.1.34 =
- Fix(request-pickup): auto-open payment modal only with open_payment flag

= 2.1.33 =
- Fix(request-pickup): disable auto-open payment modal via pickup_number
- Fix(request-pickup): redirect success flow to pickup list page

= 2.1.32 =
- Fix(resi-print): preserve oids sanitization for non-numeric order ids
- Fix(request-pickup): restore scan-to-pay flow after pickup redirect
- Cache profile response during throttling

= 2.1.31 =
- Enhance volumetric configuration checks for product variations

= 2.1.30 =
- Remove inline styles from "Complete Setup" button
- Count private variations in volumetric setup

= 2.1.29 =
- Enhance checkout process with force insurance handling and cache validation
- Persist chosen shipping methods in session during checkout updates and AJAX requests
- Update cancel shipment button text for clarity in transaction summary
- Enhance Store API checkout process with destination area handling and metadata persistence
- Implement volumetric calculations for cart items and update related services
- Implement product volumetric configuration tracking and UI updates
- Add WooCommerce Shipping Locations step to setup checklist and update related UI elements
- Exclude variable parents from volumetric readiness
- Make volumetric box calculation packable
- Update money formatting in OngkirPricingService and adjust script initialization in form-billing-address
- Set current gateway in custom checkout payment row
- Show shipping rates before payment selection
- Refresh block checkout shipping rates
- Show block checkout transactions correctly
- Refresh checkout fee context
- Enhance block checkout compatibility and styling
- Prevent classic checkout fee refresh loop
- Use native fees on classic checkout
- Restore classic checkout order total
- Support COD fees in block checkout
- Initialize block checkout compatibility on cart flows
- Harden block checkout COD fee detection
- Remove unnecessary paragraph tags around submit buttons in address and webhooks sections
- Normalize block checkout payment method
- Persist block checkout transaction context
- Match block district select markup
- Persist block checkout transactions after cart reset
- Isolate ShopVerse district select wrapper
- Fix block checkout order fee persistence
- Fix block checkout COD payment detection
- Fix block checkout district session bridge
- Add block checkout native fee path
- Find block district field after react render

= 2.1.28 =
- Update description for shipping insurance feature
- Enhance SQL query in SetupMigration and improve variable naming in section-account
- Change district field type from select to text and update handling in JS
- Try to resolve district selector
- Implement insurance and COD fee calculations for checkout process
- Add insurance configurations options
- Guided setup information
- Add guide to setup tracking page
- Refactor design of settings
- Add Cash on Delivery configuration and management
- Keep block district select outside react field
- Trigger block district lookup from postcode input
- Avoid duplicate classic checkout fee rows
- Avoid duplicate district field on classic checkout
- Restore classic checkout fee display
- Allow dynamic district values in block checkout
- Support block checkout district and fees
- Update district field ID and enhance postcode change handling
- District wont rendered on custom woocommerce themes
- Cod fee, insurance, and district wont show on some custom themes
- Issues on shopverse like theme approach (react)
- Complete the force insurance configurations
- Cod fee calculations
- Miss-calculation on all package tabs
- Update icon URL for settings page in Admin class
- Update variable names for Cash on Delivery settings in origin setup
- Request not sanitized

= 2.1.27 =
- Improve request handling after the hotfix on print awb

= 2.1.26 =
- Handling on print resi still wont load

= 2.1.25 =
- Persistent district from /cart and /checkout
- Add options to filter the transactions by "All"
- Add detail button to list table
- Remove unused templates
- Select2 registrations failed
- Select2 rendering issue glitch

= 2.1.24 =
- Feat(modal): overlay and experience improvement
- Update footer content
- Native look admin plugins design
- Improve handling on 404 when printing awb
- Move request pickup detail to another page to prevent glitchy dialog pop-up
- Comply testing compliance
- Media modal issues
- Resolve PR review comments for URL construction
- Possibility direct callable via admin-post.php
- Html escape on print label name
- Dialog modal design glitch
- Single print button issues
- Admin permission access denied
- Typo on variable name

= 2.1.23 =
- Retrigger code

= 2.1.22 =
- Add premium styling for carrier list
- Add cancel shipment logic
- Cart input not rendered properly
- Handle race conditions on cancel by webhooks
- Navigation issues on redirecting

= 2.1.21 =
- District area not loaded on checkout page
- Fix(transaction): transaction list not using HPOS format

= 2.1.20 =
- Button styling to native

= 2.1.19 =
- Button styling on 6.9.4
- Unable to search whitelist expeditions

= 2.1.18 =
- Update transaction queries to include payments for processed order count
- Add processed order count and filter to transaction process view
- Add KiriminAja Shipping metabox to WooCommerce order edit screen
- Update permission checks to require only manage_woocommerce capability across controllers and templates
- Update permission checks to require both manage_options and manage_woocommerce capabilities across controllers and templates

= 2.1.17 =
- Update plugin row meta links and modify plugin URI in header
- Add meta links for View Details, Support, and Developer in plugin row
- Takeout legacy plugins but with backward plugin update compatibility

= 2.1.16 =
- Feat(readme): Update readme and content

= 2.1.15 =
- First official release on WordPress.org
- Interactive map picker with geolocation for store address setup
- Nonce auto-refresh to keep long-open admin pages working
- Status filter counts for request pickup and transaction views
- Unpaid shipment count badge in admin menu
- Legacy shortcode alias and dedicated tracking stylesheet
- Improved order status handling to prevent pickup of non-processable orders
- Improved date formatting with time display for orders and transactions
- Full security audit and WordPress.org Plugin Directory compliance
- Bundled Select2 library locally (no CDN dependency)

== Upgrade Notice ==

= 2.1.15 =
First official release on WordPress.org with full security audit, interactive map picker, and improved transaction management.