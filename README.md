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

This runs 400+ ParaTest-backed tests covering security, escaping, prefix compliance, template structure, and build integrity.
The suite is executed through ParaTest so test files run in parallel using `paratest.xml`.

The required unit/runtime suites run from the plugin root without a WordPress server or network API. PHP needs the curl, SQLite3 and PDO SQLite extensions for runtime fixtures. Frontend checks require Bun 1.4.2 and Node 20.19+ or 22.12+ (Vite 8); CI uses Node 22. Locked React, React DOM and Happy DOM development dependencies are required: DOM suites fail rather than skip when dependencies are missing. These VM/DOM and compiled Svelte tests execute production code with fixture transport/UI boundaries; they are not live-browser E2E coverage.

`make zip` includes `bun run frontend:check` (formatting, lint, Svelte checking, style checks and payment/buyer/instant runtime suites) and the frontend build. The pre-commit hook runs frontend checks for staged frontend assets, blocks, tests, scripts, locks, translations and PHP integration changes. PR CI tests PHP 8.1, 8.2, 8.3 and 8.5; WP-CLI smoke checks cover the declared WordPress 6.8 / WooCommerce 8.5 minimum and WordPress 6.8 / WooCommerce 10.6, without starting a browser server.

### Logging

The plugin uses WooCommerce's native logger through `kiriof_log()` and `KiriminAjaOfficial\Utils\Logger`.

Current source identifiers:

- `kiriminaja_api` for external API transport and profile/cache failures
- `kiriminaja_import` for region cache warmups and bundled-data refresh fallbacks
- `kiriminaja_shipping` for shipping-rate, courier, tracking, and pickup API requests
- `kiriminaja_payment` for COD fee and COD adjustment flows
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

Tag publishing first builds the official zip, then runs `make test` against that exact source/staged artifact before GitHub release or SVN deployment. No artifact is rebuilt after verification.

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
