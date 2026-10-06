# Instant Delivery Buyer TODO

Status: Buyer quote/order implementation and local regression coverage implemented; live release gates remain open.

## Classic pin-only extension and safe reactivation

### Subdistrict naming, unified search and local Choices

Buyer labels in Classic/Blocks/cart/account/editor now say **Subdistrict**, localized **Desa / Kelurahan**. Internal district field IDs, additional-field schema keys and persisted order/session names remain compatible; admin province/city/kecamatan hierarchy terminology is unchanged.

Classic Subdistrict and Province selects use manually downloaded, pinned Choices.js 11.2.4 in `assets/lib/choices`, with MIT license and SHA-256 provenance. No Svelte/npm runtime dependency or browser CDN is used. Styles inherit theme typography and share control geometry; original native fields, selected IDs, disabled/required/ARIA and Woo country-state events remain authoritative. Country/courier controls keep their separate legacy enhancement. Province replacement between select/text/hidden controls and repeated checkout changes dispose obsolete wrappers without resurrecting old fields.

Subdistrict name/postcode searches now use one bounded `GET api/mitra/v6.1/addresses` request per lookup. The returned official `subdistrict_id` (not a Kecamatan ID) is the selectable identity. Complete hierarchy/positive identifiers, duplicate/alias conflicts, response size and deadline are validated; postal searches enforce returned postcode membership. No parent/child fan-out or invented village IDs. Fixed reason diagnostics omit raw query, response, credentials and backtrace. Existing postal-only cache policy remains.

Choices cancels only obsolete read-only lookups, keeps selected native values during results updates, and distinguishes loading, zero matches and API failure. Typed village results in isolated Chromium are produced through the production PHP unified-address fixture. Twenty-one browser regressions cover native Woo country lifecycle, matching control typography, search shapes/errors/stale response/clear, real Leaflet geometry and existing courier actions; no live merchant lookup/booking was performed. The historical generic error log alone cannot pinpoint transport versus invalid response cause, so deployment verification should use new safe reason codes if lookup still fails.

### Native layout and map controls follow-up

Pricing/pin validation correction: Express `/api/mitra/v6.1/shipping_price` requires Kecamatan `origin`/`destination` in addition to optional village `subdistrict_origin`/`subdistrict_destination`. The repository now resolves verified parent IDs from authoritative unified-address mappings, never substitutes a village ID for its parent, and includes current postcodes in cache fingerprints. Successful lookups retain only bounded numeric hierarchy/postcode mappings; cold resolution uses postal search with exact village membership, while unmapped/changed identities fail before pricing. Existing pickup/booking payload contracts are not changed by this correction.

Classic pin rejection now reports a fixed reason and status (409 address/session conflict, 422 shape/coordinates/postcode membership, 503 lookup unavailable), not the unrelated search-error string. The UI localizes allowlisted codes and requires `pin_saved:true` before marking the pin acknowledged. Checks include nonce, enabled Instant policy, shipping/session availability, scope, positive selected village, coordinate ranges, full six-field address binding, session-selected ID and authoritative postcode membership before/after lookup. These checks do not establish that a coordinate geometrically lies inside a named village polygon. Regular checkout submission remains independent of a failed Instant pin save. Browser marker visibility alone is not a confirmed saved location.

Pin-anchor correction: Classic and Blocks marker artwork uses a 32×44 viewBox with its actual tip at (16,44), anchored to the Leaflet viewport center. Blocks does not lift the tip away from the coordinate during movement. The shared coverage circle is an unfilled dotted stroke (`fill:false`, zero fill opacity, round caps); radius/origin and eligibility logic are unchanged. Real Leaflet browser assertions transform the SVG tip into screen coordinates rather than merely testing the icon bounding box, and inspect the boundary's fill/dash attributes at desktop/mobile widths.

Separate-shipping follow-up: WC Booster's flex-third address wrapper rules are overridden only on the plugin-marked native wrapper: wide address/District/pin rows stay full-width and intended field pairs retain two columns. District dropdowns attach to `document.body` with a scoped class rather than an overflow-clipped row; initialization is idempotent across native checkout refreshes. Placement observes direct address-row changes, not dropdown/Leaflet descendants, avoiding feedback loops while editing. Legacy district mutations are serial/latest-pending and trigger rates only after the final selected address commits; missing native country controls use localized actual country, not a hardcoded Indonesia fallback. Courier radios are hidden only after their dropdown enhancement succeeds and remain usable otherwise. Fifteen isolated Chromium tests now include real WC Booster flex rules, open Select2 hit-testing, repeated billing/shipping cycles, courier selection and native-radio fallback. Real provider/zone/rate availability remains unverified without an authorized installed-store fixture.

