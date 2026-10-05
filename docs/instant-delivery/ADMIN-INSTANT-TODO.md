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
- [x] Show current prices for the shipments selected in the table; the explicit Confirm & process action accepts those prices without duplicate dialog checkboxes. Every selected row must remain eligible. Close the dialog and uncheck failed rows in the table instead of silently skipping them.
- [x] Compact shadcn Alert and default-collapsed shadcn Collapsible Order Information. Section headings explicitly reset margins; Shipping Information stays visible, with price arrows/count/gap details only when prices change. Totals explicitly compare carrier shipping charges, not buyer checkout admin fees or insurance.
- [x] Hide the payment selector and payment PIN entirely when the verified account offers only TOP; continue sending the exact `top` dispatch mode and omit the outbound API payment method. TOP does not imply payment is settled.
- [x] Countdown uses the server's 120-second quote expiry and automatically requotes on visible expiry without booking. Preserve table-selected IDs and a valid payment choice, and clear the credit PIN on every refresh. The countdown remains on the action; no redundant validity label or skip/confirmation/price-change checkboxes appear. Repeated, expired or malformed quote responses and refresh errors stop automatic activity and offer explicit recovery.
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

## Failure presentation and tracking policy

### Payment cards, balance, and dedicated Instant PIN step

- Label preview fills the dialog with screen-reader-only title/description, floating print/open controls and the header close icon (no bottom Close action). Instant preview tries the shared SDK AWB print endpoint after validating every selected booked AWB; verified public HTTPS URL results open the carrier PDF, otherwise a separately nonced local shipment label is displayed with explicit fallback notice. Published OpenAPI does not guarantee Instant print support; no live carrier call was made in tests. Cross-origin embedded PDFs may require Open in new tab for browser printing; local fallback remains an explicit alternate, not carrier acceptance evidence.
- Transactions/Payments date controls use the official shadcn RangeCalendar in a popover with Apply and All Dates reset. Inclusive calendar ranges serialize `date_from`/`date_to`; search/status/pagination retain them, switching delivery workspace clears them. Legacy month URLs remain valid. Backend validates real ISO dates and ordering, uses inclusive-day/exclusive-next-day prepared predicates across HPOS/legacy/all query branches and payment groups, and invalid explicit ranges show no rows rather than widening the result. Existing timestamp-column semantics are retained; installed timezone/browser behavior still requires verification.

- Transaction detail URLs now use the WooCommerce order ID in `?page=kiriminaja-transaction-detail&id=<wc_order_id>`, for both Regular and Instant transaction-list links and WooCommerce order metabox links. The page reads an unambiguous transaction by WC order ID after capability validation; malformed, missing or duplicate matches redirect to the list. No internal-ID fallback is attempted because numeric IDs may collide across orders. Old bookmarks containing internal transaction IDs must be updated; displayed custom order numbers do not replace the actual WC order ID. Mutation endpoints and internal UI row keys still use their existing identities.

- QRIS result UI: status-only refreshes preserve the original same-payment QR and known amount when the endpoint omits them (empty QR/null amount). A returned nonempty QR replaces it; paid/refunded/failed/expired responses clear it, and remote expiry still hides it. The QR is centered responsively on a white background. Manual refresh controls are adjacent to Close in the result footer, with payment IDs on multi-payment batches; no buttons inside the payment-detail card. Polling, request spacing and duplicate-booking protection remain unchanged.

- Status color parity: Waiting for Shipment uses the fixed blue `info` palette in Express and Instant list/detail/fallback presentation and legacy helper badges, not the admin-theme accent. On Hold/Pending Payment use amber `warning` consistently in WC badge maps and transaction-list presentation; the WC hold state is evaluated before a local `new` shipment so an On Hold label cannot inherit the shipment color. Instant payment/issue overrides and shipment actions are unchanged.

- Per-digit PIN reveal: the shared password-backed input shows only the newly typed/replaced digit. Other populated cells remain masked; keyboard navigation, pointer repositioning, blur, paste, external PIN resets and submission remask the revealed cell. Focus alone never reveals existing digits, and pasted full PINs are never displayed. Runtime coverage includes repeated digits and insertion into an earlier position. This changes presentation only, not validation or the submitted PIN.

- PIN presentation follow-up: exclude Bits UI's transparent overlay input from the general dialog input reset, explicitly remove native input paint/focus shadow, and give each of the six separate cells its own solid border without relying on Tailwind preflight. The PIN root fills the dialog content width with six responsive columns and gaps; the accessible label and description use `sr-only`. Match Bits UI's actual `[data-active]` attribute for cell focus styling. Both PIN-step footers replace Close with Back to Summary; Instant no longer duplicates the back action. Back clears the PIN and keeps the reviewed summary/payment selection. The header close control remains available when safe. Offline regressions verify composition, masking, reset selectors, two Instant footer actions, and back-state cleanup; installed-store visual verification remains open.

