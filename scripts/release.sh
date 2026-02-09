#!/bin/bash
#
# Release script for MWE EtchWP Enhancements
#
# Usage: ./scripts/release.sh
#
# This script creates a WordPress-compatible ZIP file for distribution.
# It excludes development files that should not be in the production release.
#

set -e

PLUGIN_SLUG="mwe-etchwp-enhancements"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(dirname "$SCRIPT_DIR")"
PARENT_DIR="$(dirname "$PLUGIN_DIR")"
ZIP_FILE="$PARENT_DIR/$PLUGIN_SLUG.zip"

echo "Creating release ZIP for $PLUGIN_SLUG..."
echo "Plugin directory: $PLUGIN_DIR"
echo "Output: $ZIP_FILE"

# Remove existing ZIP if present
if [ -f "$ZIP_FILE" ]; then
    rm "$ZIP_FILE"
    echo "Removed existing ZIP file"
fi

# Create ZIP excluding development files
cd "$PARENT_DIR"
zip -r "$ZIP_FILE" "$PLUGIN_SLUG" \
    -x "*.git*" \
    -x "*.DS_Store" \
    -x "*vendor/*" \
    -x "*node_modules/*" \
    -x "*tests/*" \
    -x "*phpunit.xml*" \
    -x "*composer.*" \
    -x "*docs/*" \
    -x "*.phpunit.result.cache" \
    -x "*scripts/*"

echo ""
echo "✓ Release ZIP created: $ZIP_FILE"
echo "  Size: $(ls -lh "$ZIP_FILE" | awk '{print $5}')"
echo ""
echo "Contents:"
unzip -l "$ZIP_FILE" | tail -n +4 | head -n -2
