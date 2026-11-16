# FunnelKit checkout compatibility

## Recipient contact

FunnelKit's phone widget may submit a national number such as `81234567890` while displaying `+62` separately. Its `wfacp_input_phone_field` JSON supplies `billing` and `shipping` entries containing `code`, `number`, and `hidden`.

Classic review requests now validate the current phone against that metadata and use the matching Indonesian international number for the shipping quote. Final checkout uses the same effective shipping contact. Native billing phone values are not rewritten by the plugin, and the general Instant phone validator still rejects national-only numbers without this explicit context.

In shipping-first forms with shared billing, empty hidden shipping-name fields inherit the current billing names. Separate billing does not authorize that name fallback. Explicit phone clears, mismatched country codes, and malformed metadata remain invalid rather than reusing previous customer/session values.

## Forced billing destination

When WooCommerce forces shipping to the billing address, Classic pinpoint saves and clears use the billing draft scope even if FunnelKit submits a shipping-labelled pin. Existing shipping-only drafts must be saved again; the plugin does not revive them from session history.

For Indonesian FunnelKit single-address forms, missing billing city, state, postcode, and district fields can be recovered from the current shipping district selection. Recovery requires exact matching streets and unit/address-line-2 values, a unique server-verified district and postcode, and no conflicting populated billing fields or review-request address aliases. Optional billing-toggle fields are not required. Pickup methods, foreign countries, malformed input, and failed or ambiguous district lookups do not authorize recovery.

Review aliases and final checkout fields use that same verified hierarchy, including when WooCommerce has already copied some repaired billing fields. Forced-billing recipient phones use the existing matching Indonesian widget context during both review and final checkout; native billing phone values remain unchanged. Invalid widget evidence explicitly clears the effective shipping phone rather than falling back to a saved or raw billing number.

Regression fixtures cover saving/replacing/clearing pins, address conflicts, omitted toggle fields, review aliases, final fieldset omissions, current and legacy phone metadata, and the offline quote/rate lifecycle. Live checkout, payment, and courier booking still require staging verification.

## Hierarchy recovery

District option caches and hierarchy identity caches are separate. A complete, verified postcode option-cache hit now restores its hierarchy identities without a network lookup. Legacy or malformed option rows are revalidated through the official address API. A village/postcode mismatch requires authoritative revalidation; labels are never used to invent parent IDs.

Demand-driven WP-Cron refreshes revalidate postcode mappings in the background. Jobs are deduplicated, scheduled after 60 seconds, limited to 20 pending postcodes, and cooled down for five minutes per postcode. Failed refreshes do not overwrite good option caches. Normal WordPress cron execution must be available; this is not a continuously running worker.

Valid cached identities keep checkout off the network path. A first lookup with no verified mapping still needs an official response; if that fails, rates remain unavailable rather than using a guessed hierarchy. Background jobs do not change customer addresses, pins, orders, or automatically submit checkout.

## Diagnostic distinction

`recipient_invalid` is an Instant contact validation failure. `destination_hierarchy_unavailable` is an Express address mapping failure; it is not the same error and does not prove Instant's pricing API was called. A saved pin also does not establish courier availability or override the backend coverage limit.
