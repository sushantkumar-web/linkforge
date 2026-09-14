<?php
namespace App\Controllers;

use App\Core\Database;

class RedirectController {
    public function handle($slug) {
        $pdo = Database::getInstance();
        
        $stmt = $pdo->prepare("SELECT id, destination_url, status FROM links WHERE short_code = ? LIMIT 1");
        $stmt->execute([$slug]);
        $link = $stmt->fetch();

        if ($link && $link['status'] === 'active') {
            // 1. Increment total clicks (keep this for fast dashboard reading)
            $update = $pdo->prepare("UPDATE links SET clicks = clicks + 1 WHERE id = ?");
            $update->execute([$link['id']]);
            
            // 2. Gather Privacy-Conscious Analytics
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            $referrer = $_SERVER['HTTP_REFERER'] ?? 'Direct';
            
            // Hash IP + Date for daily unique visitors (Privacy first!)
            $visitor_hash = hash('sha256', $ip . date('Y-m-d') . $ua);
            
            // Basic UA Parsing (Lightweight for V1)
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
            
            // 3. Log the click
            $log = $pdo->prepare("INSERT INTO click_logs (link_id, visitor_hash, referrer, browser, os, device_type) VALUES (?, ?, ?, ?, ?, ?)");
            $log->execute([$link['id'], $visitor_hash, $referrer, $browser, $os, $device]);

            // 4. Redirect
            header("Location: " . $link['destination_url'], true, 302);
            exit;
        }

        http_response_code(410);
        echo "410 - Link Gone or Disabled.";
        exit;
    }
}