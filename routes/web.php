<?php
// routes/web.php

$routes = [
    'GET' => [
        '/' => 'App\Controllers\DashboardController@index',
        '/install' => 'App\Controllers\InstallController@index',
        '/login' => 'App\Controllers\AuthController@login',
        '/analytics' => 'App\Controllers\AnalyticsController@view',
        '/analytics/export' => 'App\Controllers\AnalyticsController@export',
        '/api-keys' => 'App\Controllers\DeveloperController@index',
        '/settings' => 'App\Controllers\SettingsController@index',
        '/qr' => 'App\Controllers\QrController@render',
    ],
    'POST' => [
        '/install' => 'App\Controllers\InstallController@setup',
        '/login' => 'App\Controllers\AuthController@authenticate',
        '/links/create' => 'App\Controllers\LinkController@store',
        '/links/update' => 'App\Controllers\LinkController@update',
        '/links/toggle' => 'App\Controllers\LinkController@toggle',
        '/links/delete' => 'App\Controllers\LinkController@delete',
        '/api-keys/create' => 'App\Controllers\DeveloperController@create',
        '/api-keys/revoke' => 'App\Controllers\DeveloperController@revoke',
        '/settings/update' => 'App\Controllers\UpdateController@runMigrations',
    ]
];

$baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);

// 1. Dedicated REST API Dispatcher (Catches /api/v1/ anywhere in the URI)
if (strpos($uri, '/api/v1/') !== false) {
    $apiUri = substr($uri, strpos($uri, '/api/v1/'));
    $api = new \App\Controllers\ApiController();
    $api->dispatch($method, $apiUri);
    exit;
}

// 2. Short Link Redirect Engine (Handles both GET visits and POST password unlocks)
$isWebPostRoute = ($method === 'POST' && array_key_exists($uri, $routes['POST']));

if (!array_key_exists($uri, $routes['GET']) && !$isWebPostRoute && $uri !== '/') {
    $slug = trim($uri, '/');
    $controller = new \App\Controllers\RedirectController();
    $controller->handle($slug);
    exit;
}

// 3. Web CSRF Protection Firewall (Protects dashboard POST routes only)
if ($method === 'POST' && strpos($uri, '/api/') === false && $uri !== '/install' && $isWebPostRoute) {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die("403 Forbidden: Invalid CSRF Token.");
    }
}

// 4. Standard Web Controller Execution
if (isset($routes[$method][$uri])) {
    list($controller, $action) = explode('@', $routes[$method][$uri]);
    $instance = new $controller();
    $instance->$action();
} else {
    http_response_code(404);
    require BASE_PATH . '/resources/views/errors/404.php';
}