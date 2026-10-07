# TesterArmy e2e business-flow pilot

Optional, isolated test project using [tester-army/e2e](https://github.com/tester-army/e2e).
Node >=24.8 (or >=22.22.3), Bun, PHP, and the root Composer dependencies are required.

```sh
cd tests/e2e
bun install --frozen-lockfile
bun run browser:install
cd ../.. && node scripts/build-buyer.mjs && cd tests/e2e
bun run test
```

Versions are pinned in this project's package/lock files independently of the plugin frontend. Telemetry is disabled. No model credentials or agent steps are configured.

## What it actually tests

- Real Chromium runs the generated buyer state/Classic/Blocks entries and real Svelte/Bits UI controls with jQuery 3.7.1, and the unmodified test-only WooCommerce 9.9.5 country-select script (see fixtures/README.md).
- Seller-disabled Instant adds no pin UI, map or location request.
- Pin mutation uses only canonical address/pin fields, leaving the original district input visible.
- Editing the delivery address invalidates the pin and blocks an Instant checkout attempt.
- District search exercises array/nested/bounded envelopes, minimum input, pending/error/empty distinctions, cancellation and stale response protection, native ID/hidden label synchronization and a single clear change. A typed `sari harjo` request renders production PHP unified-address service fixture results.
- Woo native ID/US state options, text and hidden state replacement round trips retain one Svelte selector wrapper, while country controls keep Select2. District/province geometry and typography are compared under adverse theme input CSS, with desktop/mobile screenshots and keyboard search.
- The runner also executes existing PHP production-service fixtures: accepted QRIS stays unpaid and cannot book twice; uncertain outcomes retain duplicate protection and do not resubmit.

The browser HTML, geolocation, Leaflet boundary, and AJAX response are isolated fixtures. Requests are intercepted for `https://fixture.test`, no app server is started, and no WordPress instance, live shipment, payment, credential, or customer data is used. This is **not** installed-WooCommerce/theme E2E coverage. The business fixtures execute production services with fake WordPress/API boundaries, not a real HTTP backend.

Reports and browser traces are written to ignored `.e2e/`. This directory is excluded from the distributable through the existing `tests/` package exclusion.

## Testing strategy

Use this to complement—not wholesale replace—Bun/ParaTest. Keep exhaustive authorization, rollback/CAS races, fee calculations, callback idempotency and API-shape tests in fast deterministic suites. Add real-browser journeys here where they validate business outcomes across user interactions, network requests and persisted state.

A later installed-store target needs an authorized disposable staging database, test user/product fixtures, provider API stubs, explicit cleanup, and a no-live-booking guard. Classic and Blocks should each have a journey. Use deterministic locators/assertions first; optional `agent.act` needs an explicitly selected local model/subscription/API provider and a privacy review because rendered app data may reach that provider.

The framework is pre-1.0. Upgrade core/web engine together only after checking compatibility, and never allow agent steps to submit shipments or payments on production.

## Buyer runtime fixtures

`fixtures/buyer-source.ts` maps historical script names to deployed `assets/buyer/dist` entries. Build these assets before running the browser suite. Only native district/shipping/payment/event adapters are read from `assets/buyer/js`; archived pin, state, selector, and Blocks implementations must not run. Dropdown content is portaled to the body. Native fallback fixtures deliberately omit the enhancement entry, rather than depending on a missing Choices library. Source buyer CSS and generated Classic component CSS are both loaded; local courier artwork requests must match the canonical `/assets/buyer/img/couriers/` path.
