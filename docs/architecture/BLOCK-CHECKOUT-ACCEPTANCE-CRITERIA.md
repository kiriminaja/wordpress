# Checkout Blocks — Acceptance Criteria and Evidence

## Scope and status

This is the single follow-up checklist for **WooCommerce Checkout Blocks**. Classic checkout and the admin shipment-processing dialog are out of scope. Account behavior is included only where it affects checkout restoration or persistence.

**Audit snapshot:** current `feat/instant-delivery` source and local regression fixtures, including the Express validation/recovery, buyer recovery, eligibility, privacy, and release-gating changes. **Implemented / local verification is not release Done.** This evidence refresh reviewed code and named tests; it did not independently rerun the suites, start a WordPress server, or perform browser/live courier requests. Final local verification: `make zip` passed; ParaTest with real Woo/WordPress renderer fixtures enabled passed **1,404 tests / 26,155 assertions** (4 warnings and 9 deprecations remain). Frontend build gates passed **264 client tests**: 211 buyer, 43 Instant, and 10 payment. Frozen Bun install, ZIP integrity, source/translation parity, editor catalog inclusion, and development-file exclusions passed. These checks do not close live release gates.

- **Implemented / local verification:** a checked item has a concrete production path and named local regression evidence. It does not establish deployed behavior.
- **Verify:** browser, deployed WooCommerce, or live API evidence is still needed.
- **Decide:** a product/support rule remains open.
- An unchecked item remains open; checked local items must remain passing in the final build.

## Remaining release actions

1. Implement and run real browser E2E for guest/authenticated checkout, permissions, lookup/update failure, quote expiry, native courier retention, payment retry, and order creation. VM/React DOM tests are not E2E.
2. Record live canonical subdistrict and Express/GoSend/GrabExpress quote-to-order evidence on the supported WooCommerce/theme matrix.
3. Reconcile cart, payment, receipt/email, and transaction amounts with live coupons, insurance, COD, free shipping, and inclusive/exclusive Woo taxes; Instant's supported contract is IDR with no shipping tax.
4. Complete accessibility/touch and deployed translation checks, store privacy disclosure, and transaction/custom-row export/retention policy. Decide whether location-permission recovery needs an in-editor Retry.
5. Repeat the recorded local source/artifact gates on the final release commit and in CI. Configured CI and passing local fixtures alone do not close live release blockers.

## Already implemented — keep these working

- Native WooCommerce owns courier selection; Express and Instant remain separate methods.
- District uses a native-style select and canonical **subdistrict/kelurahan IDs**.
- Address-card badges and edit-only controls work without DOM polling.
- Location permission is requested when the editor opens; no map or tiles render before location succeeds.
- Matching saved pins are restored; full address changes invalidate them.
- Outside-radius pins remain saveable for Express; Instant has a server-enforced **40 km straight-line** limit.
- Instant rejects COD, ignores Express insurance preferences, and preserves ETA in hours.
- Instant shipping plus its separate non-taxable Admin Fee equals the API total exactly once.
- Express and Instant use validated durable snapshots and transaction ownership locks; checkout creates an unbooked transaction, not an automatic courier booking.

## First priorities — correctness and recovery

### AC-01 — Express must validate the final selected quote

**Implemented / local verification · Live journey remains a release blocker**

- [x] Validate the exact selected Express rate identity, zone, connected API-key configuration, merchant-enabled service policy, destination, package, and effective pickup origin before Store API acceptance.
- [x] Reject unsupported COD even when the client bypasses gateway visibility.
- [x] Recalculate and compare shipping, insurance, actual COD fees, applicable shipping coupons, and final payable amount; persist a JSON-safe validated calculation and full origin snapshot.
- [x] Changed/unavailable pricing rejects checkout rather than silently substituting a courier or repricing the confirmed order; transaction creation consumes the validated snapshot.
- [ ] Confirm the error/retry journey and all financial lines against live courier responses and deployed WooCommerce.

**Evidence:** `ExpressCheckoutValidationService`, `CheckoutController`, `CheckoutCalculationService`, and `CreateTransactionService`; `ExpressCheckoutValidationRuntimeTest::{rejects_invalid_selection_and_late_policy_without_calculation,rejects_changed_fees_and_final_amount_without_repricing_order,pins_json_safe_calculation_with_current_shipping_coupon,pins_effective_full_origin_for_pricing_and_retries}`; `ExpressCheckoutControllerRuntimeTest::update_then_processed_pins_real_validation_without_repricing_or_customer_save`.

