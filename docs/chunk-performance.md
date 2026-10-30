# On-demand dialog and calendar chunks

Measured against `99c435b4bda321ad99b6a1a92bca78e7afd12747` on 2026-10-28.
Follow-up branch: `perf/reduce-initial-workspace-chunks`. This is separate from
PRs #300/#301; no commit or PR was created for this follow-up.

Release integration update: committed as `6361997d`, then included in PR #301 for
`v3.1` together with current main `b1b05a3a` and the already squash-merged #300.
The sentence above describes the original measurement stage, before that commit.

## Comparison with current main

Built `origin/main` at `b1b05a3a` in an isolated temporary checkout with the same
locked dependencies and production environment as the integrated candidate.
Inventories are `main-comparison-{before,after}.json` beside the original evidence.

| Initial route JS | Main gzip bytes | v3.1 candidate | Reduction | Requests main / candidate |
| --- | ---: | ---: | ---: | ---: |
| Transactions | 211,611 | 140,966 | 33.4% | 3 / 33 |
| Payments | 211,611 | 116,492 | 44.9% | 3 / 21 |
| Transaction detail | 211,611 | 109,766 | 48.1% | 3 / 19 |
| Settings root | 211,611 | 80,662 | 61.9% | 3 / 15 |

This comparison includes #300 route splitting and the later deferred features;
the original table below isolates the incremental chunk commit. Increased request
counts and deferred first-open costs still apply; no browser speedup is inferred.
Fresh integrated verification: 1,577 PHP tests / 18,781 assertions (two renderer
skips), 867 frontend tests, package/source parity and manifest checks passed on
local PHP 8.5.10. The supported minimum/CI baseline remains PHP 8.2.35.

## Changes

- Transactions, transaction detail and payments no longer import closed action,
  print, pickup, processing or payment dialogs into their initial route graphs.
  Literal dynamic imports load implementations on first intent.
- `DeferredDialog` supplies an immediate cancellable loading dialog and explicit
  retry on import failure. It retains the loaded child for later reopening with
  current props. Host destruction invalidates pending completion. Existing child
  request/polling/close behavior remains in its implementation.
- Date filtering retains its immediate popover trigger; date parsing and the real
  range-calendar implementation load only when opened. Reopening creates a fresh
  panel from current props, while unrelated parent changes do not overwrite a draft.
- Payments auto-refresh now pauses from initial dialog intent, including the import
  wait and error state, until the payment/reschedule dialog closes. Transactions
  already paused for their dialog states.
- Existing PHP-translated loading/error/retry/close labels are supplied by the
  page bootstraps. No new dependencies or custom vendor chunk configuration.

These are true network boundaries in the admin ESM build. Merely wrapping a static
import in `{#if open}` would not defer downloading its code. Vite supplies async
CSS/dependency preloading when a dynamic import is requested. The PHP head preload
resolver continues following static imports only, so it does not defeat the new
boundaries by preloading closed dialogs or calendar panels.

## Measurement

The same locked Vite 8.3.0 / Svelte 5.57.1 dependencies were used before and after,
with Node 24.21.0 / Bun 1.4.4 on macOS arm64. Gzip level 9 is applied independently
to each emitted file. Initial totals traverse each unique file in the workspace
entry plus selected route's static graph. This includes shared runtime/UI chunks,
not just the selected page file. First-open measurements add the selected feature's
static graph to that route, deduplicating files already loaded.

Raw inventories, CSS, largest chunks and first-open totals are retained in
`docs/benchmarks/chunks/{before,after}.json`. These are reproducible build-byte
measurements, **not browser FCP/LCP, CPU, or time-to-interactive benchmarks**.

| Route | Before initial JS gzip bytes | After | Reduction | JS requests before / after |
| --- | ---: | ---: | ---: | ---: |
| Transactions | 200,130 | 140,966 | 29.6% | 33 / 33 |
| Payments | 145,650 | 116,492 | 20.0% | 23 / 21 |
| Transaction detail | 137,998 | 109,766 | 20.5% | 26 / 19 |
| Pickup detail | 98,994 | 99,218 | -0.2% | 17 / 17 |
| Settings root | 80,237 | 80,662 | -0.5% | 14 / 15 |
| Account | 95,664 | 95,890 | -0.2% | 13 / 13 |
| Couriers | 87,310 | 88,539 | -1.4% | 18 / 21 |
| Tracking | 74,095 | 74,364 | -0.4% | 8 / 8 |
| Technical | 86,438 | 87,142 | -0.8% | 17 / 18 |

Negative reductions are increases: shared-chunk reshuffling slightly regresses
unchanged routes, most notably couriers (+1,229 gzip bytes and three requests).
No uniform improvement is claimed. Initial route CSS is unchanged: 11,588 gzip
bytes on transactions and 11,439 on the other measured routes. Independently
enqueued WordPress/design-system styles are outside the route manifest metric.

The largest individual JavaScript chunk changes from 137,141 raw bytes
(TransactionsApp, 33,801 gzip) to 125,721 raw bytes (shared scroll-lock chunk,
40,459 gzip). That is an **8.3% raw-size decrease**, but the new largest chunk has
more gzip bytes than the old largest chunk because its content differs. The
transactions page chunk itself becomes 80,518 raw / 19,263 gzip bytes; the full
initial route remains the meaningful user-facing comparison.

