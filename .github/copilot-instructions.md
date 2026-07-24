# KiriminAja Official — WordPress/WooCommerce Shipping Plugin

These are local repository instructions for GitHub Copilot. `AGENTS.md` is the canonical source for shared agentic workflow rules. Keep this file aligned with `AGENTS.md` and `CLAUDE.md`.

## Project Overview

KiriminAja Official is a WooCommerce shipping plugin for Indonesian online sellers. It provides real-time shipping rates from multiple couriers, COD support, pickup scheduling, label printing, and package tracking—all from the WooCommerce dashboard.

- **Plugin slug:** `kiriminaja-official`
- **Namespace:** `KiriminAjaOfficial`
- **PHP autoloading:** PSR-4 via Composer (`"KiriminAjaOfficial\\" → "./inc"`)
- **Minimum WooCommerce:** 8.0.0
- **WC tested up to:** 10.6
- **Text domain:** `kiriminaja-official`
- **Version:** Defined in both `kiriminaja.php` header and `KIRIOF_VERSION` constant.

---

## Architecture

```
inc/
├── Base/           → Core classes (Activate, BaseInit, BaseService, Deactivate, Enqueue, Helper, KiriminAjaApi, PageGenerator, Validator)
├── Controllers/    → WordPress hook registrations + AJAX handlers
├── Services/       → Business logic (each flow has its own service or sub-folder)
│   ├── CheckoutServices/       → CheckoutCalculationService, CreateTransactionService, OngkirPricingService, ValidationCodCalculationService
│   ├── KiriminAja/             → GenerateOrderId
│   ├── OrderEditPageServices/  → ShippingInfoServices
│   ├── ShippingProcessServices/→ GetShippingProcessDetailService, GetShippingProcessPayment
│   ├── TransactionProcessServices/ → CancelTransactionService, GetRequestPickupScheduleService, GetTransactionDetailSummary, SendRequestPickupTransactionService
│   └── UtilServices/           → GetWCCartAttributeService
├── Repositories/   → Database & external API data access (KiriminajaApiRepository, PaymentRepository, SettingRepository, TransactionRepository, WpPostMetaRepository, WpWcOrderProductLookup, WpWcOrderStatRepository)
├── Migration/      → DB table creation (SetupMigration)
├── Pages/          → Admin menu & page provisioning (Admin, AdminPost)
└── Utils/          → Value objects (ServiceResponse, DimensionConverter, WeightConverter)

wc/                 → WooCommerce-specific classes (KiriminajaShippingMethod, AdminWoocommerceSetting, OverwriteWoocommercePlugin)
templates/          → PHP view templates (admin pages, order metabox, tracking, checkout)
├── front/          → Customer-facing: checkout/billing/shipping address forms, after-checkout page, tracking
├── order/          → WC order edit page metabox
├── partials/       → Shared partials (footer)
├── product/        → WC product general tab custom field
├── request-pickup/ → Payments list page (server-rendered)
├── request-pickup-detail/ → Request Pickup Detail page (server-rendered, dedicated admin page)
├── setting/        → Plugin settings pages (setuped/unsetuped)
├── transaction-process/ → Transactions list page
└── woocommerce/    → WC template overrides (cart, checkout)
assets/             → Frontend CSS/JS/libs (Select2, admin scripts)
frontend/           → Frontend public assets
tests/              → ParaTest test suite
lang/               → Translation files
scripts/            → Build/release helper scripts
```

### Design Pattern: Controller → Service → Repository

1. **Controller** — Registers WordPress hooks/actions. Validates nonce + capabilities. Sanitizes input. Delegates to a Service.
2. **Service** — Contains business logic. Extends `BaseService`. Returns `ServiceResponse` via `self::success()` or `self::error()`.
3. **Repository** — Direct database queries (`$wpdb`) or external API calls (extends `KiriminAjaApi`).

### Service Registration

All controllers and base classes are registered via `Init::get_services()` in `inc/Init.php`:

```php
Base\Enqueue::class,
Pages\Admin::class,
Controllers\ProductController::class,
Controllers\SettingController::class,
Controllers\CallbackController::class,
Controllers\GeneralAjaxController::class,
Controllers\ShippingProcessController::class,
Controllers\TransactionProcessController::class,
Controllers\CheckoutController::class,
Controllers\TrackingFrontPageController::class,
Controllers\EditOrderController::class,
```

---

## Admin Pages

Admin pages are registered via `Pages\Admin` → `Base\PageGenerator`. The page system supports:

- **Top-level menu:** `kiriminaja-konfigurasi` (Settings page, icon position 56)
- **Sub-pages** (only registered when WooCommerce is active):
  - `kiriminaja-transaction-process` — Transactions list
  - `kiriminaja-request-pickup` — Payments list
  - `kiriminaja-request-pickup-detail` — Request Pickup Detail (hidden sub-page, not shown in sidebar)
  - `kiriminaja-konfigurasi` — Settings (re-uses parent slug to replace auto-generated first sub-item)

