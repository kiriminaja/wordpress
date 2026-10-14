# Behavioral testing, E2E first

## Ownership

- **User-visible flows:** isolated Chromium tests in `tests/e2e`, loading the deployed Svelte/Blocks bundles and native event boundaries. Use these for layout, navigation, filter persistence, courier selection, reloads and checkout interaction. `E2E` CI runs once, not once per PHP version. Fixtures reject live requests and never book shipments or take payments.
- **Server invariants:** small ParaTest behavior tests for authorization/nonces, exact IDs, quote binding/expiry, money, database selection, and irreversible booking outcomes. Keep these even when a happy-path browser scenario exists; a mocked Store API boundary does not prove server enforcement.
- **Collaborators:** use Mockery at existing injected repository/SDK seams. Verify externally meaningful calls (`once`, `never`, exact identity) and results, not private execution order. Always clean expectations via `MockeryPHPUnitIntegration`. Do not alias Woo/WordPress globals or mock the implementation being tested. Retain real database fixtures when SQL/HPOS behavior is the subject.
- **Release checks:** aggregate PHP syntax and package integrity gates. One syntax gate still lints every owned PHP file using the current runner's PHP binary, pruning dependencies before traversal.

## Avoid

Do not add PHP tests that read an entire PHP/Svelte/CSS file and assert helper names, class spelling, source offsets or method ordering when runtime/browser coverage exists. DOM text assertions and SQL integration assertions are different: they verify output or query behavior rather than code spelling.

Do not reduce the displayed count by grouping unrelated failures, skipping security scenarios or narrowing source discovery. Consolidate parameter combinations only when they express one contract and give useful case diagnostics. Remove a structural test only after identifying its behavioral replacement; registration/packaging boundaries may still need a small structural guard until an executable counterpart exists.

## Commands

```sh
# Server behavior (ParaTest is the only PHP test runner)
make test
vendor/bin/paratest --configuration paratest.xml --filter ShipmentDetailContractsTest

# Real compiled component checks
bun run frontend:check

# Browser user flows, no WordPress/dev server required
bun run build
cd tests/e2e
bun install --frozen-lockfile
bun run browser:install
bun run test
```

Packaged production changes still require `make zip` before package-parity tests. Browser mocks and isolated PHP fixtures do not establish installed-theme/provider readiness.

## First consolidation pass

- Preserve all 196 owned PHP syntax checks in one gate instead of 197 test cases; avoid provider-time dependency traversal.
- Replace ten repeated subprocess fee/payment tests with four direct Mockery behavior contracts; keep shared fixture files consumed by browser tests.
- Remove three duplicate UI/template source tests and 27 legacy Blocks source-contract methods already covered by executed component/server/browser suites.
- Leave the remaining mixed legacy suites for incremental behavioral migration. This is not a claim that all source assertions are gone or the server suite is now tiny.
