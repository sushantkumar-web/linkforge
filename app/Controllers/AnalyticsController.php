<?php
namespace App\Controllers;

use App\Core\Database;
use PDO;

class AnalyticsController {

    /**
     * GET /analytics          → global aggregate analytics
     * GET /analytics?id=X     → per-link analytics
     */
    public function view() {
        if (empty($_SESSION['user_id'])) {
            $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
            header("Location: {$baseURL}/login");
            exit;
        }

        $linkId = (int)($_GET['id'] ?? 0);

        if ($linkId) {
            $this->renderLinkAnalytics($linkId);
        } else {
            $this->renderGlobalAnalytics();
        }
    }

    // ---------------------------------------------------------
    // GLOBAL ANALYTICS (all links)
    // ---------------------------------------------------------
    private function renderGlobalAnalytics() {
        $pdo = Database::getInstance();
        $userId = $_SESSION['user_id'];
        $role = $_SESSION['role'] ?? 'user';
        $isSuperAdmin = ($role === 'super_admin');

        $range = $_GET['range'] ?? '30d';
        [$days, $bucket] = $this->parseRange($range);

        // Base WHERE clause — super admin sees everything, users see their own
        $scope = $isSuperAdmin ? '' : ' AND l.user_id = ?';
        $scopeParams = $isSuperAdmin ? [] : [$userId];

        // 1. KPI cards (over the selected range)
        $dateFilter = $days > 0 ? " AND cl.clicked_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)" : '';

        $kpiSql = "
            SELECT
                COUNT(cl.id) AS total_clicks,
                COUNT(DISTINCT cl.visitor_hash) AS unique_visitors
            FROM click_logs cl
            JOIN links l ON cl.link_id = l.id
            WHERE 1=1 {$scope} {$dateFilter}
        ";
        $stmt = $pdo->prepare($kpiSql);
        $stmt->execute($scopeParams);
        $kpis = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $total_clicks = (int)($kpis['total_clicks'] ?? 0);
        $unique_visitors = (int)($kpis['unique_visitors'] ?? 0);

        // Count links
        if ($isSuperAdmin) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM links");
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM links WHERE user_id = ?");
            $stmt->execute([$userId]);
        }
        $total_links = (int)$stmt->fetchColumn();

        $avg_clicks = $total_links > 0 ? (int)round($total_clicks / $total_links) : 0;

        // 2. Timeline
        $bucketExpr = ($bucket === 'hour')
            ? "DATE_FORMAT(cl.clicked_at, '%Y-%m-%d %H:00:00')"
            : "DATE(cl.clicked_at)";

        $timelineSql = "
            SELECT {$bucketExpr} AS bucket, COUNT(*) AS cnt
            FROM click_logs cl
            JOIN links l ON cl.link_id = l.id
            WHERE 1=1 {$scope} {$dateFilter}
            GROUP BY bucket
            ORDER BY bucket ASC
        ";
        $stmt = $pdo->prepare($timelineSql);
        $stmt->execute($scopeParams);
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $timeline = $this->fillTimeline($rows, $days, $bucket);
        $maxBucket = 1;
        foreach ($timeline as $t) {
            if ($t['count'] > $maxBucket) $maxBucket = $t['count'];
        }

