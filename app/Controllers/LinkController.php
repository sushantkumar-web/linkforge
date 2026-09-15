<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Cache;
use PDOException;

class LinkController {

    /**
     * POST /links/create
     */
    public function store() {
        $userId = $_SESSION['user_id'];
        $url = filter_var($_POST['url'] ?? '', FILTER_SANITIZE_URL);
        $title = trim($_POST['title'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $password = $_POST['password'] ?? '';
        $tags = trim($_POST['tags'] ?? '');
        $expiresAt = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
        $fallbackUrl = trim($_POST['fallback_url'] ?? '');

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            flash('error', 'Please enter a valid destination URL.');
            $this->back();
        }

        // SSRF / redirect-loop protection
        if (!$this->isUrlSafe($url)) {
            flash('error', 'That URL points to a private network or this server. Shortening it is forbidden.');
            $this->back();
        }

        $pdo = Database::getInstance();

        if (empty($slug)) {
            $slug = $this->generateUniqueSlug($pdo);
        } else {
            $check = $pdo->prepare("SELECT id FROM links WHERE short_code = ? LIMIT 1");
            $check->execute([$slug]);
            if ($check->fetch()) {
                flash('slug_taken', [
                    'attempted'   => $slug,
                    'suggestions' => $this->suggestSlugs($pdo, $slug),
                ]);
                $this->back();
            }
        }

        $passHash = $password ? password_hash($password, PASSWORD_BCRYPT) : null;

        try {
            $stmt = $pdo->prepare("INSERT INTO links (user_id, title, destination_url, fallback_url, short_code, expires_at, pass_hash) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $title ?: null, $url, $fallbackUrl ?: null, $slug, $expiresAt, $passHash]);
            $linkId = (int)$pdo->lastInsertId();

            if (!empty($tags)) {
                $this->syncTags($pdo, $linkId, $userId, $tags);
            }

            Cache::forget($slug);
            flash('success', 'Link created: /' . $slug);
        } catch (PDOException $e) {
            flash('error', 'Could not create link: ' . $e->getMessage());
        }

        $this->back();
    }

    /**
     * POST /links/update
     */
    public function update() {
        $userId = $_SESSION['user_id'];
        $role = $_SESSION['role'] ?? 'user';
        $linkId = (int)($_POST['link_id'] ?? 0);

        if (!$linkId) {
            flash('error', 'Invalid link.');
            $this->back();
        }

        $pdo = Database::getInstance();

        // Permission check: super_admin can edit anything, everyone else only their own
        if ($role === 'super_admin') {
            $check = $pdo->prepare("SELECT short_code FROM links WHERE id = ?");
            $check->execute([$linkId]);
        } else {
            $check = $pdo->prepare("SELECT short_code FROM links WHERE id = ? AND user_id = ?");
            $check->execute([$linkId, $userId]);
        }
        $row = $check->fetch();
        if (!$row) {
            flash('error', 'Link not found or you do not have permission to edit it.');
            $this->back();
        }
        $slug = $row['short_code'];

        // Validate destination URL if provided
        $url = !empty($_POST['url']) ? filter_var($_POST['url'], FILTER_SANITIZE_URL) : null;
        if ($url !== null) {
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                flash('error', 'Invalid destination URL.');
                $this->back();
            }
            if (!$this->isUrlSafe($url)) {
                flash('error', 'That URL points to a private network or this server.');
                $this->back();
            }
        }

        // Validate fallback URL
        $fallbackUrl = !empty($_POST['fallback_url']) ? trim($_POST['fallback_url']) : null;
        if ($fallbackUrl && !filter_var($fallbackUrl, FILTER_VALIDATE_URL)) {
            $fallbackUrl = null;
        }

        $expiresAt = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;

        // Build update payload
        $updates = [];
        $params = [];

        if ($url !== null) {
            $updates[] = 'destination_url = ?';
            $params[] = $url;
        }
        if (array_key_exists('title', $_POST)) {
            $updates[] = 'title = ?';
            $params[] = trim($_POST['title']) ?: null;
        }
        if (array_key_exists('fallback_url', $_POST)) {
            $updates[] = 'fallback_url = ?';
            $params[] = $fallbackUrl;
        }
        if (array_key_exists('expires_at', $_POST)) {
            $updates[] = 'expires_at = ?';
            $params[] = $expiresAt;
        }
        if (!empty($_POST['remove_password'])) {
            $updates[] = 'pass_hash = NULL';
        } elseif (!empty($_POST['password'])) {
            $updates[] = 'pass_hash = ?';
            $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }

        try {
            if (!empty($updates)) {
                $params[] = $linkId;
                $sql = "UPDATE links SET " . implode(', ', $updates) . " WHERE id = ?";
                $pdo->prepare($sql)->execute($params);
            }

            if (isset($_POST['tags'])) {
                $this->syncTags($pdo, $linkId, $userId, $_POST['tags']);
            }

            // Purge redirect cache so the new destination is used immediately
            Cache::forget($slug);

            flash('success', 'Link updated.');
        } catch (PDOException $e) {
            flash('error', 'Database error: ' . $e->getMessage());
        }

        $this->back();
    }

    /**
     * POST /links/toggle
     */
    public function toggle() {
        $userId = $_SESSION['user_id'];
        $role = $_SESSION['role'] ?? 'user';
        $linkId = (int)($_POST['link_id'] ?? 0);

        $pdo = Database::getInstance();

        if ($role === 'super_admin') {
            $stmt = $pdo->prepare("UPDATE links SET status = IF(status = 'active', 'disabled', 'active') WHERE id = ?");
            $stmt->execute([$linkId]);
            $slugStmt = $pdo->prepare("SELECT short_code FROM links WHERE id = ?");
            $slugStmt->execute([$linkId]);
        } else {
            $stmt = $pdo->prepare("UPDATE links SET status = IF(status = 'active', 'disabled', 'active') WHERE id = ? AND user_id = ?");
            $stmt->execute([$linkId, $userId]);
            $slugStmt = $pdo->prepare("SELECT short_code FROM links WHERE id = ? AND user_id = ?");
            $slugStmt->execute([$linkId, $userId]);
        }

        if ($stmt->rowCount() === 0) {
            flash('error', 'Link not found or permission denied.');
        } else {
            $slug = $slugStmt->fetchColumn();
            if ($slug) Cache::forget($slug);
            flash('success', 'Link status changed.');
        }

        $this->back();
    }

    /**
     * POST /links/delete
     */
    public function delete() {
        $userId = $_SESSION['user_id'];
        $role = $_SESSION['role'] ?? 'user';
        $linkId = (int)($_POST['link_id'] ?? 0);

        $pdo = Database::getInstance();

        // Grab slug for cache purge
        if ($role === 'super_admin') {
            $slugStmt = $pdo->prepare("SELECT short_code FROM links WHERE id = ?");
            $slugStmt->execute([$linkId]);
            $slug = $slugStmt->fetchColumn();

            $pdo->prepare("DELETE FROM click_logs WHERE link_id = ?")->execute([$linkId]);
            $stmt = $pdo->prepare("DELETE FROM links WHERE id = ?");
            $stmt->execute([$linkId]);
        } else {
            $slugStmt = $pdo->prepare("SELECT short_code FROM links WHERE id = ? AND user_id = ?");
            $slugStmt->execute([$linkId, $userId]);
            $slug = $slugStmt->fetchColumn();

            $pdo->prepare("DELETE FROM click_logs WHERE link_id = ?")->execute([$linkId]);
            $stmt = $pdo->prepare("DELETE FROM links WHERE id = ? AND user_id = ?");
            $stmt->execute([$linkId, $userId]);
        }

        if ($stmt->rowCount() === 0) {
            flash('error', 'Link not found or permission denied.');
        } else {
            if ($slug) Cache::forget($slug);
            flash('success', 'Link deleted.');
        }

        $this->back();
    }

    // ---------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------

    private function generateUniqueSlug($pdo) {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        do {
            $slug = '';
            for ($i = 0; $i < 6; $i++) {
                $slug .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $check = $pdo->prepare("SELECT id FROM links WHERE short_code = ? LIMIT 1");
            $check->execute([$slug]);
        } while ($check->fetch());
        return $slug;
    }

    private function suggestSlugs($pdo, $base) {
        $suggestions = [];
        $candidates = [
            $base . '-' . substr(bin2hex(random_bytes(3)), 0, 3),
            $base . '-' . date('Y'),
            $base . '-' . random_int(10, 99),
            $base . '-' . substr(bin2hex(random_bytes(4)), 0, 4),
        ];
        foreach ($candidates as $c) {
            $check = $pdo->prepare("SELECT id FROM links WHERE short_code = ? LIMIT 1");
            $check->execute([$c]);
            if (!$check->fetch()) {
                $suggestions[] = $c;
            }
            if (count($suggestions) >= 3) break;
        }
        return $suggestions;
    }

    private function syncTags($pdo, $linkId, $userId, $tagString) {
        $pdo->prepare("DELETE FROM link_tags WHERE link_id = ?")->execute([$linkId]);

        $tags = array_filter(array_map('trim', explode(',', strtolower((string)$tagString))));
        if (empty($tags)) return;

        $tagStmt  = $pdo->prepare("INSERT IGNORE INTO tags (user_id, name) VALUES (?, ?)");
        $findStmt = $pdo->prepare("SELECT id FROM tags WHERE user_id = ? AND name = ? LIMIT 1");
        $linkStmt = $pdo->prepare("INSERT IGNORE INTO link_tags (link_id, tag_id) VALUES (?, ?)");

        foreach ($tags as $tag) {
            $tagStmt->execute([$userId, $tag]);
            $findStmt->execute([$userId, $tag]);
            $tagId = $findStmt->fetchColumn();
            if ($tagId) {
                $linkStmt->execute([$linkId, $tagId]);
            }
        }
    }

    /**
     * Prevents SSRF and redirect loops to the current host.
     */
    private function isUrlSafe($url) {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) return false;

        $currentHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // Strip port from current host for comparison
        $currentHost = preg_replace('/:\d+$/', '', $currentHost);
        if (strcasecmp($host, $currentHost) === 0) return false;

        $ip = gethostbyname($host);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            $isPublic = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
            if ($isPublic === false) return false;
        }

        return true;
    }

