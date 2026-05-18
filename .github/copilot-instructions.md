# KiriminAja Official — WordPress/WooCommerce Shipping Plugin

## Project Overview

KiriminAja Official is a WooCommerce shipping plugin for Indonesian online sellers. It provides real-time shipping rates from multiple couriers, COD support, pickup scheduling, label printing, and package tracking—all from the WooCommerce dashboard.

- **Plugin slug:** `kiriminaja-official`
- **Namespace:** `KiriminAjaOfficial`
- **PHP autoloading:** PSR-4 via Composer (`"KiriminAjaOfficial\\" → "./inc"`)
- **Minimum WooCommerce:** 5.0.0
- **Text domain:** `kiriminaja-official`

---

## Architecture

```
inc/
├── Base/           → Core classes (BaseInit, BaseService, Enqueue, Helper, KiriminAjaApi, Validator, PageGenerator)
├── Controllers/    → WordPress hook registrations + AJAX handlers
├── Services/       → Business logic (each flow has its own service or sub-folder)
├── Repositories/   → Database & external API data access
├── Migration/      → DB table creation (settings, transactions, payments)
├── Pages/          → Admin menu registration
└── Utils/          → Value objects (ServiceResponse, DimensionConverter, WeightConverter)

wc/                 → WooCommerce-specific classes (Shipping Method, admin settings overrides)
templates/          → PHP view templates (admin pages, order metabox, tracking, checkout)
assets/             → Frontend CSS/JS/libs (Select2, admin scripts)
tests/              → PHPUnit tests
```

### Design Pattern: Controller → Service → Repository

1. **Controller** — Registers WordPress hooks/actions. Validates nonce + capabilities. Sanitizes input. Delegates to a Service.
2. **Service** — Contains business logic. Extends `BaseService`. Returns `ServiceResponse` via `self::success()` or `self::error()`.
3. **Repository** — Direct database queries (`$wpdb`) or external API calls (extends `KiriminAjaApi`).

---

## Core Flows

### 1. Checkout (shipping rate calculation + order creation)

- `CheckoutController` hooks into WooCommerce checkout lifecycle.
- `Kiriof_Shipping_Method_Controller` (in `wc/KiriminajaShippingMethod.php`) extends `WC_Shipping_Method` to calculate rates via KiriminAja API.
- On checkout submit: `CreateTransactionService` generates a KiriminAja order with the selected expedition, weight/dimension, insurance, and COD data.
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

- `ShippingProcessController` manages payment form retrieval, shipping detail, pickup rescheduling, and AWB/resi label printing via a custom feed endpoint (`/transaction-resi-print`).

### 5. Callback (webhook from KiriminAja)

- `CallbackController` registers a custom feed endpoint at `/kiriminaja-callback`.
- `CallbackHandlerService` validates the Bearer token header, then dispatches based on `body->method`:
  - `processed_packages`, `shipped_packages`, `finished_packages`, `returned_packages`, `validated_packages`, `rejected_packages`, `canceled_packages`, `return_finished_packages`.
- Each callback method updates the transaction status in `wp_kiriminaja_transactions` and syncs the WooCommerce order status.

### 6. Tracking (public-facing)

- `TrackingFrontPageController` provides a shortcode `[kiriminaja-tracking-front-page]` and an AJAX handler.
- `KiriminAjaTrackingService` fetches tracking data via `KiriminajaApiRepository::getTracking()`.

### 7. Order Edit Page (admin metabox)

- `EditOrderController` adds a "KiriminAja Shipping" metabox on the WooCommerce order edit screen.
- Supports both legacy (`shop_order`) and HPOS (`woocommerce_page_wc-orders`) screens.

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

3. **Capability check** — Admin AJAX endpoints require `manage_woocommerce`:

   ```php
   if ( ! current_user_can( 'manage_woocommerce' ) ) {
       wp_send_json_error( array( 'status' => 403, 'message' => __( 'Insufficient permissions', 'kiriminaja-official' ) ) );
       wp_die();
   }
   ```

4. **Input sanitization** — Always `wp_unslash()` then `sanitize_text_field()` for scalars, `array_map('sanitize_text_field', ...)` for arrays, and `kiriof_sanitize_recursive()` for deeply nested structures.

