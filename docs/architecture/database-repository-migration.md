# Database Repository Migration Plan

## Status

Accepted architecture direction. No runtime migration has started.

## Decision

Use repository contracts as the stable boundary for database access. Repository implementations may use `$wpdb`, WooCommerce data stores, or WordPress APIs depending on which mechanism owns the data.

The repository pattern is the target architecture. Replacing `$wpdb` or introducing another database abstraction is outside this plan.

## Goals

- Remove database queries from controllers, services, and templates.
- Expose persistence operations through small contracts based on application use cases.
- Keep WooCommerce HPOS and legacy order storage behavior compatible.
- Make database behavior testable without requiring every caller to construct a concrete repository.
- Keep complex WooCommerce reporting queries as prepared SQL when that remains the clearest implementation.

## Non-goals

- Replacing `$wpdb` with an ORM or query-builder dependency.
- Creating a generic repository base class for unrelated domains.
- Introducing a dependency injection container.
- Hiding schema migrations behind repository contracts.
- Forcing cross-table reports or WooCommerce-owned storage through a generic CRUD repository.
- Changing public plugin behavior as part of the structural migration.

## Current state

The plugin already has classes under `inc/Repositories`, but the current boundary is incomplete:

- Controllers and services instantiate concrete repositories directly.
- Templates contain list, filter, count, and join queries.
- `TransactionRepository` combines transaction persistence, reporting, HPOS detection, WooCommerce joins, and SQL fragment generation.
- `ShipmentLocationRepository` combines persistence with limits and default-location rules.
- Remote API clients are named repositories even though they do not represent local persistence.
- Error logging and return conventions vary between repositories.
- Several services and controllers still access `$wpdb` directly.

### Baseline maturity score

Repository-pattern maturity is currently estimated at **21%**. This is an architecture score, not a measure of feature completeness.

| Dimension | Weight | Current measurement | Score |
| --- | ---: | --- | ---: |
| Database containment | 40% | 8 of 21 non-migration files using `$wpdb` are repositories | 15.2% |
| Persistence contracts | 15% | No persistence interfaces exist | 0% |
| Dependency composition | 20% | 127 direct repository constructions across 39 runtime files | 0% |
| Presentation isolation | 15% | Four templates use `$wpdb`; templates construct repositories 12 times | 0% |
| Repository cohesion | 10% | Five of nine named repositories have no obvious policy or SQL leakage | 5.6% |
| **Total** | **100%** | | **20.8%, rounded to 21%** |

Supporting inventory:

- 22 runtime files use `$wpdb`: 8 repositories, 3 controllers, 3 services, 4 templates, 1 migration, and 3 other runtime files.
- 10 concrete persistence classes exist under `inc/Repositories`.
- Two classes named repositories are remote API clients rather than persistence repositories.
- `TransactionRepository` publicly exposes order-table metadata and a reusable SQL fragment.
- `SettingRepository`, `ShipmentLocationRepository`, and `ShippingDiscountRegionRepository` contain application policy in addition to persistence.

The baseline should be recalculated after each migration phase using the same dimensions. The target is not the number of classes named `Repository`; the target is an enforced persistence boundary.

### Progress log

#### Iteration 1: transaction print state

- Moved the `is_printed` and `printed_at` update out of `ShippingProcessController`.
- Added `TransactionPrintRepositoryInterface` as the first persistence contract.
- Made `TransactionRepository` implement the print-state operation.
- Wired the controller dependency in `Init`, the current composition root.
- Added runtime tests for query parameters, empty input, and controller delegation.

Measured change:

- Runtime files using `$wpdb`: 22 to 21.
- Database access contained in repositories or migrations: 9 of 22 (41%) to 9 of 21 (43%).
- Persistence contracts: 0 to 1.
- Controllers with direct `$wpdb` access: 3 to 2.
- Templates with direct `$wpdb` access: unchanged at 4.
- Estimated overall repository-pattern maturity: 21% to 23%.

#### Iteration 2: tracking page lookup