**Boundary:** availability means connected configuration plus merchant policy and a service returned by pricing—not a separate account-entitlement model. Pricing may use the isolated **30-second cache**; validation is not a guarantee of a fresh network API call on every submission.

### AC-02 — Express transaction creation must be reliable and idempotent

**Implemented / local verification · Live payment/recovery verification pending**

- [x] Repeated/concurrent processed-order work uses atomic ownership and replay checks to create one transaction in the local concurrency fixture.
- [x] Insert failure retains durable validated calculation, destination, package, and origin context without modifying confirmed order prices.
- [x] Store API transaction failure propagates an actionable **503**, rather than reporting fulfillment setup success.
- [x] Retry reconstructs durable context, respects an in-flight owner, avoids duplicate insertion, and does not automatically book a shipment.
- [ ] Verify deployed payment failure, admin recovery visibility, and concurrent request behavior with the production database.

**Evidence:** `CreateTransactionService` and Express hooks in `CheckoutController`; `ExpressTransactionRecoveryRuntimeTest::test_failed_insert_concurrent_worker_and_duplicate_replay_are_safe`; `ExpressCheckoutControllerRuntimeTest::{transaction_failure_propagates_503_and_keeps_context_until_success,pending_transaction_replay_is_verified_before_success_cleanup,retry_reconstructs_context_from_durable_snapshots_after_transient_cleanup}`.

### AC-03 — Every checkout-to-account write must respect the saved address

**Implemented / local verification · Browser account-switch/retry verification pending**

- [x] Blocks uses the authenticated ownership/full-address destination guard; the legacy direct `CustomerDistrictService::save()` path is excluded from Blocks transaction creation.
- [x] Temporary checkout destinations cannot overwrite unrelated saved district/pin metadata through that path.
- [x] Local destination/restoration fixtures reject cross-customer, malformed, and mismatched saved context.
- [ ] Verify guest, login/logout, account-switch, failed-payment, and retry journeys in a real browser without changing an unrelated account profile.

**Evidence:** `CustomerShippingDestinationService::syncCheckout()`, `CheckoutController::afterCheckoutAfterCreated()`, `CustomerShippingDestinationRuntimeTest`, `BuyerDestinationRuntimeTest`, `BuyerCheckoutAdapter.test.ts`, and `ExpressCheckoutControllerRuntimeTest::update_then_processed_pins_real_validation_without_repricing_or_customer_save`.

### AC-04 — Instant eligibility must match package support

**Implemented / local verification · Contract: one Instant package only**

- [x] Suppress Instant for unsupported multi-package carts; recheck the same restriction during final and durable snapshot validation.
- [x] Publish a safe unsupported-package reason while leaving native Express/other options available.
- [x] Reject unsupported currency and taxable Instant configurations; do not create selectable rates that predictably fail final validation.
- [x] Local package/origin fixtures preserve native selection and prevent unsupported packages from generating Instant quotes/fees.
- [ ] Verify mixed providers/origins and the visible explanation on deployed checkout.

**Evidence:** `Kiriof_Instant_Shipping_Method_Controller`, `InstantCheckoutController`, `InstantCheckoutShippingRuntimeTest::{test_canonical_multi_package_currency_and_virtual_guards_skip_api_without_selection_changes,test_quote_arguments_preserve_actual_package_origin_and_native_selection}`, and `InstantCheckoutOrderRuntimeTest`. Multi-package Instant support is not claimed.

### AC-05 — District lookup must not leave Blocks permanently loading

**Implemented / local verification · Live failure UX pending**

- [x] Blocks lookup exits loading after **10 seconds**, aborts the browser request, and offers Retry.
- [x] Upstream address transport has an **8-second per-request** bound and a **25-second total lookup** budget; bounded child discovery fails closed instead of returning partial/parent IDs.
- [x] Retry preserves valid identity until options are confirmed; expired/replaced responses cannot replace current options.
- [x] Collapsed-card status exposes lookup/save failure and a route back to Edit/recovery.
- [ ] Verify deployed slow/upstream failures and collapsed-card announcements with assistive technology.

**Evidence:** `BuyerCheckoutAdapter.test.ts` (“district lookup deadline aborts and reports once; retry ignores late old success”), `BuyerAddressCardUi.test.ts`, `AddressApiTransportRuntimeTest::test_address_timeout_and_sdk_child_contract_are_bounded`, and `SubdistrictSdkRuntimeTest::test_failures_are_closed_and_never_return_parent_or_partial_data`.

