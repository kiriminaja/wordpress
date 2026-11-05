# v3.1: remove repeated work without another loading boundary

Baseline: `ed3c2cbb4ac3333f2828cfe7689ded390e85d88c` on `v3.1`.
This follow-up is proposed on `perf/v31-remove-repeated-work`, targeting `v3.1`.
It removes work rather than introducing more dynamic imports, longer debounces,
persistent data caches, or speculative buyer mutations.

## Overall performance work, including earlier merged changes

These are separate measurements, not additive percentages or a single end-to-end
speedup. Prior changes are already in `v3.1` through #300/#301; this PR adds the
status-count, policy CPU and duplicate-refresh changes below.

| Area | Before / after | Scope / evidence |
| --- | --- | --- |
| Transactions initial JS | 211,611 / 140,966 gzip bytes (-33.4%) | Current-main comparison in `docs/chunk-performance.md` |
| Payments initial JS | 211,611 / 116,492 gzip bytes (-44.9%) | Same production dependency-graph comparison |
| Transaction detail initial JS | 211,611 / 109,766 gzip bytes (-48.1%) | Same comparison |
| Settings root initial JS | 211,611 / 80,662 gzip bytes (-61.9%) | Same comparison |
| Payment read-model SQL | 18 / 5 statements (-72.2%) | Workspace payment fixture |
| Transaction status-count SQL | 7 / 1 statements (-85.7%) | This PR; 45.6–52.5% lower SQLite fixture medians |
| Healthy origin reads, three consumers | 9 / 3 statements (-66.7%); writes 3 / 0 | Buyer origin fixture |
| Account district POSTs, ten street edits | 11 / 1 (-90.9%) | Compiled account runtime request budget |
| Initial map configuration resolution | 2 / 1 calls (-50%) | Classic/Blocks localization fixture |
| Rate-policy CPU | 123.772 / 76.641 ms (-38.1%) per 10,000 checks | This PR; fake-DB CPU fixture |
| Overlapping buyer refresh events | 2 / 1 (-50%) | This PR; phone/pin integrated event fixture |
| Unused settings catalog counts | 2 / 0 | Connected non-root sections and disconnected settings |

Initial JS reductions trade more module requests and deferred first-open work;
total emitted code is not uniformly smaller. This PR adds no new lazy boundary,
longer debounce, or admin route-byte change. Database timings are local SQLite,
policy timings are CPU-only, and client counts are controlled runtime observations.
Live MySQL latency, TTFB, FCP/LCP and checkout completion time remain unmeasured.

## Kept changes

### 1. Consolidate transaction status badges into one database query

`WordPressTransactionListQuery::getStatusCounts()` previously executed seven
separate `COUNT(DISTINCT order_id)` queries, repeating order/transaction joins and
the physical-product predicate. It now executes one conditional aggregate with
seven independent `COUNT(DISTINCT CASE WHEN ... THEN order_id END)` expressions.

Important preserved differences:

- Tabs overlap; they are not exclusive buckets. Orders with duplicate transactions
  or payment rows must not inflate any individual count.
- Only the All tab explicitly restricts order type. Other predicates retain their
  original status/trash differences, rather than incorrectly sharing a global filter.
- Express regular tabs exclude deficits and unresolved phone issues. Issue count
  remains Express-only even when the selected partition is Instant.
- Express processed evidence remains a matching payment and non-canceled transaction.
  A correlated `EXISTS` avoids payment-row fanout without tightening that evidence.
- Instant processed evidence still requires booked lifecycle, a nonempty payment ID,
  and a non-NULL status code; numeric zero remains valid evidence.
- HPOS/legacy order storage and the physical-item predicate remain supported.
- The existing phone-issue resolver/cache is used once. The benchmark warms its
  request-local ID list: it does not measure cold invalid-phone discovery or order hydration.
- No cached counts, schema changes or index migrations are introduced.

The SQLite harness uses the actual production query class against deterministic
in-memory tables: 2,000 orders, duplicates of order/transaction/payment records,
physical/virtual products, invalid phone IDs, varied status/evidence cases, and
explicit indexes on item order, item metadata, product metadata, payment pickup,
and transaction order columns. Three warmups, fifteen measured samples per scope.
Same PHP 8.5.10 / SQLite 3.54.0 on macOS arm64 for both implementations.

