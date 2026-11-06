# Onboarding loading performance

Branch: `perf/onboarding-loading`, based on `origin/main` at `84c242a1`. Settings PR #316 remains separate; its required-step extraction overlaps this branch and should be reconciled when merging.

## Backend

The onboarding bootstrap contains account, address, couriers, and shipping steps only. Previously, constructing those steps also calculated optional product volumetric readiness and tracking-page status. The page now requests required steps directly. Full `get_steps()` output and the default first-incomplete behavior remain available to optional notices.

The isolated production-page fixture records eight SQL calls before and six after, including a reduction from two catalog aggregate queries to zero and one tracking lookup to zero. Profile requests are unchanged. No persistent cache or authorization changes were introduced. Connected onboarding still waits for its existing profile request; that network latency has not been removed.

`OnboardingBackendLoadingRuntimeTest` compares the production page/service with the retained baseline across setup stages, profile outcomes, optional notices, redirect flags/destinations/exclusions and unauthorized access. Baseline payload comparisons exclude only the four newly added loading/retry presentation strings. Credentials are not exposed in the bootstrap.

## Frontend

| Initial onboarding static JavaScript | Before | After |
| --- | ---: | ---: |
| Uncompressed bytes | 375,497 | 310,019 |
| Per-file gzip bytes | 124,072 | 104,151 |
| JS files | 26 | 21 |

This removes 19,921 gzip bytes (16.1%) and five initial JS requests. The measurements traverse the onboarding entry and every static import, not the entry file alone. Raw inventories are retained in `benchmarks/onboarding-loading.json`.

Courier selection and subdistrict search implementations now load only when their respective UI is rendered. Selection, address state and save validation remain owned by the parent. Google-only map provider imports run inside the existing Google address-map branch, with generation checks after asynchronous loading. Leaflet follows its existing path.

Deferred controls show translated loading status and explicit error/retry controls. Disposed hosts ignore late completions. Retry restores focus to the loaded control only if focus has not moved elsewhere. This is a code/runtime review, not a live browser screenshot or screen-reader certification.

## Trade-offs and validation

- The first opening of address search or courier selection can wait for its deferred JS. Deferred bytes are shifted, not eliminated from a complete onboarding journey.
- External map scripts/styles, HTML, images and API latency are outside the JS table. No FCP/LCP, live MySQL, or production page-load percentage is claimed.
- Existing workspace route byte/request budgets pass; no budget was relaxed.
- Full PHP validation: 1,611 tests, 19,651 assertions, two skips.
- Focused backend/navigation validation: nine tests, 210 assertions.
- `bun run frontend:check:all` passed. Named scripts may reuse content-matched successful test receipts; the command is not a claim that every cached suite executed freshly.
- Svelte checking reports zero errors/warnings. Package build and final source/build parity are checked separately.
- No local WordPress server was started.
