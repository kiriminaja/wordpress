# Svelte admin migration matrix

Target: move at least 90% of custom KiriminAja WordPress admin UI to Svelte while keeping authorization, persistence, WordPress hooks, WooCommerce integration, and server-side read models in PHP.

Native WooCommerce settings fields, product editor controls, checkout UI, and WordPress list-table compatibility code are outside the percentage target unless a custom KiriminAja surface owns the interaction.

## Progress

| Surface | Current state | Migration strategy |
| --- | --- | --- |
| Onboarding progress | Svelte | Progress and navigation state use the existing event bridge. |
| Onboarding account/courier/shipping | Svelte | Rendering is Svelte; persistence and sequencing remain in the legacy bridge during transition. |
| Onboarding address/map | Legacy | Next onboarding slice: move fields and map state without changing origin persistence. |
| Settings root / first connection | Svelte | Shared JSON bootstrap and WordPress AJAX client established. |
| Settings webhooks | Svelte | Callback URL persistence stays on the existing AJAX action. |
| Settings technical | Svelte | Region/courier cache jobs and diagnostics use existing AJAX actions. |
| Settings account | Svelte | Connection status, setup-key update, disconnect, and enabled courier summary use existing endpoints. |
| Settings couriers | Svelte | Async courier list, bulk selection, optimistic save, and rollback use existing endpoints. |
| Settings tracking | Svelte | Tracking-page inventory and guide are rendered from a PHP bootstrap snapshot. |
| Transactions list | Legacy | Build shared admin shell, filters, table, pagination; retain WooCommerce modal actions initially. |
| Payments list | Svelte | Filters, status tabs, table, and pagination are Svelte; QR/payment and reschedule dialogs remain legacy bridge. |
| Pickup detail | Legacy | Port summary cards and read-only tables after list shell. |
| Order metabox | Legacy | Port custom shipment summary/actions only; keep native WooCommerce order editing native. |
| Coupon extension UI | Legacy | Port custom region/courier picker after settings courier primitives stabilize. |

## Ordered migration

1. Finish onboarding address/map using the same settings stores and form components.
2. Port Transactions filters/table using shared admin-list primitives, then progressively replace action dialogs.
3. Port Payments QR/payment and reschedule dialogs.
4. Port Pickup detail and order shipment metabox.
5. Port custom coupon extension controls.
6. Remove legacy jQuery handlers only after each fallback has shipped and the Svelte path has equivalent tests.

## Boundaries

- Every page keeps PHP fallback markup until the corresponding Svelte surface is stable.
- JSON bootstrap payloads are emitted as non-executable `application/json` blocks.
- Svelte calls existing AJAX endpoints; it does not duplicate domain logic.
- New interactive primitives wrap Bits UI under `src/lib/ui/`.
- Icons come from individual `@tabler/icons-svelte` imports.
- Generated files under `assets/admin/dist/` are ignored by Git and built by `make frontend` / `make zip`.
