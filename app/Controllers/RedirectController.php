<?php
namespace App\Controllers;

use App\Core\Database;

class RedirectController {
    public function handle($slug) {
        $pdo = Database::getInstance();
        
        $stmt = $pdo->prepare("SELECT id, destination_url, status, expires_at FROM links WHERE short_code = ? LIMIT 1");
        $stmt->execute([$slug]);
        $link = $stmt->fetch();

        if (!$link) {
            http_response_code(404);
            require BASE_PATH . '/resources/views/errors/404.php';
            exit;
        }

        // 1. Check Link Expiration (410 Gone)
        if ($link['expires_at'] && strtotime($link['expires_at']) <= time()) {
            http_response_code(410);
            require BASE_PATH . '/resources/views/errors/410.php';
            exit;
        }

        // 2. Check Disabled Status
        if ($link['status'] !== 'active') {
            http_response_code(403);
            die("<div style='font-family:sans-serif;text-align:center;padding:50px;'><h2>Link Disabled</h2><p>This link has been temporarily disabled by its owner.</p></div>");
        }

        // 3. Increment Clicks & Collect Privacy Analytics
        $update = $pdo->prepare("UPDATE links SET clicks = clicks + 1 WHERE id = ?");
        $update->execute([$link['id']]);

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $referrer = $_SERVER['HTTP_REFERER'] ?? 'Direct';
        $visitor_hash = hash('sha256', $ip . date('Y-m-d') . $ua);

        $browser = 'Other';
        if (strpos($ua, 'Firefox') !== false) $browser = 'Firefox';
        elseif (strpos($ua, 'Chrome') !== false) $browser = 'Chrome';
        elseif (strpos($ua, 'Safari') !== false) $browser = 'Safari';
        elseif (strpos($ua, 'Edge') !== false) $browser = 'Edge';

        $os = 'Other';
        if (strpos($ua, 'Windows') !== false) $os = 'Windows';
        elseif (strpos($ua, 'Mac') !== false) $os = 'macOS';
        elseif (strpos($ua, 'Linux') !== false) $os = 'Linux';
        elseif (strpos($ua, 'Android') !== false) $os = 'Android';
        elseif (strpos($ua, 'iPhone') !== false) $os = 'iOS';

        $device = (strpos($ua, 'Mobile') !== false || strpos($ua, 'Android') !== false || strpos($ua, 'iPhone') !== false) ? 'Mobile' : 'Desktop';

        $log = $pdo->prepare("INSERT INTO click_logs (link_id, visitor_hash, referrer, browser, os, device_type) VALUES (?, ?, ?, ?, ?, ?)");
        $log->execute([$link['id'], $visitor_hash, $referrer, $browser, $os, $device]);

        // 4. Clean 302 Redirect
        header("Location: " . $link['destination_url'], true, 302);
        exit;
    }
}