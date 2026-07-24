# CLAUDE.md

This is the canonical local instruction file for Claude and other agentic coding tools in this repository. Keep `CLAUDE.md`, `AGENTS.md`, and `.github/copilot-instructions.md` aligned when these project rules change.

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

- Use ParaTest as the default test runner: `make test`.
- For focused checks, run `vendor/bin/paratest --configuration paratest.xml --filter <Name>`.
- Keep `tests/` string/structure assertions aligned with the current implementation when behavior is intentionally changed.

## Packaging

- `CLAUDE.md`, `AGENTS.md`, `paratest.xml`, tests, development files, and cache directories must not be included in the distributable zip.
- The distributable archive is produced by `make zip`.