5. **Output escaping** — Use `esc_html()`, `esc_attr()`, `esc_url()`, `wp_json_encode()` in templates.

6. **Database queries** — Always use `$wpdb->prepare()` with placeholders. Interpolated table names are acceptable (prefixed tables) but must be escaped via `esc_sql()`.

---

## Coding Conventions

- **Prefix:** All global functions use `kiriof_` prefix. Constants use `KIRIOF_` prefix.
- **Constants:** `KIRIOF_DIR`, `KIRIOF_URL`, `KIRIOF_NONCE`, `KIRIOF_SLUG`, `KIRIOF_VERSION`, `KIRIOF_PLUGIN_BASENAME`.
- **Class registration:** Each service class implements a `register()` method that hooks into WordPress actions/filters. `Init::register_services()` iterates and calls `register()`.
- **Service responses:** Always return `ServiceResponse` objects (via `self::success($data)` or `self::error($data, $message, $status)`). Controllers check `$service->status !== 200` to decide `wp_send_json_error` vs `wp_send_json_success`.
- **Fluent setters:** Service classes use fluent method chaining for parameters: `->orderIds($ids)->schedule($schedule)->call()`.
- **HPOS compatibility:** Repository and controller code checks for WooCommerce HPOS (High-Performance Order Storage) using `OrderUtil::custom_orders_table_usage_is_enabled()`.
- **Translations:** Use `__('text', 'kiriminaja-official')` for all user-facing strings.
- **PHPCS comments:** Use `// phpcs:ignore` with explicit sniff names and a justification comment when suppressing warnings.

---

## Database Tables

| Table                              | Purpose                                                                                |
| ---------------------------------- | -------------------------------------------------------------------------------------- |
| `{prefix}_kiriminaja_settings`     | Key-value plugin settings (api_key, oid_prefix, setup_key, callback_url, origin data)  |
| `{prefix}_kiriminaja_transactions` | Shipping transactions linked to WC orders (order_id, awb, status, pickup_number, etc.) |
| `{prefix}_kiriminaja_payments`     | Payment/batch records for grouped shipments                                            |

---

## External API

- **Base URL:** `https://client.kiriminaja.com`
- **Auth:** Bearer token (stored in settings table as `api_key`)
- **Key endpoints used:**
  - `POST /api/service/api-request/integrate` — Setup key integration
  - `POST /api/mitra/v6.1/shipping_price` — Shipping rate calculation
  - `POST /api/mitra/v6.1/request_pickup` — Request courier pickup
  - `POST /api/mitra/v6.1/awb/print` — Print shipping label
  - `POST /api/mitra/v3/cancel_shipment` — Cancel shipment
  - `POST /api/mitra/tracking` — Package tracking
  - `POST /api/mitra/v2/schedules` — Pickup schedules
  - `POST /api/mitra/v2/get_payment` — Payment details
  - `POST /api/mitra/couriers` — Available couriers
  - `GET /api/mitra/kelurahan_by_name?search=` — Sub-district search
  - `POST /api/mitra/set_callback` — Register callback URL

---

## Testing

- Framework: PHPUnit 10.5+
- Test files: `tests/` directory
- Run: `vendor/bin/phpunit` or `make test`
- Tests cover: access control, cancel transaction flow, i18n, plugin structure, prefix validation, security validation, syntax validation.

---

## Build & Release

- `make zip` — Build distributable ZIP (excludes dev files)
- `make test` — Run PHPUnit
- `make release` — Bump version, tag, and build
- Version is defined in both `kiriminaja.php` header and `KIRIOF_VERSION` constant.

---

## Key Reminders for Code Generation

1. New controllers must be added to `Init::get_services()` array.
2. New AJAX handlers must include capability check + nonce verification BEFORE any logic.
3. All `$_POST`/`$_GET`/`$_REQUEST` access must be sanitized and unslashed.
4. Use `wp_remote_get()`/`wp_remote_post()` for HTTP calls (never `curl` directly).
5. Service classes should extend `BaseService` and return `ServiceResponse`.
6. Repository classes handle raw data access; never put business logic in repositories.
7. Template files live in `templates/` and are included via `require_once` or `include`.
8. JavaScript/CSS assets go in `assets/` and are enqueued via `Enqueue` class.
