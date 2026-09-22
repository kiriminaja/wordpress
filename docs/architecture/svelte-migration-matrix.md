# Svelte admin migration matrix

Target: move at least 90% of custom KiriminAja WordPress admin UI to Svelte while keeping authorization, persistence, WordPress hooks, WooCommerce integration, and server-side read models in PHP.

Native WooCommerce settings fields, product editor controls, checkout UI, and WordPress list-table compatibility code are outside the percentage target unless a custom KiriminAja surface owns the interaction.

## Progress

| Surface | Current state | Migration strategy |
| --- | --- | --- |
| Onboarding progress | Svelte | Progress and navigation state use the existing event bridge. |
| Onboarding forms | Svelte | Account, address/map, courier, and shipping rendering are Svelte; persistence and sequencing remain in the legacy bridge during transition. |
| Settings root / first connection | Svelte | Shared JSON bootstrap and WordPress AJAX client established. |
| Settings webhooks | Svelte | Callback URL persistence stays on the existing AJAX action. |
| Settings technical | Svelte | Region/courier cache jobs and diagnostics use existing AJAX actions. |
| Settings account | Svelte | Connection status, setup-key update, disconnect, and enabled courier summary use existing endpoints. |
| Settings couriers | Svelte | Async courier list, bulk selection, optimistic save, and rollback use existing endpoints. |
| Settings tracking | Svelte | Tracking-page inventory and guide are rendered from a PHP bootstrap snapshot. |
| Transactions list | Svelte with server row bridge | Filters, search, pagination, and table shell are Svelte. Escaped PHP row fragments preserve complex WooCommerce action eligibility during transition. |
| Payments workspace | Svelte with legacy controller bridge | List, filters, pagination, QR modal, and reschedule modal are Svelte. Existing jQuery still owns AJAX/QR/PIN orchestration temporarily. |
| Pickup detail | Svelte | Summary cards and shipment table use a server-generated bootstrap payload; print/detail URLs stay server-owned. |
| Order metabox | Svelte with server fragment bridge | Shipment summary/actions mount through Svelte while PHP retains COD/discount calculations and action attributes. Native WooCommerce order editing stays native. |
| Coupon extension UI | Legacy | Port custom region/courier picker after settings courier primitives stabilize. |

## Ordered migration

1. Replace Transactions server row fragments with typed row serializers, then progressively replace action dialogs.
2. Port custom coupon extension controls.
3. Replace server HTML bridges with typed serializers for Transactions and the order metabox.
4. Replace jQuery modal controllers with Svelte state/actions after typed AJAX coverage exists.
5. Remove legacy handlers only after each fallback has shipped and the Svelte path has equivalent tests.

## Boundaries

- Every page keeps PHP fallback markup until the corresponding Svelte surface is stable.
- JSON bootstrap payloads are emitted as non-executable `application/json` blocks.
- Svelte calls existing AJAX endpoints; it does not duplicate domain logic.
- New interactive primitives wrap Bits UI under `src/lib/ui/`.
- Icons come from individual `@tabler/icons-svelte` imports.
- Generated files under `assets/admin/dist/` are ignored by Git and built by `make frontend` / `make zip`.