- Visibility/loading follow-up: Bits UI's closed content remains in the DOM with `hidden`; the panel's grid display can override native hiding because this WordPress stylesheet does not provide Tailwind preflight. Now mount order cards only when `orderInformationOpen` and enforce scoped `display:none !important` for hidden/closed content. Initial/reopened review is closed; automatic price refresh preserves the user's chosen open state. Tests assert no `[data-order-id]` cards in the closed DOM, not just the trigger attribute.
- Shared payment cards sort Credit before QRIS and Instant defaults to verified available credit, otherwise QRIS, matching Express. Refresh preserves the user's available choice. Field.Label's inherited choice-card border/ring/flex styles are reset once at the shared selector, preventing duplicate selected outlines and parent-dependent layout; known remaining balance wording is shared, but QRIS descriptions remain flow-accurate. Scoped keyboard focus rings remain.
- Installed official shadcn Skeleton and Spinner with the project Bun runner; retained the existing icon-library version. `ShipmentSummarySkeleton` is shared with content-shaped variants: Instant rates notice/closed order trigger/totals/payment cards, Express totals/date/time/payment cards. Loading exposes one polite labelled busy status and no fake values or selectable placeholders. `ShipmentOperationProgress` keeps the actual PIN/current form visible during validation/submission, using an Alert and Spinner; buttons compose the same Spinner via `data-icon`, not generic Button loading props. Submission disables Express primary controls explicitly. Reduced-motion skeleton styles are respected. No extra API calls, auto-booking, or synthetic result states are introduced.

- Shared UI follow-up: Express and Instant now import **the same** `src/lib/payments/CreditPinInput.svelte` and `PaymentMethodSelector.svelte`. Removed the Instant-only PIN wrapper and duplicated inline payment/OTP markup. `CreditPinInput` follows the installed shadcn Input OTP API (`Root` → six individually bordered masked `Slot`s in a full-width responsive grid; label and help text are screen-reader-only), with unique caller-provided input/description IDs, six numeric digits, password masking, autocomplete off, invalid/disabled semantics, and no payment/network/storage logic. `PaymentMethodSelector` composes `Field.Set`/`Legend`, clickable choice-card labels, `RadioGroup`, icons, balance lines, and shared state styling. Each caller supplies options, labels, availability, and flow-specific descriptions; API/payment rules remain in the dialogs/services. Shared scoped PIN/card styles apply equally to Express and Instant. Compiled integration tests exercise the real shared components in both flows, including masked cells, labels, balance, disabled/invalid states, and bound submissions.

- Summary uses installed shadcn `RadioGroup` choice cards, `Field.Group`/`Field.Set`, and the Express payment-card layout. KA Credit displays the verified numeric remaining balance, including balances above 32 bits (fixture: Rp4.000.724.100). Unknown balance is null—not invented zero—and cannot authorize credit; insufficient funds and unknown availability have distinct messages. TOP still bypasses payment selection/PIN.
- Summary → **Input PIN & Validation** → booking/result. The shared `CreditPinInput` composes the installed six-cell `InputOTP` with numeric-only input, password input, masked cells, no autocomplete, and invalid/disabled semantics. Choosing credit does not show a PIN on Summary. Back, close, refresh, expiry, and failed validation clear it. No browser PIN storage or logging.
- `kiriof_instant_validate_credit` is a capability/nonce/user/quote/context-bound read-only preflight. Invalid PIN is retryable without transaction claims, metadata changes, booking, or quote consumption. Only strict successful validation permits dispatch; dispatch independently revalidates before claims. Stale validation after close/selection changes cannot dispatch. Expiry while entering/validating PIN returns to refreshed Summary without silently extending authorization.
- Shared `CreditBalance` parser understands installed SDK normalized direct `{balance}` and bounded wrapper forms. Fixed Instant's obsolete `results.balance` access and Express UI's missing-balance-to-zero fallback. No formatted currency coercion, negative/nonfinite balance acceptance, or 32-bit truncation. Express keeps a known amount visible even on an unavailable card and distinguishes failed lookup from insufficient funds.
- Current PIN API successful envelope is accepted without requiring obsolete nested `data.valid`; an explicitly false legacy `valid` remains rejected. PIN transport errors bypass raw remote logging, and fixed sanitized failure messages are returned.
- Order Information uses a visible outline Button with a stable `kiriof-instant-order-trigger` class independent of forwarded primitive slots, scoped native-style resets, outline/border, explicit chevron rendering, default closed state, and accessible expanded/controls behavior. This supersedes the earlier ghost-trigger choice. The matching admin-list source CSS is compiled into the packaged workspace CSS in this build; trigger/card/PIN selectors and workspace CSS/JS ZIP parity are verified.

