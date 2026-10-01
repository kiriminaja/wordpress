# Buyer checkout infrastructure TODO

## Status and scope

This document records the buyer checkout investigation and the first implementation pass. Historical source findings below describe the pre-migration code; remaining checklist items describe follow-up work.

Scope: classic WooCommerce cart/checkout, Cart and Checkout Blocks, district/subdistrict selection, courier selection, insurance/COD recalculation, and possible searchable-select and Leaflet extensions.

The review covered the complete core buyer checkout JavaScript in `assets/wp/js/form-billing-address.js` and `assets/wp/js/kiriof-block-checkout.js`, supporting buyer scripts/templates, and relevant checkout PHP. Implementation now has isolated JavaScript adapter/queue tests and PHP runtime tests. A real WooCommerce browser/network reproduction is still required to verify the reported visual glitch against the merchant's installed version and theme. No local WordPress or development server was started.

Line references describe the reviewed source and may move as implementation changes.

## Implemented foundation

- `assets/wp/js/kiriof-buyer-checkout.js` renders a React-owned, locally searchable district control through the supported shipping-address inner block (`kiriminaja-official/checkout-district`). It reads the shipping address from the WooCommerce cart store, publishes checkout extension data, and uses WooCommerce validation for pending/error states. It does not write editing-address booleans as address objects or replace React inputs. The existing order-summary Slot/Fill stays only as a constrained fallback.
- `assets/wp/js/kiriof-checkout-session.js` serializes plugin mutations and coalesces the latest pending snapshot. Only success is acknowledged; failures retain an explicit retry. Native shipping/customer operations gate dispatch, and successful responses do not trigger extra whole-cart invalidations.
- Native WooCommerce selection remains the sole courier writer on the modern path. Courier-only changes do not cause another plugin mutation; delayed destination/payment payloads never select a courier. Native and explicit Store API selections preserve package-indexed sessions.
- `inc/Services/BuyerDestination.php` defines a version-1 shipping district snapshot. The server verifies positive IDs against postcode lookup results and replaces supplied labels with canonical labels. Final submitted postcode/country must match the shipping address, and submitted modern data wins over stale sessions.
- Modern sync is session-only; returning customers' persistent district data is not erased during initialization or failed lookup. Explicit clears cannot revive saved customer IDs during shipping calculation. Failed transaction creation retains retry context.
- The real checkout factory shares successful five-digit postcode lookups through a five-minute transient cache. Native and classic fee-cache keys include the cart hash, so merchandise changes invalidate cached insurance/COD fees.
- Classic checkout and unsupported Blocks installations retain the legacy adapter. Modern activation waits for an actual shipping-address control mount; a missing inner block yields to the constrained SlotFill fallback. In-address mounts take priority over fallback summary mounts, with one shared destination model and one effect owner. Experimental Slot availability remains version-sensitive.

The original version-1 contract remains backward compatible. Version 2 adds optional `destination_latitude`, `destination_longitude`, and a six-field `shipping_address` snapshot. Coordinates never replace district validation. A changed street, city, province, postcode, or country invalidates the pin, and final server validation checks the complete address binding.

`kiriminaja-official/map-checkout` is a locked shipping-address inner block. Its Leaflet map renders automatically with a fixed center indicator and a top-right Current location button. Map movement saves the center on `moveend`, not on every animation frame. Initial centering/resizing never selects the default center. Device location is requested only on button activation and browser permission; no automatic location permission prompt, address geocoding, or Google dependency is used. Automatic rendering connects to the configured HTTPS tile provider; retain attribution and document provider use in the store privacy notice.

There are no manual coordinate inputs, Apply button, or Load map button. Keyboard arrows move the map and Enter selects its center. Map failure does not make an optional pin mandatory. The pin is saved as order metadata; buyer Instant quotes and dispatch are not implemented by this map change.

Buyer checkout remains Express-only even after every address field is completed and a pin is placed, moved, or cleared. GoSend, GrabExpress, Borzo, and rows tagged Instant are excluded from both checkout rate consumers regardless of admin service enablement. `BuyerInstantExclusionRuntimeTest` exercises the production destination sync and shipping filters against fresh and cached mixed pricing results, including COD/insurance and genuine zero coordinates; it also rejects construction of an Instant pricing client. Pin changes do not grant Instant checkout eligibility.

### Courier switching and COD