**Transport contract:** address routes are derived from the installed SDK transport contract and executed through the bounded adapter. There is no claim that a literal/static `getSubdistrict()` SDK method exists; live SDK/API compatibility remains AC-12.

### AC-06 — A stalled checkout update must have safe recovery

**Implemented / local verification · Live stalled-request recovery pending**

- [x] A **15-second watchdog** turns Saving into an actionable stalled/error state, including when controls are collapsed.
- [x] An uncertain request remains in flight: recovery never starts overlapping mutations. Reload is the recovery path while it never settles; explicit retry becomes possible after actual settlement.
- [x] Preserve latest district/pin/payment intent and ignore late success as acknowledgement after timeout.
- [x] Required unacknowledged destination updates block Place order.
- [ ] Verify the reload/retry experience during a real stalled Store API request.

**Evidence:** `kiriof-checkout-session.js`, `BuyerCheckoutSession.test.ts` (“timeout holds serialization until actual settlement, never acknowledges late success, and retries only latest”), `BuyerCheckoutAdapter.test.ts` (“never-settling cart mutation requires reload or actual settle before explicit retry”), and `BuyerAddressCardUi.test.ts`.

## Next priorities — complete the buyer experience

### AC-07 — Idle checkout must recover from Instant quote expiry

**Implemented / local verification · Native browser integration remains open**

- [x] Read-only Cart status exposes the earliest valid current Instant expiry; the buyer schedules refresh at the **120-second quote deadline** through the serialized native update gate.
- [x] Expired/ineligible selected Instant rates block confirmation while refresh is pending; current package caches are invalidated server-side.
- [x] Local refresh fixtures preserve the eligible native selected service and update recipient context without automatic placement, booking, or Express fallback.
- [x] Refresh failure enters the existing explicit recovery path rather than acknowledging stale payable prices.
- [ ] Demonstrate idle expiry, visible total changes, continued selection, and Retry in deployed WooCommerce before new confirmation.

**Evidence:** `InstantCheckoutQuoteService`, `InstantCheckoutStatusService`, `InstantCheckoutController`, `InstantCheckoutStatusRuntimeTest::{test_current_native_rates_use_earliest_valid_instant_expiry_without_private_metadata,test_registered_sync_callback_refreshes_package_caches_and_preserves_native_selection_and_recipient}`, and `BuyerCheckoutAdapter.test.ts` (“selected Instant quote expiry refreshes once through the serialized native gate”).

### AC-08 — Explain why Instant is unavailable

**Implemented / local verification · Live messaging pending**

- [x] Blocks consumes server eligibility status and displays whitelisted safe reasons for missing pin, outside coverage, COD, unsupported package/items/weight/currency/tax, and temporary quote failure.
- [x] Reasons offer appropriate edit/payment/retry/alternative guidance without credentials, raw upstream errors, or private origin details.
- [x] Current server context and rate state replace stale diagnostics; recovery does not take native courier ownership.
- [ ] Verify messages and stale-warning clearing in a deployed browser, including policy-controlled unavailability.

**Evidence:** `InstantCheckoutStatusRuntimeTest::{test_session_diagnostics_require_current_contents_and_recent_known_codes,test_real_controller_registers_readonly_cart_status_allowlist}`, `InstantCheckoutShippingRuntimeTest::test_eligibility_reasons_use_only_fixed_safe_messages_and_codes`, and buyer adapter/address-card fixtures. Intentionally disabled services need not advertise a buyer-facing message; safe policy reasons may be shown where applicable.

### AC-09 — Confirm money, currency, tax, and discount rules

**Implemented / local verification · Live financial configurations remain a release blocker**

- [x] Instant explicitly supports **IDR only, no shipping tax**; rate eligibility, final validation, and durable snapshot reuse reject unsupported configurations. Admin Fee is non-taxable.
- [x] Local Instant fixtures reconcile shipping plus Admin Fee against API total without double charging.
- [x] Express retains Woo-native currency/tax amounts; validation compares actual shipping, insurance, COD fees, coupons, and payable totals rather than applying an unverified conversion or post-confirmation edit.
- [x] Blocks free-shipping calculations preserve actual service IDs/raw quote and real insurance/COD amounts, rather than inventing synthetic service availability.
- [ ] Reconcile cart summary, payment, order shipping/fees, receipt/confirmation, email, and transaction amounts in live WooCommerce with inclusive/exclusive taxes, coupons, rounding, free/zero shipping, insurance, and COD.

