<?php
namespace App\Controllers;

use App\Core\Database;

class ApiKeyController {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();

        if (empty($_SESSION['user_id'])) {
            header('Location: ' . str_replace('/index.php', '', $_SERVER['PHP_SELF']) . '/login');
            exit;
        }
    }

    public function index() {
        $userId = $_SESSION['user_id'];
        $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);

        // 1. Fetch Metrics
        $stmt = $this->db->prepare("SELECT COUNT(*) as total_keys, SUM(status='active') as active_keys, SUM(total_requests) as total_calls FROM api_keys WHERE user_id = ?");
        $stmt->execute([$userId]);
        $metrics = $stmt->fetch(\PDO::FETCH_ASSOC);

        // 2. Fetch API Keys
        $stmt = $this->db->prepare("SELECT * FROM api_keys WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        $keys = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // 3. Fetch Recent Telemetry Logs
        $stmt = $this->db->prepare("
            SELECT l.*, k.name as key_name, k.key_prefix 
            FROM api_request_logs l 
            JOIN api_keys k ON l.api_key_id = k.id 
            WHERE k.user_id = ? 
            ORDER BY l.created_at DESC LIMIT 15
        ");
        $stmt->execute([$userId]);
        $recentLogs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // 4. One-time token display
        $newKeyPlain = $_SESSION['new_api_key'] ?? null;
        unset($_SESSION['new_api_key']);

        require BASE_PATH . '/resources/views/api-keys.php';
    }

    public function generate() {
        $userId = $_SESSION['user_id'];
        $role = $_SESSION['role'] ?? 'user';
        $name = trim($_POST['name'] ?? 'Untitled Key');
        $scopes = isset($_POST['scopes']) ? implode(',', $_POST['scopes']) : 'links:read';
        $rateLimit = (int)($_POST['rate_limit_rpm'] ?? 60);

        // Role-based rate limit cap
        $maxAllowed = match($role) {
            'super_admin' => 1000,
            'admin'       => 500,
            default       => 120,
        };
        if ($rateLimit > $maxAllowed) $rateLimit = $maxAllowed;
        if ($rateLimit < 10) $rateLimit = 10;

        // Generate raw key
        $rawKey = 'lf_live_' . bin2hex(random_bytes(24));
        $keyPrefix = substr($rawKey, 0, 16) . '...';
        $hashedKey = hash('sha256', $rawKey);

        $stmt = $this->db->prepare("INSERT INTO api_keys (user_id, name, key_prefix, hashed_key, scopes, rate_limit_rpm) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $name, $keyPrefix, $hashedKey, $scopes, $rateLimit]);

        $_SESSION['new_api_key'] = [
            'name'  => $name,
            'token' => $rawKey,
        ];

        $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        header('Location: ' . $baseURL . '/api-keys');
        exit;
    }

    public function revoke() {
        $keyId = (int)($_POST['key_id'] ?? 0);
        $userId = $_SESSION['user_id'];

        $stmt = $this->db->prepare("UPDATE api_keys SET status = IF(status = 'active', 'revoked', 'active') WHERE id = ? AND user_id = ?");
        $stmt->execute([$keyId, $userId]);

        $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        header('Location: ' . $baseURL . '/api-keys');
        exit;
    }

    public function delete() {
        $keyId = (int)($_POST['key_id'] ?? 0);
        $userId = $_SESSION['user_id'];

        $stmt = $this->db->prepare("DELETE FROM api_keys WHERE id = ? AND user_id = ?");
        $stmt->execute([$keyId, $userId]);

        $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        header('Location: ' . $baseURL . '/api-keys');
        exit;
    }
}