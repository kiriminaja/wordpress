# Instant Delivery Buyer TODO

Status: Buyer quote/order implementation and local regression coverage implemented; live release gates remain open.

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
