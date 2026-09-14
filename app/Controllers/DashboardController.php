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
        $filter = $_GET['filter'] ?? 'all';

        // KPIs
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(id) as total_links,
                SUM(CASE WHEN status = 'active' AND (expires_at IS NULL OR expires_at > NOW()) THEN 1 ELSE 0 END) as active_links,
                SUM(clicks) as total_clicks
            FROM links 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        $kpis = $stmt->fetch();
        
        $total_links = $kpis['total_links'] ?? 0;
        $active_links = $kpis['active_links'] ?? 0;
        $total_clicks = $kpis['total_clicks'] ?? 0;
        
        $stmtUnique = $pdo->prepare("
            SELECT COUNT(DISTINCT cl.visitor_hash) 
            FROM click_logs cl 
            JOIN links l ON cl.link_id = l.id 
            WHERE l.user_id = ?
        ");
        $stmtUnique->execute([$user_id]);
        $unique_visitors = $stmtUnique->fetchColumn() ?: 0;

        // Apply Tab Filter
        // Apply Tab Filter
        $sql = "
            SELECT l.*, GROUP_CONCAT(t.name SEPARATOR ',') as tags
            FROM links l
            LEFT JOIN link_tags lt ON l.id = lt.link_id
            LEFT JOIN tags t ON lt.tag_id = t.id
            WHERE l.user_id = ?
        ";
        
        if ($filter === 'active') {
            $sql .= " AND l.status = 'active' AND (l.expires_at IS NULL OR l.expires_at > NOW())";
        } elseif ($filter === 'disabled') {
            $sql .= " AND l.status = 'disabled'";
        } elseif ($filter === 'expired') {
            $sql .= " AND l.expires_at IS NOT NULL AND l.expires_at <= NOW()";
        }

        // Group by link ID so GROUP_CONCAT doesn't collapse rows into a single NULL record
        $sql .= " GROUP BY l.id ORDER BY l.created_at DESC LIMIT 50";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $recent_links = $stmt->fetchAll();

        $baseURL = "http://" . $_SERVER['HTTP_HOST'] . str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        require BASE_PATH . '/resources/views/dashboard.php';
    }
}