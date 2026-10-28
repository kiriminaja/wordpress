# Buyer-side performance follow-up

Measured 2026-10-28 against `0973754cb45d16b9263e6c6210969f8df8825a23`, the
workspace performance branch after merging main. Changes are on
`perf/buyer-checkout-query-performance`, separate from PR #300. No new persistent
cache, price-cache TTL extension, canonical-address bypass or checkout mutation
prefetch is introduced.

## Implemented and measured

### 1. Read the default pickup origin without a repair UPDATE

`ShipmentLocationService::getDefaultLocation()` previously ran
`ensureDefaultExists()` before reading the default. Even with a healthy default,
that executed a SELECT, an UPDATE clearing other default flags, then another SELECT.
Separate service instances are constructed by shipping, default map coverage and
validation consumers, so repeated reads can also repeat writes.

The method now reads the active default first and invokes repair only when missing.
The existing object-local cache remains unchanged. No origin cache is shared between
services or persisted across requests. Explicit package origins are still authoritative.

The benchmark executes the real service and repository on SQLite, substituting only
wpdb transport and minimal WordPress functions. Dataset: 100 deterministic locations,
active default ID 37, five warmups and 30 measured samples for each case. Each sample
checks every returned row field against the seeded expected row. Before/after result
SHA-256 values match. Times exclude seeding, WordPress/WooCommerce bootstrap and network.
Runtime: PHP 8.2.34, SQLite 3.54.0 in-memory, macOS arm64.

| Case | Before SQL (reads / writes) | After SQL (reads / writes) | Before median / p95 ms | After median / p95 ms |
| --- | ---: | ---: | ---: | ---: |
| Three independent services, one read each | 9 (6 / 3) | 3 (3 / 0) | 0.132084 / 0.137459 | 0.024854 / 0.031250 |
| One service, three reads | 3 (2 / 1) | 1 (1 / 0) | 0.043937 / 0.044333 | 0.007500 / 0.008333 |

These are **sub-millisecond SQLite fixture timings, not production MySQL or full
checkout request timings**. Avoided SQL/writes are the main result. Real database
latency, indexes, table size, concurrent updates and object cache configuration can
change timing substantially. The three-service case is a representative consumer
composition, not an assertion that every checkout request constructs exactly three.

Regression coverage executes the production repository with SQLite for healthy
reads, object-local reuse, independent services, selected origins, missing defaults,
inactive rows, empty origins, legacy seeding and WooCommerce store fallback. Failed
repair returns cached null rather than attempting more seed writes in that object.

**Intentional boundary:** ordinary reads no longer repair pre-existing duplicate
active defaults. They deterministically return the lowest-ID active default with no
write. Default creation/promotion still enforces uniqueness through `setDefault()`;
explicit `ensureDefaultExists()` still repairs duplicates. A regression exercises the
read followed by explicit repair. No migration or background repair was added.

Reproduce:

```sh
git show 0973754cb45d16b9263e6c6210969f8df8825a23:inc/Services/ShipmentLocationService.php > /tmp/buyer-origin-baseline-service.php
php scripts/benchmark-buyer-origin.php --query-source=/tmp/buyer-origin-baseline-service.php
php scripts/benchmark-buyer-origin.php
```

Options: `--warmups=5 --samples=30`. Current shorthand: `bun run benchmark:buyer-origin`.
Raw SQL, per-sample counts/times, source hashes and parity hashes are retained in
`docs/benchmarks/buyer/origin-before.json` and `origin-after.json`.

### 2. Reuse map configuration within one checkout localization

Both Classic and Blocks registered two public configuration objects. The buyer
configuration already contains `map_checkout_config()`, but separate map localization
called it again, repeating package/default-origin coverage resolution and filters.

Both outputs now reuse the same map configuration from that invocation:

| Registration scenario | Before map-config calls | After map-config calls |
| --- | ---: | ---: |
| Initial Classic or Blocks localization | 2 | 1 |
| Fully localized repeat | 0 | 0 |
| Only buyer configuration missing | 1 | 1 |
| Only map configuration missing | 1 | 1 |
| Localization reset with new package context | 2 | 1 |

The fixture executes the production registration methods and parent buyer-config
builder. A map-config override counts calls and supplies changing context markers;
WordPress transport and ancillary services are isolated. Before/after localized
payloads are strictly equal for the deterministic configuration. These are **method
call budgets, not measured SQL counts or page timing**. Filters now run once when both
outputs are produced, so intentionally stateful map filters will also execute once.

There is no cross-call memoization: after localization data is reset, a different
package context produces fresh coverage. Account configuration is unchanged. Partial
localization, repeated registration, translations, coupon strings and native Classic
checkout flags are covered by `BuyerConfigPerformanceRuntimeTest`.

Reproduce call-count comparisons with the existing fixture and a saved baseline:

```sh
git show 0973754cb45d16b9263e6c6210969f8df8825a23:inc/Base/Enqueue.php > /tmp/buyer-enqueue-baseline.php
php tests/fixtures/buyer-config-performance.php blocks /tmp/buyer-enqueue-baseline.php
php tests/fixtures/buyer-config-performance.php blocks
php tests/fixtures/buyer-config-performance.php classic /tmp/buyer-enqueue-baseline.php
php tests/fixtures/buyer-config-performance.php classic
```

Full fixture outputs are retained as `config-{blocks,classic}-{before,after}.json`
under `docs/benchmarks/buyer/`.

### 3. Stop account street edits from repeating postcode queries

The native account shipping-address bridge already calculated whether country or
postcode changed, but restarted district lookup after every full-address edit.
Each restart cleared settled options or aborted pending verification. A street edit
can invalidate a map pin, but does not change postcode-scoped district choices.

The bridge now starts district lookup only when its normalized country/postcode
identity changes. Full-address edits still update state, clear the old pin and
publish the current snapshot. Initial canonical verification, explicit retry,
postcode changes and country transitions are unchanged. No checkout state writes
are introduced into the account form.

Using the real compiled production Svelte account entry in the existing DOM harness:

- Initial verification plus ten street edits, each separately advancing the 250 ms
  debounce: **11 district POSTs before, 1 after**.
- Pending verification survives unrelated street edits without abort/restart; its
  original network deadline and eventual canonical response still apply.
- Settled options and selection remain usable while the old pin is invalidated.
- Lookup-key changes requery and clear selection/pin; error retry remains explicit.
- Tests monitor and assert zero checkout dispatches for these account scenarios.

The baseline request-budget scenario was executed against the original compiled
entry before changing the bridge. The optimized budget is a passing regression in
`tests/AccountShippingDestination.test.ts`, test named
`account district request budget for ten separately debounced street edits`.
Run `bun test --timeout 20000 tests/AccountShippingDestination.test.ts`.
Timers and responses are controlled by the harness. These are **HTTP call counts,
not real network, upstream API or database timings**: the server's exact-postcode
cache may already prevent many upstream API calls.

## Buyer query audit: what already works

- `SettingRepository::getSettingByKey()` uses a static request-local cache, including
  missing rows. Repeated repository construction does not itself imply repeated SQL.
- `CheckoutServiceFactory::districtSearch()` caches successful, nonempty exact
  postcode results for 300 seconds. Free text, empty and failed searches remain live.
- Express pricing uses `PricingCacheService` request/session/transient reuse for
  compatible payloads. Repeated calculations do not necessarily mean repeated API calls.
- Instant quote reuse is bounded by a fingerprint and expiry. Final validation
  reconstructs context and must not be removed just to lower a benchmark number.
- Store API extension handling already avoids a redundant `calculate_totals()` call.

