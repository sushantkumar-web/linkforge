<?php
namespace App\Controllers;

use App\Core\Database;
use PDO;

class DashboardController {
    public function index() {
        if (!isset($_SESSION['user_id'])) {
            $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
            header("Location: {$baseURL}/login");
            exit;
        }
        
        $pdo = Database::getInstance();
        $user_id = $_SESSION['user_id'];
        
        // Fetch KPIs
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(id) as total_links,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_links,
                SUM(clicks) as total_clicks
            FROM links 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        $kpis = $stmt->fetch();

        // Calculate Unique Visitors across all links owned by this user
        $stmtUnique = $pdo->prepare("
            SELECT COUNT(DISTINCT cl.visitor_hash) as unique_visitors 
            FROM click_logs cl
            JOIN links l ON cl.link_id = l.id
            WHERE l.user_id = ?
        ");
        $stmtUnique->execute([$user_id]);
        $unique_visitors = $stmtUnique->fetchColumn() ?: 0;
        
        $total_links = $kpis['total_links'] ?? 0;
        $active_links = $kpis['active_links'] ?? 0;
        $total_clicks = $kpis['total_clicks'] ?? 0;
        
        // Fetch Recent Links
        $stmt = $pdo->prepare("SELECT * FROM links WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
        $stmt->execute([$user_id]);
        $recent_links = $stmt->fetchAll();
        
        // Base URL for the copy button
        $baseURL = "http://" . $_SERVER['HTTP_HOST'] . str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        
        require BASE_PATH . '/resources/views/dashboard.php';
    }
}