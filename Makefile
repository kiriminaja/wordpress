# Makefile for zipping the plugin

PLUGIN_NAME := kiriminaja
VERSION_RAW := $(shell git describe --tags --abbrev=0)
VERSION := $(shell echo $(VERSION_RAW) | awk -F. '{$$NF+=1; OFS="."; print $$1,$$2,$$3}')
ZIP_FILE := $(PLUGIN_NAME)-$(VERSION).zip
EXCLUSIONS := '*.git* *.github* /*node_modules/* .editorconfig .gitattributes .Makefile .gitignore .DS_Store .idea/* .vscode/* .github/* .git/* *.zip'

.PHONY: zip

zip:
	@echo "Creating zip archive for $(PLUGIN_NAME) version $(VERSION)..."
	zip -r $(ZIP_FILE) . -x $(EXCLUSIONS)
	@echo "Archive created: $(ZIP_FILE)"