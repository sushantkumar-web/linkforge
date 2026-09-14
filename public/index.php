<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// 1. Hardened Session Settings
session_set_cookie_params([
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

// 2. Generate a Global CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 3. Global Security Headers
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

// public/index.php
define('BASE_PATH', dirname(__DIR__));

// 4. Output Minifier (Strips whitespace and comments for extreme speed)
function minify_html($buffer) {
    $search = [
        '/\>[^\S ]+/s',     // Strip whitespaces after tags
        '/[^\S ]+\</s',     // Strip whitespaces before tags
        '/(\s)+/s',         // Shorten multiple whitespace sequences
        '/<!--(.|\s)*?-->/' // Remove HTML comments
    ];
    $replace = ['>', '<', '\\1', ''];
    return preg_replace($search, $replace, $buffer);
}
ob_start("minify_html");

// 1. Get the exact URI cleanly from our Apache rewrite rule
$uri = isset($_GET['uri']) ? '/' . trim($_GET['uri'], '/') : '/';

// 2. The Installer Trap
if (!file_exists(BASE_PATH . '/config/config.php')) {
    if ($uri !== '/install') {
        // Dynamically find the base path (handles localhost subfolders gracefully)
        $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        header("Location: {$baseURL}/install");
        exit;
    }
}

// 3. Autoloader for our custom MVC
spl_autoload_register(function ($class) {
    $path = BASE_PATH . '/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($path)) {
        require $path;
    }
});

// 4. Execute Routing
$method = $_SERVER['REQUEST_METHOD'];
require BASE_PATH . '/routes/web.php';