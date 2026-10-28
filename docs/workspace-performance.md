# Workspace startup and query performance

Measured 2026-10-28 against baseline `3827ae843b1093cf6586311bba0679950e7fc72b`.

## What preloading does, and does not do

This is a WordPress-hosted Svelte 5 application, not SvelteKit. PHP embeds a JSON
bootstrap and a loading placeholder; the entry calls `mount`, not `hydrate`.
The initial page already contains its data, so there is no mandatory browser data
fetch before rendering the list. Switching to hydration alone would not reduce
PHP query time or JavaScript download size.

The workspace previously imported every page and settings section eagerly. It now:

- Imports only the active route/section and its shared dependencies.
- Emits head `modulepreload` links for the active route's static dependency graph,
  using Vite's generated manifest. It does not preload other routes or deferred dialogs.
- Uses a relative Vite base so dynamic JavaScript/CSS URLs resolve under the plugin
  installation, including WordPress installations in a subdirectory.
- Loads route code on link hover/focus, unless Save-Data or a 2G connection is reported.
  Speculative loading never fetches authenticated HTML or caches payment data.
- Retains the current mounted page during soft-navigation fetch/module loading and
  ignores superseded responses before replacing it.
- Retains the boot fallback until mounting succeeds, including visible retry on failure.

Preloading shortens dependency discovery/download waits. It cannot guarantee that
“Preparing the workspace…” appears only during hot reload: cold caches, slow
connections and CPU work can still require it. Hiding the fallback would create
a blank page, not improve performance. Eliminating the cold-start placeholder
requires server-rendered meaningful content, for example an SSR integration or a
PHP-rendered list with progressive enhancement. That is not implemented here.
No dev server or HMR behavior was changed.

## Frontend benchmark

`node scripts/benchmark-workspace.mjs [dist-directory]` traverses the generated
manifest and measures each unique JavaScript file in the entry plus selected route's
static import graph. Gzip uses level 9 independently per file. This includes Svelte
and shared UI dependencies, not just the small entry loader. CSS, images, HTML and
deferred dynamic imports are excluded. Measurements use the same locked dependencies,
Bun 1.4.4, Node 24.21.0, Vite 8.3.0 and Svelte 5.57.1 on macOS arm64.

| Route | Before gzip bytes | After gzip bytes | Reduction | JS requests before / after |
| --- | ---: | ---: | ---: | ---: |
| Transactions | 211,611 | 200,107 | 5.4% | 3 / 33 |
| Payments | 211,611 | 145,627 | 31.2% | 3 / 23 |
| Transaction detail | 211,611 | 137,975 | 34.8% | 3 / 26 |
| Pickup detail | 211,611 | 98,971 | 53.2% | 3 / 17 |
| Settings root | 211,611 | 80,214 | 62.1% | 3 / 14 |
| Account | 211,611 | 95,641 | 54.8% | 3 / 13 |
| Couriers | 211,611 | 87,287 | 58.8% | 3 / 18 |
| Tracking | 211,611 | 74,072 | 65.0% | 3 / 8 |
| Technical | 211,611 | 86,415 | 59.2% | 3 / 17 |

Raw static-graph bytes: before 792,306 on every route; after 683,673 transactions,
474,815 payments, 438,010 transaction detail, 303,953 pickup detail, 238,746 settings,
292,513 account, 258,438 couriers, 223,142 tracking and 257,500 technical.

The trade-off is more requests. Recursive head preloads remove serial discovery
of the active graph, but do not eliminate request overhead. These are proven byte
reductions, **not measured FCP/LCP or time-to-interactive improvements**. In particular,
the transactions route needs a browser benchmark before claiming a speedup.
HTTP/1.1 and high-latency connections may behave differently from HTTP/2 or HTTP/3.

## Backend changes and benchmark

### Payment list

Previously the page, total, each of five status badges and oldest date repeatedly
executed the grouped Express/Instant union. Each displayed Instant payment then
queried its members separately. The final implementation:

- Computes all status counts in one conditional aggregate, without changing global
  badge semantics or pushing list filters below payment grouping.
- Fetches all displayed Instant memberships in one prepared SQL join against page
  identities. SQL comparison retains column collation; returned identities match
  the selected groups. Membership remains complete and ordered, without
  `GROUP_CONCAT` truncation.
- Does not persistently cache mutable payment results.

Benchmark: PHP 8.2.34, SQLite 3.54.0 in-memory, CLI opcache off, macOS arm64.
2,000 payment groups (1,000 Express / 1,000 Instant), three members per group,
6,000 transactions, four equally distributed payment statuses, 20 displayed groups
(10 Express / 10 Instant), no indexes, three warmups and 15 timed iterations.
Seeding, WordPress bootstrap and rendering are outside the timed region.
Median is the middle sample; p95 uses nearest rank.

| Operation | Before median / p95 ms | After median / p95 ms | SQL calls before / after |
| --- | ---: | ---: | ---: |
| Page and membership | 12.131 / 12.342 | 10.727 / 10.903 | 12 / 3 |
| Global status counts | 23.159 / 27.148 | 4.936 / 4.990 | 5 / 1 |
| Oldest date | 4.817 / 5.010 | 4.812 / 4.866 | 1 / 1 |
| Combined | 40.057 / 44.296 | 20.502 / 20.671 | 18 / 5 |

