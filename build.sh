#!/bin/bash
VERSION="1.0.1"
ZIP_NAME="linkforge-v${VERSION}.zip"

echo "🔨 Building ${ZIP_NAME}..."

# Clean old builds
rm -f ${ZIP_NAME}

# Create archive excluding dev artifacts
zip -r ${ZIP_NAME} . \
    -x "*.git*" \
    -x ".DS_Store" \
    -x "config/config.php" \
    -x "storage/temp_update.zip" \
    -x "build.sh"

echo "✅ Build complete: ${ZIP_NAME}"
