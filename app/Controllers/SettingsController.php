<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Mailer;

class SettingsController {

    public function index() {
        if (!isset($_SESSION['user_id'])) {
            $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
            header("Location: {$baseURL}/login");
            exit;
        }

        $current_version = defined('APP_VERSION') ? APP_VERSION : '1.0.2';
        $latest_version = $current_version;
        $download_url = '';
        $release_notes = 'No updates available.';

        // Check GitHub for latest release
        $repo = 'sushantkumar-web/linkforge';
        $apiUrl = "https://api.github.com/repos/{$repo}/releases/latest";

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 4);
            curl_setopt($ch, CURLOPT_USERAGENT, 'LinkForge-App');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/vnd.github.v3+json']);
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
            // Fail silently if offline or rate-limited
        }

        $update_available = version_compare($current_version, $latest_version, '<');

        // Load existing settings for the Email tab
        $pdo = Database::getInstance();
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
        $settings = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        $baseURL = "http://" . $_SERVER['HTTP_HOST'] . str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        require BASE_PATH . '/resources/views/settings.php';
    }

    public function saveEmail() {
        if (!in_array($_SESSION['role'] ?? 'user', ['super_admin', 'admin'])) {
            http_response_code(403);
            die('Forbidden');
        }

        $pdo = Database::getInstance();

        $fields = [
            'mail_driver' => $_POST['mail_driver'] ?? 'log',
            'smtp_host'   => trim($_POST['smtp_host'] ?? ''),
            'smtp_port'   => (string)((int)($_POST['smtp_port'] ?? 587)),
            'smtp_user'   => trim($_POST['smtp_user'] ?? ''),
            'smtp_secure' => $_POST['smtp_secure'] ?? 'tls',
            'from_email'  => trim($_POST['from_email'] ?? ''),
            'from_name'   => trim($_POST['from_name'] ?? 'LinkForge'),
        ];

        // Only update password if the user typed a new one
        if (!empty($_POST['smtp_pass'])) {
            $fields['smtp_pass'] = $_POST['smtp_pass'];
        }

        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        foreach ($fields as $k => $v) {
            $stmt->execute([$k, $v]);
        }

        flash('success', 'Email settings saved.');
        $this->redirect('/settings?tab=email');
    }

    public function testEmail() {
        if (!in_array($_SESSION['role'] ?? 'user', ['super_admin', 'admin'])) {
            http_response_code(403);
            die('Forbidden');
        }

        header('Content-Type: application/json');

        $to = filter_var($_POST['test_email'] ?? '', FILTER_VALIDATE_EMAIL);
        if (!$to) {
            echo json_encode(['ok' => false, 'error' => 'Invalid email address.']);
            exit;
        }

        $subject = 'LinkForge test email';
        $body = "
            <p>This is a test email from your LinkForge installation.</p>
            <p>If you're reading this, your email configuration is working correctly.</p>
            <p style='color:#6B7280;font-size:12px;'>Sent at " . date('Y-m-d H:i:s') . "</p>
        ";

        $result = Mailer::send($to, $subject, $body);

        if ($result === true) {
            echo json_encode(['ok' => true, 'message' => 'Test email sent. Check your inbox (or storage/logs/mail/ if using the log driver).']);
        } else {
            echo json_encode(['ok' => false, 'error' => $result]);
        }
        exit;
    }

    private function redirect(string $path): void {
        $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        header('Location: ' . $baseURL . $path);
        exit;
    }
}