Combined median fell 48.8%; p95 fell 53.3%. Output totals, status counts,
row counts and oldest date matched. The unchanged oldest-date operation is not
claimed as an optimization. These are **SQLite fixture timings, not production
MySQL/MariaDB or end-to-end page timings**. Separate before/after runs are subject
to scheduling noise. The query-count reduction is deterministic for this dataset;
pages without Instant groups skip the membership query entirely.

Reproduce the backend comparison without loading two competing class definitions:

```sh
rtk proxy sh -c 'git show 3827ae843b1093cf6586311bba0679950e7fc72b:inc/Queries/WordPressPaymentListQuery.php > /tmp/payment-list-baseline.php'
rtk php scripts/benchmark-payment-list.php --query-source=/tmp/payment-list-baseline.php
rtk php scripts/benchmark-payment-list.php
```

Options: `--groups=2000 --warmups=3 --iterations=15`.
Raw samples and frontend graph inventories are in `docs/benchmarks/workspace/`.
To rebuild the old frontend separately, use a clean checkout/worktree of the baseline
with locked dependencies, then `bun run build:admin --manifest workspace-manifest.json`.
Run the current `scripts/benchmark-workspace.mjs` against that dist directory and
compare with the current `bun run benchmark:workspace` output. Do not compare the
loader file alone or mix source revisions with old generated assets.

### Settings

Product readiness runs two catalog-wide `COUNT(DISTINCT ...)` queries. It was
prepared before dispatch, even when the selected section did not consume it.
The settings template now requests lightweight shared data and lets the connected
root bootstrap calculate readiness only when needed. Existing callers of
`prepare()` retain the original eager default.

Deterministic call-budget regression tests establish:

- Account/couriers/tracking/technical and disconnected settings: **2 to 0** catalog counts.
- Connected root: still **2**, calculated once.
- Connected unknown-section fallback: **4 to 2**, without duplicated root preparation.

These are avoided-query counts, not measured settings-page milliseconds.

## Remaining query hotspots and live validation

The transaction list still issues seven overlapping global status-count queries
in `WordPressTransactionListQuery::getStatusCounts`, repeating joins and shippable-item
checks. They are not mutually exclusive categories. Combining them requires
preserving HPOS/legacy storage, DISTINCT order semantics, Express payment evidence,
Instant booking evidence and phone-issue exclusions. It was not changed speculatively.
Cold-cache invalid-phone discovery also scans eligible transactions in batches and
can call `wc_get_order()` for each candidate, not merely displayed rows.

Next live benchmark should use a representative staging database and authenticated
browser, without exposing credentials or customer data in artifacts:

1. Capture Query Monitor/slow-log SQL counts, cumulative DB time and TTFB separately
   for transactions, payments and settings; distinguish warm/cold object caches.
2. Run `EXPLAIN ANALYZE` (or the database's supported `EXPLAIN` equivalent) on grouped
   payment and transaction queries. Record rows examined, temporary tables/filesorts
   and index use before recommending composite indexes or migrations.
3. Measure 20+ cold-cache and warm-cache browser runs with fixed CPU/network throttling.
   Record FCP/LCP, mount-ready time, long tasks and transfer bytes; compare medians/p95.
   Verify preload URLs match actual requests and are reused, including subdirectory installs.
4. Check Save-Data, keyboard-focus preloads, import failures/retry, browser back/forward,
   rapid navigation and real payment changes. Verify no stale payment HTML is cached.

No local WordPress/dev server was started. DOM routing tests replace component
imports/mount boundaries and are not browser E2E or real component render benchmarks.
The existing component-runtime suites separately exercise compiled production components.

## Reference mechanisms

Final verification:

- `make zip`: successful; manifest references exist in the archive, modified PHP
  matches packaged sources, and development/test/documentation files are excluded.
- `make test-force`: freshly executed, 1,455 tests and 17,009 assertions, no failures
  or errors; two environment-dependent WordPress/WooCommerce renderer tests skipped.
- `KIRIOF_TEST_FORCE=1 bun run frontend:check`: freshly executed, 772 tests passed,
  plus formatting, lint, Svelte diagnostics and style-contract checks.
- Routing runtime checks cover active-route imports, hover/focus without page fetches,
  Save-Data/2G suppression, loading fallback through successful mount, visible
  error/retry, existing-shell retention and stale-fetch rejection. PHP integration
  checks actual preload markup, the versioned entry URL and subdirectory URLs.
- `git diff --check`: passed.

- [Vite backend integration](https://vite.dev/guide/backend-integration): manifest
  traversal and recursive static-dependency modulepreload links.
- [Vite build options](https://vite.dev/config/build-options): relative base resolution
  for dynamic import preloads and generated manifests.
- [Svelte imperative API](https://svelte.dev/docs/svelte/imperative-component-api):
  `mount` versus `hydrate`.
