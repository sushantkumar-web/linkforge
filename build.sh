#!/usr/bin/env bash
set -e

VERSION="1.1.1"
BUILD_DIR="build_tmp"
ARCHIVE_NAME="linkforge-v${VERSION}.zip"

# --- Locate PHP ---
PHP_BIN=""
for candidate in \
    "$(command -v php 2>/dev/null)" \
    "/Applications/XAMPP/xamppfiles/bin/php" \
    "/usr/local/bin/php" \
    "/opt/homebrew/bin/php" \
    "/usr/bin/php"
do
    if [ -n "$candidate" ] && [ -x "$candidate" ]; then
        PHP_BIN="$candidate"
        break
    fi
done

if [ -z "$PHP_BIN" ]; then
    echo "✕ ABORT: php binary not found. Install PHP or add it to your PATH."
    exit 1
fi

echo "▶ Building LinkForge v${VERSION}..."
echo "▶ Using PHP: $PHP_BIN"

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
    --exclude='storage/backups/*' \
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

# --- Recreate placeholder folders that must exist after install ---
mkdir -p "$BUILD_DIR/storage/cache/links"
mkdir -p "$BUILD_DIR/storage/logs/mail"
mkdir -p "$BUILD_DIR/storage/backups"
mkdir -p "$BUILD_DIR/app/Libraries/phpqrcode/cache"
mkdir -p "$BUILD_DIR/app/Libraries/phpqrcode/temp"
touch "$BUILD_DIR/storage/cache/.gitkeep"
touch "$BUILD_DIR/storage/cache/links/.gitkeep"
touch "$BUILD_DIR/storage/logs/.gitkeep"
touch "$BUILD_DIR/storage/logs/mail/.gitkeep"
touch "$BUILD_DIR/storage/backups/.gitkeep"
touch "$BUILD_DIR/app/Libraries/phpqrcode/cache/.gitkeep"
touch "$BUILD_DIR/app/Libraries/phpqrcode/temp/.gitkeep"

# --- Sanity check: refuse to build if config.php leaked in ---
if [ -f "$BUILD_DIR/config/config.php" ]; then
    echo "✕ ABORT: config/config.php was copied into the build."
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

echo "▶ Building frontend assets..."

# --- CSS: comment-stripped + collapsed, with banner ---
"$PHP_BIN" -r '
$f = "'"$BUILD_DIR"'/public/assets/css/app.css";
if (file_exists($f)) {
    $css = file_get_contents($f);
    $css = preg_replace("!/\*[^*]*\*+([^/][^*]*\*+)*/!", "", $css);
    $css = str_replace(["\r\n", "\r", "\n", "\t"], "", $css);
    $css = preg_replace("/\s*([\{\}:;,])\s*/", "$1", $css);
    $css = str_replace(";}", "}", $css);
    $banner = "/*! LinkForge v'$VERSION' | AGPLv3 | https://github.com/sushantkumar-web/linkforge */\n";
    file_put_contents("'"$BUILD_DIR"'/public/assets/css/app.min.css", $banner . trim($css));
}
'

# --- JS: bundle all files into one, no regex minification ---
# Regex-based JS minification is fundamentally unsafe (breaks regex literals,
# strings containing //, template literals, and arrow functions).
# The web server's gzip/brotli compresses the bundle by 70-80% anyway.
JS_FILES="app.js search.js links.js"
JS_BANNER="/*! LinkForge v${VERSION} | AGPLv3 | https://github.com/sushantkumar-web/linkforge */"
JS_OUT="$BUILD_DIR/public/assets/js/app.bundle.js"
JS_TMP=""

for js in $JS_FILES; do
    src="$BUILD_DIR/public/assets/js/$js"
    if [ -f "$src" ]; then
        if [ -z "$JS_TMP" ]; then
            echo "$JS_BANNER" > "$JS_OUT"
        fi
        echo "" >> "$JS_OUT"
        echo "/* ---- $js ---- */" >> "$JS_OUT"
        cat "$src" >> "$JS_OUT"
        echo "" >> "$JS_OUT"
    fi
done

if [ -f "$JS_OUT" ]; then
    SIZE_RAW=$(wc -c < "$JS_OUT" | tr -d ' ')
    echo "▶ Bundled $JS_FILES into app.bundle.js ($SIZE_RAW bytes raw)"
fi

echo "▶ Assets compiled."

# --- Create release archive ---
cd "$BUILD_DIR"
zip -rq "../$ARCHIVE_NAME" . -x "*.DS_Store"
cd ..
rm -rf "$BUILD_DIR"

# --- Report ---
SIZE=$(du -h "$ARCHIVE_NAME" | cut -f1)
echo "✔ Build complete: ${ARCHIVE_NAME} (${SIZE})"

# --- Final guard ---
if unzip -l "$ARCHIVE_NAME" | awk '{print $4}' | grep -qE '^config/config\.php$|^storage/logs/mail/.*\.html$'; then
    echo "✕ WARNING: The archive appears to contain sensitive files. Do NOT publish."
    exit 1
fi
echo "✔ Archive is clean."