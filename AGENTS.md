# AGENTS.md

This is the canonical local instruction file for agentic coding tools in this repository. Keep `AGENTS.md`, `CLAUDE.md`, and `.github/copilot-instructions.md` aligned when these project rules change.

## Project Context

This repository is the KiriminAja Official WordPress plugin for WooCommerce. Keep changes scoped to plugin behavior, tests, packaging, and documentation that directly support the requested task.

## Workflow

- Use `rtk` before shell commands in this workspace.
- Check `git status --short --branch` before edits and avoid touching unrelated user changes.
- Prefer narrow fixes in the existing service/controller/template structure.
- Follow the official WordPress Coding Standards for all plugin changes: https://developer.wordpress.org/coding-standards/wordpress-coding-standards/
- Apply those standards to PHP, JavaScript, CSS, HTML, documentation, escaping, sanitization, naming, and formatting decisions unless the surrounding file has a stronger local convention.
- Be extra careful with WordPressCS / Plugin Check database sniffs: when using `$wpdb->prepare()` with dynamic `IN (...)` placeholders, pass the prepared SQL directly into the query call instead of storing it in an intermediate variable, and avoid mismatched replacement counts or interpolated placeholder variables that trigger `PreparedSQL` warnings.
- Do not start local WordPress or dev servers unless explicitly requested.
- When changing packaged source files, run `make zip` before final verification because build/source parity is tested.

## Testing

- Testing is explicit only: commits and `make zip` must not run tests or validation suites. Run manual test commands when requested; PR CI keeps its explicit checks.
- `simple-git-hooks` has no managed hooks and preserves other hooks. Keep the legacy frontend pre-commit script a no-op so stale installations cannot run tests.

- Use ParaTest as the default test runner: `make test`.
- `make test` / `composer run test` and the named JS test scripts reuse content-matched successful runs. Use `make test-force`, `composer run test:force`, or `KIRIOF_TEST_FORCE=1` when fresh execution is required; report cache hits as reused results, not newly executed tests.
- ParaTest is the only supported PHP test entry point (`make test` or `composer run test`). Do not invoke the underlying PHPUnit binary directly; PHPUnit remains an internal dependency of ParaTest.
- For focused checks, run `vendor/bin/paratest --configuration paratest.xml --filter <Name>`.
- Keep `tests/` string/structure assertions aligned with the current implementation when behavior is intentionally changed.

## Packaging

- `AGENTS.md`, `paratest.xml`, tests, development files, and cache directories must not be included in the distributable zip.
- The distributable archive is produced by `make zip`.

## PHP Dependencies

- Manage PHP dependencies and scripts with Composer and the committed `composer.json` / `composer.lock`; a nonempty tracked lockfile is mandatory.
- Install with `composer install --prefer-dist --no-interaction --no-progress --no-scripts`; CI caches the Composer download directory and rejects lockfile changes. Do not use `composer update` in CI.
- Keep tests explicit: use `composer run test` or `make test`; dependency installation, commits, and packaging must not run tests automatically.
- Packaging uses a separate production install (`composer install --no-dev --no-scripts --prefer-dist --optimize-autoloader`) under `build/composer-vendor-cache`, not the development `vendor/` tree. Preserve cache inventory and runtime SDK autoload integrity checks.