**Evidence:** `InstantCheckoutQuoteRuntimeTest`, `InstantCheckoutShippingRuntimeTest`, `InstantCheckoutOrderRuntimeTest`, `ExpressCheckoutValidationRuntimeTest::{leaves_tax_and_currency_to_woocommerce,rejects_changed_fees_and_final_amount_without_repricing_order}`, and `CheckoutFreeShippingCalculationRuntimeTest::{test_blocks_free_shipping_retains_real_quote_insurance_and_cod,test_blocks_free_coupon_cannot_invent_missing_or_disabled_services}`. Local Express tax fixtures are not evidence for every live cart/tax configuration.

### AC-10 — Recipient and cart changes must refresh the correct context

**Implemented / local verification · Browser verification remains open**

- [x] Recipient name/phone changes enqueue fresh recipient snapshots; server refresh invalidates affected cached rates and retains native selection in local fixtures.
- [x] Local queue/fingerprint checks retain newest address/payment/item intent and reject obsolete quote/destination context.
- [ ] First-name, last-name, and phone-only edits produce matching selectable quotes in real WooCommerce Blocks before submission.
- [ ] Quantity, variation, coupon, and pickup-origin changes visibly refresh affected rates and fees in real checkout.
- [ ] Rapid address/payment/cart changes, reload, back/forward restoration, and failed-payment retry reconcile with current server state and native selected courier.

**Evidence:** `BuyerCheckoutAdapter.test.ts` (“server ineligibility blocks stale selected Instant and recipient changes enqueue fresh snapshots”), `BuyerCheckoutSession.test.ts`, `InstantCheckoutStatusRuntimeTest::test_registered_sync_callback_refreshes_package_caches_and_preserves_native_selection_and_recipient`, quote fingerprints and final checks in `InstantCheckoutController`. These are unit/runtime tests, not browser evidence.

### AC-11 — Location permission and delivery-pin meaning must be clear

**Implemented / local verification; Decide / Verify · Medium priority**

- [x] Editor copy distinguishes the device location from the delivery pin and asks the buyer to confirm the delivery location.
- [x] Local permission-gate tests keep the map hidden after denial/unavailable/timeout and ignore stale callbacks; Express remains independent of a successful device location.
- [x] Saved-pin coverage warning is outside the permission-gated map, so denial does not hide the outside-radius explanation.
- [ ] Verify device-location wording, ordering for another person, actual browser permissions, and denied-location Express checkout.
- [ ] Decide whether transient permission failures need an in-editor Retry; **reopening the editor is the current intentional recovery path**.

**Evidence:** `createLocationGate()` / `MapControl` in `kiriof-map-checkout.js`, `MapCheckout.test.ts`, and `BuyerAddressCardUi.test.ts`. Asking immediately on editor open and hiding tiles until permission succeeds are intentional. The removed reset button is not a gap or an acceptance requirement.

## Release verification — prove the implemented foundation

### AC-12 — Verify subdistrict IDs and the complete order journey

**Implemented / local verification · Live API/order evidence is a release blocker**

- [x] SDK-derived local lookup fixtures return canonical child IDs for multiple villages and fail closed on old/unconfirmed parent IDs, rather than guessing/remapping them.
- [x] Local quote/order fixtures preserve selected service, destination/pin, price breakdown, ETA, package, and fixed effective pickup origin.
- [ ] Live postcode lookup returns all matching villages and clear reselection for old parent IDs.
- [ ] Live Express and enabled GoSend/GrabExpress accept the canonical selected child IDs and correct origin/destination context.
- [ ] Confirm the complete order/receipt journey and visible API failure/server rejection recovery without a misleading success screen.

**Evidence:** `KiriminajaApiRepository::sub_district_search()`, v3 postcode cache, `SubdistrictSdkRuntimeTest::{test_parent_lookup_routes_to_actual_child_ids_and_canonical_labels,test_exact_postcode_uses_addresses_and_confirms_children_without_zip}`, `ExpressCheckoutValidationRuntimeTest`, `ExpressCheckoutControllerRuntimeTest`, and `InstantCheckoutOrderRuntimeTest`. Mocked SDK responses do not establish live ID compatibility.

### AC-13 — Define and test supported Blocks versions and layouts

**Implemented / local verification · Browser/theme matrix is a release blocker**