        // 3. Top performing links (top 10 by clicks in range)
        $topSql = "
            SELECT l.id, l.short_code, l.title, l.destination_url,
                   COUNT(cl.id) AS range_clicks
            FROM links l
            LEFT JOIN click_logs cl
                ON cl.link_id = l.id
                " . ($days > 0 ? "AND cl.clicked_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)" : "") . "
            WHERE 1=1 {$scope}
            GROUP BY l.id
            ORDER BY range_clicks DESC, l.clicks DESC
            LIMIT 10
        ";
        $stmt = $pdo->prepare($topSql);
        $stmt->execute($scopeParams);
        $topLinks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 4. Traffic sources
        $refSql = "
            SELECT COALESCE(NULLIF(cl.referrer, ''), 'Direct') AS name, COUNT(*) AS count
            FROM click_logs cl
            JOIN links l ON cl.link_id = l.id
            WHERE 1=1 {$scope} {$dateFilter}
            GROUP BY name
            ORDER BY count DESC
            LIMIT 6
        ";
        $stmt = $pdo->prepare($refSql);
        $stmt->execute($scopeParams);
        $referrers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 5. Devices
        $devSql = "
            SELECT COALESCE(NULLIF(cl.device_type, ''), 'Unknown') AS name, COUNT(*) AS count
            FROM click_logs cl
            JOIN links l ON cl.link_id = l.id
            WHERE 1=1 {$scope} {$dateFilter}
            GROUP BY name
            ORDER BY count DESC
        ";
        $stmt = $pdo->prepare($devSql);
        $stmt->execute($scopeParams);
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 6. Browsers
        $browSql = "
            SELECT COALESCE(NULLIF(cl.browser, ''), 'Unknown') AS name, COUNT(*) AS count
            FROM click_logs cl
            JOIN links l ON cl.link_id = l.id
            WHERE 1=1 {$scope} {$dateFilter}
            GROUP BY name
            ORDER BY count DESC
            LIMIT 6
        ";
        $stmt = $pdo->prepare($browSql);
        $stmt->execute($scopeParams);
        $browsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 7. OS breakdown
        $osSql = "
            SELECT COALESCE(NULLIF(cl.os, ''), 'Unknown') AS name, COUNT(*) AS count
            FROM click_logs cl
            JOIN links l ON cl.link_id = l.id
            WHERE 1=1 {$scope} {$dateFilter}
            GROUP BY name
            ORDER BY count DESC
            LIMIT 6
        ";
        $stmt = $pdo->prepare($osSql);
        $stmt->execute($scopeParams);
        $oses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $baseURL = "http://" . $_SERVER['HTTP_HOST'] . str_replace('/index.php', '', $_SERVER['PHP_SELF']);

        // Make vars available to view
        $range = $range;
        require BASE_PATH . '/resources/views/analytics-global.php';
    }

    // ---------------------------------------------------------
    // PER-LINK ANALYTICS (existing behavior)
    // ---------------------------------------------------------
    private function renderLinkAnalytics(int $linkId) {
        $pdo = Database::getInstance();
        $userId = $_SESSION['user_id'];
        $role = $_SESSION['role'] ?? 'user';
        $isSuperAdmin = ($role === 'super_admin');

        // Ownership check
        if ($isSuperAdmin) {
            $stmt = $pdo->prepare("SELECT * FROM links WHERE id = ?");
            $stmt->execute([$linkId]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM links WHERE id = ? AND user_id = ?");
            $stmt->execute([$linkId, $userId]);
        }
        $link = $stmt->fetch();

        if (!$link) {
            http_response_code(404);
            die("Link not found or unauthorized.");
        }

        // Devices
        $stmt = $pdo->prepare("SELECT device_type AS name, COUNT(*) AS count FROM click_logs WHERE link_id = ? GROUP BY device_type ORDER BY count DESC");
        $stmt->execute([$linkId]);
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Browsers
        $stmt = $pdo->prepare("SELECT browser AS name, COUNT(*) AS count FROM click_logs WHERE link_id = ? GROUP BY browser ORDER BY count DESC");
        $stmt->execute([$linkId]);
        $browsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Referrers
        $stmt = $pdo->prepare("SELECT referrer AS name, COUNT(*) AS count FROM click_logs WHERE link_id = ? GROUP BY referrer ORDER BY count DESC LIMIT 5");
        $stmt->execute([$linkId]);
        $referrers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // OS
        $stmt = $pdo->prepare("SELECT os AS name, COUNT(*) AS count FROM click_logs WHERE link_id = ? GROUP BY os ORDER BY count DESC");
        $stmt->execute([$linkId]);
        $oses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Daily clicks — last 7 days
        $stmt = $pdo->prepare("
            SELECT DATE(clicked_at) AS click_date, COUNT(*) AS daily_count
            FROM click_logs
            WHERE link_id = ? AND clicked_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY DATE(clicked_at)
            ORDER BY click_date ASC
        ");
        $stmt->execute([$linkId]);
        $rawHistory = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $timeline = [];
        $maxClicks = 1;
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $label = date('D (M j)', strtotime($date));
            $count = (int)($rawHistory[$date] ?? 0);
            if ($count > $maxClicks) $maxClicks = $count;
            $timeline[] = ['label' => $label, 'count' => $count];
        }

        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT visitor_hash) FROM click_logs WHERE link_id = ?");
        $stmt->execute([$linkId]);
        $unique_visitors = (int)$stmt->fetchColumn();

        $baseURL = "http://" . $_SERVER['HTTP_HOST'] . str_replace('/index.php', '', $_SERVER['PHP_SELF']);

        require BASE_PATH . '/resources/views/analytics.php';
    }

    // ---------------------------------------------------------
    // Export (per-link CSV)
    // ---------------------------------------------------------
    public function export() {
        while (ob_get_level() > 0) ob_end_clean();

        if (!isset($_SESSION['user_id'])) die("Unauthorized");

        $linkId = (int)($_GET['id'] ?? 0);
        $userId = $_SESSION['user_id'];
        $role = $_SESSION['role'] ?? 'user';
        $isSuperAdmin = ($role === 'super_admin');

        $pdo = Database::getInstance();

        if ($isSuperAdmin) {
            $stmt = $pdo->prepare("SELECT id, short_code FROM links WHERE id = ?");
            $stmt->execute([$linkId]);
        } else {
            $stmt = $pdo->prepare("SELECT id, short_code FROM links WHERE id = ? AND user_id = ?");
            $stmt->execute([$linkId, $userId]);
        }
        $link = $stmt->fetch();
        if (!$link) die("Link not found");

        $filename = "linkforge-analytics-{$link['short_code']}-" . date('Y-m-d') . ".csv";

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Click ID', 'Timestamp (UTC)', 'Referrer', 'Device', 'OS', 'Browser']);

        $stmt = $pdo->prepare("SELECT id, clicked_at, referrer, device_type, os, browser FROM click_logs WHERE link_id = ? ORDER BY clicked_at DESC");
        $stmt->execute([$linkId]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['id'],
                $row['clicked_at'],
                $row['referrer'],
                $row['device_type'],
                $row['os'],
                $row['browser'],
            ]);
        }

        fclose($output);
        exit;
    }

    // ---------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------

    /**
     * Parse a range token like '24h', '7d', '30d', '90d', 'all' into
     * [days, bucketType]. Bucket is 'hour' or 'day'.
     */
    private function parseRange(string $range): array {
        return match ($range) {
            '24h'  => [1,   'hour'],
            '7d'   => [7,   'day'],
            '30d'  => [30,  'day'],
            '90d'  => [90,  'day'],
            'all'  => [0,   'day'],
            default => [30, 'day'],
        };
    }

    /**
     * Fill missing buckets with 0 so the chart has continuous data.
     */
    private function fillTimeline(array $rows, int $days, string $bucket): array {
        $out = [];

        if ($bucket === 'hour') {
            // Last 24 hours, hour by hour
            for ($i = 23; $i >= 0; $i--) {
                $ts = strtotime("-{$i} hours");
                $key = date('Y-m-d H:00:00', $ts);
                $label = date('H:00', $ts);
                $out[] = [
                    'label' => $label,
                    'count' => (int)($rows[$key] ?? 0),
                ];
            }
        } else {
            if ($days === 0) {
                // All time — show last 90 days to avoid massive charts
                $days = 90;
            }
            for ($i = $days - 1; $i >= 0; $i--) {
                $ts = strtotime("-{$i} days");
                $key = date('Y-m-d', $ts);
                $label = $days > 30 ? date('M j', $ts) : date('D j', $ts);
                $out[] = [
                    'label' => $label,
                    'count' => (int)($rows[$key] ?? 0),
                ];
            }
        }

        return $out;
    }
}