- Moved tracking-shortcode page discovery out of `SettingController`.
- Added `TrackingPageRepositoryInterface` and a read-only `TrackingPageRepository`.
- Wired the controller dependency in `Init`.
- Added runtime tests for query constraints, shortcode patterns, ordering, and controller delegation.

Measured change from iteration 1:

- Runtime files using `$wpdb`: unchanged at 21 because the query moved into a repository.
- Database access contained in repositories or migrations: 9 of 21 (43%) to 10 of 21 (48%).
- Persistence contracts: 1 to 2.
- Controllers with direct `$wpdb` access: 2 to 1.
- Templates with direct `$wpdb` access: unchanged at 4.
- Estimated overall repository-pattern maturity: 23% to 27%.

#### Iteration 3: activation page setup

- Reused `TrackingPageRepositoryInterface` for activation-time tracking page discovery.
- Removed duplicated tracking shortcode SQL from `AdminPost`.
- Replaced direct writes to the WordPress options table with `update_option()`.
- Injected the tracking page repository at the plugin activation composition boundary.
- Added runtime tests for repository delegation and WordPress Options API usage.

Measured change from iteration 2:

- Runtime files using `$wpdb`: 21 to 20.
- Database access contained in repositories or migrations: 10 of 21 (48%) to 10 of 20 (50%).
- Pages with direct `$wpdb` access: 2 to 1.
- Injected runtime consumers of persistence contracts: 2 to 3.
- Persistence contracts: unchanged at 2 because the existing tracking-page contract was reused.
- Templates with direct `$wpdb` access: unchanged at 4.
- Estimated overall repository-pattern maturity: 27% to 29%.

#### Iteration 4: application persistence boundary

- Moved payment and transaction admin-list SQL into dedicated query contracts and implementations.
- Reduced payment and transaction templates to capability checks and render-service delegation.
- Centralized product volumetric readiness, tracking content, and shipping-zone persistence queries.
- Removed direct `$wpdb` access from controllers, services, pages, templates, and plugin helpers.
- Added a database transaction manager for the shipment-origin atomic update.
- Removed all repository construction from templates.
- Reused injected dependencies in high-construction consumers including checkout, settings, edit-order, onboarding, API, and shipping-discount flows.

Measured change from iteration 3:

- Application and presentation files using `$wpdb`: 10 to 0.
- Repository/query/infrastructure containment: 50% to 100%.
- Templates using `$wpdb`: 4 to 0.
- Template repository constructions: 12 to 0.
- Persistence and transaction contracts: 2 to 7.
- Injected runtime consumers: 3 to 17.
- Consumer-side repository constructions: 127 to 75.
- Estimated overall repository-pattern maturity: 29% to 78%.

### Current operational scorecard

The maturity percentage is directional. Use the raw metrics below to decide the next migration target and prevent superficial score improvements.

| Metric | Baseline | Current | Target |
| --- | ---: | ---: | ---: |
| Application files using `$wpdb` | 10 | 0 | 0 |
| Repository/query/infrastructure containment | 41% | 100% | 100% |
| Controllers using `$wpdb` | 3 | 0 | 0 |
| Services using `$wpdb` | 3 | 0 | 0 |
| Pages and root helpers using `$wpdb` | 3 | 0 | 0 |
| Templates using `$wpdb` | 4 | 0 | 0 |
| Persistence and transaction contracts | 0 | 7 | Contract for each application persistence boundary |
| Injected runtime consumers | 0 | 17 | All application consumers |
| Consumer-side repository constructions | 127 | 20 | 0 |
| Template repository constructions | 12 | 0 | 0 |

The remaining maturity gap is primarily dependency composition and repository cohesion. `TransactionRepository` and `SettingRepository` are still broad concrete dependencies, API clients still use repository naming, and 20 consumer-side construction sites remain.

#### Iteration 5: composition-root consolidation

- Moved request-pickup dependencies into the `Init` composition root.
- Moved COD adjustment dependencies into the `Init` composition root.
- Moved shipping payment and print-flow dependencies into the `Init` composition root.
- Reused transaction, payment, settings, API, and shipment-location collaborators within those flows.
- Preserved targeted no-constructor test paths through reflection only where the test exercises an unrelated private formatter.