The [WordPress transient API](https://developer.wordpress.org/reference/functions/get_transient/)
can use external object cache or options. Repeated transient calls are not automatically
one SQL query each, so transient counts alone are insufficient evidence of database cost.

## Ranked next opportunities, not implemented or timed

1. **Fee recalculation for valid zero fees.** `CheckoutController` treats zero COD and
   insurance as a reason to recalculate even with a matching fee context. A successful
   zero result should be distinct from a missing calculation. Before extending reuse,
   include authoritative origin, destination postcode and account/credential context.
   Preserve final validation; measure calculation calls and external pricing calls
   separately on cold and warm passes.
2. **Courier-service policy parsing per returned rate.** SQL reads are already cached,
   but each `isCourierServiceEnabled()` reparses/filters the policy and canonical
   service matching may repeatedly build the available catalog. Benchmark complete
   rate filtering first. A request-local normalized snapshot needs same-request
   invalidation after policy writes/rollback; avoid a global policy cache.
3. **Cart/catalog asset scope.** Block Cart currently admits the full Blocks checkout
   bundle and map provider, and shop/product pages receive broad legacy dependencies.
   Separate Cart-required coupon/presentation behavior from map/district behavior only
   after proving native shipping, third-party templates and tracking still work.
4. **Buyer build architecture.** Independent IIFEs use `inlineDynamicImports: true`.
   Adding `import()` alone cannot defer network downloads. Measure duplicated shared
   code and provider payloads before switching to module/chunk architecture or separate
   WordPress-owned handles.
5. **Global address identity transient.** Resolution repeatedly reads/filters up to 500
   identities, and remembering rows rewrites the transient. Profile with and without a
   persistent object cache; account for expiry and concurrent writers before memoizing.
6. **Remaining admin transaction queries.** Seven overlapping status-count queries
   still repeat order/item predicates. Consolidation must retain HPOS/legacy storage,
   DISTINCT-order semantics and non-exclusive classification. See the workspace report.

## Production evidence still needed

No local WordPress/dev server was started and no live buyer/carrier requests were made.
There are no measured TTFB, FCP/LCP or shipping-rate-ready claims in this follow-up.

On representative staging data, compare Classic checkout, Blocks checkout, Block Cart,
account shipping editor and tracking separately. Capture cold/warm object/session caches,
SQL counts/cumulative DB time, writes, external API counts, HTTP requests and p50/p95
rate-ready time. Use database-supported `EXPLAIN`/`EXPLAIN ANALYZE` before recommending
indexes. Verify origin changes, account changes, postcode changes, free shipping,
COD, zero insurance, multiple packages, quote expiry and concurrent checkout updates.

Local PHP is 8.2.34, below main's newly declared minimum 8.2.35. Local verification
results are reported with that caveat; the installed runtime was not changed.

## Final verification

Review/sync follow-up: merged `main` through `72875e9d` (#302) via the updated
workspace branch, including its collation, alias, mount-ownership and fresh-checkout
test fixes. The buyer benchmark is also CLI-only and reproduction commands no
longer require rtk. Fresh combined-branch verification: 1,478 PHP tests, 17,285
assertions, two renderer skips, and 798 frontend tests passed plus static/style
checks. ZIP parity and development-file exclusions passed. The benchmark CLI smoke
run still executes successfully. Historical verification below predates this sync.

- `make zip`: successful. Changed PHP matches packaged bytes; benchmark scripts,
  documentation, tests and source-only frontend files remain excluded.
- `make test-force`: freshly executed, 1,461 tests, 17,120 assertions, no failures
  or errors, two environment-dependent WordPress/WooCommerce renderer skips.
- `KIRIOF_TEST_FORCE=1 bun run frontend:check`: freshly executed, 784 tests passed,
  plus formatting, lint, Svelte diagnostics and style-contract checks.
- Final origin samples retain matching expected-result hashes; all deterministic
  localized configuration payloads compare equal to baseline.
- `git diff --check`: passed. No servers started, commits created or PR changes made.
