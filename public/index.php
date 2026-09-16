<?php
date_default_timezone_set('Asia/Kolkata');
// Report all PHP errors
error_reporting(E_ALL);

// Display errors on the screen
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
define('LINKFORGE_START', microtime(true));
define('APP_VERSION', '1.2.3');
// 1. Hardened Session Settings (Cloudflare & Subpath Aware)
$isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// 2. Global CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 3. Global Security Headers
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

// 4. Output Minifier
function minify_html($buffer) {
    // 1. Protect <script> and <style> blocks from any modification.
    //    Minifying inline JS with regex is unreliable and breaks pages.
    $protected = [];
    $buffer = preg_replace_callback(
        '#<(script|style)\b[^>]*>.*?</\1>#is',
        function ($m) use (&$protected) {
            $key = "\x00LF_PROTECTED_" . count($protected) . "\x00";
            $protected[$key] = $m[0];
            return $key;
        },
        $buffer
    );

    // 2. Safe whitespace collapsing on the remaining HTML.
    $search = [
        '/\>[^\S ]+/s',   // strip whitespace after tags
        '/[^\S ]+\</s',   // strip whitespace before tags
        '/(\s)+/s',       // collapse multiple whitespace
    ];
    $replace = ['>', '<', '\\1'];
    $buffer = preg_replace($search, $replace, $buffer);
    // 3. Restore the protected blocks.
    if (!empty($protected)) {
        $buffer = strtr($buffer, $protected);
    }
    return $buffer;
}
ob_start("minify_html");

define('BASE_PATH', dirname(__DIR__));

// 5. PSR-4 Autoloader (Case-sensitive Linux safe)
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = BASE_PATH . '/app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// 6. URL Normalization & Routing
// 6. URL Normalization & Routing
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Strip /public prefix if accessed directly
$uri = preg_replace('#^/public#', '', $uri);

$baseDir = dirname($_SERVER['SCRIPT_NAME']);
if ($baseDir !== '/' && strpos($uri, $baseDir) === 0) {
    $uri = substr($uri, strlen($baseDir));
}
$uri = '/' . trim($uri, '/');
$method = $_SERVER['REQUEST_METHOD'];

// Auto-run pending migrations in local development only.
// In production this does nothing (define APP_ENV=production in config).
$isLocal = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', 'localhost:80'], true)
        || str_starts_with($_SERVER['HTTP_HOST'] ?? '', 'localhost')
        || str_starts_with($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1');

if ($isLocal && file_exists(BASE_PATH . '/config/config.php')) {
    try {
        \App\Core\MigrationRunner::run();
    } catch (\Throwable $e) {
        error_log('[Auto-migrate] ' . $e->getMessage());
    }
}

require BASE_PATH . '/routes/web.php';