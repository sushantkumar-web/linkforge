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
    $userId = $_SESSION['user_id'];
    $role = $_SESSION['role'] ?? 'user';
    $isSuperAdmin = ($role === 'super_admin');
    $filter = $_GET['filter'] ?? 'all';
    $tag = trim($_GET['tag'] ?? '');
    $tag = ($tag === 'all') ? '' : $tag;

    // ---------------------------------------------------------
    // Build the WHERE fragment once, reuse for KPIs and table
    // ---------------------------------------------------------
    $scopeSql = $isSuperAdmin ? '' : ' AND l.user_id = ?';
    $scopeParams = $isSuperAdmin ? [] : [$userId];

    $tagSql = '';
    $tagParams = [];

    if ($tag !== '') {
        // Filter by tag name — scoped to the user's own tags unless super admin
        if ($isSuperAdmin) {
            $tagSql = " AND l.id IN (SELECT lt.link_id FROM link_tags lt JOIN tags t ON lt.tag_id = t.id WHERE t.name = ?)";
            $tagParams[] = $tag;
        } else {
            $tagSql = " AND l.id IN (SELECT lt.link_id FROM link_tags lt JOIN tags t ON lt.tag_id = t.id WHERE t.user_id = ? AND t.name = ?)";
            $tagParams[] = $userId;
            $tagParams[] = $tag;
        }
    }

    $filterSql = '';
    if ($filter === 'active') {
        $filterSql = " AND l.status = 'active' AND (l.expires_at IS NULL OR l.expires_at > NOW())";
    } elseif ($filter === 'disabled') {
        $filterSql = " AND l.status = 'disabled'";
    } elseif ($filter === 'expired') {
        $filterSql = " AND l.expires_at IS NOT NULL AND l.expires_at <= NOW()";
    }

    // ---------------------------------------------------------
    // KPI cards — reflect the current tag/filter scope
    // ---------------------------------------------------------
    $kpiSql = "
        SELECT
            COUNT(l.id) AS total_links,
            SUM(CASE WHEN l.status = 'active' AND (l.expires_at IS NULL OR l.expires_at > NOW()) THEN 1 ELSE 0 END) AS active_links,
            COALESCE(SUM(l.clicks), 0) AS total_clicks
        FROM links l
        WHERE 1=1 {$scopeSql} {$tagSql} {$filterSql}
    ";
    $stmt = $pdo->prepare($kpiSql);
    $stmt->execute(array_merge($scopeParams, $tagParams));
    $kpis = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

    $total_links  = (int)($kpis['total_links']  ?? 0);
    $active_links = (int)($kpis['active_links'] ?? 0);
    $total_clicks = (int)($kpis['total_clicks'] ?? 0);

    // Unique visitors (respects tag scope)
    $uniqueSql = "
        SELECT COUNT(DISTINCT cl.visitor_hash)
        FROM click_logs cl
        JOIN links l ON cl.link_id = l.id
        WHERE 1=1 {$scopeSql} {$tagSql} {$filterSql}
    ";
    $stmt = $pdo->prepare($uniqueSql);
    $stmt->execute(array_merge($scopeParams, $tagParams));
    $unique_visitors = (int)$stmt->fetchColumn();

    // ---------------------------------------------------------
    // Links table
    // ---------------------------------------------------------
    $tableSql = "
        SELECT l.*,
               GROUP_CONCAT(DISTINCT t.name SEPARATOR ',') AS tags,
               u.email AS owner_email
        FROM links l
        LEFT JOIN link_tags lt ON l.id = lt.link_id
        LEFT JOIN tags t ON lt.tag_id = t.id
        LEFT JOIN users u ON l.user_id = u.id
        WHERE 1=1 {$scopeSql} {$tagSql} {$filterSql}
        GROUP BY l.id
        ORDER BY l.created_at DESC
        LIMIT 50
    ";
    $stmt = $pdo->prepare($tableSql);
    $stmt->execute(array_merge($scopeParams, $tagParams));
    $recent_links = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // Active tag for the view
    $activeTag = $tag;

    $baseURL = "http://" . $_SERVER['HTTP_HOST'] . str_replace('/index.php', '', $_SERVER['PHP_SELF']);
    require BASE_PATH . '/resources/views/dashboard.php';
}
}