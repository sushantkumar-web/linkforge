<?php
namespace App\Controllers;

use App\Core\Database;
use PDOException;

class LinkController {
    public function store() {
        if (!isset($_SESSION['user_id'])) die("Unauthorized");

        $url = filter_var($_POST['url'], FILTER_SANITIZE_URL);
        $custom_slug = trim($_POST['slug'] ?? '');
        
        // 1. UTM Builder Logic
        $utm_source = trim($_POST['utm_source'] ?? '');
        $utm_medium = trim($_POST['utm_medium'] ?? '');
        $utm_campaign = trim($_POST['utm_campaign'] ?? '');
        
        if ($utm_source || $utm_medium || $utm_campaign) {
            $parsed = parse_url($url);
            $query = [];
            
            // Preserve existing query parameters if they exist
            if (isset($parsed['query'])) {
                parse_str($parsed['query'], $query);
            }
            
            // Add our UTMs
            if ($utm_source) $query['utm_source'] = $utm_source;
            if ($utm_medium) $query['utm_medium'] = $utm_medium;
            if ($utm_campaign) $query['utm_campaign'] = $utm_campaign;
            
            // Rebuild URL
            $url = $parsed['scheme'] . '://' . ($parsed['host'] ?? '');
            if (isset($parsed['port'])) $url .= ':' . $parsed['port'];
            if (isset($parsed['path'])) $url .= $parsed['path'];
            $url .= '?' . http_build_query($query);
            if (isset($parsed['fragment'])) $url .= '#' . $parsed['fragment'];
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            die("Invalid URL format.");
        }

        $slug = $custom_slug ?: substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);

        $pdo = Database::getInstance();
        try {
            $stmt = $pdo->prepare("INSERT INTO links (user_id, destination_url, short_code) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $url, $slug]);
            
            $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
            header("Location: {$baseURL}/");
            exit;
        } catch (PDOException $e) {
            die("Error: That short code might already be taken.");
        }
    }
    public function toggle() {
        if (!isset($_SESSION['user_id'])) die("Unauthorized");
        // Rate Limiting (Max 20 links per minute per user)
        if (!isset($_SESSION['link_timestamps'])) {
            $_SESSION['link_timestamps'] = [];
        }
        
        // Filter out timestamps older than 60 seconds
        $_SESSION['link_timestamps'] = array_filter($_SESSION['link_timestamps'], function($time) {
            return $time > (time() - 60);
        });
        
        if (count($_SESSION['link_timestamps']) >= 20) {
            http_response_code(429);
            die("429 Too Many Requests: Rate limit exceeded. Please wait a minute.");
        }
        
        // Log this creation attempt
        $_SESSION['link_timestamps'][] = time();
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
        $stmt = $pdo->prepare("DELETE FROM links WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
        
        $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        header("Location: {$baseURL}/");
        exit;
    }
}