### Hidden Sub-Pages

Pages with `'hidden' => true` are registered normally for access control but removed from the sidebar via `PageGenerator::hideHiddenSubPages()` on the `admin_head` hook (NOT `admin_menu` — this ensures WordPress validates page access before hiding).

The "Request Pickup Detail" page uses `submenu_file` filter in `Admin.php` to highlight the "Payments" menu item when viewing the detail page.

### AdminPost

`Pages\AdminPost` auto-creates required WooCommerce pages (Checkout, Tracking, Cart) if they don't exist, and configures WooCommerce settings (legacy mode, shipping calculator, COD).

---

## Core Flows

### 1. Checkout (shipping rate calculation + order creation)

- `CheckoutController` hooks into WooCommerce checkout lifecycle.
- `Kiriof_Shipping_Method_Controller` (in `wc/KiriminajaShippingMethod.php`) extends `WC_Shipping_Method` to calculate rates via KiriminAja API.
- On checkout submit: `CreateTransactionService` generates a KiriminAja order with the selected expedition, weight/dimension, insurance, and COD data.
- Additional services: `CheckoutCalculationService`, `OngkirPricingService`, `ValidationCodCalculationService`.
- Pricing calls go through `KiriminajaApiRepository::getPricing()`.

### 2. Settings (integration, origin, callback)

- `SettingController` exposes AJAX endpoints for integration setup (setup key), origin address, callback URL, and expedition whitelist.
- `SettingService` processes setup keys by calling `KiriminajaApiRepository::processSetupKey()` and stores API credentials in `wp_kiriminaja_settings`.
- Settings are stored as key-value rows in the `{prefix}_kiriminaja_settings` table.

### 3. Transaction Processing (pickup, cancel, detail)

- `TransactionProcessController` handles request-pickup scheduling, sending pickup requests, fetching transaction summaries, and cancellation.
- Auto-cancel hook: when a WooCommerce order is cancelled (`woocommerce_order_status_cancelled`), the linked KA transaction is also cancelled.
- Services: `GetRequestPickupScheduleService`, `SendRequestPickupTransactionService`, `CancelTransactionService`, `GetTransactionDetailSummary`.

### 4. Shipping Process (payment, detail, reschedule, label print)

- `ShippingProcessController` manages payment form retrieval, shipping detail, pickup rescheduling, and AWB/resi label printing.
- **Label printing** uses two endpoints (for backward compatibility):
  - Legacy feed endpoint: `add_feed('transaction-resi-print', ...)` — accessible via `/?feed=transaction-resi-print`
  - Admin post action: `admin_post_kiriof_resi_print` — accessible via `admin-post.php?action=kiriof_resi_print` (preferred, used by the detail page)
- The `resiPrint()` method verifies nonce (`kiriof_resi_print`) and `manage_woocommerce` capability, fetches AWB data from the API, downloads the PDF, and streams it to the browser.
- Services: `GetShippingProcessDetailService`, `GetShippingProcessPayment`.

### 5. Request Pickup Detail (dedicated admin page)

- Server-rendered admin page at `admin.php?page=kiriminaja-request-pickup-detail&pickup_number=XXX`.
- Controller: `templates/request-pickup-detail/index.php` — validates `manage_woocommerce`, reads `pickup_number` from `$_GET`, calls `GetShippingProcessDetailService`, redirects to Payments list if invalid.
- View: `templates/request-pickup-detail/view/index.php` — shows back button, Print All button, summary cards (package count, COD/non-COD counts), and a transaction table with per-row Print and Detail (order edit) buttons.
- Print URLs use `admin_url('admin-post.php?action=kiriof_resi_print&oids=...&_wpnonce=...')`.
- Linked from the Payments list page and the Transactions page (after request pickup).

### 6. Callback (webhook from KiriminAja)

- `CallbackController` registers a custom feed endpoint at `/kiriminaja-callback`.
- `CallbackHandlerService` validates the Bearer token header, then dispatches based on `body->method`:
  - `processed_packages`, `shipped_packages`, `finished_packages`, `returned_packages`, `validated_packages`, `rejected_packages`, `canceled_packages`, `return_finished_packages`.
- Each callback method updates the transaction status in `wp_kiriminaja_transactions` and syncs the WooCommerce order status.

### 7. Tracking (public-facing)

- `TrackingFrontPageController` provides a shortcode `[kiriminaja-tracking-front-page]` (legacy alias: `[wp-tracking-front-page]`) and an AJAX handler.
- `KiriminAjaTrackingService` fetches tracking data via `KiriminajaApiRepository::getTracking()`.

### 8. Order Edit Page (admin metabox)

