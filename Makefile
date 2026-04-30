# Makefile for zipping the plugin (WordPress.org friendly)

PLUGIN_SLUG := kiriminaja-official

# Read version from kiriminaja.php KIRIOF_VERSION
VERSION := $(shell grep "KIRIOF_VERSION" kiriminaja.php | sed "s/.*'\([0-9.]*\)'.*/\1/")

# VARIANT: official (default, WP.org-ready, strips UpdaterController)
#          legacy   (full pack, keeps UpdaterController for non-WP.org customers)
VARIANT ?= official

ifeq ($(VARIANT),legacy)
  ZIP_FILE := $(PLUGIN_SLUG)-legacy-$(VERSION).zip
else
  ZIP_FILE := $(PLUGIN_SLUG)-$(VERSION).zip
endif

BUILD_DIR := build
STAGE_DIR := $(BUILD_DIR)/$(PLUGIN_SLUG)

RSYNC_EXCLUDES := \
	--exclude=.git/ \
	--exclude=.github/ \
	--exclude=.idea/ \
	--exclude=.vscode/ \
	--exclude=node_modules/ \
	--exclude=$(BUILD_DIR)/ \
	--exclude=scripts/ \
	--exclude=.DS_Store \
	--exclude=.distignore \
	--exclude=.editorconfig \
	--exclude=.gitattributes \
	--exclude=.gitignore \
	--exclude=Makefile \
	--exclude=*.zip \
	--exclude=phpunit.xml \
	--exclude=tests/ \
	--exclude=build/ \
	--exclude=.phpunit.cache/
	--exclude=.wordpress-org/

.PHONY: zip clean changelog release test tag github-release publish \
        release-legacy github-release-legacy publish-legacy zip-legacy

# BUMP: patch (default), minor, major
BUMP ?= patch

# Allow positional version: `make release 2.1.9` or `make release v2.1.9`
# Any extra goals after release/publish/changelog are treated as V=<version>.
RELEASE_GOALS := $(filter release publish changelog,$(MAKECMDGOALS))
ifneq ($(RELEASE_GOALS),)
  EXTRA_GOALS := $(filter-out release publish changelog tag github-release zip clean test,$(MAKECMDGOALS))
  ifneq ($(EXTRA_GOALS),)
    V := $(patsubst v%,%,$(firstword $(EXTRA_GOALS)))
    # Make the extra args no-op targets so Make doesn't error out.
    $(eval $(EXTRA_GOALS):;@:)
  endif
endif

test:
	vendor/bin/phpunit --testdox

changelog:
	@php scripts/changelog.php $(if $(V),$(V),) $(if $(FROM),$(FROM),) $(BUMP)

tag:
	@echo "Creating git tag v$(VERSION)..."
	git tag -a "v$(VERSION)" -m "Release v$(VERSION)"
	@echo "Tag v$(VERSION) created."

github-release:
	@php scripts/github-release.php $(VERSION)

release: changelog
	@# Re-read version after changelog bumped it
	$(eval VERSION := $(shell grep "KIRIOF_VERSION" kiriminaja.php | sed "s/.*'\([0-9.]*\)'.*/\1/"))
	@$(MAKE) zip
	@echo ""
	@echo "Release v$(VERSION) ready!"
	@echo "  1. Commit: git add -A && git commit -m 'chore: release v$(VERSION)'"
	@echo "  2. Tag:    make tag"
	@echo "  3. Push:   git push && git push --tags"
	@echo "  4. GitHub: make github-release"
	@echo ""

publish: release
	@# Full flow: build, commit, tag, push, open GitHub release
	$(eval VERSION := $(shell grep "KIRIOF_VERSION" kiriminaja.php | sed "s/.*'\([0-9.]*\)'.*/\1/"))
	git add -A
	git commit -m "chore: release v$(VERSION)"
	@$(MAKE) tag
	git push
	git push --tags
	@$(MAKE) github-release

clean:
	rm -rf $(BUILD_DIR) $(ZIP_FILE)

zip: clean
	@echo "Creating zip archive for $(PLUGIN_SLUG) version $(VERSION) [variant=$(VARIANT)]..."
	mkdir -p $(STAGE_DIR)
	rsync -a $(RSYNC_EXCLUDES) ./ $(STAGE_DIR)/
	cp composer.json $(STAGE_DIR)/
	@if [ -f composer.lock ]; then cp composer.lock $(STAGE_DIR)/; fi
	@if [ "$(VARIANT)" = "official" ]; then \
		echo "Stripping UpdaterController for WP.org-friendly build..."; \
		rm -f $(STAGE_DIR)/inc/Controllers/UpdaterController.php; \
		perl -i -ne 'print unless /Controllers\\UpdaterController::class\s*,/' $(STAGE_DIR)/inc/Init.php; \
	else \
		echo "Legacy build: keeping UpdaterController."; \
	fi
	cp composer.json $(STAGE_DIR)/
	@if [ -f composer.lock ]; then cp composer.lock $(STAGE_DIR)/; fi
	cd $(STAGE_DIR) && composer install --no-dev --optimize-autoloader --no-interaction 2>/dev/null; rm -f $(STAGE_DIR)/composer.json $(STAGE_DIR)/composer.lock
	(cd $(BUILD_DIR) && zip -r ../$(ZIP_FILE) $(PLUGIN_SLUG))
	@echo "Archive created: $(ZIP_FILE)"

# --- Legacy variants (full pack, keeps UpdaterController) ---

zip-legacy:
	@$(MAKE) zip VARIANT=legacy

release-legacy: changelog
	$(eval VERSION := $(shell grep "KIRIOF_VERSION" kiriminaja.php | sed "s/.*'\([0-9.]*\)'.*/\1/"))
	@$(MAKE) zip VARIANT=legacy
	@echo ""
	@echo "Legacy release v$(VERSION) ready (UpdaterController included)!"
	@echo "  Artifact: $(PLUGIN_SLUG)-legacy-$(VERSION).zip"
	@echo ""

github-release-legacy:
	@php scripts/github-release.php $(VERSION) legacy

publish-legacy: release-legacy
	$(eval VERSION := $(shell grep "KIRIOF_VERSION" kiriminaja.php | sed "s/.*'\([0-9.]*\)'.*/\1/"))
	git add -A
	git commit -m "chore: release v$(VERSION) (legacy)" || true
	@$(MAKE) tag
	git push
	git push --tags
	@$(MAKE) github-release-legacy