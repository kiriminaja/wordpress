# Checkout Blocks — Remaining Acceptance Criteria

## Scope and status

This is the single follow-up checklist for **WooCommerce Checkout Blocks**. Classic checkout and the admin shipment-processing dialog are out of scope. Account behavior is included only where it affects checkout restoration or persistence.

**Audit snapshot:** current `feat/instant-delivery` source, including the latest 40 km coverage changes.

The foundation is implemented, but Blocks checkout is **not yet verified as release-ready**. This audit reviewed source and existing tests; it did not run a live WooCommerce checkout or courier API request.

- **Gap:** the current implementation is missing a protection or behavior.
- **Verify:** implementation exists, but deployed/browser/API evidence is still needed.
- **Decide:** a product or support rule must be agreed before implementation.
- Check a box only when its behavior is demonstrated—not merely when code exists.

## Already implemented — keep these working

- Native WooCommerce owns courier selection; Express and Instant remain separate methods.
- District uses a native-style select and canonical **subdistrict/kelurahan IDs**.
- Address-card badges and edit-only controls work without DOM polling.
- Location permission is requested when the editor opens; no map or tiles render before location succeeds.
- Matching saved pins are restored; full address changes invalidate them.
- Outside-radius pins remain saveable for Express; Instant has a server-enforced **40 km straight-line** limit.
- Instant rejects COD, ignores Express insurance preferences, and preserves ETA in hours.
- Instant shipping plus its separate Admin Fee equals the API total exactly once.
- Instant final validation, durable snapshots, and locked transaction retries are implemented.
- Checkout creates an unbooked transaction; it does not automatically book a courier.

## First priorities — correctness and recovery

### AC-01 — Express must validate the final selected quote

**Gap · Release blocker**

Express validates the destination at submission, but does not have Instant's equivalent binding between the current quote, selected shipping line, service policy, and payable amounts. It recalculates transaction pricing after order creation.

- [ ] Before accepting a Store API checkout, validate the exact Express service and its current enabled policy.
- [ ] Reject COD when the selected service no longer supports it, even if a client bypasses gateway visibility.
- [ ] Validate shipping, insurance, COD fees, and applicable shipping discounts against the amount the buyer confirmed.
- [ ] A changed/unavailable quote returns an actionable checkout error; it never silently substitutes a courier or changes the order total after confirmation.

**Evidence:** `CheckoutController::afterStoreApiCheckoutUpdateOrderFromRequest()` and `CreateTransactionService::call()` / `updateWcTotalOrder()`.

### AC-02 — Express transaction creation must be reliable and idempotent

**Gap · Release blocker**

The Blocks Express path checks for an existing transaction before inserting, but does not use Instant's atomic ownership lock. Insert failures are logged and returned from; order totals can already have been updated.

- [ ] Repeated or concurrent processed-order requests create exactly one transaction for the WooCommerce order.
- [ ] An insert failure leaves consistent order amounts and enough durable context for safe recovery.
- [ ] The failure is visible to the appropriate buyer/admin workflow rather than silently treating fulfillment setup as successful.
- [ ] Recovery after payment failure or retry does not duplicate a transaction or automatically book a shipment.

**Evidence:** `CheckoutController::afterStoreApiCheckoutOrderProcessed()` / `afterCheckoutAfterCreated()`, `CreateTransactionService`, and the transaction table's lack of a unique Woo order key. A real concurrent duplicate was not reproduced in this audit.

### AC-03 — Every checkout-to-account write must respect the saved address

**Gap · Release blocker**

The new destination service checks ownership and the saved profile address. An older Express post-transaction path still writes District directly through `CustomerDistrictService`, bypassing that address guard.

- [ ] All Blocks account writes use the same authenticated ownership and full shipping-address matching rules.
- [ ] A temporary delivery address never overwrites the saved account district or pin unless the buyer deliberately saves that address through the supported flow.
- [ ] Guest, login/logout, and account-switch scenarios cannot reuse another customer's saved destination.
- [ ] Failed checkout/payment and subsequent retry preserve the correct order destination without changing an unrelated account profile.

**Evidence:** the direct `CustomerDistrictService::save()` call in `CheckoutController::afterCheckoutAfterCreated()` versus `CustomerShippingDestinationService::syncCheckout()`.

### AC-04 — Instant eligibility must match package support

**Gap · Release blocker**

Instant rates are calculated for individual packages, but final checkout and Admin Fee calculation support only one package. A selectable option must not lead to a predictable final rejection.

- [ ] Choose and document one contract: support multiple Instant packages, or suppress Instant for unsupported multi-package carts.
- [ ] If unsupported, explain the restriction before submission and retain available Express/other shipping options.
- [ ] If supported, quotes, origin coverage, fees, selections, snapshots, and transactions are validated separately for every package.
- [ ] Mixed providers and different pickup origins cannot overwrite each other's selections or charges.

**Evidence:** `Kiriof_Instant_Shipping_Method_Controller::calculate_shipping()`, `InstantCheckoutController::shipping()` / `validateOrder()` / `addAdminFee()`.

