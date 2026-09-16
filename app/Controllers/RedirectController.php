<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Cache;
use PDO;

class RedirectController {

    public function handle(string $slug): void {
        // --- Compute the request host and canonical cache key ---
        // Strip port so "localhost:80" and "localhost" share cache entries.
        // This must match LinkController::forgetLinkCache() exactly.
        $requestHost = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
        $cacheKey = $requestHost . ':' . $slug;

        // 1. Check cache layer first
        $link = Cache::get($cacheKey);

        if (!$link) {
            $pdo = Database::getInstance();

            // Look up domain record (custom domain verification)
            $domainId = null;
            $domainStmt = $pdo->prepare("SELECT id FROM domains WHERE hostname = ? AND verified_at IS NOT NULL LIMIT 1");
            $domainStmt->execute([$requestHost]);
            $domainRow = $domainStmt->fetch(PDO::FETCH_ASSOC);
            if ($domainRow) {
                $domainId = (int)$domainRow['id'];
            }

            // Resolve the link. Custom domains scope the lookup to that domain;
            // unmatched hosts fall back to default (domain_id IS NULL) links.
            if ($domainId !== null) {
    $stmt = $pdo->prepare("
        SELECT id, user_id, destination_url, fallback_url, status, expires_at, pass_hash, short_code, clicks, targeting
        FROM links WHERE short_code = ? AND domain_id = ? LIMIT 1
    ");
    $stmt->execute([$slug, $domainId]);
} else {
    $stmt = $pdo->prepare("
        SELECT id, user_id, destination_url, fallback_url, status, expires_at, pass_hash, short_code, clicks, targeting
        FROM links WHERE short_code = ? AND domain_id IS NULL LIMIT 1
    ");
    $stmt->execute([$slug]);
}
            $link = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$link) {
                http_response_code(404);
                require BASE_PATH . '/resources/views/errors/404.php';
                exit;
            }

            // Cache under the canonical key
            Cache::set($cacheKey, $link);
        }

        // 2. Smart Expiration & Fallback Gate
        if (!empty($link['expires_at']) && strtotime($link['expires_at']) <= time()) {
            Cache::forget($cacheKey);

            if (!empty($link['fallback_url'])) {
                header("Location: " . $link['fallback_url'], true, 302);
                exit;
            }

            http_response_code(410);
            if (file_exists(BASE_PATH . '/resources/views/errors/410.php')) {
                require BASE_PATH . '/resources/views/errors/410.php';
            } else {
                echo "<!DOCTYPE html><html><head><title>Link Expired</title><style>body{background:#0F1115;color:#9CA3AF;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}.card{background:#16191F;border:1px solid #282C34;padding:32px;border-radius:8px;text-align:center;max-width:400px;}h1{color:#F5F5F5;font-size:20px;margin-bottom:8px;}</style></head><body><div class='card'><h1>Link Expired</h1><p>This link has reached its expiration date and is no longer active.</p></div></body></html>";
            }
            exit;
        }

        // 3. Disabled Gate
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

    
        // 5. Apply targeting rules
       $destination = $this->applyTargeting($link, $_SERVER['HTTP_USER_AGENT'] ?? '');
       $linkId = (int)$link['id'];
       $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
       $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
       $referrer = $_SERVER['HTTP_REFERER'] ?? 'Direct';
       $visitorHash = hash('sha256', $ip . date('Y-m-d') . $ua);

        // Fire webhook for link.clicked (throttled: max 1 per link per 60s)
        if (!empty($link['user_id']) && !empty($link['id'])) {
            $throttleDir = BASE_PATH . '/storage/cache';
            $throttleKey = $throttleDir . '/webhook_click_' . (int)$link['id'] . '.lock';

            $shouldFire = true;
            if (is_file($throttleKey) && (time() - filemtime($throttleKey)) < 60) {
                $shouldFire = false;
            }

            if ($shouldFire) {
                if (is_writable($throttleDir)) {
                    @touch($throttleKey);
                }

                $linkOwnerId = (int)$link['user_id'];
                $linkData = [
                    'id'         => (int)$link['id'],
                    'short_code' => $link['short_code'],
                    'clicks'     => (int)$link['clicks'] + 1,
                    'clicked_at' => date('c'),
                ];

                register_shutdown_function(function() use ($linkOwnerId, $linkData) {
                    try {
                        \App\Core\WebhookDispatcher::dispatch($linkOwnerId, 'link.clicked', $linkData);
                    } catch (\Throwable $e) {
                        error_log('[Webhook.clicked] ' . $e->getMessage());
                    }
                });
            }
        }

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

        // 6. Non-Blocking Analytics Logging
ignore_user_abort(true);

// Skip logging entirely if this is a bot / crawler / preview.
// Prevents inflated click counts from Google, Facebook link previews,
// uptime monitors, and other non-human traffic.
$isBot = \App\Core\BotDetector::isBot();

if ($isBot) {
    // Log the bot filter for debugging (only when debug mode is on)
    if (defined('LINKFORGE_DEBUG') && LINKFORGE_DEBUG) {
        error_log("[Bot filter] skipped click for /{$link['short_code']} — matched: " . (\App\Core\BotDetector::matchedPattern() ?? 'unknown'));
    }
} else {
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
}
exit;
        
    }
    /**
 * Resolve the final destination URL by applying targeting rules.
 *
 * Rules are evaluated in order. First match wins. Falls back to
 * destination_url if no rule matches or if targeting is empty.
 */
private function applyTargeting(array $link, string $ua): string {
    $default = $link['destination_url'];

    if (empty($link['targeting'])) {
        return $default;
    }

    $rules = json_decode($link['targeting'], true);
    if (!is_array($rules) || empty($rules)) {
        return $default;
    }

    $device = $this->detectDevice($ua);
    $country = $this->detectCountry();

    foreach ($rules as $rule) {
        $type  = $rule['type']  ?? '';
        $match = $rule['match'] ?? '';
        $url   = $rule['url']   ?? '';

        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) continue;

        if ($type === 'device' && $match === $device) {
            return $url;
        }

        if ($type === 'country' && $country !== null && $match === $country) {
            return $url;
        }
    }

    return $default;
}

/**
 * Basic device detection from User-Agent string.
 * Returns 'mobile', 'tablet', or 'desktop'.
 */
private function detectDevice(string $ua): string {
    if ($ua === '') return 'desktop';
    $lower = strtolower($ua);

    // Tablet check first — many tablets match the mobile markers
    if (str_contains($lower, 'ipad') || str_contains($lower, 'tablet')) {
        return 'tablet';
    }
    if (str_contains($lower, 'android') && !str_contains($lower, 'mobile')) {
        return 'tablet';
    }

    // Mobile
    if (str_contains($lower, 'mobile')
        || str_contains($lower, 'iphone')
        || str_contains($lower, 'ipod')
        || str_contains($lower, 'android')
        || str_contains($lower, 'blackberry')
        || str_contains($lower, 'windows phone')) {
        return 'mobile';
    }

    return 'desktop';
}

/**
 * Country detection.
 * Uses Cloudflare's CF-IPCountry header when available (free on all plans).
 * Returns an ISO 3166-1 alpha-2 code, or null if we can't tell.
 */
private function detectCountry(): ?string {
    $cf = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? null;
    if ($cf && preg_match('/^[A-Z]{2}$/', $cf)) {
        // Cloudflare uses "XX" for unknown, "T1" for Tor
        if ($cf === 'XX' || $cf === 'T1') return null;
        return $cf;
    }
    return null;
}
}