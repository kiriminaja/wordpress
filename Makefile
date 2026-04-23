# Makefile for zipping the plugin (WordPress.org friendly)

PLUGIN_SLUG := kiriminaja-official

# Read version from kiriminaja.php KIRIOF_VERSION
VERSION := $(shell grep "KIRIOF_VERSION" kiriminaja.php | sed "s/.*'\([0-9.]*\)'.*/\1/")
ZIP_FILE := $(PLUGIN_SLUG)-$(VERSION).zip

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
	--exclude=.phpunit.cache/

.PHONY: zip clean changelog release test tag github-release publish

# BUMP: patch (default), minor, major
BUMP ?= patch

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
	@echo "Creating zip archive for $(PLUGIN_SLUG) version $(VERSION)..."
	mkdir -p $(STAGE_DIR)
	rsync -a $(RSYNC_EXCLUDES) ./ $(STAGE_DIR)/
	cd $(STAGE_DIR) && composer install --no-dev --optimize-autoloader --no-interaction 2>/dev/null; rm -f composer.lock
	(cd $(BUILD_DIR) && zip -r ../$(ZIP_FILE) $(PLUGIN_SLUG))
	@echo "Archive created: $(ZIP_FILE)"