Measured change from iteration 4:

- Consumer-side repository constructions: 75 to 59.
- Injected runtime consumers: unchanged at 20 by the conservative field-based metric; composition-root wiring increased.
- Application `$wpdb` containment: unchanged at 100%.
- Template isolation: unchanged at 100%.
- Estimated overall repository-pattern maturity: 78% to 80%.

#### Iteration 6: controller-owned workflow services

- Moved callback-handler repositories and API token resolution into the composition root.
- Injected cancellation and pickup-schedule services into transaction controllers.
- Reused transaction and API repositories between request-pickup, cancellation, and schedule workflows.
- Removed repository construction from callback, cancellation, and pickup-schedule services.

Measured change from iteration 5:

- Consumer-side repository constructions: 59 to 52.
- Application `$wpdb` containment: unchanged at 100%.
- Template isolation: unchanged at 100%.
- Estimated overall repository-pattern maturity: 80% to 82%.

#### Iteration 7: checkout service factory

- Added a request-local checkout service factory with shared repository dependencies.
- Kept calculation, pricing, cart-attribute, COD-deficit, and transaction-creation services operation scoped.
- Removed repository construction from checkout calculation, pricing, cart attributes, COD deficit, order ID generation, and transaction creation.
- Injected the factory into checkout, general AJAX, and transaction-origin pricing flows.
- Reused the same factory from the WooCommerce shipping method through a framework-compatible helper.

Measured change from iteration 6:

- Consumer-side repository constructions: 52 to 39.
- Application `$wpdb` containment: unchanged at 100%.
- Template isolation: unchanged at 100%.
- Estimated overall repository-pattern maturity: 82% to 85%.

#### Iteration 8: framework-safe composition seams

- Added request-local repository accessors for WordPress and WooCommerce callback boundaries.
- Removed direct repository construction from the shipping method, WooCommerce order columns, base API configuration, and shared helper counters.
- Made Checkout, Edit Order, and Setting controllers require dependencies from `Init`.
- Reused one region repository and API service during region cache refreshes.
- Kept compatibility fallbacks only in services that may still be instantiated outside the main composition root.

Measured change from iteration 7:

- Consumer-side repository constructions: 39 to 20.
- Application `$wpdb` containment: unchanged at 100%.
- Template isolation: unchanged at 100%.
- Estimated overall repository-pattern maturity: 85% to 90%.

### Current maturity score

| Dimension | Weight | Current assessment | Score |
| --- | ---: | --- | ---: |
| Database containment | 40% | All `$wpdb` access is inside migrations, repositories, query implementations, or transaction infrastructure | 40% |
| Persistence contracts | 15% | Seven narrow contracts cover the extracted high-risk boundaries; broad legacy repositories remain concrete | 8% |
| Dependency composition | 20% | Consumer-side constructions fell from 127 to 20; framework callbacks use request-local accessors and internal controllers require composition | 18% |
| Presentation isolation | 15% | Templates contain no `$wpdb` access or repository construction | 15% |
| Repository cohesion | 10% | Admin lists and specialized reads/writes are separated; broad transaction/settings repositories and API naming remain | 7% |
| **Total** | **100%** | | **90%** |

### Plugin-owned tables

- `kiriminaja_settings`
- `kiriminaja_transactions`
- `kiriminaja_payments`
- `kiriminaja_provinces`
- `kiriminaja_cities`
- `kiriminaja_shipment_location`

Schema creation and upgrades remain the responsibility of `inc/Migration/SetupMigration.php` during this migration.

### Completed direct database extraction

The original extraction targets are now behind repository, query, or transaction contracts:

- `templates/transaction-process/index.php`
- `templates/request-pickup/index.php`
- `templates/setting/setuped/index.php`
- `templates/setting/setuped/section-tracking.php`
- `inc/Controllers/SettingController.php`
- `inc/Controllers/ShippingProcessController.php`
- `inc/Controllers/TransactionProcessController.php`
- `inc/Services/OnboardingSetupStateService.php`
- `inc/Services/WooCommerceShippingMethodRegistrationService.php`