Compiled real RadioGroup/InputOTP/Collapsible regressions cover 4B/unknown/insufficient balances, Summary/PIN separation, masked paste/input, failed validation retry, Back/close/expiry cleanup, stale validation rejection, and no booking before PIN success. Live installed-store/theme/payment verification remains open.

### Verified request/response sample alignment

- The follow-up real error sample has `status: false`, a textual `message`, and an `errors.address_note` string with **no result**. Accept this narrowly validated field-error envelope as definite rejection under HTTP 2xx/400/422, superseding the earlier empty-result-only restriction. Rollback must still be verified before retry eligibility. Unknown/nested identity structures, null/nonempty result fields, invalid/missing error maps, and conflicting status remain protected. Log redacted actual field messages for pickup/destination notes; do not persist or expose raw remote error bodies.

- A merchant-supplied successful v6.2 sample established `payment_method: qris`, root/destination `address_note`, `results.payment.payment_id`, capitalized `service_type: Instant`, package `status: 110`, optional/missing AWB, `live_track_url`, and `poly_line`. This supersedes prior QRIS-to-cash assumptions and the earlier absence of a booking-polyline contract. The installed docs' mock response must not override this evidence.
- Pickup notes use explicit historical origin notes, then origin address line 2; destination notes use the current Woo address line 2, never stale snapshot fallback. Both fall back to their validated full address if empty. They are part of the reviewed context fingerprint, so changing notes requires a new quote. The adapter also guarantees nonempty note strings for callers outside this context and refuses malformed note types before transport.
- Admin QRIS now sends `qris` literally. KA Credit still validates its PIN and sends `credit`; TOP still omits the method and never infers payment success. Existing direct-adapter cash compatibility does not make cash a QRIS alias or enable recipient COD.
- Matching keeps exact partner order ID and courier; case-only variations of known `instant`/`sameday` service types are accepted without changing the persisted selected service spelling. Different service names, padded values, missing identities, and unknown shipment codes remain invalid. `payment_id`, unpaid amount/QR, status 110 and absent AWB in the working response are covered by a real dispatch-service regression.
- The accepted booking's validated `poly_line` is decoded to bounded numeric `shipping_info.instant_route_points`; no schema migration/nonexistent tracking column and no raw remote package/encoded response persistence. Present legacy tracking payloads remain authoritative. Empty `live_track_url` never enables Live Tracking even with valid route evidence. Malformed route data is omitted, not grounds to invalidate an otherwise confirmed booking. Later authenticated recovery/callback contract remains distinct from this booking sample.
- Do not add `insurance_type: basic` by default or claim insurance coverage from an echoed sample; existing unsupported Instant insurance/COD restrictions remain. Item quantities/dimensions/metadata, package type ID 7, actual gram weights, selected quote shipping cost, and origin/date validation remain unchanged. The sample amount can include fees; no repricing or paid/completed inference is made from it.

The merchant demonstrated a successful external request; offline regressions prove plugin alignment with that sample, not successful bookings on every account/courier. Historical unknown rows are not silently reset or rebooked.

- Recovery follow-up: uncertain pending Instant rows (no AWB/payment identity/remote code) expose a targeted **Recheck booking** action in list and normal detail views. It uses the existing explicit single-order reconciliation GET with nonce/capability checks; no booking retry, no automatic polling, no unlocking from not-found/unknown. Older blanket “Check Remote Status removed” notes are superseded only for this uncertain-booking recovery. Diagnostic logs now retain redacted plain-text API messages instead of categories alone, with bounded message length and request-derived secret/customer redaction before truncation. No raw payloads/messages are returned to the browser.

- Order Information uses the documented `Collapsible.Trigger` child snippet with the installed ghost `Button`, not an unstyled primitive or nested buttons. A dialog-scoped native-button reset removes WordPress/browser bevels and background images, retains semantic hover/focus states, allows narrow-width wrapping, and leaves the Collapsible default closed. Compiled DOM regressions use the real Button and check forwarded expanded/control attributes and chevron composition; live WordPress visual verification remains open.

### Dispatch rollback/retry audit

- HTTP 400 evidence exposed an additional gap: the bounded transport discarded non-2xx JSON before the adapter could examine rejection evidence. It now privately inspects bounded JSON and logs whitelisted schema/category facts (never raw text). HTTP 400/422 may use the same narrow explicit-empty rejection rule as HTTP 2xx; HTTP status alone, unknown nested structures, and missing/null results still cannot authorize retry. No verified request-encoding/schema mismatch was found in the installed SDK and available request documentation. Production validation details remain needed to identify the actual 400 cause.