Total emitted admin JavaScript grows from 858,715 to 876,563 raw bytes (+2.1%) and
259,669 to 278,636 independently gzipped bytes (+7.3%). Splitting adds boundaries,
load/error handling and separate compression overhead; it does not remove all
feature code. Users opening every feature may download more overall.

### First-open cost, from a fresh route

| Interaction | Additional gzip bytes | Additional JS requests | Route plus feature gzip bytes |
| --- | ---: | ---: | ---: |
| Transactions: Instant process | 28,673 | 9 | 169,639 |
| Transactions: request pickup | 18,610 | 6 | 159,576 |
| Transactions: action dialog | 16,732 | 4 | 157,698 |
| Transactions: calendar | 18,868 | 1 | 159,834 |
| Payments: Scan to Pay | 8,815 | 4 | 125,307 |
| Payments: Instant Scan to Pay | 9,584 | 5 | 126,076 |
| Payments: calendar | 19,640 | 3 | 136,132 |
| Transaction detail: action | 26,849 | 11 | 136,615 |

These are separate first-use scenarios, not numbers to add together: features
share dependencies. Retained/imported modules are reused on subsequent opening.
First opening can wait for the network; the immediate loading dialog remains
closable with Escape/Cancel and supports retry instead of silently failing.
Payment deep links and automatic deficit opening still trigger the relevant import.

Reproduce after installing locked dependencies:

```sh
bun run build:admin
node scripts/benchmark-workspace.mjs
```

To compare with the baseline, build a separate clean checkout of the baseline
revision, then run the current benchmark script against that checkout's admin
`dist` directory. Do not overwrite this worktree's source to build the old revision.
The script's original route-byte fields remain available; it now additionally
reports route CSS, first-open additional graphs, total emitted JS and largest chunks.

## Buyer bundle audit, not changed in this pass

Current buyer IIFE outputs measured raw/gzip bytes:

| Entry | Raw bytes | Gzip bytes |
| --- | ---: | ---: |
| account-shipping | 183,588 | 59,168 |
| classic selectors | 166,989 | 53,013 |
| blocks | 84,868 | 29,643 |
| pin | 58,006 | 21,396 |
| tracking | 35,864 | 13,913 |
| admin-maps | 13,287 | 5,038 |
| state | 10,903 | 4,082 |

Buyer outputs are built independently as IIFEs with `inlineDynamicImports: true`.
Adding `import()` without changing that architecture will not create deferred
network chunks. WordPress supplies React/Blocks and the selected provider's SDK;
these remote/local dependencies are not included in the table. Registered aliases
pointing at the same file must not be counted as duplicate downloads.

Promising next work, requiring compatibility evidence:

1. The state bridge exports a full map/session implementation, while the observed
   legacy shipping-option consumer only needs `kiriofShippingSelection`. Audit the
   public globals and third-party consumers before pruning them.
2. Classic selectors and the Instant pin bridge can coexist and independently embed
   Svelte runtime code. Consolidate/externalize shared runtime only with explicit
   WordPress loading/order contracts; do not force map code onto non-Instant checkout.
3. The registry includes both Leaflet and Google adapter implementations. A deliberate
   provider entry/loader design could defer the unused adapter, but static adapters
   must not be confused with the much larger separately loaded provider SDK.
4. Block Cart receives checkout/map code; split cart-required coupon/presentation
   behavior only after verifying cart shipping controls and third-party templates.
5. Shared tooltip/dialog/scroll-lock UI remains a large admin dependency. Inspect
   used Bits UI primitives and runtime browser costs before forcing vendor chunks.
   Arbitrary chunk-size caps/manual vendor bundles can increase requests or eager
   coupling without reducing the full route graph.

## Verification and limits

- `make zip`: successful. Packaged PHP matches source; every manifest JS/CSS/asset
  is present, and tests/scripts/docs/source-only frontend files stay excluded.
- `make test-force`: fresh, 1,478 tests, 17,285 assertions, no failures/errors,
  two environment-dependent WordPress/WooCommerce renderer skips.
- `KIRIOF_TEST_FORCE=1 bun run frontend:check`: fresh, 826 tests passed, plus formatting,
  lint, Svelte diagnostics and style-contract checks.
- Real compiled deferred-dialog tests cover loading/cancel, retry, late resolution,
  current props, retained reopening, Escape and focus restoration through handoff.
- Real calendar tests cover delayed/failed loading, closing/reopening, draft behavior
  and the existing date selection semantics.
- Production payment page + DeferredDialog + AutoRefresh tests cover three row-action
  flows and two payment deep links. Pending/loaded dialogs pause refresh; close resumes
  it. Their module transport and unrelated display boundaries are controlled fixtures.
- Build tests create isolated fresh production Vite output, assert dialogs/calendars
  stay outside initial static graphs, validate emitted dependencies, and enforce
  initial gzip/request budgets. They explicitly set `NODE_ENV=production`: inheriting
  Bun's test environment would measure development instrumentation rather than release bytes.
- Local PHP remains 8.2.34, below required/CI 8.2.35. No server or live browser session
  was started. Browser network/CPU timings, HTTP/1 vs HTTP/2 behavior and real WP
  keyboard/accessibility E2E remain unmeasured here. No new database optimization.

References: [Vite async loading](https://vite.dev/guide/features#async-chunk-loading-optimization),
[Svelte component lifecycle](https://svelte.dev/docs/svelte/svelte).
