# Makefile for zipping the plugin

PLUGIN_NAME := kiriminaja
VERSION_RAW := $(shell git describe --tags --abbrev=0)
VERSION := $(shell echo $(VERSION_RAW) | awk -F. '{$$NF+=1; OFS="."; print $$1,$$2,$$3}')
ZIP_FILE := $(PLUGIN_NAME)-$(VERSION).zip
EXCLUSIONS := .git/\* .github/\* .idea/\* frontend/\* .vscode/\* node_modules/\* .editorconfig .gitattributes Makefile .gitignore .DS_Store \*.zip

.PHONY: zip install dev build clean

# Build production assets
build:
	@echo "Building frontend assets..."
	bun install && bun run build
	@echo "Build complete!"

# Install dependencies
install:
	@echo "Installing frontend dependencies..."
	bun install
	@echo "Dependencies installed!"

# Start development server
dev:
	@echo "Starting Vite dev server..."
	bun run dev

# Clean build artifacts
clean:
	@echo "Cleaning build artifacts..."
	rm -rf assets/.vite
	rm -rf node_modules
	rm -f bun.lockb
	@echo "Clean complete!"

# Create plugin zip for distribution
zip: build
	@echo "Creating zip archive for $(PLUGIN_NAME) version $(VERSION)..."
	zip -r $(ZIP_FILE) . -x $(EXCLUSIONS)
	@echo "Archive created: $(ZIP_FILE)"
