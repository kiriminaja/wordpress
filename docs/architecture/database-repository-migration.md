# Database Repository Migration Plan

## Status

Proposed architecture plan. No runtime migration has started.

## Decision

Use repository contracts as the stable boundary for database access before adopting BerlinDB.

The first implementation of each contract can continue using `$wpdb`. BerlinDB may replace selected implementations after existing behavior is covered by tests and callers no longer depend on SQL details.

This sequence reduces migration risk because controllers, services, and templates can be cleaned up without changing the storage engine at the same time.

## Goals

- Remove database queries from controllers, services, and templates.
- Expose persistence operations through small contracts based on application use cases.
- Keep WooCommerce HPOS and legacy order storage behavior compatible.
- Make database behavior testable without requiring every caller to construct a concrete repository.
- Allow selected plugin-owned tables to move to BerlinDB later.
- Keep complex WooCommerce reporting queries as prepared SQL when that remains the clearest implementation.

## Non-goals

- Rewriting every query into BerlinDB in one release.
- Creating a generic repository base class for unrelated domains.
- Introducing a dependency injection container.
- Hiding schema migrations behind repository contracts.
- Forcing cross-table reports or WooCommerce-owned storage through BerlinDB.
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

This can be done after database boundaries because it is not required for the BerlinDB decision.

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

### Phase 6: BerlinDB pilot

Add BerlinDB only after the repository contracts and characterization tests are stable.

Recommended pilot order:

1. Shipment locations.
2. Province and city cache.
3. Payments.
4. Simple transaction CRUD and lookups.

For each pilot:

- Keep the existing `$wpdb` adapter available during development.
- Run the same contract tests against both implementations where practical.
- Compare generated schema and indexes with existing production tables.
- Verify upgrades on existing installations, not only fresh activation.
- Benchmark bulk operations and filtered reads.
- Confirm the packaged dependency works on the supported PHP and WordPress versions.

Exit condition: BerlinDB provides a clear maintenance or correctness benefit without changing application callers.

## Queries that may remain prepared SQL

BerlinDB should not be a consistency goal by itself. Keep focused prepared SQL for cases where it is clearer or safer:

- Cross-table transaction reports.
- HPOS and legacy order-table compatibility queries.
- WooCommerce product and order-item reporting joins.
- Correlated existence checks for shippable products.
- Aggregate dashboard counts.
- Migration and schema inspection queries.
- Explicit transaction statements where the selected abstraction does not provide an equivalent boundary.

These queries still belong behind a repository, adapter, or query-service contract.

## BerlinDB adoption risks

- BerlinDB becomes the first production Composer dependency shipped by this plugin.
- Another WordPress plugin may ship a conflicting version in the same PHP process.
- The build may need dependency prefixing or isolation.
- Composer platform checks are currently disabled, so CI must verify the real supported PHP matrix.
- Existing table schemas and upgrade paths must remain compatible with installed sites.
- Bulk cache refresh may regress if row-by-row abstractions replace efficient database operations.
- BerlinDB does not remove the need for WooCommerce HPOS compatibility logic.

Before adoption, confirm the BerlinDB version, supported PHP and WordPress versions, package isolation strategy, schema ownership, and rollback plan.

## Pull request sequence

Keep changes reviewable and independently releasable:

1. Characterization tests and shared result conventions.
2. Transaction-process query service and template cleanup.
3. Request-pickup query service and template cleanup.
4. Remaining controller and service query extraction.
5. Repository contracts and incremental constructor injection.
6. Transaction repository split and domain-rule extraction.
7. BerlinDB shipment-location proof of concept.
8. BerlinDB expansion only after the pilot is accepted.

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
- BerlinDB is adopted only for tables where its benefits are demonstrated.
- The release ZIP excludes this `docs/` directory.