Map geometry is now isolated from generic theme `.form-row`/`.button` rules: a full-width panel, explicit 320px clipping viewport, full-inset Leaflet canvas, neutral 44px location icon, contained pin-state badge above attribution, and a fixed center marker. The badge is status only, not another action. Scoped zoom resets remove inherited link underlines; attribution uses compact readable text. Real Leaflet Chromium checks at 1200px and 390px load conflicting theme rules after plugin CSS and assert map/control bounds, no overlap/horizontal overflow, accepted pin persistence, repeated address toggles and idle observer stability. The test tile response is synthetic, not a live location-provider guarantee.

The Instant-enabled Classic pin section uses a form label and follows the active native district row (including repeated separate-address toggles and Woo/theme priority sorting). An idempotent DOM observer repairs placement without recreating maps or pin state. Native Email is moved—not copied—to Contact Information before Billing details, preserving its name/value/validation. City/Province and Postcode/Phone use adjacent native row priorities/classes with mobile stacking. District SelectWoo stays authoritative: full-width row-owned dropdown/search and response-shape checks cover name/postcode queries and fail safely on malformed results.

Classic and Blocks use icon-only accessible floating Current location controls and map-overlay checked/unchecked pin badges. The badge turns complete only for accepted address-bound coordinates (and, for Classic, acknowledged pin persistence). Device-location prose and duplicate placed-state paragraph are removed; permission/error/movement feedback remains. No new booking calls, Google Maps, or map CDN are introduced. Ten isolated Chromium E2E regressions cover real Select2 typed search/envelopes/selection, active-row placement, repeated toggles, theme reorder repairs, email preservation, map controls and guarded booking outcomes. Live theme/provider search/geolocation still requires installed-store verification.

### Legacy checkout asset boundaries

`form-billing-address.js` is now only the small ready-time entry point. Existing behavior lives in `assets/wp/js/checkout/state.js`, `blocks-compatibility.js`, `classic-district.js`, and `shipping-payment.js`. WordPress dependencies enforce that order before the entry; localization belongs to `kiriof-checkout-state`, not the entry. Per-file modification times invalidate browser caches. Existing public function/global names are retained for theme compatibility; this is a mechanical modularization, not a new checkout behavior or production minification step. Blocks compatibility remains its own fallback module and does not own the current native Blocks adapter. Tests execute each actual file in one persistent browser VM and retain Classic/cart/account/Blocks behavior checks.

- Classic retains its existing production subdistrict search field, native theme field layout, courier dropdown/radio synchronization, payment and insurance flow. It does not mount the Blocks district experience, perform a second district lookup, hide existing controls, or restyle City/Province.
- Only when seller settings enable supported Instant couriers, enqueue a small delivery-pin section using bundled Leaflet. A matching saved pin is preferred; otherwise browser location permission is requested automatically. Device location before district choice remains a suggestion until the original district field has a valid value. Address/scope changes invalidate the old pin and dispose stale map/location callbacks.
- The pin reads the original effective billing/separate-shipping district field and posts a private canonical snapshot through `sync_classic_pin`. This nonce-guarded, Instant-policy-gated route validates the existing district/address and writes only canonical pin/quote state—not district aliases, payment, insurance, or courier selection. Legacy district completion notifies the pin bridge; matching fee refreshes preserve it and changed districts invalidate it. Server mutation requests are serialized and not aborted.
- Classic final validation still rejects stale/tampered Instant pins and verifies order/quote identity. Existing persistence, activation and HPOS safety fixes are retained; they do not alter input ownership.
- Activation preserves configured Classic/Blocks pages and current HPOS/legacy order storage, including when orders are out of sync. Older overwritten page content is not automatically restored.
- Offline regression coverage exercises pin-only DOM behavior, disabled seller policy, native-field preservation, legacy selection completion, serialized pin updates, stale callbacks and backend ownership. Installed-theme/browser, real geolocation/quotes/payments and gateway compatibility remain open live checks. Blocks keeps its separate implementation.

Scope: Classic Checkout, Checkout Blocks, buyer destination, separate Instant pricing, and order/transaction persistence.

## Implemented

