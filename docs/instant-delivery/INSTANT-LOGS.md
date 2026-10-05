# Instant checkout logs

## Authenticated processed/payment callbacks and Payments list

The [official Instant shipment event](https://developer.kiriminaja.com/docs#webhook/POST/instantshipmentevent) was reviewed against its backing [OpenAPI JSON](https://developer.kiriminaja.com/docs/openapi/json). The contract lists `processed_packages` (105), `shipped_packages` (106), `canceled_packages` (300; existing 302 compatibility retained), and `finished_packages` (200). Only `method` and `data` are required at the root; `packages` may be omitted and `payment` may be omitted or null. Each data row requires `order_id`. Supplied package status/identities must agree with the method and persisted shipment. With no package metadata/status, the authenticated event method supplies lifecycle state; it never supplies payment status. Processed does not require or invent paid state. Explicit `packages:null` is not in the schema and remains rejected.

Known service casing (`Instant`/`instant`) is normalized consistently with booking validation. Payment 0/9 in string/integer form is normalized independently; unknown/absent payment status does not overwrite stored payment state. Nullable QR, driver/location details and timestamps do not cause raw payload storage. Microsecond UTC timestamps are validated and stored at second precision; an absent lifecycle timestamp may be filled on a same-state event. Valid `poly_line` route evidence is decoded into numeric `shipping_info.instant_route_points`, with geographic/point/byte bounds and a 60,000-byte total snapshot cap. Missing/malformed route evidence cannot erase an existing route or block lifecycle updates. Stale events may fill missing routes but cannot replace current route evidence.

Whole-batch prevalidation and bearer authentication remain mandatory; unsupported methods, malformed supplied metadata and contradictory identities cause no writes. Duplicate/late processed events do not regress shipped or terminal lifecycle state and do not complete the WooCommerce order. All four sanitized official examples are checked through authenticated routing and the real state writer; tests cover minimal payloads, optional/null payment, retries, mismatches, timestamps, route CAS conflicts and privacy. A successful response is sent only after persistence and required WooCommerce effects verify; failures remain retryable. The documented User-Agent is informational and is not used instead of bearer authentication.

Courier scope remains the plugin's supported GoSend/Grab Express integration. Borzo support is intentionally not implied by this webhook audit; its multi-destination order/booking integration remains outside the implemented scope. No live provider webhook delivery was performed during verification.

After installing the fix, the provider may retry a previously rejected callback through its normal authenticated mechanism; do not rebook the order. Never paste bearer headers into support logs; rotate any exposed token. Payment QR/private customer payload fields are not included in diagnostics.

The Payments workspace (`kiriminaja-request-pickup`) now reads Instant payment groups directly from persisted transaction `instant_payment_id` metadata alongside legacy Express payments. Instant payment IDs are not Express pickup numbers. Instant rows show durable paid/unpaid/pending/refunded state and a Details link scoped to the Instant partition; they cannot launch Express payment, schedule, print or cancellation flows. Booking success alone never implies paid status. Filters/counts/months/pagination include both sources and use namespaced row identities.

## Admin “Unknown outcome” booking diagnostics

### Successful HTTP response rejected during confirmation

`booking_response_invalid` at `confirm_booking` after `transport_success`, HTTP 200 and a positive acknowledgement is a local validation failure, not proof of API rejection. A string payment ID, integer package status and absent/null AWB are compatible with a valid unpaid QRIS booking; the old type-only log cannot identify which check failed.

Confirmation logs now add a fixed `validation_reason`, such as `package_status_unsupported`, `package_status_alias_conflict`, `stored_service_identity_mismatch`, `tracking_url_invalid`, `origin_snapshot_conflict` or `booked_shipping_snapshot_conflict`. No actual status values, URLs, origin/customer data, payment IDs or exception text are included. Invalid or contradictory identities remain guarded.

A reproduced origin-snapshot failure arose when an authoritative historical snapshot contained aliases (`origin_*`), extra location fields or numeric-string coordinates. Context building correctly normalized these for the API, but booking metadata attempted to replace the raw historical representation, which the immutable-origin check refused after remote acceptance. New preparation now preserves a present validated snapshot verbatim; an absent snapshot is still filled from the reviewed origin. No immutable-state checks are weakened, and a concurrently changed origin still fails confirmation.

Existing unknown bookings are not reset or resubmitted by this change. Use **Recheck booking** to obtain authenticated matching remote evidence, then support if necessary. Old prepared envelopes may still contain normalized-origin metadata and cannot be safely rewritten without verifying their reviewed source; a not-found tracking response is not permission to retry. Do not create another booking merely to obtain the new diagnostic reason.

### Actual explanatory messages and targeted recheck

Merchant-observed validation failures use `{ "message": "Terdapat kesalahan pada data yang dikirimkan", "errors": { "address_note": "address_note wajib diisi" }, "status": false }`, with no result container. On HTTP 2xx/400/422 this now qualifies as a definite validation rejection only when the field-error map is nonempty and bounded, keys are known booking-field paths, error values are nonempty text/text lists, and there are no unknown fields, null/nonempty result containers, or booking identities. Malformed errors, status coercions, contradictory evidence, and other HTTP statuses remain ambiguous. Verified rollback restores original transaction data and permits a new quote, never automatic rebooking or reset of older unknown rows.

`error_body.validation_fields` includes pickup `address_note` and `packages.destination.address_note`. `validation_messages` contains bounded redacted actual field explanations, including `address_note wajib diisi`, on both HTTP failures and negative acknowledgements under HTTP 2xx. PIN-field messages are always redacted entirely. Raw field names outside the allowlist and arbitrary error structures/values are never logged or sent to the browser.

The subsequent successful merchant sample exposed request differences (missing pickup/destination `address_note`, QRIS incorrectly mapped to cash) and response casing/aliases. These are now aligned with that sample; diagnostics still redact notes and other request-derived private values. Obtain a fresh quote after installing the rebuilt package. Do not repeat an existing uncertain booking merely to test corrected payloads; use Recheck booking first and confirm nonacceptance before rebooking.

Error categories alone were not actionable when the API returned `status: false`, null/missing result, and an unrecognized root message. Diagnostics now include `transport.error_body.messages` for `message`, `text`, and `statusMessage`: actual plain-text prose with request-derived sensitive values, API token, arbitrary emails/URLs, labeled secrets, PINs, long numbers/phones, and coordinates redacted. Redaction runs on the full decoded message before the 2048-byte cap; HTML/control characters are removed. This is not a full raw-body log and is never sent to the browser. Share only the new redacted entry; old discarded messages cannot be recovered.

Uncertain pending Instant bookings with no remote status, AWB, or payment identity now expose **Recheck booking** in list/detail actions, even though processing/selection remain disabled. This explicitly calls the existing nonce/capability-guarded single-order reconciliation API, using a tracking GET, not booking POST. No automatic polling or resubmission. Authenticated matching booking evidence may update local state; a not-found/unknown response does not remove duplicate protection or prove rejection. The fallback detail payload remains fail-closed. This replaces the earlier blanket removal only for recovery of uncertain bookings; it does not restore a broad remote-status action on every shipment.

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
