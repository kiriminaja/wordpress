# Instant checkout logs

## Admin “Unknown outcome” booking diagnostics

### HTTP 400/422 responses

Before the HTTP-error inspection fix, `http_failure` returned before reading JSON. Consequently `acknowledgement_type: NULL` and `result_type: NULL` on an HTTP 400 did **not** establish that the API body had null/missing fields. The response had been discarded. A short `elapsed_ms` rules out a 25-second timeout but does not reveal the validation error or prove a booking never happened.

The bounded transport now reads HTTP error bodies using the same 2 MiB cap, preserves its failure tuple, and provides only an in-memory internal JSON inspection seam. No raw body is persisted or returned to the browser. `transport.error_body` logs a fixed format, boolean status/type, result type, whether a message exists, broad message categories, and whitelisted validation paths. Numeric package/item indexes are removed; unknown names and all field values/messages are omitted. Categories are hints extracted from root message keywords, not an authenticated error-code contract or proof of remote rejection.

Only HTTP **400/422** plus the existing narrowly verified `status: false`, explicit empty result container(s), and no contradictory identity/unknown nested data qualify as `operation_rejected`. Such orders use verified restoration and a fresh quote. Missing/null results, nested errors with uncertain contract, contradictory identities, malformed/oversized bodies, 401/429/5xx, and transport exceptions remain ambiguous. The official v6.2 description has no authoritative 400 response schema, so do not broaden this rule to “every HTTP 400 is cancelled.” An old persisted unknown booking is not automatically reset by this change.

The admin booking path previously swallowed unverified responses and local confirmation exceptions without a booking log. An old “Unknown outcome” cannot be diagnosed retrospectively from that message alone. Install the complete rebuilt ZIP for diagnostics on subsequent submissions; **do not resubmit an existing unknown booking just to obtain a log**. First have support verify its remote status using its existing transaction/order identity. The durable duplicate guard remains in place.

New unknown results append a random 16-character **Reference** and point to **WooCommerce → Status → Logs**, source **`kiriminaja_instant`**. Search the newest source log for that reference. Each outbound group has its own reference; all affected rows in that group share it. `booking_submitted` and `booking_confirmed` use info severity; unknown results and definite rejections use error severity. These entries do not require `WP_DEBUG`. WooCommerce logging must be enabled and its threshold, plus any `kiriof_logger_threshold` filter, must permit error entries. If the store cannot write logs, check its configured WooCommerce log handler/storage and hosting permissions; diagnostic failure cannot safely alter a remote booking outcome.

| Diagnostic code | Interpretation |
| --- | --- |
| `booking_call_exception` | Calling the booking adapter threw; this does not prove remote rejection. |
| `booking_unacknowledged` | No strict successful acknowledgement. Inspect the nested `transport` facts. |
| `package_match_not_unique` | No exact package match or more than one; position is never used to infer identity. |
| `payment_id_missing_or_invalid` | The matched response lacks a validated payment identity, including for TOP; no payment state is inferred. |
| `booking_identity_mismatch` | Courier/service identity does not exactly match the reviewed request. |
| `booking_response_invalid` | Shipment-state validation refused the matched response. |
| `local_confirmation_failed` | Confirmation/persistence or loading the saved booking failed; `stage` identifies the boundary. |
| `booking_rejected` | A definite, sanitized rejection was recognized; existing rollback rules still apply. |
| `booking_not_submitted` | Local validation or transport facts prove no send began; restoration is safe, but must be verified. |
| `booking_rollback_failed` | Restoration could not be verified; do not advertise or attempt another booking. |

Nested transport diagnostics distinguish `http_failure` (with numeric `http_status`), `invalid_json`, `invalid_json_shape`, `invalid_content_length`, `oversized_response`, `stalled_response`, `transport_exception`, local validation failure, and adapter exceptions. `elapsed_ms` is a monotonic duration, not a remote acceptance timestamp. `transport_success` means only HTTP/JSON success, not a confirmed shipment. A transport exception alone does not identify DNS, TLS, or timeout; duration may help support investigate but is not proof.

Logs contain fixed reason codes, counts, field types, acknowledgement booleans, hashed local transaction identifiers, and random correlation references. They intentionally exclude request/response bodies, arbitrary upstream messages, exception text/traces, authorization headers, API credentials, PINs, QR content, payment IDs, raw order identities, names, phone numbers, streets, and coordinates. Backtraces are explicitly disabled. Unknown bookings retain their durable request snapshot/claim and original public pricing; diagnostics never retry booking or relax confirmation checks. Tests use offline HTTP/repository fixtures, not live booking evidence.

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

`outside_instant_radius` indicates the validated destination is more than 40 km straight-line from the actual pickup origin. This check happens locally before a quote/dispatch request, including durable checkout retries. It does not imply courier road-distance availability inside the radius; API coverage is still required. Outside locations may still be saved for Express.

Install the complete rebuilt package when investigating Instant reverting to Express. The shipping-selection filter now uses WooCommerce's previous package selection (third argument), rather than its default first rate. An available native Instant rate must survive totals recalculation. Rates that disappear because of changed context or expiry are not revived from a stale mirror.

Instant names stay clean; API hour ETA remains native delivery time. Admin Fee is a separate native totals row. Delivery plus Admin Fee equals API `total_price`, while booking uses `shipping_costs`. Order validation checks the durable fee identity and amount; it does not silently substitute an Express transaction.
