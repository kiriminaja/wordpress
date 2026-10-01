# Instant Delivery Admin TODO

Status: In progress

Last reviewed: 2026-09-30

Scope: merchant/admin workflows only

This checklist tracks the admin-first implementation from the [Instant Delivery requirement](https://telegra.ph/Instant-09-30) and [implementation audit](implementation-audit.md). Instant checkout and buyer address work is tracked separately in [BUYER-INSTANT-TODO.md](BUYER-INSTANT-TODO.md).

## Current implementation slice

- [x] Confirm the synced branch is based on `v3`.
- [x] Preserve the existing Express courier/service policy behavior as the compatibility baseline.
- [x] Add delivery-type-aware courier discovery without leaking Instant couriers into Express rate calculation.
- [x] Replace courier-management tabs with one Express/Instant courier grid, per the latest feedback.
- [x] Move courier search beside Enable All in the settings toolbar and preserve selections while searching.
- [x] Keep Instant opt-in explicit; never enable Instant automatically during legacy whitelist migration.
- [x] Use API-returned Instant child services when available.
- [x] Apply Shopify child definitions for GoSend and GrabExpress (`instant`, `sameday`) from `kaj-shopify-plugin` commit `5a9a2d7`.

## Courier management

- [x] Expose only `gosend` and `grab_express` as supported Instant couriers when returned by the account/API.
- [x] Remove Borzo from discovery, cached catalogs, selectable saved policies, dispatch, labels, and active logo mappings. Historical Borzo transactions remain classified as Instant to prevent Express routing.
- [x] Remove all courier-management tabs, including the International placeholder; International couriers remain unsupported.
- [x] Store the courier type/delivery type with the service policy or derive it deterministically from the catalog.
- [x] Validate Instant service selections server-side, including unavailable historical selections.
- [x] Apply Enable All / Disable All to the supported catalog without silently erasing unrelated saved choices.
- [x] Keep legacy CSV mirrors compatible with existing Express installations.
- [x] Add admin tests for API/cache/fallback rows, malformed policy, aliases, rollback, explicit Instant opt-in, and cross-tab preservation.

## Transaction workspace

- [x] Add clickable, persistent `Regular Delivery`, `Instant Delivery`, and existing `Order Issue` tabs.
- [x] Add delivery-type query scoping so Instant rows never appear in Regular Delivery.
- [x] Persist indexed `delivery_type` and nullable `vehicle`; migrate legacy Instant courier rows and retry incomplete schema upgrades.
- [x] Preserve existing delivery metadata during partial updates and clear stale action selections when switching tabs.
- [x] Port the complete supported Shopify Instant status mapping into a shared WooCommerce status adapter.
- [x] Do not add the prohibited admin location-confirmation button.
- [x] Prevent Instant rows from reaching Express pickup, cancellation, COD adjustment, printing, origin-change, or tracking handlers while dedicated handlers are pending.
- [x] Show Instant courier/service, vehicle, payment method/status/ID, order ID, AWB, fee, status, issue badge, and guarded Process Shipment / Print Labels actions.
- [x] Keep `Find New Driver` informational/system-automatic with a tooltip and no manual action.

## Process Shipment / Request Pickup

- [x] Add a separate Instant dispatch service and Process Shipment dialog without a schedule picker.
- [x] Reprice using the same origin/destination before dispatch; use a user-bound 120-second quote and revalidate the complete context fingerprint before submission.
- [x] Show saved/current prices and require per-order price-change acknowledgment plus explicit submission confirmation.
- [x] Clear Instant price-review layout: rate-change notice, expandable order details (merchant order number, validated courier/service, origin and recipient labels), five-order preview/show-more, changed-order count and selected-shipment Before/After/Price Gap/Total summary. Totals explicitly compare carrier shipping charges, not buyer checkout admin fees or insurance.
- [x] Hide the payment selector and payment PIN entirely when the verified account offers only TOP; continue sending the exact `top` dispatch mode and omit the outbound API payment method. TOP does not imply payment is settled.
- [x] Countdown uses the server's 120-second quote expiry and automatically requotes on visible expiry without booking. Preserve exclusions/skip choice and valid payment choice, but clear confirmations, changed-price acknowledgments and credit PIN on every refresh. Repeated, expired or malformed quote responses and refresh errors stop automatic activity and offer explicit recovery.
- [x] Abort stale quote responses/timers on close, unmount or changed selection; allow cancelling read-only price refresh, but block dialog dismissal during dispatch. Dispatch consumes the token and stops the quote clock; unknown booking outcomes are never automatically retried.
- [x] Reuse the SDK's `php-http/curl-client` and Nyholm PSR-7 factories for Instant transport instead of bundling Guzzle. Inherit SDK headers/JSON/query construction; keep 25-second overall and 5-second connection timeouts, HTTPS/TLS verification, no redirects/retries, fixed silent failures, and a 2 MiB response cap enforced by both progress and a bounded response stream.
- [x] Enforce 1–10 packages per compatible origin/courier/vehicle request; split larger selections and report partial outcomes.
- [x] Reject Express/Borzo rows from the Instant dispatch path and keep existing Express endpoints unchanged.
- [x] Normalize account-verified TOP, QRIS, and KA Credit payments; validate PIN/credit before claiming orders and provide bounded automatic polling plus manual payment refresh for matched bookings.
- [x] Reuse the stored `order_id`; use atomic pending claims, expiring owner leases, and consumed quote tokens to prevent duplicate bookings.
- [x] Persist verified remote payment/status/AWB/tracking data and immutable sender/recipient/item snapshots immediately after a matched booking response.
- [x] Use official SDK Instant pricing and an explicit SDK-transport adapter for the documented v6.2 request schema.
- [ ] Verify actual Sandbox booking/payment responses and courier acceptance before declaring production readiness. The OpenAPI success shape is a mock, not a production guarantee.
- [ ] Browser-check the deployed dialog styles, TOP versus QRIS/credit controls, background/foreground expiry, cancellation and failed refresh recovery. Local Svelte/DOM tests replace UI boundaries and transport, not live WooCommerce admin/theme/API integration.

## Print Labels

- [x] Add an authenticated, dedicated Instant label preview/render route; do not send Instant labels to Express print APIs.
- [x] Render A6 local HTML labels using actual AWB and immutable booked address/item snapshots.
- [x] Print the same-origin label frame, wait for iframe readiness, abort stale previews, and validate a separate render nonce.
- [x] Exclude missing-AWB, unbooked, cancelled, unsupported-courier, and incomplete-snapshot rows from labels.
- [ ] Confirm an official carrier-issued Instant label endpoint/layout. Current labels explicitly identify themselves as local shipment labels, not carrier-issued documents.

## Cancellation, tracking, and webhooks

- [x] Route Instant cancellation to the dedicated DELETE endpoint with a durable claim before the remote request; do not infer final cancellation from request acceptance.
- [x] Route tracking/reconciliation to the Instant endpoint and render only validated live-tracking URLs; not-found does not release an uncertain booking claim.
- [x] Add an authenticated Instant webhook branch for shipped, canceled, and finished methods; reject mixed Express/Instant batches and malformed metadata before mutation.
- [x] Use strict CAS, monotonic lifecycle/payment merging, callback replay protection, and reload/recompute after concurrent changes.
- [x] Complete eligible WooCommerce orders after delivered confirmation; keep shipment cancellation separate from WooCommerce cancellation/refunds.
- [x] Preserve newer callback states when booking responses or payment refreshes arrive late; persist reviewed shipment context before outbound booking.
- [x] Bound direct Instant network requests and manual/automatic browser payment checks; preserve exact callback credentials and payload identities.
- [x] Keep ambiguous booking outcomes in the Instant tab with a fixed issue reason and durable pending claim; never automatically resubmit an uncertain booking.

## External dependencies

- [x] Supply an identified `kaj-shopify-plugin` revision for GoSend/GrabExpress courier child names: `5a9a2d7a3f738bf98c408312a536de400ba30077`.
- [x] Adapt the available Shopify transaction detail/status mapping to WooCommerce before enabling Instant shipment actions; list and detail now share `InstantDeliveryStatus`.
- [x] Persist Instant status, payment, coordinate, and tracking metadata with an independent retryable migration.
- [ ] Confirm the production Instant booking endpoint and response contract.
- [ ] Confirm `package_type_id` and account entitlement.
- [ ] Confirm the `Find New Driver` status trigger and terminal-state rules.

## Reference source and next slice

Local reference: `/Users/user1/Kerjaa/kaj-shopify-plugin`, revision `5a9a2d7a3f738bf98c408312a536de400ba30077`.

- `app/helpers/expedition.ts`: GoSend and GrabExpress both expose `instant` / `Instant` and `sameday` / `Same Day`. Borzo is commented out, so do not invent a Borzo child catalog from this source.
- `app/constants/transaction.ts`: status filters include `need_confirmation`, `waiting_for_shipment`, `waiting_for_payment`, `waiting_for_awb_generation`, `ready_delivered`, `find_new_driver`, `on_delivery`, `shipment_problem`, `finish`, `retur`, `finish_retur`, `cancel_requested`, and `cancel`.
- `app/helpers/kiriminaja.ts`: mapping depends on internal status, payment state, AWB presence, and Instant readiness. `101` is Find New Driver; `106` is On Delivery; `200` is Delivered; `300`/`302` are Cancelled; `350` is Cancellation Process. Copy the complete mapping, not just these examples, into the transaction adapter.
- `app/constants/orders/order.status.ts` and `app/models/packageOrder.ts`: use these alongside the helper to establish the order lifecycle mapping. Do not reuse Shopify's location-confirmation action in WordPress.

Next release step: perform the authorized Sandbox/end-to-end verification in [ADMIN-INSTANT-RELEASE-GATES.md](ADMIN-INSTANT-RELEASE-GATES.md). Cancellation, tracking, reconciliation, callbacks, and bounded payment polling are implemented and regression-tested. Valid saved coordinate/address context remains mandatory; this admin work does not supply missing buyer pins.

`InstantDeliveryStatus` uses persisted remote status codes and Instant payment state, never the local shipment enum as a remote payment status. List, detail, and fallback detail use the same labels, tones, issue reasons, and driver-replacement tooltip. Unknown remote combinations remain unknown rather than inheriting local success. Missing destination coordinates are an issue to resolve through the buyer address, not an admin location-confirmation action.

Remote status, payment method/status/ID, destination coordinates, and tracking URL have nullable persisted fields. The independent `kiriof_instant_metadata_v1` migration retries incomplete upgrades even when the earlier partition migration is already complete. Repository writes validate supplied metadata and preserve omitted fields; zero coordinates are valid.

Processed filtering and badge counts use persisted Instant booking evidence without joining Express payment rows. Full Shopify-state query filtering remains a separate UI/query enhancement; webhook ingestion is now implemented.

The settings screen stores supported courier preferences in one grid. Enabling Instant preferences does not imply buyer checkout is implemented; dispatch requires an eligible saved Instant record with coordinates, physical item data, and valid origin/address snapshots.

The transaction Instant tab remains enabled even when empty. Courier-management tabs have been removed entirely, per feedback. Account/API eligibility determines which courier rows appear.

### Checkout and asset verification

The separate `/Users/user1/Kerjaa/wordpress` checkout on `v3` still hardcoded `disabled: true` and the tooltip `Instant delivery is not available in this workspace` in the transaction tab, and a disabled Instant courier trigger. The feature branch removes both flags. Sync the feature branch into the serving checkout before checking the tabs; rebuilding a different worktree does not update the serving checkout.

The serving plugin loads compiled assets from `assets/admin/dist`, not Svelte source. Those assets are intentionally Git-ignored and must be built in the serving checkout (`make frontend`) after syncing source. No PHP filter disables the Instant tab; courier API filtering affects rows only.

## Verification

- [x] Run focused courier/settings, transaction partition, navigation, action-guard, and migration retry tests.
- [x] Run frontend lint, formatting, Svelte, accessibility/style checks, and Bun runtime tests.
- [x] Run `make test` for the courier-feedback/dispatch/label slice: 900 tests and 16,054 assertions passed; existing warnings/deprecations remain.
- [ ] Run `make zip` before packaging/release verification.

Frontend verification passed: formatting, lint, Svelte diagnostics, style checks, payment tests, courier-selection tests, and Instant tab navigation tests. The admin assets were rebuilt with `bun run build`. ZIP regeneration was intentionally skipped at the user's request; local staging files were refreshed only for source parity tests.

The dispatch, context, atomic claim, authenticated endpoint, immutable label, Processed-query, preview, lifecycle, payment-polling, callback, and concurrency regressions passed. Changed shipping paths passed WordPress/Plugin Check security, nonce, escaping, alternative-function, prepared-SQL, and database-parameter sniffs. No live shipment or payment was submitted. Closing the modal stops polling; ambiguous outcomes require explicit remote reconciliation and never cause automatic rebooking.

The local engineering improvements are not a verified 9/10 production rating. Real account responses, carrier-label behavior, upstream SDK facade timeout support, and the other unchecked [release gates](ADMIN-INSTANT-RELEASE-GATES.md) must be confirmed first.