- Fixed unchecked restoration: compare-and-swap/release results and reloaded original values must be verified before a row receives `retryable: true`. A failed write, exception, missing row, or racing authenticated callback produces a non-retryable unknown result rather than “Please try again.” Authoritative callback state is never reset.
- Fixed unsubmitted group cleanup: each group leaves the cleanup set immediately before calling `book()`. `finally` restores all groups that never reached that boundary, even when an unexpected exception occurs after an earlier group was submitted. Submitted unknown/accepted groups remain fenced.
- Fixed partial-batch rejection: return earlier confirmed rows/payments alongside `failed`/`skipped` rows restored after rejection. Do not throw away an earlier QRIS payment or imply the complete batch was cancelled remotely. Select only restored orders for the next quote.
- Verified local adapter validation or a failure before transport `sendRequest()` begins is explicitly `operation_not_submitted: true` and safe to restore. HTTP failures, malformed responses, and exceptions once sending begins are **not** proof of rejection. No inferred timeout cancellation and no blind retry.
- Before verified acceptance, keep existing address fields, origin snapshot, vehicle, payment method, public price, shipment/payment identities, timestamps, and Woo lifecycle unchanged. The durable transaction claim and a versioned `_kiriof_instant_prepared` **private** recovery envelope inside `shipping_info` are the only preparation writes. This envelope preserves reviewed metadata; it does not replace top-level address/item fields or enable labels. Authenticated lifecycle recovery publishes it through existing snapshot validation. Legacy prepared snapshots still recover via the older path.
- A rejected/provably unsubmitted order is restored to its original values, including removal of the private envelope, and becomes selectable again. Close the dialog, select those orders, and obtain a **fresh quote**; consumed tokens and uncertain booking requests are never reused automatically.
- Closing the review dialog before dispatch does not write shipment data or call booking. Closing a result dialog does not cancel an accepted/uncertain remote booking. Existing ambiguous rows from an older installation are not bulk-reset: remote acceptance must be checked first.

Offline regressions cover exact original-data restoration and a successful fresh retry; rollback failure/exception/callback races; never-submitted later groups after an unexpected exception; partial batch payments; private-versus-visible preparation data; authenticated recovery; and no automatic redispatch. Real API/theme/financial verification remains open.

- [x] Validation, quote and PIN failures throw before transaction writes. Structured definite booking rejection restores prepared state and throws a fixed error; reviewed public shipping cost is committed only after a verified booking.
- [x] Unknown/network outcomes retain private duplicate-booking claims and prepared snapshots. Their list/detail label remains Waiting for Shipment (or Woo On Hold), without an Instant order issue badge; they remain unselectable until an authenticated callback resolves them or support reviews them. No blind automatic redispatch or false claim that an uncertain remote booking was rejected.
- [x] Removed Check Remote Status actions and reconcile dialog mode from list/detail admin surfaces. Private backend recovery/callback safety remains.
- [x] Instant Live Tracking is hidden unless explicit, valid, nonempty polyline evidence and a safe tracking URL are available; no route is inferred from endpoints or driver coordinates.
- [ ] Confirm and implement an authenticated production polyline persistence contract. The current documented tracking response and repository do not supply/persist `instant_tracking_payload`; existing URL-only rows intentionally show no Live Tracking.

Remote multi-group processing is not an atomic database transaction: earlier successful remote groups cannot safely be undone when a later group fails. Only definitely rejected/not-submitted claims are rolled back; ambiguous/accepted groups retain duplicate protection.

## Read-only Instant detail map

- [x] Instant transaction detail renders bundled Leaflet using historical pickup/delivery coordinates, including valid zero values. No device permission, pin editing, saved-address changes, booking request, geocoding, or third-party route request occurs.
- [x] Valid persisted route evidence renders as a solid recorded path. With no valid route, complete saved endpoints render a dashed straight-line illustration, explicitly not a predicted road route or live tracking. Missing/invalid coordinates show an unavailable state, never the current/default warehouse or fabricated endpoints.
- [x] Recorded routes can render without saved endpoints; marker labels distinguish actual saved locations from first/last recorded route points. Tile errors retain the route disclaimer; map/layer/resize resources are disposed when navigating away or replacing detail data.
- [x] Leaflet loads before the admin workspace on list/detail pages so client-side navigation into detail is supported. No Google Maps or external Leaflet CDN is used. HTTPS map tiles and attribution follow the configured provider.
- [ ] Browser-verify direct detail load and workspace navigation on the deployed admin theme. A road-following predicted route would require a separately agreed routing provider/privacy/error contract; it is not inferred by this illustration.
