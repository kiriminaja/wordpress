# KiriminAja Official — WooCommerce Plugin

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/kiriminaja-official)](https://wordpress.org/plugins/kiriminaja-official/)
[![WordPress Plugin Rating](https://img.shields.io/wordpress/plugin/stars/kiriminaja-official)](https://wordpress.org/plugins/kiriminaja-official/)
[![WordPress Plugin Downloads](https://img.shields.io/wordpress/plugin/dt/kiriminaja-official)](https://wordpress.org/plugins/kiriminaja-official/)
[![Tested up to](https://img.shields.io/wordpress/plugin/tested/kiriminaja-official)](https://wordpress.org/plugins/kiriminaja-official/)
[![License](https://img.shields.io/wordpress/plugin/license/kiriminaja-official)](https://www.gnu.org/licenses/gpl-2.0.html)

A WordPress/WooCommerce plugin that integrates [KiriminAja](https://kiriminaja.com) shipping services into your online store. Supports COD and non-COD delivery across multiple couriers in Indonesia.

## Features

- Live shipping rate calculation at checkout
- Multi-courier support (JNE, J&T, SiCepat, and more)
- Shipping discounts via WooCommerce coupons (fixed/percentage shipping) with courier/area restrictions
- COD (Cash on Delivery) with daily fund disbursement
- Package pickup scheduling from your location
- AWB printing and shipment tracking
- Webhook-based status updates

## Requirements

- WordPress 6.8+
- WooCommerce 8.5+ (checkout fixtures cover 10.6)
- PHP 8.1+

## Installation

1. Download the latest release zip
2. Go to **Plugins → Add New → Upload Plugin** in WordPress admin
3. Upload the zip and activate
4. Navigate to **KiriminAja → Integration** and enter your setup key
5. Configure shipping preferences under **KiriminAja → Shipping**

Get your setup key from the [KiriminAja Dashboard](https://app.kiriminaja.com) under **Settings → App Integration → WooCommerce**.

## API Reference

https://developer.kiriminaja.com/docs

## Contributing

### Setup

```bash
git clone git@github.com:kiriminaja/plugin-wp.git
cd plugin-wp
composer install
bun install --frozen-lockfile
```

### Running Tests

```bash
make zip                     # required before source/package parity checks
make test
```

This runs the PHP unit/runtime tests and repository validation checks through ParaTest, with test files parallelized using `paratest.xml`. `make test` and `composer test` reuse an unchanged successful suite; cache hits are explicitly printed instead of pretending to execute tests.

```bash
make test-force                         # fresh PHP execution, no result reuse
composer test:force                     # same fresh ParaTest suite
bun run test:js                         # cached JS test groups
bun run test:js:force                   # rerun every JS group
KIRIOF_TEST_FORCE=1 make test            # environment equivalent
KIRIOF_TEST_FORCE=1 bun run test:instant # force one JS group
```

PHP receipts live under `.paratest.cache/kiriof-tests`; JS receipts live under `node_modules/.cache/kiriof/tests`. Keys include source/test/config/lock bytes, exact command arguments, relevant generated assets, installed dependency contents, runtime binaries/configuration, and environment. PHP also tracks the staged package used by parity checks; JS tracks PHP's runtime because some tests call PHP fixtures. Failures, unreadable inputs, and edits during execution cannot publish success. Coverage/report/watch/update and nondeterministic modes execute normally rather than reusing success. These are local optimizations, not proof of a new execution or compatibility with live external services; use force for a fresh run.

For PHP behavior tests, call the production method and use Mockery at repository, API, and WooCommerce boundaries. Use `MockeryPHPUnitIntegration` so expectations are verified and mocks are closed after each test. Assert returned values and side effects, not PHP source snippets or private implementation details. Keep static checks for packaging and repository-wide standards, and test independent input rules separately instead of multiplying unrelated permutations.

```bash
vendor/bin/paratest --configuration paratest.xml --filter SettingServiceDependencyInjectionRuntimeTest
```

The required unit/runtime suites run from the plugin root without a WordPress server or network API. PHP needs the curl, SQLite3 and PDO SQLite extensions for runtime fixtures. Frontend checks require Bun 1.4.2 and Node 20.19+ or 22.12+ (Vite 8); CI uses Node 22. Locked React, React DOM and Happy DOM development dependencies are required: DOM suites fail rather than skip when dependencies are missing. These VM/DOM and compiled Svelte tests execute production code with fixture transport/UI boundaries; they are not live-browser E2E coverage.

`make zip` builds admin and buyer assets and packages the plugin; it does not run tests, formatting, lint, Svelte checks, style checks or PHP literal validation. Run `bun run frontend:check` explicitly for frontend validation, and `php scripts/check-php-literal-escapes.php` for PHP literal validation. PR CI runs both checks explicitly before packaging, then runs PHP tests and WordPress smoke/Plugin Check jobs.

Successful frontend checks and buyer builds are reused only when content-matched inputs and generated output hashes agree. Run `KIRIOF_TEST_FORCE=1 bun run frontend:check` to rerun frontend validation, or set `KIRIOF_BUILD_FORCE=1` to bypass build and validation receipts. Buyer-build receipts track installed dependency manifests rather than every dependency source file; reinstall with `bun install --frozen-lockfile` after manual dependency edits and force a rebuild. Test/validation receipts check dependency file contents.

Commits do not run repository-managed tests. `bun install` retains the hook setup command but installs no hooks and preserves unrelated hooks. The legacy `scripts/frontend-pre-commit.sh` is a no-op for existing installations that still reference it. Tests remain available through the manual commands above and PR CI.

### Logging

The plugin uses WooCommerce's native logger through `kiriof_log()` and `KiriminAjaOfficial\Utils\Logger`.

Technical settings exports plugin-only file logs for today and the previous two UTC dates, including rotations. Exports use bounded memory, include at most 64 MiB of recent log input, and omit individual lines over 1 MiB rather than emit partially redacted records. Failed Express pickup requests include a privacy-redacted copy of their post-mapping JSON under `request_payload_json`: operational fields and numeric types remain, while contact details, addresses, coordinates, descriptive text and PINs are redacted. Authorization headers are never included. Historical logs can still contain personal details, so share exports only with the merchant's consent. No diagnostic export is sent automatically.

Current source identifiers:

- `kiriminaja_api` for external API transport and profile/cache failures
- `kiriminaja_import` for region cache warmups and bundled-data refresh fallbacks
- `kiriminaja_shipping` for shipping-rate, courier, tracking, and pickup API requests
- `kiriminaja_payment` for COD fee and COD adjustment flows
- `kiriminaja_request_pickup` for failed Express booking payload diagnostics
- `kiriminaja_settings` for setup key, callback, COD, and insurance configuration changes
- `kiriminaja_webhook` for inbound webhook validation and transaction sync events
- `kiriminaja_debug` for legacy local-only debug instrumentation

Available filters:

- `kiriof_logger_threshold` to set a plugin-level minimum level such as `error`, `warning`, `info`, `debug`, or `none`
- `kiriof_log_directory` to override the WooCommerce log directory
- `kiriof_logger_suppressed_messages` to suppress recurring noisy KiriminAja log messages through `woocommerce_logger_log_message`
- `kiriof_api_debug_logging` to enable success-level API debug logs during development

### Building

```bash
make zip
```

Produces `kiriminaja-official.zip` ready for distribution (`make zip dev` and `make zip stg` use versioned environment-specific names).

Packaging does not copy the development `vendor/` tree. It caches a separate production-only Composer install under `build/composer-vendor-cache`, keyed by the manifests, PHP/platform/Composer identity, and packaging helper. Every reuse verifies the complete vendor file inventory and runtime SDK autoloading. The SDK retains its required source install; other packages use dist archives. `make clean` removes this cache as well as the staged build. The first build installs production dependencies; unchanged subsequent builds reuse them. Packaging verifies its files but does not run test or validation suites.

On a test cache miss, deterministic fixture results are reused inside a worker and heavy UI matrices are batched with fresh case state. Forced test runs skip fingerprinting and receipt publication entirely; they invalidate any old success and execute immediately. Compiled Svelte suites build once per test process, then evaluate those bytes at a unique module path for each fresh DOM Window; production module state and native DOM caches are never shared between fixtures. Fixture teardown is also checked after failed setup.

Self-contained PHP fixtures use `tests/helpers/fixture-php.php` to avoid unrelated php.ini startup while preserving the parent's `ctype`/`mbstring` availability, including shared-extension installations. Unresolvable profiles fall back to normal PHP startup. Fixtures using real SQLite/curl keep their configured runtime; PHP syntax lint needs no extensions. Keep performance measurements on `make test-force` / `bun run test:js:force` so cached success cannot mask cold-run regressions.

### Releasing

The Makefile automates version bumping, changelog generation, zipping, tagging, and publishing.

```bash
make release                  # auto-bump patch (e.g. 2.1.8 -> 2.1.9)
make release BUMP=minor       # auto-bump minor
make release BUMP=major       # auto-bump major
make release V=2.5.0          # explicit version
make release 2.5.0            # shorthand (positional)
make release v2.5.0           # shorthand with leading "v"
make release V=2.5.0-beta.1   # explicit prerelease
make publish                  # full flow: build + commit + tag + push

# --- Individual steps ---
make changelog                # update readme.txt + KIRIOF_VERSION only
make zip                      # build distributable zip
make tag                      # create local git tag v$(VERSION)
```

`BUMP` rules: `patch` auto-rolls to `minor` at `.99`; `minor` auto-rolls to `major` at `.99`.

Changelogs use GitHub's generated release notes, with one entry per pull request rather than individual commits. Install the GitHub CLI, run `gh auth login`, and push the release branch before running `make changelog` or `make release`. `FROM` selects the previous release tag, for example `make changelog FROM=v2.4.2`.

Tag publishing builds the official ZIP and publishes it without requiring an existing validation run for the tagged commit. Tests remain available manually and in PR CI; publishing does not start tests on a tag push. Maintainers can publish hotfixes without waiting for validation.

The publish workflow reads the version's saved changelog from `readme.txt` and uses the same notes for the GitHub release. You can edit that entry before tagging without having GitHub generate a different change list. No version bump or release is created by opening a pull request.

### Branching

- `main` — stable release branch
- `sa/AB#*` — feature/fix branches
- Submit pull requests against `main`

### Code Standards

- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- Prefix all globals with `kiriof_` (functions, hooks, meta keys) or `KIRIOF_` (constants)
- Namespace PHP classes under `KiriminAjaOfficial\`
- Text domain: `kiriminaja-official`
- Sanitize all inputs, escape all outputs
- Use `$wpdb->prepare()` for database queries

### Pull Request Checklist

- [ ] All tests pass (`make test`)
- [ ] Build succeeds (`make zip`)
- [ ] No unprefixed globals introduced
- [ ] Inputs sanitized, outputs escaped
- [ ] Nonce verification on all form/AJAX handlers

## License

GPL-2.0-or-later — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html)