Changing only the selected courier must not change the available Express list or cached raw quote. Changing payment to COD intentionally removes services that the pricing API does not mark COD-capable. Returning to a non-COD payment must restore those services. `BuyerCourierSwitchRuntimeTest` verifies Ninja Standard → Tiki REG → COD → Ninja Standard/non-COD with one raw pricing request, unchanged destination/pin, and preserved rate IDs/prices. It also verifies that COD-capable Ninja stays visible under COD.

`PricingCacheService` now detaches incoming/outgoing pricing object graphs so consumers cannot narrow or mutate the shared quote; invalid response graphs are not cacheable. The COD gateway fallback has a per-controller reentrancy guard that resets through `finally`, avoiding recursive available-gateway resolution during transitions. These fixes do not establish that either bug caused every merchant-visible rate change. A captured payment/rate request sequence is still needed if rates disappear while payment, address, cart, policy, and insurance are unchanged.

Enabling buyer Instant rates is a separate change to the earlier Express-only requirement. It needs coordinate-based Instant quotes, exact enabled service/vehicle checks, quote refresh on address/pin/cart/origin changes, payment eligibility, and order/transaction snapshots validated against the selected quote. Do not display GoSend/Grab using Express API rows or lift the exclusion solely because coordinates exist.

Checkout layout recovery has one placement owner: the native WooCommerce forced-block registry. Server `render_block_data` insertion and metadata Block Hooks were removed. Missing native checkout-child identities are annotated before WooCommerce legacy migration, without modifying saved templates. The actual WooCommerce 10.6 renderer regression reproduces section multiplication from class-only child wrappers and confirms normalized identity retains one layout.

Current placement is the shipping-address step, not the order summary. WooCommerce supports a checkout inner block under `woocommerce/checkout-shipping-address-block`; the server registers `kiriminaja-official/checkout-district` and its component replaces the payment-surface placement with the same shared destination state, queue ownership, validation, extension data, and saved-restore logic.

Verification coverage: `BuyerCheckoutSession.test.ts`, `BuyerCheckoutAdapter.test.ts`, `CheckoutRaceRuntimeTest.php`, `BuyerDestinationRuntimeTest.php`, `BuyerShippingDestinationRuntimeTest.php`, and `DistrictSearchCacheRuntimeTest.php`. Buyer JavaScript tests/lint are included in `frontend:check` through `test:buyer`. Tests cover isolated state/Leaflet fixtures, an optional installed React DOM runtime, and the actual WooCommerce renderer/WordPress tokenizer when their external test source paths are provided. No live courier API or local WordPress server was used.

## Feasibility assessment

Scores describe engineering feasibility, not measured probabilities, delivery estimates, or guarantees of compatibility with every theme/plugin.

| Objective | Feasibility | Assessment |
| --- | --- | --- |
| Fix courier A to B selection flicker/reversion | 9/10 | Competing update paths, forced invalidation, and stale-selection precedence provide concrete investigation targets. |
| Move Blocks checkout toward native WooCommerce APIs | 9/10 | Supported stores and extension APIs exist; the plugin already uses some of them. |
| Support classic and Blocks checkout reliably | 8/10 | Requires explicit version coverage and end-to-end validation, not a universal compatibility claim. |
| Stabilize district/subdistrict selection | 9/10 | Replace the hidden-field/DOM synchronization bridge with a supported state and rendering contract. |
| Add a Select2-style searchable control | 9/10 | Implement a React-owned searchable control in a custom checkout block. |
| Add a Leaflet location picker | 8/10 | Requires a custom block, coordinate persistence, a suitable tile provider, and district mapping. |

## Existing architecture

Buyer checkout is not entirely jQuery. The current implementation combines:

- jQuery event handlers and imperative field/markup changes in `assets/wp/js/form-billing-address.js`.
- WooCommerce cart/payment stores and `wp.data.subscribe`.
- `extensionCartUpdate` and a registered server callback for district/payment/insurance updates.
- React hooks, a checkout Slot/Fill, and checkout filters in `assets/wp/js/kiriof-block-checkout.js`.
- Plugin AJAX, Store API customer updates, session mirrors, timers, and store invalidation.

The main concern is overlapping ownership of selection and destination state. Removing jQuery alone would not resolve that ownership problem.

The [frontend foundation](frontend-foundation.md) recommends Svelte for interactive admin islands. This buyer Blocks proposal uses WooCommerce's React extension interfaces and must remain separate from that admin migration. Do not mount a second UI framework over fields rendered by WooCommerce.

## Findings from source

### Courier selection and refresh races

