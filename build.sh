#!/usr/bin/env bash
set -e

VERSION="1.0.3"
BUILD_DIR="build_tmp"
ARCHIVE_NAME="linkforge-v${VERSION}.zip"

echo "▶ Building LinkForge v${VERSION}..."

# Clean previous build artifacts
rm -rf "$BUILD_DIR" "$ARCHIVE_NAME"
mkdir -p "$BUILD_DIR"

# --- Copy source tree, excluding dev + sensitive files ---
rsync -av \
    --exclude='.git' \
    --exclude='.gitignore' \
    --exclude='.github' \
    --exclude='.vscode' \
    --exclude='.idea' \
    --exclude='.DS_Store' \
    --exclude='config/config.php' \
    --exclude='storage/cache/*' \
    --exclude='storage/logs/*' \
    --exclude='storage/update_extracted' \
    --exclude='storage/backups' \
    --exclude='app/Libraries/phpqrcode/cache/*' \
    --exclude='app/Libraries/phpqrcode/temp/*' \
    --exclude='tests' \
    --exclude='build.sh' \
    --exclude='build_tmp' \
    --exclude='*.zip' \
    --exclude='*.tar.gz' \
    --exclude='*.log' \
    --exclude='.env' \
    --exclude='.env.*' \
    ./ "$BUILD_DIR/"

# --- Recreate placeholder .gitkeep files so folders persist in the archive ---
mkdir -p "$BUILD_DIR/storage/cache/links"
mkdir -p "$BUILD_DIR/storage/logs/mail"
mkdir -p "$BUILD_DIR/app/Libraries/phpqrcode/cache"
mkdir -p "$BUILD_DIR/app/Libraries/phpqrcode/temp"
touch "$BUILD_DIR/storage/cache/.gitkeep"
touch "$BUILD_DIR/storage/cache/links/.gitkeep"
touch "$BUILD_DIR/storage/logs/.gitkeep"
touch "$BUILD_DIR/storage/logs/mail/.gitkeep"
touch "$BUILD_DIR/app/Libraries/phpqrcode/cache/.gitkeep"
touch "$BUILD_DIR/app/Libraries/phpqrcode/temp/.gitkeep"

# --- Sanity check: refuse to build if config.php leaked in ---
if [ -f "$BUILD_DIR/config/config.php" ]; then
    echo "✕ ABORT: config/config.php was copied into the build. Check rsync excludes."
    rm -rf "$BUILD_DIR"
    exit 1
fi

# --- Sanity check: refuse to build if any mail log leaked in ---
if [ -d "$BUILD_DIR/storage/logs/mail" ] && \
   [ "$(find "$BUILD_DIR/storage/logs/mail" -type f ! -name '.gitkeep' | wc -l)" -gt 0 ]; then
    echo "✕ ABORT: email log files were copied into the build."
    rm -rf "$BUILD_DIR"
    exit 1
fi

echo "▶ Minifying frontend assets..."

# Minify CSS
php -r '
$f = "'"$BUILD_DIR"'/public/assets/css/app.css";
if (file_exists($f)) {
    $css = file_get_contents($f);
    $css = preg_replace("!/\*[^*]*\*+([^/][^*]*\*+)*/!", "", $css);
    $css = str_replace(["\r\n", "\r", "\n", "\t"], "", $css);
    $css = preg_replace("/\s*([\{\}:;,])\s*/", "$1", $css);
    $css = str_replace(";}", "}", $css);
    file_put_contents("'"$BUILD_DIR"'/public/assets/css/app.min.css", trim($css));
}
'

# Minify JS
php -r '
$f = "'"$BUILD_DIR"'/public/assets/js/app.js";
if (file_exists($f)) {
    $js = file_get_contents($f);
    $js = preg_replace("/(?:(?:\/\*(?:[^*]|(?:\*+[^*\/]))*\*+\/)|(?:(?<!\:|\\\\)\/\/[^\"'\''\n]*))/", "", $js);
    $js = preg_replace("/\s+/", " ", $js);
    $js = preg_replace("/\s*([\{\}\(\)\[\];,\:\=\+\-\*\/><!&\|])\s*/", "$1", $js);
    file_put_contents("'"$BUILD_DIR"'/public/assets/js/app.min.js", trim($js));
}
'

echo "▶ Assets compiled."

# --- Create release archive ---
cd "$BUILD_DIR"
zip -rq "../$ARCHIVE_NAME" . -x "*.DS_Store"
cd ..
rm -rf "$BUILD_DIR"

# --- Report ---
SIZE=$(du -h "$ARCHIVE_NAME" | cut -f1)
echo "✔ Build complete: ${ARCHIVE_NAME} (${SIZE})"

# --- Final guard: verify no secrets in the zip ---
if unzip -l "$ARCHIVE_NAME" | awk '{print $4}' | grep -qE '^config/config\.php$|^storage/logs/mail/.*\.html$'; then
    echo "✕ WARNING: The archive appears to contain sensitive files. Do NOT publish."
    exit 1
fi
echo "✔ Archive is clean."