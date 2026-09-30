# Instant Delivery Admin TODO

Status: In progress

Last reviewed: 2026-09-30

Scope: merchant/admin workflows only

This checklist tracks the admin-first implementation from the Instant Delivery requirement and `.github/docs/instant-delivery-implementation-audit.md`. Instant checkout and buyer address work is tracked separately in `BUYER-INSTANT-TODO.md`.

## Current implementation slice

- [x] Confirm the synced branch is based on `v3`.
- [x] Preserve the existing Express courier/service policy behavior as the compatibility baseline.
- [x] Add delivery-type-aware courier discovery without leaking Instant couriers into Express rate calculation.
- [x] Enable an explicit `Instant Delivery` courier-management tab.
- [x] Preserve service selections across Express and Instant tabs.
- [x] Keep Instant opt-in explicit; never enable Instant automatically during legacy whitelist migration.
- [x] Use API-returned Instant child services when available.
- [x] Apply Shopify child definitions for GoSend and GrabExpress (`instant`, `sameday`) from `kaj-shopify-plugin` commit `5a9a2d7`.

## Courier management

- [x] Expose `gosend`, `grab_express`, and `borzo` only when returned by the account/API and classified as Instant.
- [x] Keep International as a non-interactive “Coming soon” tab in the MVP.
- [x] Store the courier type/delivery type with the service policy or derive it deterministically from the catalog.
- [x] Validate Instant service selections server-side, including unavailable historical selections.
- [x] Ensure tab-local enable/disable actions do not erase selections in another tab.
- [x] Keep legacy CSV mirrors compatible with existing Express installations.
- [x] Add admin tests for API/cache/fallback rows, malformed policy, aliases, rollback, explicit Instant opt-in, and cross-tab preservation.

## Transaction workspace

- [ ] Add persistent `Regular Delivery`, `Instant Delivery`, and existing `Order Issue` tabs.
- [ ] Add delivery-type query scoping so Instant rows never appear in Regular Delivery.
- [ ] Port the complete status mapping and detail fields from an identified `kaj-shopify-plugin` revision.
- [ ] Do not add the prohibited admin location-confirmation button.
- [ ] Show Instant courier/service, vehicle, payment method, order ID, AWB, fee, status, issue badge, and available actions.
- [ ] Keep `Find New Driver` informational/system-automatic unless the account contract explicitly requires a merchant action.

## Process Shipment / Request Pickup

- [ ] Add a separate Instant dispatch service; do not reuse Express schedule selection or Express payload construction.
- [ ] Add same-origin/destination repricing immediately before dispatch.
- [ ] Show checkout quote and current quote when repricing changes the price; require confirmation in the dialog.
- [ ] Enforce 1–10 packages per Instant pickup request and split larger compatible selections into batches.
- [ ] Keep Express and Instant batches isolated.
- [ ] Add TOP, QRIS, and KA Credit payment normalization for Instant.
- [ ] Persist a stable Instant `order_id` across retries.
- [ ] Persist AWB/tracking data immediately after successful booking.
- [ ] Use the official PHP SDK for pricing; verify or add an explicit adapter for the installed SDK’s legacy v4 versus documented v6.2 booking contract.

## Cancellation, tracking, and webhooks

- [ ] Route Instant cancellation to `DELETE /api/mitra/v4/instant/pickup/void/{order_id}`.
- [ ] Route Instant tracking to the Instant endpoint and render only `live_tracking_url`.
- [ ] Add an Instant webhook branch for `shipped_packages`, `canceled_packages`, and `finished_packages`.
- [ ] Make webhook persistence idempotent and order by lifecycle state.
- [ ] Keep Instant operational issues in the Instant tab with an issue badge.

## External dependencies

- [x] Supply an identified `kaj-shopify-plugin` revision for GoSend/GrabExpress courier child names: `5a9a2d7a3f738bf98c408312a536de400ba30077`.
- [ ] Adapt the available Shopify transaction detail/status mapping to WooCommerce before wiring transaction tabs.
- [ ] Confirm the production Instant booking endpoint and response contract.
- [ ] Confirm `package_type_id` and account entitlement.
- [ ] Confirm the `Find New Driver` status trigger and terminal-state rules.

## Reference source and next slice

Local reference: `/Users/user1/Kerjaa/kaj-shopify-plugin`, revision `5a9a2d7a3f738bf98c408312a536de400ba30077`.

- `app/helpers/expedition.ts`: GoSend and GrabExpress both expose `instant` / `Instant` and `sameday` / `Same Day`. Borzo is commented out, so do not invent a Borzo child catalog from this source.
- `app/constants/transaction.ts`: status filters include `need_confirmation`, `waiting_for_shipment`, `waiting_for_payment`, `waiting_for_awb_generation`, `ready_delivered`, `find_new_driver`, `on_delivery`, `shipment_problem`, `finish`, `retur`, `finish_retur`, `cancel_requested`, and `cancel`.
- `app/helpers/kiriminaja.ts`: mapping depends on internal status, payment state, AWB presence, and Instant readiness. `101` is Find New Driver; `106` is On Delivery; `200` is Delivered; `300`/`302` are Cancelled; `350` is Cancellation Process. Copy the complete mapping, not just these examples, into the transaction adapter.
- `app/constants/orders/order.status.ts` and `app/models/packageOrder.ts`: use these alongside the helper to establish the order lifecycle mapping. Do not reuse Shopify's location-confirmation action in WordPress.

Next admin slice: add transaction delivery-type persistence and isolated Instant queries, then shared status normalization. Keep booking actions unavailable until origin/destination context and the SDK/API contract are implemented and tested.

The current settings screen stores preferences only and states that Instant checkout and dispatch are not available yet. This prevents saved courier preferences from implying a working booking flow.

## Verification

- [ ] Run focused courier/settings tests after each admin slice.
- [ ] Run `make test`.
- [ ] Run `make zip` before packaging/release verification.