| Confirmed source behavior | Reference | Possible consequence requiring runtime verification |
| --- | --- | --- |
| A delegated handler listens to both `change` and `click`, remembers the courier, dispatches shipping selection, and schedules a fee update. A separate store subscription also schedules fee updates. | `assets/wp/js/form-billing-address.js:120-147` | One buyer interaction can initiate overlapping selection and extension requests, in addition to WooCommerce's own handling. |
| Explicit `selectShippingRate` dispatches do not track settlement or superseded selection requests. | `assets/wp/js/form-billing-address.js:2360-2378` | An earlier request may overlap with a newer buyer selection. |
| Pending courier intent expires after three seconds; fee payloads prefer that pending value over the selected store rate. | `assets/wp/js/form-billing-address.js:2274-2290,2663-2668` | Intent and acknowledged state can disagree, particularly on slow connections. |
| The plugin invalidates shipping/cart state and schedules extra refreshes after an extension update. | `assets/wp/js/form-billing-address.js:2292-2323,2404-2421,2742-2752` | Unnecessary fetching/re-rendering can expose intermediate server state. The WooCommerce FAQ warns that whole-store invalidation can cause a cart flash. |
| The recently completed refresh-key check runs before the in-flight/pending-request check. | `assets/wp/js/form-billing-address.js:2712-2734` | If B completed recently, A starts, and the buyer selects B again within the deduplication window, B can be discarded instead of queued behind A. |
| Both successful and failed extension requests run the same completion callback, recording the refresh key as completed. | `assets/wp/js/form-billing-address.js:2744-2767` | A failed update can suppress a subsequent retry as though it succeeded. |
| The server gives the plugin's cached shipping method precedence over WooCommerce's supplied method, after checking explicit request selection. | `inc/Controllers/CheckoutController.php:1759-1811` | A stale plugin mirror can preserve the old courier during recalculation. |

These findings support a race-condition explanation for A to B to A to B behavior, but do not prove which request causes the reported glitch.

### District rendering and persistence

- `inc/Controllers/CheckoutController.php:2002-2015` registers the current district additional field as `text`. Its comment explains that dynamically fetched district IDs cannot fit the fixed enum used by a registered select.
- `assets/wp/js/form-billing-address.js:332-440` hides the React-rendered source fields, changes their input properties, sets values through the native input setter, and dispatches events to synchronize them with the replacement control.
- `assets/wp/js/form-billing-address.js:1007-1029` adds mutation-observer resynchronization around React updates.
- `inc/Controllers/CheckoutController.php:804-810` reads the submitted additional district field only when the session destination is empty. A nonempty stale session value can therefore take precedence over the submitted field.
- The additional field is optional and has no registration-time validation callback at `inc/Controllers/CheckoutController.php:2008-2027`. Classic plugin validation cannot be assumed to apply to Store API checkout requests.

The visible control, hidden source field, React store, and server session represent the same destination through different update paths. Their disagreement is a strong stability risk, not proof that every district glitch has the same cause.

## Proposed state ownership

- WooCommerce owns native shipping-rate selection and its acknowledged cart state.
- The plugin owns district identity, district label, insurance preference, and any destination coordinates through a defined extension contract.
- PHP validates that contract and calculates shipping, insurance, and COD values. Client input must not determine authoritative fees.
- Use `extensionCartUpdate` when extension input changes server-calculated cart data. Its response already updates the Blocks cart.
- Use `setExtensionData` for custom values submitted with checkout. It does not itself recalculate the cart or persist order metadata.
- Track buyer intent separately from acknowledged server state. Do not let a timeout or stale session mirror silently become the source of truth.
- Keep classic checkout adapters separate from Blocks adapters while reusing PHP business rules.

## P0: Reproduce and isolate courier reversion

- [ ] Record the WooCommerce/WordPress versions, theme, checkout type, active payment integrations, and whether the buyer is logged in.
- [ ] Capture network timing for native shipping selection, `cart/extensions`, customer updates, cart reads, and plugin AJAX without logging customer addresses or credentials.
- [ ] Correlate each request with buyer intent, selected store rate, plugin session mirror, and response application order.
- [ ] Reproduce A to B, A to B to A, and recently completed B to A to B with throttled and failed requests.
- [ ] Determine whether the plugin's delegated handler duplicates WooCommerce's native shipping selection.
- [x] Give native courier selection a single owner and coordinate extension recalculation with acknowledged shipping state on the modern path.
- [x] Give WooCommerce's valid resolved method precedence over the plugin mirror.
- [x] Replace timer-based completed-key deduplication with a serialized latest-pending queue on the modern path.
- [x] Separate successful acknowledgment from failure handling and preserve explicit retry behavior on the modern path.
- [x] Remove redundant whole-store invalidations from the modern extension-update path.