### AC-05 — District lookup must not leave Blocks permanently loading

**Gap · High priority**

Blocks has cancellation and Retry after rejection, but no request deadline. The account form's 10-second timeout does not apply to Blocks.

- [ ] A slow or never-settling lookup exits loading after a defined deadline and shows Retry.
- [ ] Server lookup work also has connection/response deadlines and bounded upstream calls; a browser abort alone must not leave an unbounded API request running.
- [ ] Retry restores canonical options without clearing a valid saved identity prematurely.
- [ ] Late responses from an expired or replaced request cannot replace current options.
- [ ] Lookup and save failures remain understandable when the address card is collapsed, with a clear route to Edit/Retry.

**Evidence:** the lookup effect and collapsed-card branch in `assets/wp/js/kiriof-buyer-checkout.js`; postcode discovery caps child calls, but its generic SDK transport has no explicit lookup timeout configuration.

### AC-06 — A stalled checkout update must have safe recovery

**Gap · High priority**

The serialized queue remains in flight until `extensionCartUpdate()` settles. An unresolved request leaves the buyer in Saving indefinitely.

- [ ] A stalled update produces a bounded, actionable error/recovery state.
- [ ] Recovery does not start overlapping server mutations or acknowledge an uncertain response as successful.
- [ ] The latest district, pin, and payment intent is preserved; obsolete responses cannot revive older state.
- [ ] Place order cannot proceed with an unacknowledged required destination update.

**Evidence:** `assets/wp/js/kiriof-checkout-session.js` (`pump()` / `complete()`) and the buyer adapter's saving validation.

## Next priorities — complete the buyer experience

### AC-07 — Idle checkout must recover from Instant quote expiry

**Gap / Verify · High priority**

The buyer quote expires after 120 seconds and final validation correctly rejects it. Automatic refresh exists in the **admin dialog**, not in the buyer adapter.

- [ ] Define and demonstrate what happens when an idle buyer crosses the quote deadline.
- [ ] Expired prices cannot remain presented as payable without clear refresh/recovery.
- [ ] Refresh preserves the chosen service when still eligible; changed prices update the visible totals before a new confirmation.
- [ ] Refresh failure shows Retry and never causes automatic order placement, booking, or a silent Express fallback.

**Evidence:** `InstantCheckoutQuoteService::validate()`, Instant rate expiry metadata, and the destination/payment-driven effects in `kiriof-buyer-checkout.js`. Native WooCommerce refresh behavior still needs browser verification.

### AC-08 — Explain why Instant is unavailable

**Gap · High priority**

The server records eligibility reasons in `kiriof_instant_checkout_status`, but the Blocks buyer UI does not consume them. The map's radius warning does not explain every reason a rate disappears.

- [ ] Show safe, relevant reasons such as missing pin, outside 40 km, COD selected, unsupported items/weight, or temporary quote failure.
- [ ] Distinguish a buyer-correctable issue from an unavailable/disabled service without exposing merchant credentials or private origin details.
- [ ] Each message offers the appropriate next step: edit, switch payment, retry, or use another available method.
- [ ] Recovering eligibility clears stale warnings and does not alter native courier selection unnecessarily.

**Evidence:** `Kiriof_Instant_Shipping_Method_Controller::store_status()` and the current Cart coverage-only extension.

### AC-09 — Confirm money, currency, tax, and discount rules

**Decide / Verify · Release blocker for financial configurations**

Instant consumes integer API amounts, but the quote context has no explicit checkout currency. Admin Fee is non-taxable; shipping tax treatment is not explicitly aligned with the API total.

- [ ] Declare supported currency; reject unsupported currency or apply a verified conversion consistently.
- [ ] Define whether shipping taxes are included, additional, or disabled, and verify both inclusive/exclusive Woo tax settings.
- [ ] Cart summary, payment amount, order shipping/fee lines, confirmation, email, and transaction amounts reconcile without double charges.
- [ ] Coupons, free/zero shipping, rounding, insurance, and COD cannot produce unexplained total changes.

**Evidence:** `InstantCheckoutQuoteService::build()`, the Instant shipping rate, `InstantCheckoutController::addAdminFee()` / `checkSnapshot()`. The size of any live discrepancy has not been measured.

### AC-10 — Recipient and cart changes must refresh the correct context

**Verify · High priority**

The geographic pin binds to six address fields. Instant quotes also depend on recipient name/phone, items, payment, and pickup origin. Native Woo updates may refresh these; the plugin queue alone does not prove that integration.

- [ ] First-name, last-name, and phone-only edits produce a matching selectable quote before submission.
- [ ] Quantity, product variation, coupon, and pickup-origin changes invalidate affected rates and fees correctly.
- [ ] Rapid address/payment/cart changes retain the newest intent and the available native selected courier.
- [ ] Reload, back/forward restoration, and failed-payment retry reconcile with current server state—not an obsolete rate or pin.