- [x] Separate zone-managed `kiriminaja-instant` shipping method; Express pricing never supplies Instant rates.
- [x] GoSend and GrabExpress only; Borzo excluded. Preserve enabled API service codes, including uppercase codes.
- [x] Request browser location permission when an eligible shipping editor opens; render Leaflet only after success, with a fixed center indicator. Denied, missing, timed-out or invalid location leaves the picker hidden. Save user map movement on `moveend`; matching saved pins take precedence over the permitted device location.
- [x] No manual latitude/longitude, Load Map, or Apply controls. Initial map center is not a selected pin.
- [x] Collapsed native shipping-address card shows district/pin status badges. District/map controls follow Woo's edit state through an event-driven bridge and React portal, without DOM polling or rewriting native inputs. Hidden controls retain district validation and saved-pin state.
- [x] Keep coordinates optional for Express and district required. Instant requires valid origin and address-bound destination pins.
- [x] Version-2 destination state through session/Store API and durable order metadata; full shipping-address changes invalidate the pin. Saved version-2 pins survive reload.
- [x] Authenticated My Account shipping-address district/pin form and status badges; validated durable user snapshots hydrate future checkout. Matching checkout updates sync without silently replacing a saved profile with a temporary delivery address. Account edits persist only after successful Save address.
- [x] Guest and authenticated quote/order paths; no blanket logged-in-only Instant restriction.
- [x] Dedicated bounded transport and short-lived session quote cache. Context includes cart, origin, address/pin, vehicle, timezone, payment and enabled-service policy.
- [x] Enforce 40 km inclusive straight-line pickup-origin coverage before quotes, final checkout, durable retries and admin shipment context/dispatch. Advisory map circles and outside warnings use known actual origins; outside pins remain usable for Express.
- [x] Motor only; enforce valid items/dimensions and the 40,000 gram limit. Default request timezone WIB.
- [x] Reject COD. Ignore Express insurance preferences for Instant without changing those preferences.
- [x] Validate the exact cached quote at final checkout; reject expiry or changed context instead of silently repricing or switching couriers.
- [x] Retain native Instant selections on recalculation using WooCommerce's previous package selection, not its default first rate. Avoid stale Express mirror restoration and cross-package selection writes.
- [x] Clean courier names. Express service/insurance evidence stays in the selected native description; Instant keeps hour ETA without unsupported-insurance text.
- [x] Customer order-details Shipment section includes saved Instant and Express courier/service titles (including all relevant shipping items and legacy Express method ID variants). Generic titles fall back only to public persisted courier/service keys; no checkout-session lookup, API booking or private payment/quote metadata. Virtual-only/unrelated orders remain excluded, and Instant does not link to the Express-only public tracking flow. Placement is after WooCommerce's order-details table, not inside the theme's Order/Date/Total overview. Live classic/Blocks/theme rendering remains unverified.
- [x] Instant Delivery charges raw `shipping_costs`; a separate native Admin Fee row charges validated `admin_fee`. Their sum is API `total_price`, with no double charge and no row for zero fee.
- [x] Validate durable admin-fee identity and reject missing, altered, taxed or duplicate rows. Persist customer total/admin fee separately and retain raw carrier shipping cost for booking.
- [x] Idempotent unbooked Instant transaction creation with durable snapshots and retry protection; checkout never automatically books the shipment.
- [x] Authorized courier-policy saves add companion Instant zone methods only where Express is enabled. Preserve existing disabled Instant instances.
- [x] Safe readiness/calculation/error logs without credentials, quote tokens or customer location details.

## Remaining release gates

- [ ] Browser verification on the deployed theme for Classic and Blocks, guest/authenticated customers, returning pins, and address changes.
- [ ] Verify collapsed card badges, native Edit transition, validation recovery, card replacement and fail-open behavior on deployed Woo/theme markup.
- [ ] Browser verification of granted/denied/unavailable geolocation and keyboard/mobile map controls.
- [ ] Verify the selected Instant rate remains selected through real payment/address/cart requests and creates the matching Instant order/transaction.
- [ ] Verify native Admin Fee placement and delivery/hour description in the deployed order summary, confirmation and emails.
- [ ] Live supported-courier quote, explicit admin booking, payment and failure/retry verification with authorized test data.
- [ ] Audit customer export/erasure handling for persisted coordinate data.

Local tests use production services with isolated WooCommerce/API/repository doubles. Passing tests and a rebuilt ZIP do not establish live API availability or universal theme compatibility.
