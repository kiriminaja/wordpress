# Automatic saved-origin address repair

`OriginAddressMigrationService` schedules a versioned background migration on activation and on the first normal `init` after upgrading. It waits until an API key is configured. Activation and checkout rendering do not perform address lookups.

## Matching saved addresses

The worker searches the official unified-address endpoint using the saved five-digit postcode. If the saved postcode is empty, it may use the postcode at the end of a complete saved area label. An explicitly conflicting or malformed postcode is not replaced.

A repair requires either:

- The saved village ID is present in the verified postcode results, with no conflicting complete saved hierarchy label.
- Exactly one result matches all four saved hierarchy components and the postcode. Comparison ignores ASCII case and whitespace within each component. `Sari Harjo, Ngaglik, Sleman, DI Yogyakarta, 55581` can therefore match `Sariharjo, Ngaglik, Sleman, DI Yogyakarta, 55581`.

The worker does not use fuzzy matching, abbreviations, street-address inference, a first-result fallback, or a sole-postcode-result guess for an unknown ID. Ambiguous results, conflicting identities, malformed data, foreign origins, and labels that cannot fit the warehouse column remain unchanged.

Older default-location seeding omitted the legacy area label. The migration can read that label from the legacy origin settings only when the saved ID and postcode still describe the same default warehouse. New default seeds preserve the label directly.

## Persistence and concurrency

The repair changes only the warehouse village ID, canonical area label, postcode, and modification timestamp. It preserves street addresses, contact details, coordinates, active/default flags, product bindings, and historical order/transaction snapshots.

Default-origin identity mirrors are updated in the same transaction. Native WooCommerce store-address/postcode options are not rewritten. Every involved table must be InnoDB; the worker refuses automatic repair on nontransactional tables rather than risking a partial update.

A complete pre-lookup snapshot is compared with the locked warehouse and mirror rows before writing. Concurrent merchant edits, default switches, duplicate settings, and changed/missing mirrors prevent the stale repair. Successful repairs invalidate settings/option caches and bump WooCommerce's shipping cache version.

## Scheduling and diagnostics

- Cron hook: `kiriof_resolve_saved_origin_addresses`.
- Progress option: `kiriof_origin_address_migration` (non-autoloaded).
- One warehouse and at most one postcode request are processed per invocation.
- Temporary lookup/persistence failures retry up to three times per warehouse with a five-minute delay. Ambiguous/conflicting origins are left for review, and subsequent warehouses continue.
- Authoritative database read failures retain the cursor and retry; they never become an empty/completed migration.
- A lease prevents concurrent workers. Progress publication is fenced by the exact current lease owner. A watchdog event is scheduled before remote work so interrupted jobs can resume.
- Completion is `complete` or `needs_review`, with counters only. Progress and logs do not retain addresses, postcodes, coordinates, tokens, or raw API errors.
- Unresolved rows produce a fixed-code warning under `kiriminaja_shipping` with the warehouse ID. Successful routine processing is silent.

The migration depends on working WordPress cron execution. Stores using `DISABLE_WP_CRON` must run WordPress cron through their external scheduler. Deactivation clears the migration's pending cron hook. Reactivation resumes unfinished progress rather than repeating completed work.

If an origin cannot be matched safely, edit that warehouse, reselect its area from the live lookup, verify the postcode, and save. A completed migration does not continuously rewrite newly edited warehouses.

## Verification scope

Offline tests exercise deterministic matching, transactional SQL/rollback, merchant-edit guards, scheduling/lease recovery, failure privacy, and pricing after the mapping cache expires. SQLite fixtures execute the production persistence queries with translations for `FOR UPDATE` and transaction syntax; they do not establish live MySQL concurrency behavior. Installed-store/provider verification remains a deployment check.
