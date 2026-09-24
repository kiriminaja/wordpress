# Makefile for zipping the plugin (WordPress.org friendly)

PLUGIN_SLUG := kiriminaja-official
BUN := $(shell command -v bun 2>/dev/null || printf '%s' "$$HOME/.bun/bin/bun")

# Read version from kiriminaja.php KIRIOF_VERSION
VERSION := $(shell grep "KIRIOF_VERSION" kiriminaja.php | sed "s/.*'\([0-9.]*\)'.*/\1/")

BUILD_DIR := build
STAGE_DIR := $(BUILD_DIR)/$(PLUGIN_SLUG)

RSYNC_EXCLUDES := \
	--exclude=.git \
	--exclude=.git/ \
	--exclude=.github/ \
	--exclude=.idea/ \
	--exclude=.vscode/ \
	--exclude=node_modules/ \
	--exclude=src/ \
	--exclude=package.json \
	--exclude=bun.lock \
	--exclude=components.json \
	--exclude=bun.lockb \
	--exclude=.oxfmtrc.json \
	--exclude=.oxlintignore \
	--exclude=.oxlintrc.json \
	--exclude=tsconfig.json \
	--exclude=svelte.config.js \
	--exclude=vite.config.ts \
	--exclude=$(BUILD_DIR)/ \
	--exclude=docs/ \
	--exclude=scripts/ \
	--exclude=.DS_Store \
	--exclude=.distignore \
	--exclude=.editorconfig \
	--exclude=.gitattributes \
	--exclude=.gitignore \
	--exclude=.env \
	--exclude=.env.example \
	--exclude=AGENTS.md \
	--exclude=CLAUDE.md \
	--exclude=Makefile \
	--exclude=*.zip \
	--exclude=paratest.xml \
	--exclude=phpstan.neon \
	--exclude=phpstan.neon.dist \
	--exclude=phpstan-baseline.neon \
	--exclude=phpstan-baseline.neon.dist \
	--exclude=tests/ \
	--exclude=build/ \
	--exclude=.paratest.cache/ \
	--exclude=.wordpress-org/

.PHONY: frontend zip clean changelog release test tag publish dev stg plain

# BUMP: patch (default), minor, major
BUMP ?= patch

# Build environment: make zip dev | make zip stg | make zip (default = prd)
# dev/stg are registered as no-op targets so Make doesn't error out.
KIRIOF_ENV_GOALS := $(filter dev stg,$(MAKECMDGOALS))
ifneq ($(KIRIOF_ENV_GOALS),)
  KIRIOF_ENV := $(firstword $(KIRIOF_ENV_GOALS))
  $(eval $(KIRIOF_ENV_GOALS):;@:)
else
  KIRIOF_ENV := prd
endif

# Map environment to .env variable name and ZIP suffix
ifeq ($(KIRIOF_ENV),dev)
  ENV_VAR_NAME := API_BASE_URL_DEV
  ZIP_FILE     := $(PLUGIN_SLUG)-$(VERSION)-dev.zip
else ifeq ($(KIRIOF_ENV),stg)
  ENV_VAR_NAME := API_BASE_URL_STG
  ZIP_FILE     := $(PLUGIN_SLUG)-$(VERSION)-stg.zip
else
  ENV_VAR_NAME := API_BASE_URL_PRD
  ZIP_FILE     := $(PLUGIN_SLUG).zip
endif

# plain: output kiriminaja-official.zip regardless of env/version.
ifneq ($(filter plain,$(MAKECMDGOALS)),)
  ZIP_FILE := $(PLUGIN_SLUG).zip
  $(eval plain:;@:)
endif

# Allow positional version: `make release 2.1.9` or `make release v2.1.9`
# Any extra goals after release/publish/changelog are treated as V=<version>.
RELEASE_GOALS := $(filter release publish changelog,$(MAKECMDGOALS))
ifneq ($(RELEASE_GOALS),)
  EXTRA_GOALS := $(filter-out release publish changelog tag zip clean test dev stg plain,$(MAKECMDGOALS))
  ifneq ($(EXTRA_GOALS),)
    V := $(patsubst v%,%,$(firstword $(EXTRA_GOALS)))
    # Make the extra args no-op targets so Make doesn't error out.
    $(eval $(EXTRA_GOALS):;@:)
  endif
endif

test:
	vendor/bin/paratest --configuration paratest.xml --testdox

frontend:
	@test -x "$(BUN)" || (echo "Bun is required to build frontend assets." && exit 127)
	$(BUN) run frontend:check
	$(BUN) run build

changelog:
	@php scripts/changelog.php "$(V)" "$(FROM)" "$(BUMP)"

tag:
	@echo "Creating git tag v$(VERSION)..."
	git tag -a "v$(VERSION)" -m "Release v$(VERSION)"
	@echo "Tag v$(VERSION) created."

release: changelog
	@# Re-read version after changelog bumped it
	$(eval VERSION := $(shell grep "KIRIOF_VERSION" kiriminaja.php | sed "s/.*'\([0-9.]*\)'.*/\1/"))
	@$(MAKE) zip
	@echo ""
	@echo "Release v$(VERSION) ready!"
	@echo "  1. Commit: git add -A && git commit -m 'chore: release v$(VERSION)'"
	@echo "  2. Tag:    make tag"
	@echo "  3. Push:   git push && git push --tags"
	@echo ""

