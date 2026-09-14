<?php
namespace App\Controllers;

use App\Core\Database;
use PDOException;

class LinkController {
    public function store() {
        if (!isset($_SESSION['user_id'])) die("Unauthorized");

        // Rate Limiting (20 links/min)
        if (!isset($_SESSION['link_timestamps'])) $_SESSION['link_timestamps'] = [];
        $_SESSION['link_timestamps'] = array_filter($_SESSION['link_timestamps'], fn($t) => $t > (time() - 60));
        if (count($_SESSION['link_timestamps']) >= 20) {
            http_response_code(429);
            die("429 Too Many Requests: Rate limit exceeded.");
        }
        $_SESSION['link_timestamps'][] = time();

        $title = trim($_POST['title'] ?? '');
        $url = filter_var($_POST['url'], FILTER_SANITIZE_URL);
        $custom_slug = trim($_POST['slug'] ?? '');
        $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
        $pass_hash = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;

        // UTM Processing
        $utm_source = trim($_POST['utm_source'] ?? '');
        $utm_medium = trim($_POST['utm_medium'] ?? '');
        $utm_campaign = trim($_POST['utm_campaign'] ?? '');

        if ($utm_source || $utm_medium || $utm_campaign) {
            $parsed = parse_url($url);
            $query = [];
            if (isset($parsed['query'])) parse_str($parsed['query'], $query);
            if ($utm_source) $query['utm_source'] = $utm_source;
            if ($utm_medium) $query['utm_medium'] = $utm_medium;
            if ($utm_campaign) $query['utm_campaign'] = $utm_campaign;

            $url = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
            if (isset($parsed['port'])) $url .= ':' . $parsed['port'];
            if (isset($parsed['path'])) $url .= $parsed['path'];
            $url .= '?' . http_build_query($query);
            if (isset($parsed['fragment'])) $url .= '#' . $parsed['fragment'];
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            die("Invalid URL format.");
        }

        // Security: SSRF and Redirect Loop Prevention
        $host = parse_url($url, PHP_URL_HOST);
        $currentHost = $_SERVER['HTTP_HOST'] ?? 'localhost';

        if (strcasecmp($host, $currentHost) === 0) {
            die("Error: Cannot create a short link targeting this domain (prevents redirect loops).");
        }

        $ip = gethostbyname($host);
        if (
            filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false &&
            $host !== 'thesushant.in'
        ) {
            die("Error: Shortening internal or private network addresses is forbidden.");
        }

        $slug = $custom_slug ?: substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);

        $pdo = Database::getInstance();
        try {
            // FIXED: Included pass_hash in columns and values list
            $stmt = $pdo->prepare("INSERT INTO links (user_id, title, destination_url, short_code, expires_at, pass_hash) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $title ?: null, $url, $slug, $expires_at, $pass_hash]);
            $link_id = $pdo->lastInsertId();
            
            $this->syncTags($pdo, $link_id, $_SESSION['user_id'], $_POST['tags'] ?? '');

            $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
            header("Location: {$baseURL}/");
            exit;
        } catch (PDOException $e) {
            die("Error: That short code is already in use.");
        }
    }

    public function update() {
        if (!isset($_SESSION['user_id'])) die("Unauthorized");
        $id = $_POST['link_id'] ?? 0;
        $title = trim($_POST['title'] ?? '');
        $url = filter_var($_POST['url'], FILTER_SANITIZE_URL);
        $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;

        if (!filter_var($url, FILTER_VALIDATE_URL)) die("Invalid URL");

        // Security: SSRF and Redirect Loop Prevention
        $host = parse_url($url, PHP_URL_HOST);
        $currentHost = $_SERVER['HTTP_HOST'] ?? 'localhost';

        if (strcasecmp($host, $currentHost) === 0) {
            die("Error: Cannot create a short link targeting this domain (prevents redirect loops).");
        }

        $ip = gethostbyname($host);
        if (
            filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false &&
            $host !== 'thesushant.in'
        ) {
            die("Error: Shortening internal or private network addresses is forbidden.");
        }

        $pdo = Database::getInstance();

        // FIXED: Clean try/catch block without syntax errors
        try {
            if (!empty($_POST['password'])) {
                $pass_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE links SET title = ?, destination_url = ?, expires_at = ?, pass_hash = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$title ?: null, $url, $expires_at, $pass_hash, $id, $_SESSION['user_id']]);
            } else {
                $stmt = $pdo->prepare("UPDATE links SET title = ?, destination_url = ?, expires_at = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$title ?: null, $url, $expires_at, $id, $_SESSION['user_id']]);
            }

            $this->syncTags($pdo, $id, $_SESSION['user_id'], $_POST['tags'] ?? '');

            $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
            header("Location: {$baseURL}/");
            exit;
        } catch (PDOException $e) {
            die("Database error updating link: " . htmlspecialchars($e->getMessage()));
        }
    }

    public function toggle() {
        if (!isset($_SESSION['user_id'])) die("Unauthorized");
        $id = $_POST['link_id'] ?? 0;
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("UPDATE links SET status = IF(status='active', 'disabled', 'active') WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);

        $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        header("Location: {$baseURL}/");
        exit;
    }

    public function delete() {
        if (!isset($_SESSION['user_id'])) die("Unauthorized");
        $id = $_POST['link_id'] ?? 0;
        $pdo = Database::getInstance();

        $pdo->prepare("DELETE FROM click_logs WHERE link_id = ?")->execute([$id]);
        $stmt = $pdo->prepare("DELETE FROM links WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);

        $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        header("Location: {$baseURL}/");
        exit;
    }

    private function syncTags($pdo, $link_id, $user_id, string $tagString) {
        $pdo->prepare("DELETE FROM link_tags WHERE link_id = ?")->execute([$link_id]);
        
        $tags = array_filter(array_map('trim', explode(',', strtolower($tagString))));
        if (empty($tags)) return;

        $tagStmt = $pdo->prepare("INSERT IGNORE INTO tags (user_id, name) VALUES (?, ?)");
        $findStmt = $pdo->prepare("SELECT id FROM tags WHERE user_id = ? AND name = ? LIMIT 1");
        $linkStmt = $pdo->prepare("INSERT IGNORE INTO link_tags (link_id, tag_id) VALUES (?, ?)");

        foreach ($tags as $tag) {
            $tagStmt->execute([$user_id, $tag]);
            $findStmt->execute([$user_id, $tag]);
            $tagId = $findStmt->fetchColumn();
            if ($tagId) {
                $linkStmt->execute([$link_id, $tagId]);
            }
        }
    }
}