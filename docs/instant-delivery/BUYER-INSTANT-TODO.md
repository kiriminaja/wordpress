# Instant Delivery Buyer TODO

Status: Planned; admin courier settings and transaction-tab foundation are complete

Last reviewed: 2026-09-30

Scope: Classic Checkout, Checkout Blocks, buyer address, pricing eligibility

This checklist tracks buyer-side work from the [Instant Delivery requirement](https://telegra.ph/Instant-09-30) and [implementation audit](implementation-audit.md). Admin workflows are tracked separately in [ADMIN-INSTANT-TODO.md](ADMIN-INSTANT-TODO.md).

## Classic Checkout

- [ ] Show the Leaflet picker only when at least one Instant courier is enabled.
- [ ] Keep coordinate entry optional so Express/non-Instant checkout remains usable without a pin.
- [ ] Initialize the marker from navigator geolocation when permission is granted.
- [ ] Allow manual pin entry when permission is denied, unavailable, or inaccurate.
- [ ] Require coordinate confirmation before requesting Instant pricing.
- [ ] Persist guest coordinates in the checkout session, order, and transaction only.
- [ ] Persist logged-in buyer coordinates against the applicable shipping/billing address.
- [ ] Invalidate saved coordinates when address fields change.
- [ ] Recalculate rates after coordinate confirmation or address changes.
- [ ] Hide Instant when origin coordinates are incomplete.
- [ ] Hide Instant for unsupported weight, COD, insurance, or invalid package data.

## Checkout Blocks

- [ ] Restrict Instant rates to logged-in buyers.
- [ ] Wire buyer address management before enabling Instant rates.
- [ ] Persist coordinate state through a namespaced Store API extension.
- [ ] Require manual coordinate confirmation after checkout address changes.
- [ ] Avoid Classic Checkout fragment/jQuery event dependencies.
- [ ] Validate missing/stale coordinates through Store API server-side validation.

## Pricing and rate identity

- [ ] Add a dedicated Instant pricing service using the official KiriminAja PHP SDK.
- [ ] Include origin/destination coordinates, address, vehicle, timezone, weight, item value, and courier filter in the request/cache key.
- [ ] Use structured rate context; never parse Instant business data from a rate ID.
- [ ] Preserve raw KiriminAja shipping cost separately from customer-facing WooCommerce shipping cost.
- [ ] Debounce checkout pricing and use a separate short-lived/session-scoped cache.
- [ ] Reprice during final checkout validation.
- [ ] Back off on API rate limits without blocking Express checkout.

## Eligibility and persistence

- [ ] Support `motor` for the MVP; keep `mobil` excluded until approved.
- [ ] Enforce the 40,000 gram total weight limit.
- [ ] Require complete origin address, latitude, longitude, and timezone.
- [ ] Hide Instant when WooCommerce COD is selected in the MVP.
- [ ] Do not charge or send selectable Instant insurance in the MVP.
- [ ] Add coordinate fields to customer export/erasure handling.

## Verification

- [ ] Test navigator permission granted, denied, unavailable, and manual pin flows.
- [ ] Test address fingerprint invalidation and returning saved pins.
- [ ] Test Classic guest and authenticated persistence separately.
- [ ] Test Blocks logged-in-only eligibility and buyer address management.
- [ ] Test rate cache isolation, debounce, stale quote rejection, and raw shipping-cost persistence.
- [ ] Run `make test` and `make zip` after buyer implementation.