- `EditOrderController` adds a "KiriminAja Shipping" metabox on the WooCommerce order edit screen.
- Supports both legacy (`shop_order`) and HPOS (`woocommerce_page_wc-orders`) screens.
- Uses `ShippingInfoServices` for data retrieval.

### 9. Product (custom shipping fields)

- `ProductController` adds custom fields to the WooCommerce product "General" tab for shipping dimensions/weight.
- Hooks: `woocommerce_product_options_general_product_data`, `woocommerce_process_product_meta`.

### 10. General AJAX (subdistrict search, checkout data)

- `GeneralAjaxController` provides public AJAX endpoints (both `wp_ajax_` and `wp_ajax_nopriv_`):
  - `kiriminaja_subdistrict_search` — Kelurahan/sub-district autocomplete
  - `kiriof_get_destination_area` — Destination area lookup
  - `kiriof_get_data_after_update_checkout` — Post-checkout-update data

---

## Security Conventions (MUST follow)

1. **ABSPATH guard** — Every PHP file starts with:

   ```php
   if ( ! defined( 'ABSPATH' ) ) { exit; }
   ```

2. **Nonce verification** — All AJAX handlers verify nonce from `$_POST['data']['nonce']` against `KIRIOF_NONCE`:

   ```php
   if ( ! isset( $_POST['data']['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['data']['nonce'] ) ), KIRIOF_NONCE ) ) {
       wp_send_json_error( array( 'status' => 403, 'message' => __( 'Security check failed', 'kiriminaja-official' ) ) );
       wp_die();
   }
   ```

   Non-AJAX admin actions (e.g. label printing) use a dedicated nonce: `wp_create_nonce('kiriof_resi_print')` verified via `$_GET['_wpnonce']`.

3. **Capability check** — Admin AJAX endpoints require `manage_woocommerce`:

   ```php
   if ( ! current_user_can( 'manage_woocommerce' ) ) {
       wp_send_json_error( array( 'status' => 403, 'message' => __( 'Insufficient permissions', 'kiriminaja-official' ) ) );
       wp_die();
   }
   ```

   Admin template controllers also check this capability and call `wp_die()` on failure.

4. **Input sanitization** — Always `wp_unslash()` then `sanitize_text_field()` for scalars, `array_map('sanitize_text_field', ...)` for arrays, and `kiriof_sanitize_recursive()` for deeply nested structures.

5. **Output escaping** — Use `esc_html()`, `esc_attr()`, `esc_url()`, `wp_json_encode()` in templates.

6. **Database queries** — Always use `$wpdb->prepare()` with placeholders. Interpolated table names are acceptable (prefixed tables) but must be escaped via `esc_sql()`.

---

## Coding Conventions

- **Prefix:** All global functions use `kiriof_` prefix. Constants use `KIRIOF_` prefix.
- **Constants:** `KIRIOF_DIR`, `KIRIOF_URL`, `KIRIOF_NONCE`, `KIRIOF_SLUG`, `KIRIOF_SLUG_FILE`, `KIRIOF_VERSION`, `KIRIOF_PLUGIN_BASENAME`.
- **Class registration:** Each service class implements a `register()` method that hooks into WordPress actions/filters. `Init::register_services()` iterates and calls `register()`.
- **Service responses:** Always return `ServiceResponse` objects (via `self::success($data)` or `self::error($data, $message, $status)`). Controllers check `$service->status !== 200` to decide `wp_send_json_error` vs `wp_send_json_success`.
- **Fluent setters:** Service classes use fluent method chaining for parameters: `->orderIds($ids)->schedule($schedule)->call()`.
- **HPOS compatibility:** Repository and controller code checks for WooCommerce HPOS (High-Performance Order Storage) using `OrderUtil::custom_orders_table_usage_is_enabled()`.
- **Translations:** Use `__('text', 'kiriminaja-official')` for all user-facing strings.
- **PHPCS comments:** Use `// phpcs:ignore` with explicit sniff names and a justification comment when suppressing warnings.
- **Admin page templates:** Each admin page has a `templates/<page>/index.php` (controller) and `templates/<page>/view/index.php` (view). The controller validates permissions, fetches data, then includes the view.
- **Global helper functions:** Defined in `kiriminaja.php` — `kiriof_check_woocommerce()`, `kiriof_money_format()`, `kiriof_helper()`, `kiriof_sanitize_recursive()`.

---

## Database Tables

| Table                              | Purpose                                                                                |
| ---------------------------------- | -------------------------------------------------------------------------------------- |
| `{prefix}_kiriminaja_settings`     | Key-value plugin settings (api_key, oid_prefix, setup_key, callback_url, origin data)  |
| `{prefix}_kiriminaja_transactions` | Shipping transactions linked to WC orders (order_id, awb, status, pickup_number, etc.) |
| `{prefix}_kiriminaja_payments`     | Payment/batch records for grouped shipments                                            |