publish: release
	@# Full flow: build, commit, tag, push
	$(eval VERSION := $(shell grep "KIRIOF_VERSION" kiriminaja.php | sed "s/.*'\([0-9.]*\)'.*/\1/"))
	git add -A
	git commit -m "chore: release v$(VERSION)"
	@$(MAKE) tag
	git push
	git push --tags

clean:
	rm -rf $(BUILD_DIR) $(ZIP_FILE)

zip: frontend
	@echo "Building $(PLUGIN_SLUG) v$(VERSION) [env=$(KIRIOF_ENV)]..."
	@if command -v msgfmt >/dev/null 2>&1; then \
		msgfmt lang/kiriminaja-official-id_ID.po -o lang/kiriminaja-official-id_ID.mo && ls -l lang/kiriminaja-official-id_ID.mo; \
	elif [ -f lang/kiriminaja-official-id_ID.mo ]; then \
		echo "msgfmt not found; using committed lang/kiriminaja-official-id_ID.mo"; \
	else \
		echo "Error: msgfmt not found and lang/kiriminaja-official-id_ID.mo is missing"; \
		exit 127; \
	fi
	rm -rf $(STAGE_DIR) $(ZIP_FILE)
	mkdir -p $(STAGE_DIR)
	rsync -a $(RSYNC_EXCLUDES) ./ $(STAGE_DIR)/
	cp composer.json $(STAGE_DIR)/
	@if [ -f composer.lock ]; then cp composer.lock $(STAGE_DIR)/; fi
	# The KiriminAja SDK's Packagist archive can omit its PSR-4 source tree. Prefer
	# the Git source so runtime classes such as KiriminAja\Base\Api\Api are packaged.
	rm -rf $(STAGE_DIR)/vendor
	(cd $(STAGE_DIR) && composer install --no-dev --prefer-source --optimize-autoloader --no-interaction)
	find $(STAGE_DIR)/vendor -type d -name .git -prune -exec rm -rf {} +
	find $(STAGE_DIR)/vendor -type d \( -name .github -o -name docs -o -name example -o -name tests -o -name vendor-bin \) -prune -exec rm -rf {} +
	find $(STAGE_DIR)/vendor -type f \( -name CHANGELOG.md -o -name README.md -o -name UPGRADING.md -o -name composer.lock \) -delete
	@test -f $(STAGE_DIR)/vendor/kiriminaja/kiriminaja-php/src/Base/Api/Api.php || (echo "KiriminAja SDK API client source is missing from the package." && exit 1)
	rm -f $(STAGE_DIR)/composer.lock $(STAGE_DIR)/vendor/bin/.phpunit.result.cache
	@CUSTOM_LOCATION_LIMIT=$$(if [ -f .env ]; then grep '^MAX_CUSTOM_SHIPMENT_LOCATIONS=' .env | head -1 | cut -d= -f2- | xargs; fi); \
	if [ -n "$$CUSTOM_LOCATION_LIMIT" ]; then \
		php -r '$$file=$$argv[1]; $$limit=max(1,(int)$$argv[2]); $$content=file_get_contents($$file); $$content=preg_replace("/define\\( '\''KIRIOF_MAX_CUSTOM_SHIPMENT_LOCATIONS'\'', \\d+ \\);/", "define( '\''KIRIOF_MAX_CUSTOM_SHIPMENT_LOCATIONS'\'', ".$$limit." );", $$content, 1); file_put_contents($$file,$$content);' $(STAGE_DIR)/kiriminaja.php "$$CUSTOM_LOCATION_LIMIT"; \
		echo "  → Custom shipment address limit: $$CUSTOM_LOCATION_LIMIT"; \
	fi
	@if [ "$(KIRIOF_ENV)" != "prd" ] && [ -f .env ]; then \
		API_URL=$$(grep '^$(ENV_VAR_NAME)=' .env | head -1 | cut -d= -f2- | xargs); \
		if [ -n "$$API_URL" ]; then \
			php scripts/inject-api-url.php $(STAGE_DIR)/kiriminaja.php "$$API_URL" "$(KIRIOF_ENV)"; \
		else \
			php scripts/inject-api-url.php $(STAGE_DIR)/kiriminaja.php "https://client.kiriminaja.com" "$(KIRIOF_ENV)"; \
			echo "  → Warning: .env exists but $(ENV_VAR_NAME) is not set. Using default URL."; \
		fi; \
	elif [ "$(KIRIOF_ENV)" != "prd" ]; then \
		php scripts/inject-api-url.php $(STAGE_DIR)/kiriminaja.php "https://client.kiriminaja.com" "$(KIRIOF_ENV)"; \
		echo "  → Warning: No .env file found. Using default API base URL."; \
	fi
	(cd $(BUILD_DIR) && zip -r ../$(ZIP_FILE) $(PLUGIN_SLUG))
	@echo "Archive created: $(ZIP_FILE)"
