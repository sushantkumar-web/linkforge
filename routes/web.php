<?php
$routes = [
    'GET' => [
        '/' => 'App\Controllers\DashboardController@index',
        '/install' => 'App\Controllers\InstallController@index',
        '/login' => 'App\Controllers\AuthController@login',
        '/analytics' => 'App\Controllers\AnalyticsController@view',
        '/api-keys' => 'App\Controllers\DeveloperController@index',
        '/settings' => 'App\Controllers\SettingsController@index',
    ],
    'POST' => [
        '/login' => 'App\Controllers\AuthController@authenticate',
        '/links/create' => 'App\Controllers\LinkController@store',
        '/links/toggle' => 'App\Controllers\LinkController@toggle',
        '/links/delete' => 'App\Controllers\LinkController@delete',
        '/api/v1/links' => 'App\Controllers\ApiController@createLink',
        '/api-keys/create' => 'App\Controllers\DeveloperController@create',
        '/api-keys/revoke' => 'App\Controllers\DeveloperController@revoke',
        '/settings/update' => 'App\Controllers\UpdateController@runMigrations',
    ]
];

$baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);

// The Redirect Engine Trap
if (!array_key_exists($uri, $routes['GET']) && $method === 'GET' && $uri !== '/') {
    $slug = trim($uri, '/');
    $controller = new \App\Controllers\RedirectController();
    $controller->handle($slug);
    exit;
}

// CSRF Protection Firewall (Applies to all POST routes except the API)
if ($method === 'POST' && strpos($uri, '/api/') !== 0) {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die("403 Forbidden: Invalid CSRF Token. Please refresh the page and try again.");
    }
}
// Standard Router
if (isset($routes[$method][$uri])) {
    list($controller, $action) = explode('@', $routes[$method][$uri]);
    $instance = new $controller();
    $instance->$action();
} else {
    http_response_code(404);
    echo "404 - Not Found";
}