- [x] Headers/readmes align on **WordPress 6.8+, WooCommerce 8.5+ (tested target 10.6), PHP 8.1+**. CI pins WP 6.8 with WC 8.5.0 and 10.6.0; PHP suites cover 8.1, 8.2, 8.3, and 8.5.
- [x] Local layout/fallback fixtures cover single integration ownership, ShopVerse layout recovery, missing extension APIs/card markup, and unrelated/collection/no-shipping contexts.
- [ ] Define the supported browser matrix and run minimum/current supported Woo versions on a default block theme and deployed merchant theme, including ShopVerse where supported.
- [ ] Verify editor save/reopen yields exactly one District/map integration and required fallback controls remain usable.
- [ ] Verify virtual-only, mixed physical/virtual, local pickup/collection, and another provider do not inherit irrelevant validation/fees in real checkout.

**Evidence:** `kiriminaja.php`, `readme.txt`, `README.md`, `.github/workflows/test.yml`, `ShopVerseBlockCheckoutCompatibilityTest`, `CheckoutLayoutRecoveryRuntimeTest`, `BuyerBlocksIntegrationRuntimeTest`, and `BuyerCheckoutAdapter.test.ts`. WP-CLI activation/smoke checks are not a browser checkout matrix.

### AC-14 — Make critical tests reproducible and release-gating

**Implemented / local verification · Browser E2E is not implemented**

- [x] Required React/DOM runtimes use repository dependencies: exact **React/React DOM 19.2.4 and Happy DOM 20.8.4**, locked by `bun.lock`; external-home runtime discovery and optional silent skips are removed.
- [x] Pre-commit/frontend gates include Blocks assets/editor scripts/tests; CI installs the frozen Bun lock with Bun 1.4.2 and Node 22.
- [x] Release workflow builds the ZIP and runs `make test` against the exact tag source/artifact before publication; parity/exclusion tests remain required. Build runs frontend checks.
- [ ] Implement and run browser E2E covering guest/authenticated checkout, permission denial, lookup failure, expiry, native selection retention, order creation, and payment retry.
- [ ] Record final clean-install, PHP/frontend, build/parity, and release gate results for the commit to be released.

**Evidence:** `tests/helpers/ui-runtime.ts`, `package.json`, `bun.lock`, `scripts/frontend-pre-commit.sh`, `.github/workflows/test.yml`, `.github/workflows/release.yml`, and `PluginStructureTest`. No WordPress/browser server or live permission journey was started during this refresh.

### AC-15 — Complete accessibility, translation, and privacy checks

**Implemented / local verification · Accessibility, deployed catalogs, and privacy completeness remain open**

- [x] Coupon notices use localized Woo templates/filter arguments rather than English substring matching; editor handles attach script translations and buyer/editor strings have catalog coverage.
- [x] Standard WordPress personal-data export/erase hooks cover authenticated customer destination/pin metadata; Woo order hooks cover plugin destination metadata with explicit retained booking/financial exceptions.
- [ ] Verify keyboard/screen-reader editing, District, badges/errors/retry, map operation, touch, zoom, narrow screens, and forced colors in a real browser.
- [ ] Verify deployed buyer/editor translations; bundled MD5-named Indonesian editor JSON catalogs and registration pass static coverage, but deployed locale loading remains unverified.
- [ ] Add/verify store disclosure of geolocation, external map tiles, stored coordinates, and retention.
- [ ] Complete transaction/custom-row personal-data export coverage and document manual handling/retention policy for booking and financial records; do not claim universal erasure/export.

**Evidence:** `BlockCouponNotice.test.ts` (localized templates, reordered placeholders, exact code matching, and native-notice preservation), `Enqueue` script translation registration, `I18nValidationTest`, `EditorTranslationCatalogTest` (bundled catalog coverage), and `CustomerDestinationPrivacyRuntimeTest::{exports_only_the_requested_account_with_zero_coordinates_and_no_secrets_or_html,erases_only_plugin_shipping_keys_and_explicitly_reports_business_retention_on_repeat,woo_order_hook_preserves_native_fields_and_durable_booking_and_financial_metadata,registers_standard_wp_and_woo_hooks_without_overwriting_other_providers}`. Durable bookings/financial snapshots are intentionally retained; custom transaction-row export is not complete.

## Definition of done

Blocks checkout is ready only when the open release blockers are closed, unsupported configurations are clearly rejected before payment, and deployed guest/authenticated journeys have recorded browser/API evidence. Local test passes and a rebuilt ZIP are necessary, but not evidence of universal theme compatibility, actual browser permissions, live courier readiness, or complete transaction-data privacy handling.