| Scope | Queries before / after | Median before / after ms | Median reduction | p95 before / after ms |
| --- | ---: | ---: | ---: | ---: |
| Legacy posts, Express | 7 / 1 | 16.597 / 9.032 | 45.6% | 16.807 / 9.239 |
| Legacy posts, Instant | 7 / 1 | 16.928 / 8.039 | 52.5% | 20.612 / 8.195 |
| HPOS, Express | 7 / 1 | 16.554 / 8.937 | 46.0% | 16.794 / 9.278 |
| HPOS, Instant | 7 / 1 | 16.728 / 8.082 | 51.7% | 18.667 / 8.429 |

Every measured sample has the same counts/hash before and after and the seven-to-one
query budget. Regression goldens cover 32 storage/partition/fixture combinations,
varying duplicate rows, small/large data, and empty/populated phone caches, plus
empty tables and existing issue/list behavior. These are **SQLite query fixture
timings, not production MySQL or end-to-end admin page timings**. Consolidating
aggregates can have a different plan on MySQL/MariaDB; inspect a representative
staging `EXPLAIN`/`EXPLAIN ANALYZE` before claiming production latency improvements.
The deterministic reduction is six round trips per status-count operation (85.7%).

Raw evidence and SQL: `docs/benchmarks/v31-work-removal/transaction-counts-{before,after}.json`.

Reproduce with locked dependencies and PDO SQLite:

```sh
git show ed3c2cbb4ac3333f2828cfe7689ded390e85d88c:inc/Queries/WordPressTransactionListQuery.php > /tmp/transaction-counts-baseline.php
php scripts/benchmark-transaction-status-counts.php /tmp/transaction-counts-baseline.php
php scripts/benchmark-transaction-status-counts.php
```

### 2. Normalize the courier-service policy once per unchanged request context

`SettingRepository::getCourierServiceSelection()` reparsed and filtered the same
JSON policy for every checked rate, even though the underlying setting row was
already request-cached. It now retains one normalized snapshot per repository
instance, keyed by table, row presence, exact raw value and a request-local
invalidation generation.

The method still reads through `getSettingByKey()` on each call. Changed raw
values returned by overrides or mutated cached rows are detected; a missing policy
still returns NULL and a malformed/explicit empty policy still fails closed.
Returned array mutations do not mutate the stored snapshot. `clearCache()` and
courier transaction commit/rollback advance the generation. Table changes invalidate
it. No transient, Redis/object-cache entry or cross-request policy cache is added.

The real repository and catalog execute with a fake wpdb transport, so this is a
**CPU-only benchmark, not database timing**. Dataset: 200 rate/service pairs from 30
selected service codes; 150 enabled decisions; two warmups and fifteen measured
samples. Each sample repeats the 200 checks fifty times (10,000 calls). PHP 8.5.10
is the same before/after. Decision and normalized-policy hashes match exactly.

| Workload per 10,000 calls | Before median CPU ms | After | Reduction | Before / after p95 CPU ms |
| --- | ---: | ---: | ---: | ---: |
| Policy normalization/read | 49.565 | 2.228 | 95.5% | 53.784 / 2.250 |
| Complete rate-policy checks | 123.772 | 76.641 | 38.1% | 151.718 / 86.821 |

Normalized to one 200-rate pass, complete policy checking is about **2.475 to
1.533 ms CPU** in this fixture. This is not a large page-wide latency promise.
Database reads remain **one before and after**: do not misreport this as SQL savings.
The benchmark deliberately keeps the existing live catalog lookup/canonical-service
behavior, so it does not introduce stale entitlement or account context.

Raw evidence: `courier-policy-{before,after}.json` in the same benchmark directory.
Regression checks cover missing, NULL, corrupt and unsupported policy values,
wildcards, exact aliases, raw-value mutation, copy isolation, table separation,
cross-instance clearing, and existing atomic commit/rollback behavior.

```sh
git show ed3c2cbb4ac3333f2828cfe7689ded390e85d88c:inc/Repositories/SettingRepository.php > /tmp/courier-policy-before.php
php scripts/benchmark-courier-policy.php --repository-source=/tmp/courier-policy-before.php
php scripts/benchmark-courier-policy.php
```

### 3. Acknowledge a buyer edit already covered by a checkout refresh

FunnelKit phone handling schedules a refresh 200 ms after context changes. The
Classic pin contact handler independently schedules a refresh at 700 ms for input
or 200 ms for change. A shared phone edit could therefore emit `update_checkout`
twice for the same already-captured contact context.

