# Instant checkout logs

## A constructor fatal is a startup blocker

An `ArgumentCountError` for `InstantShipmentState::__construct()` means PHP tried to construct the state service without its required `TransactionRepository`. It is not a GoSend or Grab pricing error. Plugin initialization may stop before the checkout integration and diagnostics are registered.

The current source constructs `InstantShipmentState` with the shared transaction repository. The central factory also explicitly handles direct state-service construction instead of falling through to a zero-argument constructor. Runtime tests cover both paths.

If the installed stack trace still shows a zero-argument construction, compare the installed `inc/Init.php` with the complete rebuilt distribution. Replace the plugin as a complete package; do not copy individual service files over another version. Clear PHP OPcache through the hosting dashboard or ask the host to reload PHP if the installed file is corrected but the old stack trace persists. Do not expose `phpinfo()` or OPcache administration publicly.

## Where to find the logs

1. Install the current rebuilt `kiriminaja-official.zip` and confirm there is no new startup fatal.
2. Open checkout with a physical product. Complete the shipping address and select/move the map pin. Try a non-COD payment and note whether global shipping insurance is enabled.
3. In WordPress admin, go to **WooCommerce → Status → Logs**.
4. Filter **Source** to **`kiriminaja_instant`**, then open the newest entry. A source appears only after a log entry is written; it is not a permanent dropdown registration.
5. If that source is missing, open **`fatal-errors`** and look for a new timestamp after reproducing checkout. Also check WooCommerce logging is enabled and its severity threshold permits warnings/info.

The shipping method ID is `kiriminaja-instant`; the log source is `kiriminaja_instant` because the plugin logger normalizes hyphens to underscores. The Instant source is included in the plugin Technical log download allowlist.

## What the readiness entry tells you

`Instant checkout local readiness diagnostics (not a live quote).` is a configuration check, not an API request or a guarantee that a specific package is deliverable. Its context uses fixed reason codes, service identifiers, booleans, and the plugin version. It does not log credentials, quote tokens, exact pins, buyer names, phones, or street addresses.

Common reasons:

| Code | Meaning |
| --- | --- |
| `checkout_integration_not_ready` | The validated buyer order/transaction integration is not registered. Merely adding a method or storing a pin does not complete that flow. |
| `method_not_registered` | The Instant shipping registration hook or loaded method class is missing. This is an integration/bootstrap problem, not a live pricing refusal. |
| `services_disabled` | No explicit supported GoSend/Grab service policy is configured. |
| `account_unavailable` | API credential configuration is missing. No credential value is logged. |
| `destination_pin_missing_or_invalid` | No valid version-2 buyer destination pin is stored. |
| `destination_address_changed_or_invalid` | The pin does not match the current six-field shipping address, or the Indonesian address is incomplete. |
| `default_origin_missing` | No stored default pickup location exists. Diagnostics do not create/repair it. |
| `default_origin_coordinates_invalid` | The default origin coordinates are missing or invalid. Real numeric zero is accepted. |
| `default_origin_address_invalid` | The pickup address does not meet the Instant address requirements. |
| `default_origin_name_invalid` | The pickup name does not meet the validated API length requirement. |
| `default_origin_phone_invalid` | The configured pickup phone is invalid. |
| `default_origin_postcode_invalid` | The pickup postcode is not five digits. |
| `timezone_unsupported` | The store/pickup timezone cannot be mapped to WIB, WITA, or WIT. |
| `cod_unsupported` | COD is selected; this Instant implementation does not support COD. |
| `insurance_unsupported` | Instant insurance is unsupported. Global Express insurance preferences are preserved but ignored by Instant; enabling them alone does not block Instant. |
| `diagnostics_unavailable` | A local diagnostic dependency failed. The raw exception is intentionally not exposed. |

The currently configured default origin may differ from a package-specific warehouse origin. These logs explicitly describe configured-store readiness, not a package-specific live quote. A registered order-integration class alone is not a live rate result. The implemented buyer Instant flow validates service entitlement, quote expiry, selected raw shipping/admin/total amounts, cart/origin/pin fingerprints, and transaction persistence. Live quote availability remains API-dependent.

Shipping-method calculations, when that method is enabled in the matching zone, log `Instant checkout rate calculation completed.` to the same source with a fixed code, availability boolean, rate count, and instance ID. They do not echo remote error messages.

## What to share for investigation

Send the newest readiness entry's reason codes/boolean context, its timestamp, the installed plugin version, WooCommerce version, and whether COD/global insurance was selected. For the reported fatal, send the latest stack trace and whether it still occurs after a complete-package replacement. Do not send API keys, PINs, session cookies, customer addresses, or exact coordinates.

No live courier pricing, booking, payment, or cancellation requests were performed to verify this diagnostic patch.

## Courier selection and totals

Install the complete rebuilt package when investigating Instant reverting to Express. The shipping-selection filter now uses WooCommerce's previous package selection (third argument), rather than its default first rate. An available native Instant rate must survive totals recalculation. Rates that disappear because of changed context or expiry are not revived from a stale mirror.

Instant names stay clean; API hour ETA remains native delivery time. Admin Fee is a separate native totals row. Delivery plus Admin Fee equals API `total_price`, while booking uses `shipping_costs`. Order validation checks the durable fee identity and amount; it does not silently substitute an Express transaction.
