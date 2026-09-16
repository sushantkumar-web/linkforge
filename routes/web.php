<?php
// routes/web.php

if (!function_exists('flash')) {
    function flash($key, $value = null) {
        if ($value === null) {
            $val = $_SESSION['_flash'][$key] ?? null;
            unset($_SESSION['_flash'][$key]);
            return $val;
        }
        $_SESSION['_flash'][$key] = $value;
    }
}

$routes = [
    'GET' => [
        '/' => 'App\Controllers\DashboardController@index',
        '/install' => 'App\Controllers\InstallController@index',
        '/login' => 'App\Controllers\AuthController@login',
        '/analytics' => 'App\Controllers\AnalyticsController@view',
        '/analytics/export' => 'App\Controllers\AnalyticsController@export',
        '/api-keys' => 'App\Controllers\ApiKeyController@index', // Fixed to ApiKeyController
        '/settings' => 'App\Controllers\SettingsController@index',
        '/qr' => 'App\Controllers\QrController@render',
        '/users' => 'App\Controllers\UsersController@index',
        '/users/create' => 'App\Controllers\UsersController@create',
        '/logout' => 'App\Controllers\AuthController@logout',
        '/forgot-password' => 'App\Controllers\AuthController@showForgotForm',
        '/reset-password'  => 'App\Controllers\AuthController@showResetForm',
        '/search' => 'App\Controllers\SearchController@index',
        '/search/quick' => 'App\Controllers\SearchController@quick',
        '/tags' => 'App\Controllers\TagsController@index',
        '/links' => 'App\Controllers\LinkController@index',
        '/webhooks' => 'App\Controllers\WebhooksController@index',
        '/link' => 'App\Controllers\LinkController@show',
        '/settings/health' => 'App\Controllers\SettingsController@health',
        '/domains' => 'App\Controllers\DomainsController@index',
        '/utm-presets'       => 'App\Controllers\UtmController@index',
        '/api/utm-presets'   => 'App\Controllers\UtmController@apiList',
    ],
    'POST' => [
        '/install' => 'App\Controllers\InstallController@setup',
        '/login' => 'App\Controllers\AuthController@authenticate',
        '/links/create' => 'App\Controllers\LinkController@store',
        '/links/update' => 'App\Controllers\LinkController@update',
        '/links/toggle' => 'App\Controllers\LinkController@toggle',
        '/links/delete' => 'App\Controllers\LinkController@delete',
        '/api-keys/generate' => 'App\Controllers\ApiKeyController@generate', 
        '/api-keys/revoke' => 'App\Controllers\ApiKeyController@revoke',     
        '/api-keys/delete' => 'App\Controllers\ApiKeyController@delete',    
        '/settings/update' => 'App\Controllers\UpdateController@runMigrations',
        '/users/store' => 'App\Controllers\UsersController@store',
        '/users/update' => 'App\Controllers\UsersController@update',
        '/users/delete' => 'App\Controllers\UsersController@delete',
        '/forgot-password' => 'App\Controllers\AuthController@forgotPassword',
        '/reset-password'  => 'App\Controllers\AuthController@resetPassword',
        '/settings/email' => 'App\Controllers\SettingsController@saveEmail',
        '/settings/email/test' => 'App\Controllers\SettingsController@testEmail',
        '/tags/delete' => 'App\Controllers\TagsController@delete',
        '/links/bulk' => 'App\Controllers\LinkController@bulk',
        '/webhooks/store'  => 'App\Controllers\WebhooksController@store',
        '/webhooks/update' => 'App\Controllers\WebhooksController@update',
        '/webhooks/delete' => 'App\Controllers\WebhooksController@delete',
        '/webhooks/toggle' => 'App\Controllers\WebhooksController@toggle',
        '/webhooks/test'   => 'App\Controllers\WebhooksController@test',
        '/webhooks/retry'  => 'App\Controllers\WebhooksController@retry',
        '/domains/store'        => 'App\Controllers\DomainsController@store',
        '/domains/verify'       => 'App\Controllers\DomainsController@verify',
        '/domains/set-primary'  => 'App\Controllers\DomainsController@setPrimary',
        '/domains/delete'       => 'App\Controllers\DomainsController@delete',
        '/settings/security' => 'App\Controllers\SettingsController@saveSecurity',
        '/utm-presets/store'  => 'App\Controllers\UtmController@store',
        '/utm-presets/update' => 'App\Controllers\UtmController@update',
        '/utm-presets/delete' => 'App\Controllers\UtmController@delete',
        '/release-notes/dismiss' => 'App\Controllers\ReleaseNotesController@dismiss',
    ]
];

$baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);

// 1. Dedicated REST API Dispatcher
if (strpos($uri, '/api/v1/') !== false) {
    $apiUri = substr($uri, strpos($uri, '/api/v1/'));
    $api = new \App\Controllers\ApiController();
    $api->dispatch($method, $apiUri);
    exit;
}

// 2. Short Link Redirect Engine
$isWebPostRoute = ($method === 'POST' && array_key_exists($uri, $routes['POST']));

if (!array_key_exists($uri, $routes['GET']) && !$isWebPostRoute && $uri !== '/') {
    $slug = trim($uri, '/');
    $controller = new \App\Controllers\RedirectController();
    $controller->handle($slug);
    exit;
}

// 3. Web CSRF Protection Firewall
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
    exit; // CRITICAL: Stop execution after controller runs
} else {
    http_response_code(404);
    require BASE_PATH . '/resources/views/errors/404.php';
    exit; // CRITICAL: Stop execution after 404
}