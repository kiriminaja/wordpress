# Instant Admin Release Gates

## Readiness rule

The admin implementation must not be rated production-ready from mocked tests alone. The engineering work below addresses known lifecycle, concurrency, authorization, and recovery risks. A 9/10 production-readiness assessment requires the external verification in this document as well.

See [ADMIN-INSTANT-TODO.md](ADMIN-INSTANT-TODO.md) for implementation progress and [BUYER-INSTANT-TODO.md](BUYER-INSTANT-TODO.md) for buyer-coordinate prerequisites.

## Implemented protections

- Dedicated GoSend/Grab Instant pricing and v6.2 booking paths; no Express schedule, cancellation, tracking, or printing fallback.
- User-bound, expiring quotes; explicit price-change review; compatible batches of at most ten packages.
- Atomic dispatch and cancellation claims; cancellation is reserved before DELETE. Unknown responses do not trigger a retry or release a booking claim.
- The reviewed origin, destination, item, vehicle, price, and payment-method snapshots are persisted before booking. PINs are not persisted or logged.
- Strict compare-and-swap lifecycle updates, including null-versus-empty metadata checks, reload/recompute on races, and monotonic payment state.
- Booking responses cannot regress a newer delivered, canceled, refunded, cancellation-requested, or issue state received during the request.
- Authenticated Instant callbacks are separate from Express. Mixed batches and malformed identities are rejected before mutation; only shipped, canceled, and finished Instant methods are supported.
- Delivered shipment confirmation completes an eligible WooCommerce order. Shipment cancellation does not cancel or refund the WooCommerce order.
- Reconciliation never books a shipment. Not-found tracking does not prove that a timed-out booking is safe to retry.
- Cancellation response status `105` is treated as an accepted request, not terminal cancellation. Status `300`/`302`, a terminal tracking date, or an authenticated lifecycle event is required to confirm cancellation.
- Safe live-tracking URLs only; no Express-style Instant tracking history and no server-side fetching of those URLs.
- Bounded payment polling, manual-refresh deadlines, abort handling, rate spacing, and no overlapping automatic/manual checks.
- Direct booking, PIN validation, tracking, and cancellation transport uses a five-second connection timeout, 25-second total timeout, verified TLS, no redirects/retries, and a two-MiB response limit.
- Local labels use immutable booked snapshots, separate authorization/nonces, and abort-safe same-origin previews. They are explicitly not represented as carrier-issued labels.

## External verification required before production approval

Use an approved Sandbox account and explicit authorization to create/cancel shipments or initiate payments. Do not put credentials or PINs into repository files, tickets, logs, or captured fixtures. No live API mutation has been performed as part of these coding changes.

- [ ] Confirm account entitlement and the package category mapping; category `7` remains a configurable API-example default.
- [ ] Capture redacted, real v6.2 booking responses for GoSend and Grab Express. The OpenAPI booking response is explicitly a mock, not an authoritative production shape.
- [ ] Exercise the merchant's available payment methods: TOP, QRIS, and KA Credit. Confirm the actual `cash`/QRIS mapping and TOP method omission with the account contract.
- [ ] Verify paid, pending, expired, refunded, invalid-PIN, and insufficient-credit outcomes. A local polling timeout must not alter the remote payment state.
- [ ] Verify real tracking success and not-found responses, nullable AWBs, courier service identifiers, timestamps, and live-tracking URL aliases.
- [ ] Verify cancellation before pickup, pickup races, accepted-but-pending cancellation, duplicate cancellation rejection, and final callback confirmation.
- [ ] Replay redacted real callback payloads twice and out of order through the complete controller/repository/WooCommerce flow.
- [ ] Kill a booking request after durable claim and test reconciliation without a second booking. Confirm the operational procedure for claims whose remote status remains inconclusive.
- [ ] Run an end-to-end test with WordPress and WooCommerce, including HPOS and legacy storage, and confirm actual persisted snapshots and order status effects.
- [ ] Confirm carrier-issued Instant label availability and acceptance of any locally generated label. Do not describe local labels as official carrier labels until confirmed.
- [ ] Verify existing records have valid destination pins and address associations. This admin work does not replace buyer coordinate capture.
- [ ] Run CI integration and Plugin Check on the final revision and inspect the actual serving checkout's rebuilt admin assets.

## Remaining technical limitations

The SDK facade calls for pricing, profile, payment lookup, and balance retain the installed SDK's upstream transport, which has no configurable timeout in the inspected version. The new bounded direct transport does not silently replace those facade contracts or modify vendor code. The browser bounds payment requests, but an aborted browser request does not necessarily stop the PHP worker. Require upstream SDK timeout support or an explicitly reviewed bounded facade integration before approving this resource-exhaustion risk for production traffic.

Tracking cooldowns are atomic per shipment, not a complete merchant-wide API quota scheduler. Sandbox batch testing must account for all API calls across operators and payment groups. HTTP throttling must never initiate another booking.

WooCommerce note creation and order metadata saving are not one atomic operation. Sequential callback replay is idempotent, and lifecycle failures are retryable, but a failure between adding a cancellation note and saving its marker can duplicate that informational note. This must not duplicate a shipment, payment, refund, or WooCommerce cancellation.

Administrative recovery deliberately fails closed. Neither not-found tracking nor an uncertain cancellation clears durable claims automatically. A documented manual escalation procedure is required when remote evidence cannot establish the final outcome.

## Evidence required for the rating

Record the tested revision, environment versions, redacted response fixtures, CI run, and external confirmations. Until the unchecked gates are satisfied, describe the implementation as hardened and Sandbox-ready—not verified 9/10 production stability.