**Evidence:** buyer snapshot/queue dependencies, `InstantCheckoutQuoteService` fingerprints, and final recipient checks in `InstantCheckoutController`.

### AC-11 — Location permission and delivery-pin meaning must be clear

**Decide / Verify · Medium priority**

The current requested behavior is intentional: ask immediately, hide the map on failure, and use the granted device point when no saved pin exists. Browser location identifies the buyer's device, not necessarily the entered delivery address.

- [ ] A buyer can distinguish current device location from a confirmed delivery location, especially when ordering for another person or address.
- [ ] Denial/unavailable/timeout leaves the map hidden, keeps Express usable, and explains the recovery path.
- [ ] Decide whether transient failures need an in-editor permission Retry; currently reopening is the recovery path.
- [ ] If a saved pin is outside Instant coverage, explain it even when permission denial prevents map rendering.

**Evidence:** `createLocationGate()` / `MapControl` in `kiriof-map-checkout.js`. Keeping the reset button removed is intentional; reintroducing it is not an acceptance requirement.

## Release verification — prove the implemented foundation

### AC-12 — Verify subdistrict IDs and the complete order journey

**Verify · Release blocker**

- [ ] A postcode with several villages returns all matching child options, not one parent district.
- [ ] Old saved parent IDs require clear reselection; they are never guessed or silently remapped.
- [ ] Live Express and enabled GoSend/GrabExpress quotes accept the selected child IDs and correct origin/destination context.
- [ ] The selected courier, district, pin, price breakdown, ETA, and pickup origin survive order creation unchanged.
- [ ] API failures, unavailable coverage, and server rejection give a visible retry/alternative without a misleading success screen.

**Evidence:** `KiriminajaApiRepository::sub_district_search()`, v3 postcode cache, and current quote/order regression suites. Mocked SDK responses do not establish live ID compatibility.

### AC-13 — Define and test supported Blocks versions and layouts

**Gap / Verify · Release blocker**

The plugin header, README, and WordPress readme currently disagree on minimum/tested versions. CI activation checks are not a browser checkout matrix.

- [ ] Publish one consistent WordPress, WooCommerce, PHP, and browser support matrix.
- [ ] Verify minimum/current supported Woo versions on a default block theme and the deployed merchant theme, including ShopVerse where supported.
- [ ] Existing and newly saved checkout layouts contain exactly one working District/map integration; editor save/reopen does not duplicate checkout trees.
- [ ] Missing extension APIs or unrecognized card markup leave required controls usable through a verified fallback.
- [ ] Virtual-only, mixed physical/virtual, collection/local pickup, and another shipping provider do not inherit irrelevant plugin validation or fees.

**Evidence:** `kiriminaja.php`, `readme.txt`, `README.md`, `.github/workflows/test.yml`, Blocks registration and layout-recovery tests.

### AC-14 — Make critical tests reproducible and release-gating

**Gap · Release blocker**

Some real React/DOM tests search unrelated projects under the developer's home directory and skip when their runtimes are absent. The tag release workflow builds/publishes without invoking the full PHP suite itself.

- [ ] A clean clone installs locked test dependencies and runs required Blocks DOM/lifecycle tests without external project directories or silent skips.
- [ ] Pre-commit/CI checks include changes to Blocks assets, editor scripts, and their tests—not only Svelte/admin sources.
- [ ] Automated browser tests cover guest and authenticated checkout, permission denial, lookup failure, quote expiry, native selection retention, and order creation.
- [ ] Release publication requires passing checks for the exact commit and packaged artifact, including source parity and development-file exclusions.

**Evidence:** optional runtime loaders in `tests/MapCheckout.test.ts` / `BuyerAddressCardUi.test.ts`, `package.json`, `scripts/frontend-pre-commit.sh`, and `.github/workflows/release.yml`.

### AC-15 — Complete accessibility, translation, and privacy checks

**Gap / Verify · High priority**

- [ ] Keyboard/screen-reader users can edit the address, choose District, understand badges/errors, retry, and operate the map; touch, zoom, narrow screens, and forced colors remain usable.
- [ ] Buyer notices and editor labels are translated; coupon behavior does not depend on English text. Attach editor script translations where needed.
- [ ] Explain geolocation, external map tile requests, stored coordinates, and retention in the store's privacy disclosure.
- [ ] Verify plugin destination/pin data is included in authenticated personal-data export/erasure across user, order, and transaction storage, with explicit retention exceptions.

**Evidence:** hard-coded coupon notices in `kiriof-block-checkout.js`, editor asset registration in `Enqueue`, optional DOM tests, and destination metadata/storage services. No plugin-specific privacy exporter/eraser registration was found in the reviewed `inc` code.

## Definition of done

Blocks checkout is ready only when the release blockers above are closed, unsupported configurations are clearly rejected before payment, and the deployed guest/authenticated journeys have recorded browser/API evidence. Local test passes and a rebuilt ZIP are necessary, but are not evidence of universal theme compatibility or live courier readiness.
