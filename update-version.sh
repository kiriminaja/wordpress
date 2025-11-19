#!/bin/bash
#
# Update Plugin Version
# This script updates version numbers across all plugin files
#
# Usage: ./update-version.sh <version>
# Example: ./update-version.sh 2.0.11
#

set -e  # Exit on error

# Check if version argument is provided
if [ -z "$1" ]; then
    echo "Error: Version number required"
    echo "Usage: ./update-version.sh <version>"
    echo "Example: ./update-version.sh 2.0.11"
    exit 1
fi

NEW_VERSION="$1"

# Validate version format (e.g., 2.0.10 or 2.0.10-beta)
if ! [[ "$NEW_VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[a-zA-Z0-9]+)?$ ]]; then
    echo "Error: Invalid version format. Use X.Y.Z or X.Y.Z-suffix"
    exit 1
fi

echo "================================================"
echo "Updating KiriminAja Plugin to version $NEW_VERSION"
echo "================================================"
echo ""

# Get the plugin directory
PLUGIN_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$PLUGIN_DIR"

echo "1. Updating kiriminaja.php..."
# Update plugin header version
sed -i.bak "s/\* Version:.*/* Version:         $NEW_VERSION/" kiriminaja.php
# Update KJ_PLUGIN_VERSION constant
sed -i.bak "s/define( 'KJ_PLUGIN_VERSION', '.*' );/define( 'KJ_PLUGIN_VERSION', '$NEW_VERSION' );/" kiriminaja.php
# Update KJ_VERSION_PLUGIN constant
sed -i.bak "s/define('KJ_VERSION_PLUGIN', sanitize_text_field('.*') );/define('KJ_VERSION_PLUGIN', sanitize_text_field('$NEW_VERSION') );/" kiriminaja.php

echo "2. Updating readme.txt..."
# Update Stable tag
sed -i.bak "s/Stable tag: .*/Stable tag: $NEW_VERSION/" readme.txt

echo "3. Cleaning up backup files..."
rm -f kiriminaja.php.bak readme.txt.bak

echo ""
echo "================================================"
echo "✅ Version updated successfully!"
echo "================================================"
echo ""
echo "Updated files:"
echo "  - kiriminaja.php (Plugin header, KJ_PLUGIN_VERSION, KJ_VERSION_PLUGIN)"
echo "  - readme.txt (Stable tag)"
echo ""
echo "Next steps:"
echo "  1. Review the changes: git diff"
echo "  2. Add changelog entry to readme.txt"
echo "  3. Commit: git commit -am 'Bump version to $NEW_VERSION'"
echo "  4. Tag: git tag $NEW_VERSION"
echo "  5. Push: git push && git push --tags"
echo ""
