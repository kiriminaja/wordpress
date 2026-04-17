# V3 Migration Plan: `kiriminaja/kiriminaja-php` SDK Integration

## Overview

Replace the hand-rolled `KiriminAjaApi` + `KiriminajaApiRepository` HTTP layer with the official [`kiriminaja/kiriminaja-php`](https://github.com/kiriminaja/kiriminaja-php) SDK. This reduces maintenance burden, ensures API compatibility, and gives access to new SDK features (instant delivery, cancel shipment, etc.).

**Target branch:** `v3`  
**Base version:** 2.1.3 (from `sa/AB#25922`)  
**New version:** 3.0.0  
**PHP requirement change:** 7.0 → 8.1 (SDK requires PHP 8.1+)

---

## Current Architecture

```
KiriminAjaApi (base class — wp_remote_get/post, hardcoded base URL)
  └── KiriminajaApiRepository (10 API methods)
        └── KiriminajaApiService (thin wrapper, returns BaseService response)
              └── Controllers / Services / WC Shipping Method
```

- **Auth:** `Authorization: Bearer {api_key}` from `wp_kiriminaja_settings` DB table
- **Base URL:** `https://client.kiriminaja.com` (hardcoded)
- **Response format:** `['status' => bool, 'data' => stdClass]`

---

## SDK Architecture

```
KiriminAjaConfig::setMode(Mode::Production)::setApiTokenKey($key)
KiriminAja::methodName() → ServiceResponse
```

- **Auth:** `KiriminAjaConfig::setApiTokenKey($key)`
- **Base URL:** Resolved from mode (Production/Staging) or custom `setBaseUrl()`
- **Response:** `ServiceResponse` objects

---

## Method Mapping

| # | Plugin Method | SDK Equivalent | Endpoint | Status |
|---|--------------|----------------|----------|--------|
| 1 | `sub_district_search($search)` | `KiriminAja::getDistrictByName($name)` | `/api/mitra/v2/get_address_by_name` | ✅ Match (newer endpoint) |
| 2 | `setCallback($url)` | `KiriminAja::setCallback($url)` | `/api/mitra/set_callback` | ✅ Exact match |
| 3 | `processSetupKey($payload)` | — | `/api/service/api-request/integrate` | ❌ No SDK (keep custom) |
| 4 | `getPayment($payload)` | `KiriminAja::getPayment($paymentID)` | `/api/mitra/v2/get_payment` | ✅ Exact match |
| 5 | `getTracking($payload)` | `KiriminAja::getTracking($orderID)` | `/api/mitra/tracking` | ✅ Exact match |
| 6 | `getPricing($payload)` | `KiriminAja::getPrice(ShippingPriceData)` | `/api/mitra/v6.1/shipping_price` | ✅ Exact match |
| 7 | `getRequestPickupSchedule()` | `KiriminAja::getSchedules()` | `/api/mitra/v2/schedules` | ✅ Exact match |
| 8 | `sendPickupRequest($payload)` | `KiriminAja::requestPickup(RequestPickupData)` | `/api/mitra/v6.1/request_pickup` | ✅ Exact match |
| 9 | `get_couriers()` | `KiriminAja::getCouriers()` | `/api/mitra/couriers` | ✅ Exact match |
| 10 | `getPrintAwb($awb)` | — | `/api/mitra/v6.1/awb/print` | ❌ No SDK (keep custom) |

---

## Implementation Steps

### Step 1: Add SDK dependency
```bash
composer require kiriminaja/kiriminaja-php
```
Update `composer.json` to add `"kiriminaja/kiriminaja-php": "^2.1"` in `require`.

### Step 2: Initialize SDK in plugin bootstrap
In `kiriminaja.php`, after loading autoload, configure the SDK:
```php
use KiriminAja\Base\Config\KiriminAjaConfig;
use KiriminAja\Base\Config\Cache\Mode;

// Read API key from DB
$settings = (new SettingRepository())->getIntegrationSetting();
if ($settings && !empty($settings->api_key)) {
    KiriminAjaConfig::setMode(Mode::Production)
        ::setApiTokenKey($settings->api_key)
        ::setCacheDirectory(KIRIOF_DIR . '/cache');
}
```

### Step 3: Refactor `KiriminajaApiRepository`
Replace 8 methods with SDK calls. Keep `processSetupKey()` and `getPrintAwb()` as direct `wp_remote_post()`.

Example:
```php
// Before
public function getPricing($payload) {
    return $this->post('/api/mitra/v6.1/shipping_price', $payload);
}

// After
public function getPricing($payload) {
    $data = new ShippingPriceData();
    $data->origin = $payload['subdistrict_origin'];
    $data->destination = $payload['subdistrict_destination'];
    $data->weight = $payload['weight'];
    // ... map other fields
    return KiriminAja::getPrice($data);
}
```

### Step 4: Adapt response format
SDK returns `ServiceResponse` objects. Callers expect `['status' => bool, 'data' => stdClass]`. Options:
- **Option A (adapter):** Wrap SDK responses in the legacy format inside `KiriminajaApiRepository` — zero changes to callers.
- **Option B (direct):** Update all callers to use `ServiceResponse` — cleaner but more files to change.

**Recommended:** Option A first, then Option B incrementally.

### Step 5: Remove `KiriminAjaApi` base class
Once all methods are migrated, `inc/Base/KiriminAjaApi.php` is no longer needed (except for the 2 gap methods, which can use `wp_remote_post` directly).

### Step 6: Update version & metadata
- `kiriminaja.php`: Version → `3.0.0`, Requires PHP → `8.1`
- `readme.txt`: Stable tag → `3.0.0`, Requires PHP → `8.1`, add changelog

---

## Files to Modify

| File | Change |
|------|--------|
| `composer.json` | Add `kiriminaja/kiriminaja-php` to `require` |
| `kiriminaja.php` | SDK initialization, version bump to 3.0.0 |
| `inc/Base/KiriminAjaApi.php` | Remove (or keep minimal for gap methods) |
| `inc/Repositories/KiriminajaApiRepository.php` | Replace 8 methods with SDK calls |
| `inc/Services/KiriminajaApiService.php` | Simplify or remove (SDK handles HTTP) |
| `readme.txt` | Version bump, PHP requirement, changelog |

### Files unchanged (if using adapter pattern)
All callers remain untouched since the repository preserves the response format:
- `inc/Controllers/GeneralAjaxController.php`
- `inc/Controllers/EditOrderController.php`
- `inc/Controllers/SettingController.php`
- `inc/Controllers/ShippingProcessController.php`
- `inc/Services/SettingService.php`
- `inc/Services/KiriminAjaTrackingService.php`
- `inc/Services/CheckoutServices/OngkirPricingService.php`
- `inc/Services/CheckoutServices/CheckoutCalculationService.php`
- `inc/Services/ShippingProcessServices/GetShippingProcessPayment.php`
- `inc/Services/TransactionProcessServices/GetRequestPickupScheduleService.php`
- `inc/Services/TransactionProcessServices/SendRequestPickupTransactionService.php`
- `wc/KiriminajaShippingMethod.php`

---

## New SDK Capabilities (bonus for v3+)

These SDK methods are available but not currently used by the plugin:

| Method | Purpose |
|--------|---------|
| `getProvince()` | List all provinces |
| `getCity($provinceID)` | Cities in a province |
| `getDistrict($cityID)` | Districts in a city |
| `getSubDistrict($districtID)` | Sub-districts by district |
| `getCourierGroups()` | Courier groups |
| `getCourierDetail($code)` | Single courier details |
| `setWhiteListExpedition($services)` | Set whitelist via API |
| `fullShippingPrice($data)` | All-courier pricing |
| `cancelShipment($ref, $reason)` | Cancel shipment |
| `requestPickupInstant(...)` | Instant delivery pickup |
| `getTrackingInstant($orderID)` | Instant delivery tracking |
| `getPriceInstant($data)` | Instant delivery pricing |
| `findNewDriver($orderID)` | Find new driver (instant) |

---

## Risks & Mitigation

| Risk | Mitigation |
|------|-----------|
| SDK response format differs from legacy | Adapter pattern preserves `['status' => bool, 'data' => stdClass]` |
| SDK uses its own HTTP client (not `wp_remote_*`) | Acceptable — SDK manages its own transport |
| PHP 8.1 requirement breaks older hosts | Document in readme, major version bump signals breaking change |
| `processSetupKey` / `getPrintAwb` have no SDK support | Keep direct `wp_remote_post` for these 2 methods |
| SDK caching may conflict with WP transients | Use `setCacheDirectory()` or `disableCache()` |
