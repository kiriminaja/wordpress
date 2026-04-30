# KiriminAja Official — WooCommerce Plugin

A WordPress/WooCommerce plugin that integrates [KiriminAja](https://kiriminaja.com) shipping services into your online store. Supports COD and non-COD delivery across multiple couriers in Indonesia.

## Features

- Live shipping rate calculation at checkout
- Multi-courier support (JNE, J&T, SiCepat, and more)
- COD (Cash on Delivery) with daily fund disbursement
- Package pickup scheduling from your location
- AWB printing and shipment tracking
- Webhook-based status updates

## Requirements

- WordPress 6.0+
- WooCommerce 8.5+
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
```

### Running Tests

```bash
make test
```

This runs 125+ PHPUnit tests covering security, escaping, prefix compliance, template structure, and build integrity.

### Building

```bash
make zip
```

Produces a `kiriminaja-official-{version}.zip` ready for distribution.

### Releasing

The Makefile automates version bumping, changelog generation, zipping, tagging, and publishing.

```bash
make release                  # auto-bump patch (e.g. 2.1.8 -> 2.1.9)
make release BUMP=minor       # auto-bump minor
make release BUMP=major       # auto-bump major
make release V=2.5.0          # explicit version
make release 2.5.0            # shorthand (positional)
make release v2.5.0           # shorthand with leading "v"
make github-release           # open GitHub "New Release" page pre-filled
make publish                  # full flow: build + commit + tag + push + GitHub release

# --- Individual steps ---
make changelog                # update readme.txt + KIRIOF_VERSION only
make zip                      # build distributable zip
make tag                      # create local git tag v$(VERSION)
```

`BUMP` rules: `patch` auto-rolls to `minor` at `.99`; `minor` auto-rolls to `major` at `.99`.

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
