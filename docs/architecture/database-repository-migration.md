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

### Plugin-owned tables

- `kiriminaja_settings`
- `kiriminaja_transactions`
- `kiriminaja_payments`
- `kiriminaja_provinces`
- `kiriminaja_cities`
- `kiriminaja_shipment_location`

Schema creation and upgrades remain the responsibility of `inc/Migration/SetupMigration.php` during this migration.

### High-priority direct database access

The first extraction targets are the queries currently outside the repository layer:

- `templates/transaction-process/index.php`
- `templates/request-pickup/index.php`
- `templates/setting/setuped/index.php`
- `templates/setting/setuped/section-tracking.php`
- `inc/Controllers/SettingController.php`
- `inc/Controllers/ShippingProcessController.php`
- `inc/Controllers/TransactionProcessController.php`
- `inc/Services/OnboardingSetupStateService.php`
- `inc/Services/WooCommerceShippingMethodRegistrationService.php`

Direct database error inspection in `ShippingDiscountRegionCacheService` can remain temporarily, but the repository should eventually return or throw a consistent failure result instead.

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
