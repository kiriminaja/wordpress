# Operational logging policy

Normal checkout reads/recalculations should be silent. Logs are for actionable faults and consequential merchant operations, not a history of every hook execution.

## Removed routine producers

- Express destination fallback/meta identifiers, pricing result keys/counts and successful calculation traces.
- Instant successful/cached quotes, normal rate calculation results, expected ineligibility (missing pin/address, disabled methods, COD restrictions, no availability), and automatic local-readiness snapshots.
- No-active-coupon, expected coupon rejection/mismatch/zero-discount, successful validation/application, metadata refresh and successful region-cache scheduling/loading/refresh messages.
- Payment-form polling summaries and date/timezone instrumentation.
- Successful processed/lifecycle webhook notices, including replay chatter, and duplicate controller-level application-error warnings.
- Intermediate pickup API-response summaries; one concise pickup outcome audit remains.
- Raw pricing payload/result, tracking result, transaction-list row/month and successful order-metadata debug dumps.
- Duplicate generic subdistrict warning: the address repository owns its bounded reason/HTTP/timing diagnostic. Unexpected controller exceptions remain logged without backtrace.

These events are removed at their producers, not merely downgraded to debug or hidden by a higher global threshold.

## Retained

- Instant booking submitted/confirmed references, ambiguous acceptance, identity/response rejection, verified rollback failure and recovery errors.
- Compact merchant pickup audit, with no raw request/payment/credential payload.
- Authentication failures, malformed callbacks, unmatched transactions and unverified persistence failures.
- Actual pricing/API/transport/region-cache failures, using fixed reason codes rather than raw response or exception dumps.
- Explicit settings/COD changes and substantive merchant-action audit events.
- Existing database-failure paths and explicit opt-in development diagnostics where needed; no routine success metadata dump is required.

Log source/severity filtering and the public logging API are unchanged. An expected empty/unsupported checkout state is not automatically a warning. A real fault must not be silenced simply to reduce volume. Do not log PINs, API tokens, customer address snapshots, full payloads, QR strings, or raw API responses.

## Verification and existing records

Runtime logger-spy tests verify silent success/expected skip/poll/replay paths while confirming failures remain visible and business results/persistence stay unchanged. Structural guards keep booking diagnostics and prohibit known high-volume success producers.

This cleanup stops future noise. It does not delete historical WooCommerce logs or change their retention settings. Removing existing records is a separate merchant/admin action; use WooCommerce's log viewer and retention policy according to the store's support/audit requirements.
