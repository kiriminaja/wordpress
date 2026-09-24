# Instant Delivery Implementation Audit

Status: Proposed  
Last reviewed: 2026-09-22  
API reference: [KiriminAja Mitra API](https://developer.kiriminaja.com/docs)

## Purpose

This document records the current gap between the KiriminAja Instant Delivery API and the KiriminAja Official WooCommerce plugin. It covers rate calculation, checkout, transaction persistence, booking, payment, cancellation, tracking, webhook handling, and administration.

The checkout assessment covers both:

- WooCommerce Classic Checkout
- WooCommerce Cart and Checkout Blocks

## Executive Summary

Instant Delivery is feasible, but it cannot be implemented by only enabling GoSend, Grab Express, and Borzo in the courier whitelist.

The plugin already provides reusable WooCommerce shipping infrastructure for Classic Checkout and Checkout Blocks. However, its current pricing, transaction, pickup, payment, cancellation, tracking, and webhook flows assume an Express shipment contract.

The primary blocker is destination geolocation. Instant pricing requires destination latitude and longitude, while the plugin currently stores only a KiriminAja district or subdistrict identifier, postcode, and textual address.

Implementation should introduce a separate Instant domain flow while sharing common WooCommerce integration, cart calculation, order persistence, and administration components.

## KiriminAja Instant API Contract

### Supported couriers

The current API documentation lists:

- `gosend`
- `grab_express`
- `borzo`

### Supported vehicles

- `motor`
- `mobil`

### Supported timezones

- `WIB`
- `WITA`
- `WIT`

### Package constraint

Maximum package weight is 40,000 grams.

### Relevant endpoints

| Operation | Endpoint |
| --- | --- |
| Instant pricing | `POST /api/mitra/v4/instant/pricing` |
| Create Instant package | `POST /api/mitra/v6.2/instant/request_pickup` |
| Legacy Instant package creation | `POST /api/mitra/v4/instant/pickup/request` |
| Instant tracking | `GET /api/mitra/v4/instant/tracking/{order_id}` |
| Cancel Instant package | `DELETE /api/mitra/v4/instant/pickup/void/{order_id}` |
| Payment status | `POST /api/mitra/v2/get_payment` |
| Register callback | `POST /api/mitra/set_callback` |
| Instant shipment events | Instant webhook: `POST instantShipmentEvent` (see OpenAPI `webhooks.instantShipmentEvent`) |

The v6.2 Instant request pickup endpoint should be the default target unless KiriminAja requires the legacy v4 endpoint for a specific account or rollout.

### Instant webhook contract

The Instant webhook handler is very different from the Express delivery webhook. The authoritative contract is:

- [`POST instantShipmentEvent`](https://developer.kiriminaja.com/docs#webhook/POST/instantshipmentevent)

Key differences confirmed from the OpenAPI `instantShipmentEvent` schema:

- Allowed `method` values are only:
  - `shipped_packages`
  - `canceled_packages`
  - `finished_packages`
- There is no Instant equivalent of Express `processed_packages`, `returned_packages`, `validated_packages`, `rejected_packages`, `problem_packages`, or `return_finished_packages`.
- The `packages[]` entries expose a different model from Express, including:
  - `awb`
  - `order_id`
  - `service`
  - `service_type`
  - `status`
  - `live_tracking_url`
  - `poly_line`
- Instant tracking is intentionally thin: do not render the Express-style tracking history. Persist and render `live_tracking_url` from the Instant webhook instead.
- Prefer Instant webhook events for shipment status; use `GET /api/mitra/v4/instant/tracking/{order_id}` only as a fallback when webhook delivery is unavailable.

### Pricing field mapping

The Instant pricing response does not use the Express response shape.

Use the following mapping when creating an order:

| Pricing response | Order field |
| --- | --- |
| `result[].name` | `service` |
| `result[].costs[].service_type` | `service_type` |
| `result[].costs[].price.shipping_costs` | `shipping_cost` |

Do not send `admin_fee` or `total_price` as `shipping_cost`.

The pricing response may include insurance options, but the current Instant order contract does not support insurance allowance. The plugin should not send `insurance_type` unless KiriminAja confirms support.

## Existing Plugin Capabilities That Can Be Reused

### WooCommerce shipping method

The plugin registers a standard zone-based `WC_Shipping_Method` and adds regular `WC_Shipping_Rate` objects.

Relevant implementation:

- `wc/KiriminajaShippingMethod.php`
- `inc/Services/WooCommerceShippingMethodRegistrationService.php`

This foundation can expose Instant rates in both Classic Checkout and Checkout Blocks.

### Checkout state synchronization

The plugin already synchronizes selected shipping rates through:

- Classic Checkout form data and WooCommerce session state
- Store API `select-shipping-rate` requests
- Store API extension update callbacks

Relevant implementation:

- `inc/Controllers/CheckoutController.php`
- `inc/Controllers/GeneralAjaxController.php`

### District selection

Classic Checkout has a dynamic district selector. Checkout Blocks use an additional checkout field and Store API updates.

Relevant implementation:

- `inc/Controllers/CheckoutController.php`
- `inc/Services/CustomerDistrictService.php`
- `assets/wp/js/form-billing-address.js`

District selection remains useful for address normalization and Express rates, but it is insufficient for Instant pricing.

### Cart and package attributes

The plugin already calculates:

- Weight in grams
- Item value
- Package dimensions
- Product and cart totals

Relevant implementation:

- `inc/Services/UtilServices/GetWCCartAttributeService.php`
- `inc/Utils/WeightConverter.php`

### Order and transaction persistence

The plugin already creates WooCommerce order metadata and a local KiriminAja transaction record.

Relevant implementation:

- `inc/Services/CheckoutServices/CreateTransactionService.php`
- `inc/Repositories/TransactionRepository.php`
- `inc/Migration/SetupMigration.php`

### HPOS compatibility

Order writes use WooCommerce order methods instead of relying only on direct post metadata. This can be extended with Instant metadata.

## Current Gaps and Required Adjustments

## 1. Instant couriers are explicitly excluded

`inc/Services/KiriminajaApiService.php` filters out courier types named `instant` and `international`.

Current behavior:

```php
$excluded_types = array( 'instant', 'international' );
```

Consequences:

- Instant couriers do not appear in merchant courier settings.
- The current whitelist cannot enable GoSend, Grab Express, or Borzo.

Required adjustment:

- Stop globally excluding `instant` couriers.
- Keep Express and Instant eligibility distinct.
- Store courier type alongside courier code in the settings layer.
- Only expose Instant couriers when the account and plugin configuration support Instant delivery.
- Separate the courier settings/list UI into delivery-type tabs:
  - `Express`
  - `Instant`
  - `International (Coming soon, not rendered)` in the MVP; the International tab shows only the Coming soon state and does not render a courier list.
- Relevant courier list/settings entry points include `kiriof_get_courier_whitelist`, `kiriof_store_courier_whitelist`, `kiriminaja_search_expedition`, and the onboarding courier step.

## 2. Checkout does not capture destination coordinates

Instant pricing requires both origin and destination objects containing:

- Latitude
- Longitude
- Complete address

The origin settings already support coordinates. Checkout destination data currently contains district or subdistrict identifiers, labels, postcode, and textual address only.

Relevant implementation:

- `inc/Services/CustomerDistrictService.php`
- `inc/Controllers/CheckoutController.php`
- `inc/Controllers/GeneralAjaxController.php`

Required adjustment:

- Add a Leaflet destination map initialized from browser geolocation, with a draggable marker and explicit confirmation.
- Persist destination latitude, longitude, and normalized address in the WooCommerce customer, session, order, and KiriminAja transaction.
- Validate coordinates on the server before offering or booking Instant delivery.
- Clear an old coordinate when the customer changes address fields.
- Do not assume that the center point of a district is a valid delivery point.

This is the primary implementation blocker for both Classic Checkout and Checkout Blocks.

## 3. Express and Instant pricing use different contracts

Current pricing uses:

```text
POST /api/mitra/v6.1/shipping_price
```

The current parser expects fields such as:

- `results[]`
- `cost`
- `discount_amount`
- `service`
- `service_type`
- Express insurance and COD settings

Relevant implementation:

- `inc/Repositories/KiriminajaApiRepository.php`
- `inc/Services/CheckoutServices/OngkirPricingService.php`
- `inc/Services/CheckoutServices/CheckoutCalculationService.php`
- `wc/KiriminajaShippingMethod.php`

Instant pricing uses:

```text
POST /api/mitra/v4/instant/pricing
```

It requires:

- `item_price`
- Origin coordinates and address
- Destination coordinates and address
- Weight
- Vehicle
- Timezone
- Optional courier filter

Required adjustment:

- Add a dedicated Instant API repository method.
- Add an `InstantPricingService`.
- Normalize Express and Instant responses into an internal rate model before creating `WC_Shipping_Rate` objects.
- Keep raw API shipping cost separate from discounts applied by WooCommerce.
- Include coordinates, vehicle, timezone, item value, weight, and courier filter in the Instant cache key.

Suggested internal rate model:

```php
array(
    'delivery_type'    => 'instant',
    'service'          => 'grab_express',
    'service_type'     => 'instant',
    'vehicle'          => 'motor',
    'label'            => 'Grab Express Instant',
    'estimation'       => '1-2 hours',
    'api_shipping_cost'=> 30500,
    'customer_cost'    => 30500,
    'insurance_allowed'=> false,
    'cod_allowed'      => false,
)
```

## 4. Shipping rate identity is unsafe for `grab_express`

The plugin currently builds an expedition key by joining courier and service type with an underscore:

```text
courier_serviceType
```

It later parses the value with:

```php
explode( '_', $expedition, 2 )
```

Relevant implementation:

- `inc/Services/CheckoutServices/CreateTransactionService.php`
- `inc/Services/CheckoutServices/CheckoutCalculationService.php`
- `inc/Controllers/EditOrderController.php`

For `grab_express_instant`, the current parser produces:

```text
service = grab
service_type = express_instant
```

Required adjustment:

- Do not derive business fields by parsing a display or rate identifier.
- Store structured rate metadata keyed by the full WooCommerce rate ID.
- Persist `delivery_type`, `service`, `service_type`, and `vehicle` independently.
- Maintain a backward-compatible parser for existing Express orders only.

## 5. Instant package creation is not an Express pickup request

The current admin flow requires a pickup schedule and sends a batch of Express packages to:

```text
POST /api/mitra/v6.1/request_pickup
```

Relevant implementation:

- `inc/Services/TransactionProcessServices/SendRequestPickupTransactionService.php`
- `inc/Repositories/KiriminajaApiRepository.php`

Instant package creation uses a different endpoint and payload. It requires coordinate-based origin and destination data, vehicle, shipping cost, and line-level items.

Required adjustment:

- Add a separate `CreateInstantPackageService`.
- Do not require an Express pickup schedule for Instant delivery.
- Reprice before dispatch and reject a stale or materially changed quote.
- Send the raw `shipping_costs` value returned by Instant pricing.
- Prevent Express and Instant transactions from being submitted in one batch action.
- Persist the returned tracking code or AWB immediately.
- Dispatch is manual by admin from the order detail or Transactions workspace.

## 6. The package item model is insufficient

The current Express payload combines product names and package totals. Instant package creation requires an `items[]` list with item-level data.

Required adjustment:

Build item payloads from WooCommerce line items with:

- Name
- Description when available
- Unit or allocated price
- Unit or allocated weight
- Quantity handling

The implementation must define consistent rules for products with missing weight and for cart-level discounts.

## 7. Instant insurance is currently unsupported

The plugin can expose optional insurance or force insurance globally. Instant order creation currently does not support insurance allowance.

Required adjustment for Instant rates:

- Do not send `insurance_type`.
- Set insurance fee to zero.
- Hide or disable the insurance input.
- Do not let a global forced-insurance setting invalidate all Instant rates.
- Prevent stale Classic or Block Checkout insurance state from carrying into an Instant order.

## 8. Instant COD needs product and API confirmation

The current COD implementation uses Express pricing settings and Express request fields. The Instant v6.2 contract does not provide an equivalent documented COD calculation flow.

Required adjustment:

- Do not reuse Express COD fees or payload fields for Instant.
- Initially hide Instant rates when WooCommerce COD is selected.
- Instant receiver COD is out of scope for the MVP; do not reuse the Express `payment_method: cash` behavior.
- Enable Instant COD only after the amount, fee, eligibility, and settlement contract is documented and tested.

## 9. Payment response parsing assumes Express shape

The current payment service reads the remote payment from:

```php
$getKiriofPayment['data']->data
```

Relevant implementation:

- `inc/Services/ShippingProcessServices/GetShippingProcessPayment.php`

Instant responses can return payment information through `result`, and response status values may differ in type.

Required adjustment:

Normalize payment responses from both contracts:

```text
Express payment payload: response.data
Instant payment payload: response.result
```

Normalize status codes to strings or integers consistently before comparing them.

## 10. Cancellation and tracking use Express endpoints

Current endpoints:

```text
POST /api/mitra/tracking
POST /api/mitra/v3/cancel_shipment
```

Instant endpoints:

```text
GET /api/mitra/v4/instant/tracking/{order_id}
DELETE /api/mitra/v4/instant/pickup/void/{order_id}
```

Relevant implementation:

- `inc/Repositories/KiriminajaApiRepository.php`
- `inc/Services/KiriminAjaTrackingService.php`
- `inc/Services/CancelTransactionService.php`

The Instant tracking response contains a different model, including:

- Driver information
- Origin and destination coordinates
- Lifecycle timestamps
- Tracking code
- Live tracking URL
- Cancellation description
- Shipping, insurance, admin, and total cost fields

Required adjustment:

- Route tracking and cancellation by `delivery_type`.
- Add an Instant tracking response normalizer.
- Do not render Express-style tracking history for Instant; render the Instant `live_tracking_url` supplied by the Instant webhook instead.
- Only use `GET /api/mitra/v4/instant/tracking/{order_id}` as a fallback for fetching `live_tracking_url` when webhook delivery is unavailable.
- Preserve `live_tracking_url` when present.
- Treat Instant body `code: 0` as success and `code: 2` as data not found.
- Prefer webhooks and use tracking polling as fallback because tracking is rate-limited.

## 11. Instant webhook handling is a separate contract

The current callback handler is Express-oriented. Express supports `processed_packages`, `shipped_packages`, `canceled_packages`, `finished_packages`, `returned_packages`, `problem_packages`, and deprecated `return_finished_packages`.

The Instant webhook contract is different and narrower. The authoritative reference is [`POST instantShipmentEvent`](https://developer.kiriminaja.com/docs#webhook/POST/instantshipmentevent):

- Allowed Instant methods are only `shipped_packages`, `canceled_packages`, and `finished_packages`.
- Instant `packages[]` entries contain `awb`, `order_id`, `service`, `service_type`, `status`, `live_tracking_url`, and `poly_line`.
- There is no Instant `processed_packages` AWB event; persist tracking code/AWB from the Instant booking response.
- There is no COD/non-COD distinction in the Instant webhook payload.

Relevant implementation:

- `inc/Services/CallbackHandlerService.php`

Instant webhooks may provide summary data and package data in separate root properties. The current handler uses `body.data` when it exists and does not merge it with `body.packages`.

Required adjustment:

- Add a separate Instant webhook branch/handler instead of reusing Express lifecycle handling.
- Accept only `shipped_packages`, `canceled_packages`, and `finished_packages` for Instant.
- Persist the tracking code or AWB from the Instant booking response instead of waiting for an Express processed event.
- Merge webhook `data` and `packages` by `order_id` when both are available.
- Persist live tracking URL, package status, and other useful Instant fields.
- Do not reuse Express COD/non-COD transaction labeling for Instant. In the transaction row section that currently shows COD/non-COD, show the vehicle used to send the package instead.
- Keep webhook processing idempotent.
- Handle duplicate and out-of-order events.
- Define WooCommerce order status transitions for allocated, shipped, finished, canceled, and failed Instant deliveries.

## 12. Transaction storage lacks Instant attributes

The current transaction table stores Express-oriented fields such as district, service, dimensions, costs, AWB, pickup number, and status.

Relevant implementation:

- `inc/Migration/SetupMigration.php`
- `inc/Repositories/TransactionRepository.php`

Required Instant data includes:

- Delivery type
- Vehicle
- Origin latitude and longitude snapshot
- Destination latitude and longitude
- Normalized origin and destination addresses
- Quoted shipping cost
- Quote timestamp or expiry context
- Live tracking URL
- Remote package status
- Optional driver or route metadata

Recommended approach:

- Add indexed or frequently queried fields as columns.
- Store volatile courier-specific details in a JSON metadata column.
- Keep order metadata and transaction metadata synchronized through one service.

## 13. Current pricing cache is too broad for Instant

The existing pricing cache uses a five-minute TTL and includes a shared transient cache.

Relevant implementation:

- `inc/Services/CheckoutServices/PricingCacheService.php`

Instant rates depend on precise coordinates and may change faster than Express rates.

Required adjustment:

- Use a separate Instant cache namespace.
- Include origin and destination coordinates, vehicle, timezone, item value, weight, and courier filter in the cache key.
- Prefer session-scoped caching.
- Use a shorter configurable TTL.
- Debounce checkout requests.
- Reprice during final checkout validation and immediately before package creation.
- Add backoff for HTTP 429 responses. The documented Sandbox limit is 10 requests per minute.

## 14. WooCommerce shipping discounts must not change the API shipping cost

A WooCommerce coupon may reduce the customer-facing shipping charge, including reducing it to zero. KiriminAja package creation still requires the validated Instant API shipping cost.

Required adjustment:

Store both:

- Raw KiriminAja shipping cost
- Customer-facing WooCommerce shipping cost

Never send the discounted customer charge as the Instant API `shipping_cost` unless the API explicitly documents that behavior.

The generic free-shipping fallback must also retain the selected Instant service identity and raw quote metadata.

## WooCommerce Classic Checkout

Classic Checkout already has direct PHP hooks, posted checkout fields, WooCommerce fragments, jQuery checkout events, and order validation.

Required work:

1. Add a Leaflet destination map initialized from browser geolocation.
2. Store latitude, longitude, and normalized address in hidden checkout fields.
3. Use `motor` as the fixed MVP vehicle.
4. Trigger `update_checkout` when coordinates or vehicle change.
5. Clear the current Instant selection when the address changes.
6. Validate destination coordinates on the server.
7. Enforce the 40 kg maximum before exposing an Instant rate and during checkout submission.
8. Hide or disable insurance for Instant rates.
9. Initially hide Instant rates when COD is selected.
10. Persist the normalized Instant rate context when creating the order.

Classic Checkout is the simpler first integration target because the plugin already controls its custom fields and refresh events.

## WooCommerce Cart and Checkout Blocks

The plugin already includes Block-specific support:

- Additional checkout field registration
- Store API extension update callback
- Store API selected-rate synchronization
- Customer and order metadata aliases for district data

Relevant implementation:

- `inc/Controllers/CheckoutController.php`
- `inc/Services/CustomerDistrictService.php`
- `assets/wp/js/form-billing-address.js`

Required work:

1. Implement the Leaflet map and draggable marker as a Checkout Block integration, Slot/Fill component, or supported additional-field extension.
2. Send latitude, longitude, normalized address, vehicle, and delivery type through the Store API extension update callback.
3. Persist these fields in the WooCommerce customer, session, checkout order, and transaction.
4. Add Store API server-side validation. Classic `woocommerce_checkout_process` validation does not run for Checkout Blocks.
5. Expose namespaced Store API extension data for Instant eligibility, selected vehicle, and quote metadata.
6. Recalculate rates when coordinates or vehicle change.
7. Clear insurance state for an Instant selection.
8. Ensure scripts load on Block Checkout pages embedded outside the conventional WooCommerce checkout page.
9. Avoid relying only on DOM mutation or Classic Checkout jQuery events.

A standard `WC_Shipping_Rate` can already appear in Checkout Blocks. The additional work is required to collect and validate Instant-specific state reliably.

## Recommended Architecture

### API repository methods

Add explicit methods instead of overloading Express methods:

```php
getInstantPricing( array $payload )
createInstantPackage( array $payload )
getInstantTracking( string $order_id )
cancelInstantPackage( string $order_id )
```

### Domain services

Recommended service split:

```text
ExpressPricingService
InstantPricingService
ShippingRateNormalizer
CreateExpressPickupService
CreateInstantPackageService
ExpressTrackingService
InstantTrackingService
ShipmentCancellationService
```

### Delivery type routing

Persist `delivery_type` as either:

```text
express
instant
```

Use it to route:

- Pricing
- Checkout eligibility
- Insurance and COD behavior
- Package creation
- Payment response parsing
- Cancellation
- Tracking
- Webhook persistence
- Admin actions

### Structured rate context

Do not parse business data from the WooCommerce rate ID. Store a structured context in the WooCommerce session and order metadata.

Suggested context:

```php
array(
    'delivery_type'     => 'instant',
    'service'           => 'gosend',
    'service_type'      => 'instant',
    'vehicle'           => 'motor',
    'origin_latitude'   => -7.8032616,
    'origin_longitude'  => 110.350244,
    'destination_latitude'  => -7.7349434,
    'destination_longitude' => 110.405355,
    'api_shipping_cost' => 34000,
    'quoted_at'         => 1789999999,
)
```

## Confirmed MVP Requirements

### Checkout and buyer eligibility

- The first public release must support both WooCommerce Classic Checkout and Checkout Blocks.
- Instant Delivery is available only to logged-in buyers. Guest buyers continue to see non-Instant shipping methods.
- Browser geolocation is requested when checkout opens.
- The initial GPS result must report an accuracy radius of 100 meters or better to enable Instant automatically.
- A buyer with a less accurate result may manually correct and confirm the destination pin.
- The manually confirmed pin may not be more than 500 meters from the initial GPS coordinate. Without geocoding, this is the MVP's location consistency rule.
- GPS failure or denied permission hides Instant rates without blocking Express checkout.
- The pin represents the shipping recipient location, not necessarily the buyer's current device location.
- When `ship to a different address` is enabled, the pin belongs to the shipping address.
- Instant pricing is requested only after the buyer confirms the pin.

### Map and location behavior

- Use Leaflet with OpenStreetMap standard tiles for the MVP.
- No address search, autocomplete, reverse geocoding, MapTiler, or other geocoding provider is required.
- The GPS location is the initial marker position. The buyer may drag it to the actual recipient location and confirm it.
- OpenStreetMap attribution and tile usage requirements must be followed. A configurable tile provider may be required before traffic exceeds the standard tile service's acceptable usage.
- Save the confirmed coordinates against the logged-in buyer's shipping address.
- Use an address fingerprint containing the relevant street, district, postcode, and country fields.
- Any address-field change invalidates the saved coordinates and requires pin confirmation again.
- A returning buyer with an unchanged address uses the saved pin instead of requesting GPS again.
- Coordinates are personal data. Integrate them with WordPress personal data export and erasure tools.

### Courier, vehicle, and package eligibility

- Instant activation follows the existing courier whitelist. There is no separate global Instant feature toggle for the MVP.
- Show all available whitelisted Instant courier rates returned by KiriminAja.
- The MVP supports `motor` only.
- All physical products are eligible when their shipment data is valid.
- Instant is unavailable when any physical product has no valid weight.
- Enforce the API maximum total weight of 40,000 grams.
- Every configured shipment origin may offer Instant when it has a complete address, latitude, longitude, and timezone.
- Store `WIB`, `WITA`, or `WIT` per origin or warehouse and use that value for Instant pricing.

### Insurance and COD

- WooCommerce COD is not supported with Instant in the MVP. Hide Instant rates when COD is selected.
- Instant pricing insurance options may be shown as informational data only.
- Buyers cannot select Instant insurance, no insurance fee is charged, and the booking payload does not send insurance allowance.

### Dispatch and payment

- Instant package creation is manual by an admin.
- Dispatch actions are available from both WooCommerce order detail and KiriminAja Transactions.
- Only paid or `processing` WooCommerce orders may be dispatched.
- The admin chooses TOP, QRIS, or KA Credit for Instant dispatch. Instant payment has a separate complete flow from Express, including its own modal/UI, request state, payment status handling, PIN handling where required, polling or refresh behavior, error states, retry behavior, and success/close transitions.
- Bulk dispatch is allowed when origin, courier, vehicle, and payment method are the same.
- Bulk payment uses one Instant payment modal per compatible bulk group.
- Bulk API submission is adaptive: prefer one v6.2 request per compatible group using `packages[]`, and fall back to one request per order when the API response or failure mode requires isolation.
- Bulk processing supports partial success. Successful packages remain dispatched; failed packages retain their failure reason and can be retried.
- Reprice each order before dispatch.
- If the price changes, show the checkout quote and current quote and require confirmation per order. Admin may continue or skip each order independently.
- The merchant absorbs any shipping price increase. Do not modify the buyer's WooCommerce total.
- If the checkout courier is unavailable, show available Instant alternatives and let the admin select one. Do not switch automatically or fall back to Express.
- If an admin edits the shipping address after checkout, clear the saved order coordinates and block dispatch until a new pin is selected and repricing succeeds.
- Send the WooCommerce shipping address as the Instant destination address. The GPS/pin supplies coordinates; it does not replace the textual shipping address.
- Use one stable Instant `order_id` per WooCommerce order. A retry must reuse that identifier to avoid creating a duplicate shipment.
- Use `package_type_id = 7` as the temporary MVP constant. Keep it configurable in code so it can be replaced when KiriminAja provides the production mapping.
- Validate sender and recipient name, phone, address, and item fields against the API constraints. Block dispatch with a specific error rather than silently truncating invalid values.

#### Instant payment modal

- The Instant payment modal is a separate implementation from the Express payment modal, even when it shares visual components.
- For QRIS, show the returned QR content, payment state, polling state, paid state, expired state, API error state, retry action, and close behavior.
- Poll payment status until paid or expired, with a bounded retry/backoff policy. If the API does not provide an expiry time, use a configurable five-minute UI expiry. Do not poll indefinitely after the modal is closed; the transaction remains pending and can be refreshed from Transactions.
- KA Credit must handle PIN input/validation, insufficient balance, invalid PIN, retry limits, cancellation, and successful booking state.
- TOP must show the remote payment/booking state and handle pending, paid, rejected, and retryable states without assuming QRIS behavior.
- If booking times out after payment is marked paid, check the remote order or payment status using the stable order/payment identifier before retrying.

### Transactions workspace

The KiriminAja Transactions page must separate delivery workflows with persistent tabs, following the Shopify-style interaction pattern shown in the reference image:

- `Regular Delivery`
- `Instant Delivery`
- `Order Issue` (existing COD Deficit workflow)

International Delivery is deferred from the MVP. The tabs are functional filters, not only visual labels. Each transaction belongs to one primary delivery tab. `Order Issue` remains the existing COD Deficit workflow and is not a generic Instant operational issue inbox in the MVP.

Requirements:

- Default to `Regular Delivery` when no tab has been selected.
- Persist the active tab in the URL so refresh, browser back, and shared admin links preserve the current view.
- Scope search, pagination, bulk selection, and bulk actions to the active tab.
- Do not allow a bulk action to mix Regular and Instant transactions.
- Keep the KA Credit balance summary and payment actions available at the top of the workspace when the account supports them, including Top Up and History entry points.
- Keep filters contextual to the active tab instead of showing Express-only filters on Instant transactions.
- Regular Delivery filters continue to support order or AWB lookup, COD/non-COD, transaction status, print status, and courier.
- Instant Delivery filters support WooCommerce order or KiriminAja order/tracking lookup, transaction status, courier, and payment method. Express pickup schedule filters are not required for Instant.
- `Order Issue` continues to show COD Deficit transactions according to the existing workflow. It does not collect Instant pricing, booking, payment, webhook, or retry issues in the MVP.
- Instant operational problems remain in the Instant Delivery tab and are represented by an issue badge, actionable error message, and the appropriate retry, reprice, or resolution action.
- Instant has a special `Find New Driver` status/state. When this status is shown, the Instant table row must expose a `Find New Driver` action/button.
- Instant rows show delivery type, courier/service, vehicle used to send the package, payment method, WooCommerce order, KiriminAja order ID, tracking code or AWB, package and fee summary, current status, and state-appropriate actions. There is no COD/non-COD display for Instant rows.
- Instant actions include dispatch, safe retry, reprice, tracking, `Find New Driver` when that status applies, and void/cancel only when the remote shipment state allows it.
- Show confirmation before dispatch, retry, or void actions. For price changes, show the checkout quote and current quote in the confirmation step.
- Bulk Instant dispatch enforces the grouping rules: same origin, courier, vehicle, and payment method. Each package remains an independent API request and partial success is reported per row.
- When a tab has no transactions, show a useful empty state with the relevant next action instead of an unfiltered generic empty table.
- The tab and table remain keyboard accessible and usable on smaller admin screens without hiding critical status or action information.

### Order lifecycle, cancellation, and tracking

- A successfully dispatched order remains `processing` during delivery.
- A successful Instant delivery webhook moves the WooCommerce order to `completed` automatically.
- A failed or remotely canceled delivery leaves the order `processing` and adds an order note for manual handling.
- Instant has a special `Find New Driver` state. When present, the Instant table exposes the `Find New Driver` action while the order remains actionable; the implementation must confirm the KiriminAja trigger/endpoint and terminal-state rules for this action.
- Canceling a dispatched WooCommerce order attempts the Instant void endpoint first and records the remote result.
- Live tracking is available to administrators and the authenticated buyer who owns the order.
- Show live tracking from My Account order detail and the plugin tracking page. The tracking page must validate the WooCommerce order key before exposing the live URL.
- For Instant, render only the webhook-supplied tracking URL; do not render Express-style tracking history.
- An Instant transaction with a post-dispatch API or webhook problem remains in the Instant Delivery tab and receives an issue badge; it does not move to the COD Deficit `Order Issue` tab.

### Explicit MVP exclusions

- Guest checkout for Instant
- `mobil`
- Receiver COD
- Selectable Instant insurance
- Address search or geocoding
- Borzo multi-destination
- Automatic dispatch
- Automatic courier replacement
- Automatic Express fallback
- Generic Instant operational issue inbox; Instant issues remain in the Instant Delivery tab with an issue badge in the MVP.

## External Confirmations Still Required

These are production-readiness dependencies to confirm with KiriminAja or infrastructure owners. They are not blockers for the MVP Sandbox implementation:

1. Confirm which merchant account plans and production accounts are entitled to Instant couriers.
2. Confirm that omitting Instant insurance allowance is the correct production behavior even when pricing returns insurance options.
3. Confirm OpenStreetMap standard tile usage is acceptable for the expected production traffic, or provide a production tile service before launch.
4. Confirm the production `package_type_id` value. The MVP temporarily uses `7`, taken from the OpenAPI example.
5. Confirm the production success and error response contract for v6.2 Instant booking. The OpenAPI page labels its success response as a mock shape, so the implementation must normalize defensively and validate Sandbox responses.
6. Confirm the KiriminAja trigger, payload/status value, endpoint/action, retry semantics, and terminal-state transitions for the special Instant `Find New Driver` state.

The representative Instant request and response examples should be taken from the KiriminAja developer documentation and OpenAPI reference during implementation:

- [KiriminAja Mitra API documentation](https://developer.kiriminaja.com/docs)
- [KiriminAja OpenAPI JSON](https://developer.kiriminaja.com/docs/openapi/json)

## Suggested Delivery Phases

### Phase 1: Rate foundation

- Expose eligible Instant couriers in settings.
- Add destination geolocation for Classic Checkout.
- Add Instant pricing and normalized rate output.
- Add structured rate context.
- Enforce weight, vehicle, insurance, and COD rules.

### Phase 2: Classic Checkout booking

- Persist Instant checkout context.
- Add item-level payload mapping.
- Add Instant package creation.
- Add repricing and stale quote handling.
- Persist payment and tracking identifiers.

### Phase 3: Post-booking operations

- Add Instant payment normalization.
- Add Instant cancellation.
- Add Instant tracking and live tracking URL.
- Extend webhook processing and status transitions.
- Add Instant-specific admin actions and labels.
- Add Regular Delivery, Instant Delivery, and the existing COD Deficit `Order Issue` tabs to the Transactions workspace. Defer International Delivery.

### Phase 4: Checkout Blocks

- Add the Block-compatible geolocation component.
- Add Store API extension state and validation.
- Verify cart and checkout recalculation.
- Verify persistence for authenticated buyers and confirm that guests do not receive Instant rates.

Classic and Block work may run in parallel after the normalized Instant rate and checkout-context contracts are stable.

### Phase 5: Hardening

- Add API retry and HTTP 429 backoff.
- Add observability without logging personal data or API credentials.
- Test webhook replay and out-of-order delivery.
- Test pricing changes between checkout and dispatch.
- Test merchant accounts without Instant entitlement.

## Acceptance Criteria

### Pricing

- GoSend, Grab Express, and Borzo can be enabled independently when returned by the API.
- Instant rates require valid origin and destination coordinates.
- Rates are not shown above 40,000 grams.
- `grab_express` is preserved as a complete courier code.
- The plugin uses `price.shipping_costs` as the API shipping cost.
- The plugin never uses `admin_fee` or `total_price` as `shipping_cost`.
- Rate cache keys include all Instant pricing inputs.

### Classic Checkout

- Changing address coordinates refreshes shipping rates.
- Selecting an Instant rate clears or disables insurance.
- Invalid or missing coordinates prevent checkout with a clear error.
- The final order contains coordinates, vehicle, service, service type, delivery type, and raw quote data.

### Checkout Blocks

- The location input works without Classic Checkout fragments or jQuery checkout events.
- Store API updates persist the Instant checkout context.
- Store API validation rejects missing or stale Instant context.
- Authenticated checkout persists the Instant context; guest checkout does not receive Instant rates.

### Transactions workspace

- The active tab is reflected in the URL and survives refresh and browser navigation.
- A transaction appears in exactly one delivery tab. COD Deficit records appear in `Order Issue`; Instant operational issues remain in Instant with an issue badge.
- Instant transactions never appear in the Regular Delivery list.
- Bulk selection cannot span Regular and Instant tabs and cannot dispatch incompatible Instant groups.
- Instant rows expose the correct dispatch, repricing, tracking, retry, `Find New Driver`, and void actions for their state.
- The COD/non-COD transaction column is replaced by the vehicle for Instant rows.
- Instant API and data-quality failures remain visible in Instant with an actionable reason and retry or resolution path.
- Empty states and filters are specific to the selected tab.

### Package creation

- Express and Instant transactions cannot be mixed in one pickup request.
- Instant package creation does not require an Express schedule.
- The request contains item-level data and valid coordinates.
- The request sends the last validated raw API shipping cost.
- The returned tracking code or AWB is saved immediately.

### Tracking and cancellation

- Instant orders use Instant endpoints.
- Express orders continue to use Express endpoints.
- Instant tracking displays driver and live tracking data when available.
- Instant tracking does not render Express-style history; it renders the webhook-supplied tracking URL.
- Cancellation records the remote result and local transaction state consistently.

### Webhooks

- Duplicate webhooks do not duplicate side effects.
- Out-of-order webhooks do not move a finished order back to an earlier state.
- Instant package data is merged by `order_id`.
- Live tracking and package status are retained when supplied.
- Instant webhooks accept only `shipped_packages`, `canceled_packages`, and `finished_packages`; Express-only methods are not reused for Instant.

## Test Coverage Required

Add focused tests for:

- Courier filtering and merchant Instant eligibility
- Instant pricing request construction
- Sandbox and Production response normalization
- `grab_express` structured identity
- Motor and mobil rates
- Weight at 39,999, 40,000, and 40,001 grams
- Missing and stale destination coordinates
- Address changes after rate selection
- Instant insurance exclusion
- Instant COD exclusion until supported
- WooCommerce shipping coupons and free shipping
- Classic Checkout validation
- Checkout Blocks Store API validation
- Guest and authenticated checkout persistence
- Item-level request payloads
- Instant payment `result` parsing
- Instant tracking success and data-not-found responses
- Instant cancellation
- Duplicate and out-of-order webhooks
- HTTP 429 backoff behavior

## Out of Scope for the First Release

Unless product requirements state otherwise:

- Borzo multi-destination delivery
- Instant insurance
- Instant receiver COD
- Automatic fallback from Instant to Express after order creation
- Automatic selection of motor or mobil without a documented rule

## Source References

External documentation:

- [KiriminAja Mitra API](https://developer.kiriminaja.com/docs)
- [KiriminAja OpenAPI JSON](https://developer.kiriminaja.com/docs/openapi/json)

Primary plugin files reviewed:

- `wc/KiriminajaShippingMethod.php`
- `inc/Repositories/KiriminajaApiRepository.php`
- `inc/Repositories/TransactionRepository.php`
- `inc/Services/KiriminajaApiService.php`
- `inc/Services/CustomerDistrictService.php`
- `inc/Services/CheckoutServices/OngkirPricingService.php`
- `inc/Services/CheckoutServices/CheckoutCalculationService.php`
- `inc/Services/CheckoutServices/CreateTransactionService.php`
- `inc/Services/CheckoutServices/PricingCacheService.php`
- `inc/Services/TransactionProcessServices/SendRequestPickupTransactionService.php`
- `inc/Services/ShippingProcessServices/GetShippingProcessPayment.php`
- `inc/Services/KiriminAjaTrackingService.php`
- `inc/Services/CallbackHandlerService.php`
- `inc/Controllers/CheckoutController.php`
- `inc/Controllers/GeneralAjaxController.php`
- `inc/Controllers/EditOrderController.php`
- `inc/Migration/SetupMigration.php`
- `assets/wp/js/form-billing-address.js`