- On an actual `update_checkout`, the phone handler synchronously captures the
  current widget context and cancels its redundant scheduled refresh.
- The pin handler acknowledges the contact key included by that update. Its later
  timer still processes changes/flushes writes but does not emit another refresh
  for an unchanged contact key.
- Form submit only captures context; it does **not** cancel a refresh as if a
  checkout update had occurred. This preserves the existing submit lifecycle.
- No debounce is lengthened. A new contact key while a prior checkout is pending
  still emits a new refresh. Successful queued pin writes still emit their own
  final refreshes, and unrelated price/insurance refreshes are not suppressed.

Before-edit execution of the integrated production phone+pin harness observed
**two events, at 200 and 700 ms** for a single phone input. The revised case observes
**one at 200 ms** and still one total at 700 ms: 50% fewer plugin refresh events,
with no extra initial wait. The same compiled production controllers/bridges, real
Svelte component and controlled event transport are used. Independent negative
controls removing either acknowledgment fix failed the corresponding regression.

These are **client event counts, not measured WooCommerce AJAX or carrier HTTP
counts**. A real WooCommerce/funnel implementation may already coalesce some events.
Production network savings therefore need live staging observation. No order or
quote validation, API pricing cache duration, or checkout authority is relaxed.

Run:

```sh
bun test --timeout 20000 tests/FunnelKitPhone.test.ts tests/ClassicCheckoutAdapter.test.ts
```

The 55 focused tests cover the shared-event budget, unchanged 200/700 ms deadlines,
silent current-value capture, submit handling, later contacts, price refreshes,
serialized pin mutations, and ordinary non-FunnelKit checkout.

## Rejected experiment: shared tooltip provider

A real Svelte/Bits UI experiment shared one provider across 200 tooltips rather than
allocating one for every cell/action. Mounted window scroll listeners fell 200 to 1
and both versions cleaned up to zero. However, the measured happy-dom mount+flush
median **regressed from 39.740 to 66.623 ms** (p95 74.583 to 82.753 ms). Destroy
medians were 4.949/4.962 ms. This is not evidence of faster client rendering.

The source experiment and its new components were removed. Existing tooltip code
is unchanged. Raw results are retained as `tooltip-experiment-{before,after}.json`
for an audit trail, not a shipped speedup. A real-browser investigation could reach
a different result, but listener count alone does not justify shipping the change.

## Payload and first-use impact

Before/after admin route graphs compare exactly equal in the retained
`admin-{before,after}.json` inventories. No extra chunk, lazy feature, delayed
first-click fetch, or additional loader is introduced in this pass. The client
benefit targets duplicated refresh work, not a claimed bundle-size reduction.

## Verification

- `make zip`: successful; changed PHP and emitted buyer JS match packaged bytes,
  with tests/scripts/docs/source-only frontend files excluded.
- `make test-force`: fresh **1,581 PHP tests, 18,986 assertions**, no failures/errors,
  two environment-dependent WordPress/WooCommerce renderer skips.
- `KIRIOF_TEST_FORCE=1 bun run frontend:check`: final fresh **875 tests passed**,
  plus format, lint, Svelte diagnostics and style checks.
- An earlier full run had three compiled-runtime timeouts while benchmarks ran
  concurrently; those tests passed alone without source changes. Final full
  verification ran without competing benchmarks and passed.
- Final benchmark before/after runs used the same PHP 8.5.10 executable sequentially,
  outside test/build execution. The supported minimum/CI runtime is still PHP 8.2.35.
- `git diff --check`: passed. No server, live browser, external carrier request,
  dependency change, schema migration or persistent cache added.

## Remaining high-value work

1. Profile cold phone-issue discovery separately: the query benchmark intentionally
   warms its IDs and does not eliminate order hydration on a cold cache.
2. Capture real Classic/FunnelKit network traces before claiming the event reduction
   equals fewer HTTP requests. Check input, final checkout, errors and rapid edits.
3. Validate aggregate query plans on the merchant's MySQL/MariaDB data, with both
   HPOS and legacy storage and realistic indexes/concurrency.
4. Buyer Classic/pin scripts duplicate some Svelte runtime under the independent IIFE
   architecture. A shared WordPress-owned runtime could remove bytes without moving
   work to first use, but needs explicit public-global/loading compatibility proof.
5. Valid zero-fee reuse still needs complete authoritative origin/account context
   before relaxing recalculation. Do not trade stale shipping totals for faster tests.
