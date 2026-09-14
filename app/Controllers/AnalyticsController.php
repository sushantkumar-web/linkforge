<?php
namespace App\Controllers;

use App\Core\Database;
use PDO;

class AnalyticsController {
    public function view() {
        if (!isset($_SESSION['user_id'])) {
            $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
            header("Location: {$baseURL}/login");
            exit;
        }
        
        $link_id = $_GET['id'] ?? 0;
        $pdo = Database::getInstance();
        
        // 1. Fetch Link Details (Security check: must belong to user)
        $stmt = $pdo->prepare("SELECT * FROM links WHERE id = ? AND user_id = ?");
        $stmt->execute([$link_id, $_SESSION['user_id']]);
        $link = $stmt->fetch();
        
        if (!$link) {
            die("Link not found or unauthorized.");
        }
        
        // 2. Fetch Devices
        $stmtDev = $pdo->prepare("SELECT device_type as name, COUNT(*) as count FROM click_logs WHERE link_id = ? GROUP BY device_type ORDER BY count DESC");
        $stmtDev->execute([$link_id]);
        $devices = $stmtDev->fetchAll();
        
        // 3. Fetch Browsers
        $stmtBrowser = $pdo->prepare("SELECT browser as name, COUNT(*) as count FROM click_logs WHERE link_id = ? GROUP BY browser ORDER BY count DESC");
        $stmtBrowser->execute([$link_id]);
        $browsers = $stmtBrowser->fetchAll();
        
        // 4. Fetch Referrers
        $stmtRef = $pdo->prepare("SELECT referrer as name, COUNT(*) as count FROM click_logs WHERE link_id = ? GROUP BY referrer ORDER BY count DESC LIMIT 5");
        $stmtRef->execute([$link_id]);
        $referrers = $stmtRef->fetchAll();
        
        $baseURL = "http://" . $_SERVER['HTTP_HOST'] . str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        
        require BASE_PATH . '/resources/views/analytics.php';
    }
}