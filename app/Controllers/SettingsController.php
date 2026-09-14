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
        
        // 1. Ping the Central Server (Using a mock JSON for local testing)
        // In production, this would be: https://api.github.com/repos/yourusername/linkforge/releases/latest
        // Or your own telemetry server: https://update.thesushant.in/check
        
        try {
            $ch = curl_init();
            // Simulating your central server response with a public test endpoint
            curl_setopt($ch, CURLOPT_URL, 'https://run.mocky.io/v3/9b4eb995-167f-4b08-8f5b-610118eb3d05'); 
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_setopt($ch, CURLOPT_USERAGENT, 'LinkForge-Updater');
            $response = curl_exec($ch);
            curl_close($ch);

            if ($response) {
                $server_data = json_decode($response, true);
                if (isset($server_data['version'])) {
                    $latest_version = $server_data['version'];
                    $download_url = $server_data['download_url'];
                    $release_notes = $server_data['notes'];
                }
            }
        } catch (\Exception $e) {
            // Fail silently if server is offline, don't break the user's dashboard
        }

        $update_available = version_compare($current_version, $latest_version, '<');
        
        $baseURL = "http://" . $_SERVER['HTTP_HOST'] . str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        require BASE_PATH . '/resources/views/settings.php';
    }
}