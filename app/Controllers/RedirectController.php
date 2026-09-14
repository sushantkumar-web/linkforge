<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Cache;
use PDO;

class RedirectController {
    public function handle(string $slug): void {
        // 1. Check OPcache Layer First
        $link = Cache::get($slug);

        if (!$link) {
            $pdo = Database::getInstance();
            $stmt = $pdo->prepare("SELECT id, destination_url, status, expires_at, pass_hash, short_code FROM links WHERE short_code = ? LIMIT 1");
            $stmt->execute([$slug]);
            $link = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$link) {
                http_response_code(404);
                require BASE_PATH . '/resources/views/errors/404.php';
                exit;
            }

            Cache::set($slug, $link);
        }

        // 2. Expiration Gate (410 Gone)
        if ($link['expires_at'] && strtotime($link['expires_at']) <= time()) {
            http_response_code(410);
            require BASE_PATH . '/resources/views/errors/410.php';
            exit;
        }

        // 3. Disabled Check
        if ($link['status'] !== 'active') {
            http_response_code(403);
            die("<div style='font-family:sans-serif;text-align:center;padding:50px;'><h2>Link Disabled</h2><p>This link has been temporarily disabled by its owner.</p></div>");
        }

        // 4. Password Protection Gate
        if (!empty($link['pass_hash'])) {
            $sessionKey = 'unlocked_link_' . $link['id'];
            if (empty($_SESSION[$sessionKey])) {
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['link_password'])) {
                    if (password_verify($_POST['link_password'], $link['pass_hash'])) {
                        $_SESSION[$sessionKey] = true;
                    } else {
                        $error = "Incorrect password.";
                        require BASE_PATH . '/resources/views/errors/password.php';
                        exit;
                    }
                } else {
                    require BASE_PATH . '/resources/views/errors/password.php';
                    exit;
                }
            }
        }

        // 5. Send Redirect Header First
        $destination = $link['destination_url'];
        $linkId = (int)$link['id'];
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $referrer = $_SERVER['HTTP_REFERER'] ?? 'Direct';
        $visitorHash = hash('sha256', $ip . date('Y-m-d') . $ua);

        header("Location: " . $destination, true, 302);
        $executionMs = round((microtime(true) - LINKFORGE_START) * 1000, 2);
        header("Server-Timing: app;desc=\"LinkForge Core\";dur={$executionMs}");
        header("Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0");
        header("Pragma: no-cache");

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            flush();
        }

        // 6. Background Analytics Logging
        ignore_user_abort(true);
        try {
            $pdo = Database::getInstance();
            $pdo->prepare("UPDATE links SET clicks = clicks + 1 WHERE id = ?")->execute([$linkId]);

            $browser = 'Other';
            if (str_contains($ua, 'Firefox')) $browser = 'Firefox';
            elseif (str_contains($ua, 'Chrome')) $browser = 'Chrome';
            elseif (str_contains($ua, 'Safari')) $browser = 'Safari';
            elseif (str_contains($ua, 'Edge')) $browser = 'Edge';

            $os = 'Other';
            if (str_contains($ua, 'Windows')) $os = 'Windows';
            elseif (str_contains($ua, 'Mac')) $os = 'macOS';
            elseif (str_contains($ua, 'Linux')) $os = 'Linux';
            elseif (str_contains($ua, 'Android')) $os = 'Android';
            elseif (str_contains($ua, 'iPhone')) $os = 'iOS';

            $device = (str_contains($ua, 'Mobile') || str_contains($ua, 'Android') || str_contains($ua, 'iPhone')) ? 'Mobile' : 'Desktop';

            $log = $pdo->prepare("INSERT INTO click_logs (link_id, visitor_hash, referrer, browser, os, device_type) VALUES (?, ?, ?, ?, ?, ?)");
            $log->execute([$linkId, $visitorHash, $referrer, $browser, $os, $device]);
        } catch (\Throwable $e) {
            error_log("Analytics error: " . $e->getMessage());
        }
        exit;
    }
}