Tables are created via `Migration\SetupMigration::register()` which calls `settingsTable()`, `transactionsTable()`, and `paymentsTable()`. Tables are only created if they don't already exist.

---

## External API

- **Base URL:** `https://client.kiriminaja.com`
- **Auth:** Bearer token (stored in settings table as `api_key`)
- **Key endpoints used:**
  - `POST /api/service/api-request/integrate` — Setup key integration
  - `POST /api/mitra/v6.1/shipping_price` — Shipping rate calculation
  - `POST /api/mitra/v6.1/request_pickup` — Request courier pickup
  - `POST /api/mitra/v6.1/awb/print` — Print shipping label (returns PDF URL)
  - `POST /api/mitra/v3/cancel_shipment` — Cancel shipment
  - `POST /api/mitra/tracking` — Package tracking
  - `POST /api/mitra/v2/schedules` — Pickup schedules
  - `POST /api/mitra/v2/get_payment` — Payment details
  - `POST /api/mitra/couriers` — Available couriers
  - `GET /api/mitra/kelurahan_by_name?search=` — Sub-district search
  - `POST /api/mitra/set_callback` — Register callback URL

---

## Local Workflow Rules

- Use `rtk` before shell commands in this workspace.
- Check `git status --short --branch` before edits and avoid unrelated user changes.
- Prefer narrow fixes in the existing service/controller/template structure.
- Follow WordPress Coding Standards unless nearby code has a stronger local convention.
- When using `$wpdb->prepare()` with dynamic `IN (...)` placeholders, pass the prepared SQL directly into `$wpdb->query()` or similar calls. Do not store prepared SQL in an intermediate variable. Keep replacement counts exact and avoid interpolated placeholder variables that trigger WordPressCS / Plugin Check `PreparedSQL` warnings.
- Do not start local WordPress or dev servers unless explicitly requested.
- When changing packaged source files, run `make zip` before final verification.

## Testing

- Test runner: ParaTest
- Test files: `tests/` directory
- Run: `vendor/bin/paratest --configuration paratest.xml` or `make test`
- Tests cover: access control (`AccessControlTest`), cancel transaction flow (`CancelTransactionFeatureTest`), i18n (`I18nValidationTest`), plugin structure (`PluginStructureTest`), prefix validation (`PrefixValidationTest`), security validation (`SecurityValidationTest`), syntax validation (`SyntaxValidationTest`).
- **Important:** The workspace must be opened at the WordPress installation root so that this plugin resides at `wp-content/plugins/kiriminaja-official`. Tests rely on WordPress core file paths (e.g. `ABSPATH`) resolving correctly. If you open only the plugin folder in isolation, tests will fail.
- `AccessControlTest` has an `ADMIN_TEMPLATES` constant listing all template controllers that require `manage_woocommerce`.
- After adding new source files, run `make zip` to sync the build directory before running tests (some tests verify build/source parity).

---

## Build & Release

- `make zip` — Build distributable ZIP into `build/` directory (excludes dev files via rsync)
- `make test` — Run ParaTest with `paratest.xml` and `--testdox`
- `make release [VERSION]` — Run changelog, bump version, and build ZIP
- `make publish [VERSION]` — Full flow: release + commit + tag + push
- `make tag` — Create annotated git tag from current version
- `make changelog` — Generate/update changelog
- Version is defined in both `kiriminaja.php` header and `KIRIOF_VERSION` constant.
- Build output: `build/kiriminaja-official/` (staged) and `kiriminaja-official.zip` (archive)

---

## Activation / Deactivation

- `Base\Activate::activate()` — Flushes rewrite rules on plugin activation.
- `Base\Deactivate::deactivate()` — Flushes rewrite rules on plugin deactivation.

---

## Key Reminders for Code Generation

1. New controllers must be added to `Init::get_services()` array in `inc/Init.php`.
2. New AJAX handlers must include capability check + nonce verification BEFORE any logic.
3. All `$_POST`/`$_GET`/`$_REQUEST` access must be sanitized and unslashed.
4. Use `wp_remote_get()`/`wp_remote_post()` for HTTP calls (never `curl` directly).
5. Service classes should extend `BaseService` and return `ServiceResponse`.
6. Repository classes handle raw data access; never put business logic in repositories.
7. Template files live in `templates/` and are included via `require_once` or `include`.
8. JavaScript/CSS assets go in `assets/` and are enqueued via `Enqueue` class.
9. New admin pages must be added to `Pages\Admin::register()` sub-pages array. Hidden pages use `'hidden' => true`.
10. After adding/removing source files, run `make zip` to keep the `build/` directory in sync (tests verify this).
11. New admin template controllers should be added to `AccessControlTest::ADMIN_TEMPLATES`.