    private function back() {
        $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        header('Location: ' . $baseURL . '/');
        exit;
    }
    /**
 * POST /links/bulk
 * Perform a bulk action on multiple links.
 */
public function bulk() {
    $userId = $_SESSION['user_id'];
    $role = $_SESSION['role'] ?? 'user';
    $isSuperAdmin = ($role === 'super_admin');

    $action = $_POST['bulk_action'] ?? '';
    $ids = $_POST['link_ids'] ?? [];

    if (!is_array($ids)) $ids = [];
    $ids = array_values(array_filter(array_map('intval', $ids), fn($i) => $i > 0));

    if (empty($ids)) {
        flash('error', 'No links selected.');
        $this->back();
    }

    if (!in_array($action, ['disable', 'enable', 'delete'], true)) {
        flash('error', 'Invalid bulk action.');
        $this->back();
    }

    $pdo = Database::getInstance();
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    // RBAC scope clause
    if ($isSuperAdmin) {
        $scopeClause = '';
        $scopeParams = [];
    } else {
        $scopeClause = " AND user_id = ?";
        $scopeParams = [$userId];
    }

    // Grab slugs FIRST so we can purge redirect cache after the action
    $slugStmt = $pdo->prepare("SELECT short_code FROM links WHERE id IN ({$placeholders}) {$scopeClause}");
    $slugStmt->execute(array_merge($ids, $scopeParams));
    $slugs = $slugStmt->fetchAll(\PDO::FETCH_COLUMN);

    $affected = 0;

    if ($action === 'delete') {
        // Delete click logs first to respect FK constraints
        $delLogs = $pdo->prepare("DELETE FROM click_logs WHERE link_id IN ({$placeholders})");
        $delLogs->execute($ids);

        $stmt = $pdo->prepare("DELETE FROM links WHERE id IN ({$placeholders}) {$scopeClause}");
        $stmt->execute(array_merge($ids, $scopeParams));
        $affected = $stmt->rowCount();
    } elseif ($action === 'disable') {
        $stmt = $pdo->prepare("UPDATE links SET status = 'disabled' WHERE id IN ({$placeholders}) {$scopeClause}");
        $stmt->execute(array_merge($ids, $scopeParams));
        $affected = $stmt->rowCount();
    } elseif ($action === 'enable') {
        $stmt = $pdo->prepare("UPDATE links SET status = 'active' WHERE id IN ({$placeholders}) {$scopeClause}");
        $stmt->execute(array_merge($ids, $scopeParams));
        $affected = $stmt->rowCount();
    }

    // Purge redirect cache for affected links
    if (!empty($slugs) && class_exists('\App\Core\Cache')) {
        foreach ($slugs as $s) {
            \App\Core\Cache::forget($s);
        }
    }

    $verb = match ($action) {
        'delete'  => 'deleted',
        'disable' => 'disabled',
        'enable'  => 'enabled',
    };

    $noun = ($affected === 1) ? 'link' : 'links';
    flash('success', "{$affected} {$noun} {$verb}.");

    $this->back();
}
}