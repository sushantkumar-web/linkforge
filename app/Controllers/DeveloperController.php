<?php
namespace App\Controllers;

use App\Core\Database;
use PDO;

class DeveloperController {
    public function index() {
        if (!isset($_SESSION['user_id'])) {
            $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
            header("Location: {$baseURL}/login");
            exit;
        }
        
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("SELECT * FROM api_keys WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$_SESSION['user_id']]);
        $api_keys = $stmt->fetchAll();
        
        $baseURL = "http://" . $_SERVER['HTTP_HOST'] . str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        require BASE_PATH . '/resources/views/developer_api.php';
    }

    public function create() {
        if (!isset($_SESSION['user_id'])) die("Unauthorized");
        
        $new_key = 'lf_prod_' . bin2hex(random_bytes(16));
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("INSERT INTO api_keys (user_id, api_key) VALUES (?, ?)");
        $stmt->execute([$_SESSION['user_id'], $new_key]);
        
        $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        header("Location: {$baseURL}/api-keys");
        exit;
    }

    public function revoke() {
        if (!isset($_SESSION['user_id'])) die("Unauthorized");
        $id = $_POST['key_id'] ?? 0;
        
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("DELETE FROM api_keys WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
        
        $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        header("Location: {$baseURL}/api-keys");
        exit;
    }
}