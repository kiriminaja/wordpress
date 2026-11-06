# Settings redirect-gate performance

The Settings request runs `OnboardingSetupStateService::is_required_complete()` before rendering. Previously, that method built the entire checklist, including optional product readiness and tracking-page status. Product readiness executes two catalog aggregate queries, although neither optional step affects whether the request redirects to onboarding.

Required-step evaluation now builds only account, address, couriers, and shipping state. `get_steps()` adds the optional steps when a caller requests the full checklist. Results are retained only in the service instance, as before; no persistent cache or SQL predicates changed.

## Measured work removal

Baseline: `fac00656003d700fe33b93712c7c1423d8bb16a9`.

| Redirect-gate work | Before | After |
| --- | ---: | ---: |
| Catalog aggregate queries | 2 | 0 |
| Tracking-page lookups | 1 | 0 |

The configured Settings overview still computes fresh product readiness for its visible summary. With required onboarding complete, its catalog-query count therefore falls from four to two; Settings subsections fall from two to zero. These counts exclude unrelated plugin or WordPress work.

`tests/fixtures/settings-required-readiness-runtime.php` exercises the production service with isolated WordPress boundaries and a query-recording database double. Six before/after cases cover complete required setup and missing account, address, couriers, shipping method, or shipping locations. Full checklist arrays, required completion, and the first incomplete step match the baseline in every case. Raw comparisons are retained in `benchmarks/settings-required-readiness.json`.

Regression tests also verify that requesting the full checklist after redirect gating still evaluates optional status, and repeated calls on that service instance do not repeat those reads.

## Validation and limitations

- Fresh ParaTest run: 1,610 tests, 19,498 assertions, two skips.
- Focused run: 33 tests, 234 assertions.
- Changed PHP files pass syntax checks; `git diff --check` passes.
- `make zip` completed. The archived service matches source, and tests, `AGENTS.md`, and `paratest.xml` are excluded.
- Repository-wide `composer phpstan` fails with over 1,000 reported errors. Static analysis is not a passing validation for this change.
- No live WordPress server, production MySQL benchmark, or browser timing was run. The improvement removes known work; it does not establish a page-load latency percentage or prove that this was the only cause of slow Settings requests.

## Client-side dependency removal

SettingsRoot now uses a native button and anchor for the two connection controls instead of importing the bits-ui Button primitive. The button keeps its click handler, disabled state and explicit button type; the help link keeps its URL, external target and noopener/noreferrer protection. Existing styling is unchanged.

The full initial Settings static JavaScript graph falls from 80,662 to 80,160 gzip bytes (502 bytes, 0.6%), and from 15 to 14 requests. Measurements include the workspace entry and transitive static imports, with per-file gzip level 9; this is not a browser latency measurement. Raw inventories are in `benchmarks/settings-client-native-controls.json`.

A deferred-toolbar experiment saved more Settings bytes but increased transactions/payments bytes and request counts, violating the existing request budget. That experiment and its temporary components/tests were discarded. No deferred toolbar behavior or new loading states remain.

Validation: the 11 production chunk-graph tests passed, including unchanged budgets for other pages. `bun run frontend:check:all` completed successfully, including formatting, lint, Svelte checking and its configured test suites; individual named test scripts may reuse content-matched successful receipts. No live browser timing or UI screenshot review was performed.
