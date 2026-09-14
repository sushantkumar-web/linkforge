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

        // 5. Fetch Daily Clicks for the Past 7 Days
        $historyStmt = $pdo->prepare("
            SELECT DATE(clicked_at) as click_date, COUNT(*) as daily_count
            FROM click_logs
            WHERE link_id = ? AND clicked_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY DATE(clicked_at)
            ORDER BY click_date ASC
        ");
        $historyStmt->execute([$link_id]);
        $rawHistory = $historyStmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Fill missing days with 0 so the chart displays an unbroken 7-day timeline
        $timeline = [];
        $maxClicks = 1;
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $label = date('D (M j)', strtotime($date));
            $count = (int)($rawHistory[$date] ?? 0);
            if ($count > $maxClicks) $maxClicks = $count;
            $timeline[] = ['label' => $label, 'count' => $count];
        }
        
        $baseURL = "http://" . $_SERVER['HTTP_HOST'] . str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        
        require BASE_PATH . '/resources/views/analytics.php';
    }
    public function export() {
        if (!isset($_SESSION['user_id'])) die("Unauthorized");

        $link_id = (int)($_GET['id'] ?? 0);
        $pdo = Database::getInstance();

        // Verify ownership
        $stmt = $pdo->prepare("SELECT id, short_code FROM links WHERE id = ? AND user_id = ?");
        $stmt->execute([$link_id, $_SESSION['user_id']]);
        $link = $stmt->fetch();

        if (!$link) die("Link not found");

        $filename = "linkforge-analytics-{$link['short_code']}-" . date('Y-m-d') . ".csv";

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        // Header row
        fputcsv($output, ['Click ID', 'Timestamp (UTC)', 'Referrer', 'Device', 'OS', 'Browser']);

        // Query and stream records in chunks
        $logs = $pdo->prepare("SELECT id, clicked_at, referrer, device_type, os, browser FROM click_logs WHERE link_id = ? ORDER BY clicked_at DESC");
        $logs->execute([$link_id]);

        while ($row = $logs->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['id'],
                $row['clicked_at'],
                $row['referrer'],
                $row['device_type'],
                $row['os'],
                $row['browser']
            ]);
        }

        fclose($output);
        exit;
    }
}