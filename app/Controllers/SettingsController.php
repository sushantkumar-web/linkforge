<?php
namespace App\Controllers;

class SettingsController {
    public function index() {
        if (!isset($_SESSION['user_id'])) {
            $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
            header("Location: {$baseURL}/login");
            exit;
        }

        $current_version = '1.0.0';
        $latest_version = $current_version;
        $download_url = '';
        $release_notes = 'No updates available.';
        
// app/Controllers/SettingsController.php
$repo = 'sushantkumar-web/linkforge';
$apiUrl = "https://api.github.com/repos/{$repo}/releases/latest";

try {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 4);
    curl_setopt($ch, CURLOPT_USERAGENT, 'LinkForge-App');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/vnd.github.v3+json'
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200 && $response) {
        $release = json_decode($response, true);
        if (isset($release['tag_name'])) {
            $latest_version = ltrim($release['tag_name'], 'v');
            $release_notes = $release['body'] ?? 'Performance improvements and bug fixes.';
            $download_url = $release['zipball_url'] ?? '';
        }
    }
} catch (\Exception $e) {
    // Fail silently if offline or unauthenticated rate limit reached
}

        $update_available = version_compare($current_version, $latest_version, '<');
        
        $baseURL = "http://" . $_SERVER['HTTP_HOST'] . str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        require BASE_PATH . '/resources/views/settings.php';
    }
}