Acceptance checks:

- The latest valid buyer selection remains selected after all requests settle.
- Earlier responses cannot restore an older courier after a newer selection has been acknowledged.
- Totals, insurance, COD availability, server selection, and final order shipping line agree.
- An unavailable selected rate produces a clear fallback or validation message rather than silently preserving invalid intent.
- Failed updates do not get marked as successful or prevent recovery.

## P1: Stabilize district/subdistrict state

- [ ] Decide the canonical destination payload: district ID, display label, postcode, and billing/shipping distinction where applicable.
- [ ] Define behavior for separate billing/shipping addresses and the same-address toggle. The current Fields API's `address` location renders in both address forms; do not assume `address_type` makes it shipping-only.
- [x] Bypass the hidden-field bridge with a React-owned district control when modern APIs are available; keep legacy fallback isolated.
- [x] Discard stale search responses and invalidate district identity when shipping postcode/country changes.
- [x] Serialize destination updates and block submission through WooCommerce validation while synchronization is pending or failed.
- [x] Validate modern district identity against authoritative postcode results server-side.
- [x] Make final modern checkout data authoritative through a typed, versioned contract.
- [ ] Restore returning-customer district data through supported field/customer helpers rather than repeated DOM polling.

Acceptance checks:

- District ID and label agree across the control, cart, customer data where saved, and order metadata.
- Selecting or restoring a district survives React re-renders without duplicate controls or lost focus.
- Rapid postcode/search changes cannot apply an older destination response.
- Same-address toggles, separate shipping addresses, saved customers, and guest checkout preserve the intended destination.
- Server-side checkout validation still works if client validation is bypassed.

## P1: Complete the Blocks integration contract

- [ ] Define supported WordPress/WooCommerce versions and document fallbacks for unavailable APIs.
- [ ] Use `IntegrationInterface` and checkout/cart block registration hooks for Blocks script/data loading where appropriate.
- [ ] Use store selectors and scoped subscriptions, or `useSelect` with effects, instead of DOM selectors to observe checkout state.
- [ ] Keep subscription effects idempotent and provide cleanup; one subscription must not refresh on every unrelated store action.
- [ ] Consolidate extension cart updates under the existing namespace and callback contract.
- [ ] Audit Store API validation for district requirements, COD limits, insurance policy, and final transaction context.
- [ ] Confirm package handling rather than assuming that every cart has only shipping package zero.
- [ ] Prefer supported extension rendering for summaries; isolate unavoidable DOM fallbacks and version-test them.
- [ ] Keep classic checkout functionality intact during the Blocks migration.

## P2: Searchable district control

A Select2-style experience is feasible, but arbitrary searchable-select rendering is not provided by the basic Additional Checkout Fields API. That API provides text, select, and checkbox fields; registered select options are validated against their declared choices.

- [ ] Implement a custom checkout inner block with a React-owned asynchronous combobox.
- [ ] Choose a supported placement and show the block in the checkout editor preview.
- [ ] Define loading, empty results, invalid destination, retry, and clear-selection states.
- [ ] Support keyboard navigation, accessible labels, focus retention, and mobile interaction.
- [ ] Avoid attaching Select2 directly to a select rendered and controlled by WooCommerce React.

Literal Select2 integration would need its own lifecycle-managed DOM subtree. It is not the preferred route for this migration.

## P2: Leaflet destination picker

The custom `map-checkout` block renders Leaflet and submits optional latitude/longitude through the version-2 destination snapshot. The basic Fields API does not expose a map field type. Coordinate collection and order persistence are implemented; buyer Instant quoting remains separate work.

Use Leaflet, not Google Maps. The repository already declares `leaflet` (`^1.9.4`) and `@types/leaflet` in `package.json`. Leaflet handles map interaction; it does not supply map tiles, address search, reverse geocoding, or KiriminAja district identity. Choose those services separately without introducing a Google Maps dependency.