`ShippingDiscountRegionCacheService` now receives database errors through the region repository rather than inspecting `$wpdb` directly.

## Target boundaries

Contracts should describe application needs rather than mirror `$wpdb` methods.

### Transaction persistence

Suggested responsibility:

- Find a transaction by internal ID, order ID, pickup number, or AWB.
- Find transactions for a set of order IDs.
- Create a transaction.
- Apply callback changes.
- Update COD values.
- Store shipment-location data.
- Mark transactions as printed.

Possible contract split:

- `TransactionStore`
- `TransactionLookup`

Do not expose table names, SQL fragments, placeholder arrays, or `$wpdb` result state through these contracts.

### Transaction list and reporting queries

Filtered admin lists and dashboard counts have different needs from transaction persistence. Keep them in a read-only query service, for example:

- `TransactionListQuery`
- `TransactionMetricsQuery`

Inputs should use explicit criteria objects or validated arrays containing fields such as status, month, courier, print state, search text, page, and page size. Results should contain rows plus a total count.

This layer may continue using prepared SQL because it joins plugin tables with WooCommerce HPOS or legacy order tables.

### Payment persistence

Suggested contract:

- `PaymentStore`

Responsibilities:

- Find by ID or pickup number.
- Find the oldest payment.
- Create a payment.
- Apply verified callback updates.
- Count payments by status.

The contract must define whether a successful update that changes zero rows is considered success. Existing callback behavior needs characterization tests before the implementation changes.

### Settings persistence

Suggested contracts:

- `SettingReader`
- `SettingWriter`

Persistence should only read and write settings. Courier whitelist validation and integration setup rules belong in application services.

A separate decision can later evaluate whether WordPress Options is a better store than the current custom table. That decision is not required for this migration.

### Shipment locations

Suggested contracts:

- `ShipmentLocationStore`
- `ShipmentLocationQuery`

Database operations should be separated from rules such as the custom-location limit and ensuring one default location. Those rules belong in `ShipmentLocationService` or a dedicated application service.

The operation that switches the default location must remain atomic. Its transaction boundary should be owned by the application operation, not spread between controller and repository calls.

### Region cache

Suggested contract:

- `RegionCacheStore`

Responsibilities:

- Read provinces and cities.
- Replace or upsert cached region rows.
- Report cache freshness and row counts.

Bulk refresh performance must be measured before replacing current `$wpdb->replace()` behavior.

### WooCommerce read models

Queries against products, post meta, order statistics, HPOS tables, and shipping-zone tables should use WooCommerce APIs where those APIs support the required operation reliably.

When an API does not fit a reporting query, use a focused adapter or query service instead of placing WooCommerce table joins inside a plugin-owned entity repository.

Suggested boundaries:

- `OrderStorageInspector`
- `ProductShippingReadinessQuery`
- `ShippingZoneMethodStore`
- `TrackingPageQuery`

### Remote APIs

Rename or replace repository terminology for network clients:

- `KiriminajaApiRepository` becomes one or more gateway or client contracts.
- `CodFeeApiRepository` becomes a COD fee gateway.

Examples:

- `PricingGateway`
- `TrackingGateway`
- `PickupGateway`
- `CodFeeGateway`

This can be done after the database boundaries because it does not block repository extraction.

## Dependency wiring

Adopt constructor injection incrementally.

1. Add an optional contract dependency to a service or controller.
2. Preserve the current concrete implementation as the default during the transition.
3. Update tests to pass a fake or stub explicitly.
4. Move object construction to the plugin bootstrap or the nearest existing composition root.
5. Remove fallback construction after all call sites are migrated.

Do not introduce a service container solely for this work.

Templates must receive prepared view data. They must not construct repositories or access `$wpdb`.

## Migration phases

### Phase 0: Characterization tests

Add focused tests for behavior that must not change:

- HPOS and legacy order storage selection.
- Transaction lookup return shapes.
- Empty ID-list behavior.
- Payment and transaction callback idempotency.
- Zero-row update handling.
- Database failure handling and logging.
- Shipment-location default switching and rollback.
- Transaction list filters, pagination, and total counts.
- Request-pickup filters and joins.

