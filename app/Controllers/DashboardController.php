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

        $scopeSql = $isSuperAdmin ? '' : ' AND l.user_id = ?';
        $scopeParams = $isSuperAdmin ? [] : [$userId];

        // ==== KPIs (all-time) ====
        $stmt = $pdo->prepare("
            SELECT
                COUNT(l.id) AS total_links,
                SUM(CASE WHEN l.status = 'active' AND (l.expires_at IS NULL OR l.expires_at > NOW()) THEN 1 ELSE 0 END) AS active_links,
                COALESCE(SUM(l.clicks), 0) AS total_clicks
            FROM links l
            WHERE 1=1 {$scopeSql}
        ");
        $stmt->execute($scopeParams);
        $kpis = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $total_links  = (int)($kpis['total_links']  ?? 0);
        $active_links = (int)($kpis['active_links'] ?? 0);
        $total_clicks = (int)($kpis['total_clicks'] ?? 0);

        // Unique visitors all-time
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT cl.visitor_hash)
            FROM click_logs cl
            JOIN links l ON cl.link_id = l.id
            WHERE 1=1 {$scopeSql}
        ");
        $stmt->execute($scopeParams);
        $unique_visitors = (int)$stmt->fetchColumn();

        // ==== Clicks: today / this week / last week (for trend) ====
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM click_logs cl
            JOIN links l ON cl.link_id = l.id
            WHERE DATE(cl.clicked_at) = CURDATE() {$scopeSql}
        ");
        $stmt->execute($scopeParams);
        $today_clicks = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM click_logs cl
            JOIN links l ON cl.link_id = l.id
            WHERE cl.clicked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) {$scopeSql}
        ");
        $stmt->execute($scopeParams);
        $week_clicks = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM click_logs cl
            JOIN links l ON cl.link_id = l.id
            WHERE cl.clicked_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
              AND cl.clicked_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
              {$scopeSql}
        ");
        $stmt->execute($scopeParams);
        $prev_week_clicks = (int)$stmt->fetchColumn();

        $weekTrend = 0;
        if ($prev_week_clicks > 0) {
            $weekTrend = round((($week_clicks - $prev_week_clicks) / $prev_week_clicks) * 100, 1);
        } elseif ($week_clicks > 0) {
            $weekTrend = 100;
        }

        // ==== 30-day timeline ====
        $stmt = $pdo->prepare("
            SELECT DATE(cl.clicked_at) AS d, COUNT(*) AS c
            FROM click_logs cl
            JOIN links l ON cl.link_id = l.id
            WHERE cl.clicked_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) {$scopeSql}
            GROUP BY DATE(cl.clicked_at)
        ");
        $stmt->execute($scopeParams);
        $rawHistory = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $timeline = [];
        $maxTimeline = 1;
        for ($i = 29; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $count = (int)($rawHistory[$date] ?? 0);
            if ($count > $maxTimeline) $maxTimeline = $count;
            $timeline[] = [
                'label' => date('M j', strtotime($date)),
                'count' => $count,
            ];
        }

        // ==== Top 5 performing links ====
        $stmt = $pdo->prepare("
            SELECT l.id, l.short_code, l.title, l.destination_url, l.clicks, l.status, l.expires_at
            FROM links l
            WHERE 1=1 {$scopeSql}
            ORDER BY l.clicks DESC
            LIMIT 5
        ");
        $stmt->execute($scopeParams);
        $top_links = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $topMax = 1;
        foreach ($top_links as $tl) {
            if ((int)$tl['clicks'] > $topMax) $topMax = (int)$tl['clicks'];
        }

        // ==== Recent activity (last 12 clicks across all links) ====
        $stmt = $pdo->prepare("
            SELECT cl.clicked_at, cl.referrer, cl.device_type, cl.browser, cl.os,
                   l.short_code, l.id AS link_id
            FROM click_logs cl
            JOIN links l ON cl.link_id = l.id
            WHERE 1=1 {$scopeSql}
            ORDER BY cl.clicked_at DESC
            LIMIT 12
        ");
        $stmt->execute($scopeParams);
        $recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ==== Top traffic sources ====
        $stmt = $pdo->prepare("
            SELECT COALESCE(NULLIF(cl.referrer, ''), 'Direct') AS name, COUNT(*) AS count
            FROM click_logs cl
            JOIN links l ON cl.link_id = l.id
            WHERE 1=1 {$scopeSql}
            GROUP BY name
            ORDER BY count DESC
            LIMIT 5
        ");
        $stmt->execute($scopeParams);
        $top_referrers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ==== Devices breakdown ====
        $stmt = $pdo->prepare("
            SELECT COALESCE(NULLIF(cl.device_type, ''), 'Unknown') AS name, COUNT(*) AS count
            FROM click_logs cl
            JOIN links l ON cl.link_id = l.id
            WHERE 1=1 {$scopeSql}
            GROUP BY name
            ORDER BY count DESC
        ");
        $stmt->execute($scopeParams);
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $baseURL = "http://" . $_SERVER['HTTP_HOST'] . str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        require BASE_PATH . '/resources/views/dashboard.php';
    }
}