- [ ] Confirm whether coordinates support delivery accuracy, instant-delivery quoting, or both before defining downstream behavior.
- [x] Render the picker in a custom inner block. If a Slot/Fill is used, verify its placement and version support; `Experimental` slots are not stable contracts.
- [x] Reuse bundled Leaflet in a dedicated block container, resize through `invalidateSize`, and call `remove` on unmount. The fixed center indicator needs no Leaflet marker assets.
- [ ] Choose a configurable tile provider with appropriate attribution, usage limits, caching, availability, and any required credentials. Do not assume the public OpenStreetMap tile service provides unlimited capacity or an SLA; comply with its tile policy if used.
- [x] Define click-to-center and camera-movement selection behavior. If address search or reverse geocoding is needed, select a separate non-Google service and document its privacy, rate-limit, and usage requirements.
- [ ] Define latitude/longitude validation, order persistence, optional customer persistence, and downstream shipping payloads.
- [ ] Use extension cart updates when coordinates affect quotes; include checkout extension data for final order submission.
- [ ] Define how a map location resolves to a valid KiriminAja district ID. Leaflet coordinates and geocoder address labels do not automatically supply that identity.
- [ ] Keep manual address/district entry available when the map, tile provider, or geocoder fails, is blocked, or cannot resolve the destination.
- [ ] Cover mobile interaction, keyboard alternatives, privacy disclosures, and geolocation consent if device location is offered.

Do not mark the picker complete merely because a map renders. Quote calculation, destination validation, order metadata, and shipment creation must consume the same selected destination.

## Supported APIs and hooks

| Responsibility | API/hook | Notes |
| --- | --- | --- |
| Observe native checkout/cart state | `useSelect`; `select` + `subscribe` | Compare relevant values and clean up subscriptions. |
| Recalculate extension-driven cart data | `wc.blocksCheckout.extensionCartUpdate` | Returns the updated cart through WooCommerce's extension flow. |
| Process extension cart updates | `woocommerce_store_api_register_update_callback` | Register on the appropriate Blocks lifecycle; use one callback per namespace. |
| Register basic additional fields | `woocommerce_register_additional_checkout_field` | Not an arbitrary custom renderer. |
| Validate additional fields | `validate_callback`; `woocommerce_validate_additional_field` | Confirm availability/signatures against the supported WooCommerce versions. |
| Register checkout scripts/data | `IntegrationInterface`; `woocommerce_blocks_checkout_block_registration` | The cart has a corresponding `woocommerce_blocks_cart_block_registration` hook. |
| Render a custom checkout control | Custom inner block; `registerCheckoutBlock` | Verify allowed parent/placement for the supported versions. |
| Submit custom checkout data | `checkoutExtensionData.setExtensionData` | Define the Store API extension schema and server persistence separately. |
| Process custom checkout data | `woocommerce_store_api_checkout_update_order_from_request` | Validate request data and persist through order APIs. |
| Add content through slots | Checkout Slot/Fill APIs | Treat names prefixed with `Experimental` as version-sensitive. |

## Verification and implementation gates

- [ ] Add focused request/state tests for superseded intent, duplicate events, failed requests, and deduplication ordering.
- [ ] Add PHP runtime tests for selection precedence, destination validation, fees, and final order context.
- [ ] Run browser checks for classic checkout and Checkout Blocks, logged-in/guest buyers, separate addresses, shipping discounts, insurance, and COD/non-COD.
- [ ] Include slow/out-of-order requests, missing APIs, invalid rates, and search/map failures in the test matrix.
- [ ] Apply WordPress Coding Standards and keep existing unrelated worktree changes untouched.
- [ ] For implementation changes to packaged source, run `make zip` before final verification and use `make test` as the default PHP runner.

These are implementation gates, not universal theme-compatibility claims. This pass runs 102 buyer/map JavaScript tests, 1,150 PHP tests, frontend checks, and `make zip`. Archive integrity, buyer source parity, and development-file exclusions are verified. Real WooCommerce browser/theme testing and buyer Instant quoting remain outstanding; warnings/deprecations in the PHP suite are not represented as failures.

## References

- [WooCommerce Cart and Checkout Blocks FAQ](https://developer.woocommerce.com/docs/block-development/cart-and-checkout-blocks/faq/)
- [Updating the cart on demand](https://developer.woocommerce.com/docs/apis/store-api/extending-store-api/extend-store-api-update-cart/)
- [Additional checkout fields](https://developer.woocommerce.com/docs/block-development/cart-and-checkout-blocks/additional-checkout-fields/)
- [Handling scripts, styles, and data](https://developer.woocommerce.com/docs/block-development/cart-and-checkout-blocks/integration-interface/)
- [Adding fields and passing values](https://developer.woocommerce.com/docs/apis/store-api/extending-store-api/extend-store-api-add-custom-fields/)
- [Available slots](https://developer.woocommerce.com/docs/block-development/cart-and-checkout-blocks/available-slot-fills/)
- [Leaflet API reference](https://leafletjs.com/reference.html)
- [OpenStreetMap tile usage policy](https://operations.osmfoundation.org/policies/tiles/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
