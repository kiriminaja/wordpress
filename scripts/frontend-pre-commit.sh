#!/bin/sh
set -eu

if ! git diff --cached --name-only --diff-filter=ACMRD -- \
	'package.json' \
	'bun.lock' \
	'src/**' \
	'assets/wp/**' \
	'blocks/**' \
	'tests/**' \
	'scripts/**' \
	'lang/**' \
	'inc/**' \
	'vite.config.ts' \
	'svelte.config.js' \
	'tsconfig.json' \
	'.oxfmtrc.json' \
	'.oxlintignore' \
	'.oxlintrc.json' \
	| grep -q .; then
	exit 0
fi

BUN="$(command -v bun 2>/dev/null || printf '%s' "$HOME/.bun/bin/bun")"

if [ ! -x "$BUN" ]; then
	echo "Bun is required for the frontend pre-commit checks."
	exit 127
fi

"$BUN" run frontend:check