Exit condition: tests describe current externally observable behavior, including behavior that is unusual but relied upon.

### Phase 1: Extract template queries

Move list and count queries out of:

- `templates/transaction-process/index.php`
- `templates/request-pickup/index.php`
- setup and tracking templates

Controllers or application services should request view data from query services and pass plain arrays or view models into templates.

Exit condition: templates contain no `$wpdb`, SQL, table names, or repository construction.

### Phase 2: Remove direct queries from controllers and services

Extract:

- Product and tracking lookups from `SettingController`.
- Transaction print updates from `ShippingProcessController`.
- Transaction transaction-boundary handling from `TransactionProcessController`.
- Product configuration counts from `OnboardingSetupStateService`.
- Shipping-zone method updates from `WooCommerceShippingMethodRegistrationService`.

Exit condition: direct `$wpdb` access is limited to migrations and database adapter implementations.

### Phase 3: Introduce contracts and dependency injection

Add small interfaces for the boundaries listed above. Existing `$wpdb` repositories implement them first.

Migrate one caller group at a time. Avoid changing every constructor in one pull request.

Exit condition: application code depends on contracts, while concrete database adapters are created at composition boundaries.

### Phase 4: Split overloaded repositories

Split `TransactionRepository` into transaction persistence and read-only reporting adapters. Move HPOS and legacy order table selection into a WooCommerce-specific adapter.

Move domain rules out of `ShipmentLocationRepository` and `SettingRepository`.

Exit condition: each repository or query service has one storage responsibility and does not expose reusable SQL fragments.

### Phase 5: Normalize results and failures

Choose consistent conventions for:

- Not found.
- Empty collection.
- Successful write with zero changed rows.
- Database failure.
- Inserted identifiers.

Prefer typed domain records or documented arrays over leaking raw, inconsistent `$wpdb` objects. Introduce custom exceptions only where callers can handle them meaningfully.

Exit condition: contracts document stable return and failure semantics.

## Repository implementation rules

Repositories and query services may use focused prepared SQL when it is the clearest or safest implementation, including:

- Cross-table transaction reports.
- HPOS and legacy order-table compatibility queries.
- WooCommerce product and order-item reporting joins.
- Correlated existence checks for shippable products.
- Aggregate dashboard counts.
- Migration and schema inspection queries.
- Explicit transaction statements for atomic application operations.

These queries still belong behind a repository, adapter, or query-service contract.

Additional rules:

- Repository methods describe domain or application operations, not SQL verbs.
- Repositories do not return SQL fragments, table names, or placeholder definitions.
- Repositories do not read HTTP input or render output.
- Templates receive prepared view data and never construct repositories.
- Application policy remains in services unless atomic persistence requires a repository operation.
- WooCommerce-owned data uses supported WooCommerce APIs where practical.
- Migrations remain the only place responsible for creating or altering plugin tables.

## Pull request sequence

Keep changes reviewable and independently releasable:

1. Characterization tests and shared result conventions.
2. Transaction-process query service and template cleanup.
3. Request-pickup query service and template cleanup.
4. Remaining controller and service query extraction.
5. Repository contracts and incremental constructor injection.
6. Transaction repository split and domain-rule extraction.

Each pull request should avoid combining structural migration with unrelated feature behavior.

## Verification for each phase

Run focused tests for the changed domain first, then:

```sh
make test
make zip
```

Verify the release archive does not contain development-only files or directories, including:

- `docs/`
- `tests/`
- `scripts/`
- `AGENTS.md`
- `CLAUDE.md`
- `paratest.xml`
- cache directories

Database changes also require activation and upgrade-path tests against existing table data.

## Completion criteria

The migration is complete when:

- Templates contain no database access.
- Controllers and application services do not access `$wpdb` directly.
- Migrations and database adapters are the only layers that know table names and SQL details.
- Application code depends on repository, gateway, or query-service contracts.
- HPOS and legacy behavior remain covered by tests.
- Result and failure semantics are documented and consistent.
- The release ZIP excludes this `docs/` directory.
