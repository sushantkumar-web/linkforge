<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Cache;
use PDOException;
use App\Core\WebhookDispatcher;

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

        if (!$this->isUrlSafe($url)) {
            flash('error', 'That URL points to a private network or this server. Shortening it is forbidden.');
            $this->back();
        }

        $pdo = Database::getInstance();

        // Resolve domain_id (null = default host, otherwise verified custom domain owned by user)
        $domainId = null;
        if (!empty($_POST['domain_id'])) {
            $candidate = (int)$_POST['domain_id'];
            $dCheck = $pdo->prepare("SELECT id FROM domains WHERE id = ? AND user_id = ? AND verified_at IS NOT NULL LIMIT 1");
            $dCheck->execute([$candidate, $userId]);
            if ($dCheck->fetch()) {
                $domainId = $candidate;
            }
        }

        if (empty($slug)) {
            $slug = $this->generateUniqueSlug($pdo);
        } else {
            // Slug uniqueness is currently global. When we add multi-domain
            // support in v1.2.0, this becomes (short_code, domain_id).
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
$targeting = $this->parseTargeting($_POST['targeting_json'] ?? null);

try {
    $stmt = $pdo->prepare("
        INSERT INTO links (user_id, domain_id, title, destination_url, fallback_url, short_code, expires_at, pass_hash, targeting)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $domainId, $title ?: null, $url, $fallbackUrl ?: null, $slug, $expiresAt, $passHash, $targeting]);

            $linkId = (int)$pdo->lastInsertId();

            if (!empty($tags)) {
                $this->syncTags($pdo, $linkId, $userId, $tags);
            }

            $this->forgetLinkCache($slug, $domainId, $pdo);
            $shortUrl = $this->buildShortUrl($slug, $domainId, $pdo);

            flash('success', 'Link created: ' . $shortUrl);

            WebhookDispatcher::dispatch($userId, 'link.created', [
                'id'              => $linkId,
                'title'           => $title ?: null,
                'short_code'      => $slug,
                'short_url'       => $shortUrl,
                'destination_url' => $url,
                'created_at'      => date('c'),
            ]);
        } catch (PDOException $e) {
            flash('error', 'Could not create link: ' . $e->getMessage());
        }

        $this->back();
    }

    /**
     * GET /link?id=X
     * Unified detail view.
     */
    public function show() {
        $userId = $_SESSION['user_id'];
        $role = $_SESSION['role'] ?? 'user';
        $isSuperAdmin = ($role === 'super_admin');
        $linkId = (int)($_GET['id'] ?? 0);

        if (!$linkId) {
            $this->back();
        }

        $pdo = Database::getInstance();

        if ($isSuperAdmin) {
            $stmt = $pdo->prepare("
                SELECT l.*, u.email AS owner_email,
                       GROUP_CONCAT(DISTINCT t.name SEPARATOR ',') AS tags
                FROM links l
                LEFT JOIN link_tags lt ON l.id = lt.link_id
                LEFT JOIN tags t ON lt.tag_id = t.id
                LEFT JOIN users u ON l.user_id = u.id
                WHERE l.id = ?
                GROUP BY l.id
            ");
            $stmt->execute([$linkId]);
        } else {
            $stmt = $pdo->prepare("
                SELECT l.*,
                       GROUP_CONCAT(DISTINCT t.name SEPARATOR ',') AS tags
                FROM links l
                LEFT JOIN link_tags lt ON l.id = lt.link_id
                LEFT JOIN tags t ON lt.tag_id = t.id
                WHERE l.id = ? AND l.user_id = ?
                GROUP BY l.id
            ");
            $stmt->execute([$linkId, $userId]);
        }
        $link = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$link) {
            flash('error', 'Link not found.');
            $this->back();
        }

        $kpiStmt = $pdo->prepare("
            SELECT COUNT(*) AS total_clicks,
                   COUNT(DISTINCT visitor_hash) AS unique_visitors
            FROM click_logs WHERE link_id = ?
        ");
        $kpiStmt->execute([$linkId]);
        $kpis = $kpiStmt->fetch(\PDO::FETCH_ASSOC) ?: ['total_clicks' => 0, 'unique_visitors' => 0];

        $histStmt = $pdo->prepare("
            SELECT DATE(clicked_at) AS d, COUNT(*) AS c
            FROM click_logs
            WHERE link_id = ? AND clicked_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
            GROUP BY DATE(clicked_at)
        ");
        $histStmt->execute([$linkId]);
        $rawHistory = $histStmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        $timeline = [];
        $maxTimeline = 1;
        for ($i = 29; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $count = (int)($rawHistory[$date] ?? 0);
            if ($count > $maxTimeline) $maxTimeline = $count;
            $timeline[] = ['label' => date('M j', strtotime($date)), 'count' => $count];
        }

        $devStmt = $pdo->prepare("SELECT COALESCE(NULLIF(device_type,''),'Unknown') AS name, COUNT(*) AS count FROM click_logs WHERE link_id = ? GROUP BY name ORDER BY count DESC");
        $devStmt->execute([$linkId]);
        $devices = $devStmt->fetchAll(\PDO::FETCH_ASSOC);

        $browStmt = $pdo->prepare("SELECT COALESCE(NULLIF(browser,''),'Unknown') AS name, COUNT(*) AS count FROM click_logs WHERE link_id = ? GROUP BY name ORDER BY count DESC LIMIT 6");
        $browStmt->execute([$linkId]);
        $browsers = $browStmt->fetchAll(\PDO::FETCH_ASSOC);

        $osStmt = $pdo->prepare("SELECT COALESCE(NULLIF(os,''),'Unknown') AS name, COUNT(*) AS count FROM click_logs WHERE link_id = ? GROUP BY name ORDER BY count DESC LIMIT 6");
        $osStmt->execute([$linkId]);
        $oses = $osStmt->fetchAll(\PDO::FETCH_ASSOC);

        $refStmt = $pdo->prepare("SELECT COALESCE(NULLIF(referrer,''),'Direct') AS name, COUNT(*) AS count FROM click_logs WHERE link_id = ? GROUP BY name ORDER BY count DESC LIMIT 6");
        $refStmt->execute([$linkId]);
        $referrers = $refStmt->fetchAll(\PDO::FETCH_ASSOC);

        $feedStmt = $pdo->prepare("
            SELECT referrer, browser, os, device_type, clicked_at
            FROM click_logs
            WHERE link_id = ?
            ORDER BY clicked_at DESC
            LIMIT 25
        ");
        $feedStmt->execute([$linkId]);
        $activity = $feedStmt->fetchAll(\PDO::FETCH_ASSOC);

        $shortUrl = $this->buildShortUrl($link['short_code'], $link['domain_id'] !== null ? (int)$link['domain_id'] : null, $pdo);

        // Load list of user's verified domains for the edit form's domain selector
        $domainListStmt = $pdo->prepare("SELECT id, hostname, is_primary FROM domains WHERE user_id = ? AND verified_at IS NOT NULL ORDER BY is_primary DESC, hostname ASC");
        $domainListStmt->execute([$link['user_id']]);
        $userDomains = $domainListStmt->fetchAll(\PDO::FETCH_ASSOC);

        $isExpired = !empty($link['expires_at']) && strtotime($link['expires_at']) <= time();

        $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        require BASE_PATH . '/resources/views/link-detail.php';
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

        if ($role === 'super_admin') {
            $check = $pdo->prepare("SELECT short_code, domain_id, user_id FROM links WHERE id = ?");
            $check->execute([$linkId]);
        } else {
            $check = $pdo->prepare("SELECT short_code, domain_id, user_id FROM links WHERE id = ? AND user_id = ?");
            $check->execute([$linkId, $userId]);
        }
        $row = $check->fetch();
        if (!$row) {
            flash('error', 'Link not found or you do not have permission to edit it.');
            $this->back();
        }
        $slug = $row['short_code'];
        $oldDomainId = $row['domain_id'] !== null ? (int)$row['domain_id'] : null;
        $ownerId = (int)$row['user_id'];

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

        $fallbackUrl = !empty($_POST['fallback_url']) ? trim($_POST['fallback_url']) : null;
        if ($fallbackUrl && !filter_var($fallbackUrl, FILTER_VALIDATE_URL)) {
            $fallbackUrl = null;
        }

        $expiresAt = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;

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
        if (array_key_exists('targeting_json', $_POST)) {
    $updates[] = 'targeting = ?';
    $params[] = $this->parseTargeting($_POST['targeting_json']);
}

        // Domain change handling — only if explicitly submitted
        $newDomainId = $oldDomainId;
        if (array_key_exists('domain_id', $_POST)) {
            $candidate = $_POST['domain_id'] === '' ? null : (int)$_POST['domain_id'];
            if ($candidate !== null) {
                $dCheck = $pdo->prepare("SELECT id FROM domains WHERE id = ? AND user_id = ? AND verified_at IS NOT NULL LIMIT 1");
                $dCheck->execute([$candidate, $ownerId]);
                if ($dCheck->fetch()) {
                    $newDomainId = $candidate;
                } else {
                    $newDomainId = null;
                }
            } else {
                $newDomainId = null;
            }
            if ($newDomainId !== $oldDomainId) {
                $updates[] = 'domain_id = ?';
                $params[] = $newDomainId;
            }
        }

        try {
            if (!empty($updates)) {
                $params[] = $linkId;
                $sql = "UPDATE links SET " . implode(', ', $updates) . " WHERE id = ?";
                $pdo->prepare($sql)->execute($params);
            }

            if (isset($_POST['tags'])) {
                $this->syncTags($pdo, $linkId, $ownerId, $_POST['tags']);
            }

            // Forget cache for both the old and new domain slots
            $this->forgetLinkCache($slug, $oldDomainId, $pdo);
            if ($newDomainId !== $oldDomainId) {
                $this->forgetLinkCache($slug, $newDomainId, $pdo);
            }

            flash('success', 'Link updated.');

            WebhookDispatcher::dispatch($ownerId, 'link.updated', [
                'id'              => $linkId,
                'short_code'      => $slug,
                'short_url'       => $this->buildShortUrl($slug, $newDomainId, $pdo),
                'destination_url' => $url ?? null,
                'updated_at'      => date('c'),
            ]);
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
            $slugStmt = $pdo->prepare("SELECT short_code, domain_id FROM links WHERE id = ?");
            $slugStmt->execute([$linkId]);
        } else {
            $stmt = $pdo->prepare("UPDATE links SET status = IF(status = 'active', 'disabled', 'active') WHERE id = ? AND user_id = ?");
            $stmt->execute([$linkId, $userId]);
            $slugStmt = $pdo->prepare("SELECT short_code, domain_id FROM links WHERE id = ? AND user_id = ?");
            $slugStmt->execute([$linkId, $userId]);
        }

        if ($stmt->rowCount() === 0) {
            flash('error', 'Link not found or permission denied.');
        } else {
            $row = $slugStmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                $domainId = $row['domain_id'] !== null ? (int)$row['domain_id'] : null;
                $this->forgetLinkCache($row['short_code'], $domainId, $pdo);
            }
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

        if ($role === 'super_admin') {
            $slugStmt = $pdo->prepare("SELECT short_code, domain_id, user_id FROM links WHERE id = ?");
            $slugStmt->execute([$linkId]);
        } else {
            $slugStmt = $pdo->prepare("SELECT short_code, domain_id, user_id FROM links WHERE id = ? AND user_id = ?");
            $slugStmt->execute([$linkId, $userId]);
        }
        $row = $slugStmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            flash('error', 'Link not found or permission denied.');
            $this->back();
        }

        $slug = $row['short_code'];
        $domainId = $row['domain_id'] !== null ? (int)$row['domain_id'] : null;
        $ownerId = (int)$row['user_id'];

        $pdo->prepare("DELETE FROM click_logs WHERE link_id = ?")->execute([$linkId]);
        $pdo->prepare("DELETE FROM link_tags WHERE link_id = ?")->execute([$linkId]);

        if ($role === 'super_admin') {
            $stmt = $pdo->prepare("DELETE FROM links WHERE id = ?");
            $stmt->execute([$linkId]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM links WHERE id = ? AND user_id = ?");
            $stmt->execute([$linkId, $userId]);
        }

        if ($stmt->rowCount() === 0) {
            flash('error', 'Link not found or permission denied.');
        } else {
            $this->forgetLinkCache($slug, $domainId, $pdo);
            flash('success', 'Link deleted.');

            WebhookDispatcher::dispatch($ownerId, 'link.deleted', [
                'id'         => $linkId,
                'short_code' => $slug,
                'deleted_at' => date('c'),
            ]);
        }

        $this->back();
    }

    /**
     * POST /links/bulk
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

        if ($isSuperAdmin) {
            $scopeClause = '';
            $scopeParams = [];
        } else {
            $scopeClause = " AND user_id = ?";
            $scopeParams = [$userId];
        }

        // Grab slugs + domain_id pairs for cache purge
        $slugStmt = $pdo->prepare("SELECT short_code, domain_id FROM links WHERE id IN ({$placeholders}) {$scopeClause}");
        $slugStmt->execute(array_merge($ids, $scopeParams));
        $slugsRows = $slugStmt->fetchAll(\PDO::FETCH_ASSOC);

        $affected = 0;

        if ($action === 'delete') {
            $delLogs = $pdo->prepare("DELETE FROM click_logs WHERE link_id IN ({$placeholders})");
            $delLogs->execute($ids);

            $delTags = $pdo->prepare("DELETE FROM link_tags WHERE link_id IN ({$placeholders})");
            $delTags->execute($ids);

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

        foreach ($slugsRows as $r) {
            $d = $r['domain_id'] !== null ? (int)$r['domain_id'] : null;
            $this->forgetLinkCache($r['short_code'], $d, $pdo);
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

    private function isUrlSafe($url) {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) return false;

        $currentHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $currentHost = preg_replace('/:\d+$/', '', $currentHost);
        if (strcasecmp($host, $currentHost) === 0) return false;

        $ip = gethostbyname($host);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            $isPublic = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
            if ($isPublic === false) return false;
        }

        return true;
    }

    /**
     * Build the public short URL for a link, honoring custom domains.
     */
    private function buildShortUrl(string $slug, ?int $domainId = null, $pdo = null): string {
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

        if ($domainId !== null && $pdo) {
            $stmt = $pdo->prepare("SELECT hostname FROM domains WHERE id = ? LIMIT 1");
            $stmt->execute([$domainId]);
            $host = $stmt->fetchColumn();
            if ($host) {
                return $scheme . '://' . $host . '/' . $slug;
            }
        }

        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $basePath = rtrim(str_replace('/index.php', '', $_SERVER['PHP_SELF']), '/');
        return $scheme . '://' . $host . $basePath . '/' . $slug;
    }

    /**
     * Forget the redirect cache for a link, on all hosts it may be served from.
     * Forgetting both the current host AND the custom domain host avoids
     * stale entries after a domain change.
     */
    private function forgetLinkCache(string $slug, ?int $domainId, $pdo): void {
        $hosts = [$_SERVER['HTTP_HOST'] ?? 'default'];

        if ($domainId !== null) {
            $stmt = $pdo->prepare("SELECT hostname FROM domains WHERE id = ? LIMIT 1");
            $stmt->execute([$domainId]);
            $h = $stmt->fetchColumn();
            if ($h) $hosts[] = $h;
        }

        foreach (array_unique($hosts) as $h) {
            Cache::forget($h . ':' . $slug);
        }
    }

    private function back() {
    $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
    header('Location: ' . $baseURL . '/links');
    exit;
}
    /**
 * Parse + validate the targeting JSON from POST.
 * Returns a JSON string ready to store, or null if no valid rules.
 */
private function parseTargeting(?string $raw): ?string {
    if (empty($raw)) return null;

    $rules = json_decode($raw, true);
    if (!is_array($rules)) return null;

    $clean = [];
    foreach ($rules as $rule) {
        if (!is_array($rule)) continue;

        $type = $rule['type'] ?? '';
        $match = trim((string)($rule['match'] ?? ''));
        $url   = trim((string)($rule['url'] ?? ''));

        if (!in_array($type, ['device', 'country'], true)) continue;
        if ($match === '' || !filter_var($url, FILTER_VALIDATE_URL)) continue;
        if (!$this->isUrlSafe($url)) continue;

        // Normalize
        if ($type === 'device') {
            $match = strtolower($match);
            if (!in_array($match, ['mobile', 'desktop', 'tablet'], true)) continue;
        } elseif ($type === 'country') {
            $match = strtoupper($match);
            if (!preg_match('/^[A-Z]{2}$/', $match)) continue;
        }

        $clean[] = [
            'type'  => $type,
            'match' => $match,
            'url'   => $url,
        ];

        // Cap at 10 rules
        if (count($clean) >= 10) break;
    }

    if (empty($clean)) return null;
    return json_encode($clean, JSON_UNESCAPED_SLASHES);
}
/**
 * GET /links
 * Full links management page with table, filters, bulk actions, and modals.
 */
public function index() {
    if (empty($_SESSION['user_id'])) {
        $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        header("Location: {$baseURL}/login");
        exit;
    }

    $pdo = \App\Core\Database::getInstance();
    $userId = $_SESSION['user_id'];
    $role = $_SESSION['role'] ?? 'user';
    $isSuperAdmin = ($role === 'super_admin');
    $filter = $_GET['filter'] ?? 'all';
    $tag = trim($_GET['tag'] ?? '');
    $tag = ($tag === 'all') ? '' : $tag;

    // Scope clause
    $scopeSql = $isSuperAdmin ? '' : ' AND l.user_id = ?';
    $scopeParams = $isSuperAdmin ? [] : [$userId];

    // Tag filter
    $tagSql = '';
    $tagParams = [];
    if ($tag !== '') {
        if ($isSuperAdmin) {
            $tagSql = " AND l.id IN (SELECT lt.link_id FROM link_tags lt JOIN tags t ON lt.tag_id = t.id WHERE t.name = ?)";
            $tagParams[] = $tag;
        } else {
            $tagSql = " AND l.id IN (SELECT lt.link_id FROM link_tags lt JOIN tags t ON lt.tag_id = t.id WHERE t.user_id = ? AND t.name = ?)";
            $tagParams[] = $userId;
            $tagParams[] = $tag;
        }
    }

    // Filter clause
    $filterSql = '';
    if ($filter === 'active') {
        $filterSql = " AND l.status = 'active' AND (l.expires_at IS NULL OR l.expires_at > NOW())";
    } elseif ($filter === 'disabled') {
        $filterSql = " AND l.status = 'disabled'";
    } elseif ($filter === 'expired') {
        $filterSql = " AND l.expires_at IS NOT NULL AND l.expires_at <= NOW()";
    }

    // KPIs
    $stmt = $pdo->prepare("
        SELECT
            COUNT(l.id) AS total_links,
            SUM(CASE WHEN l.status = 'active' AND (l.expires_at IS NULL OR l.expires_at > NOW()) THEN 1 ELSE 0 END) AS active_links,
            COALESCE(SUM(l.clicks), 0) AS total_clicks
        FROM links l
        WHERE 1=1 {$scopeSql} {$tagSql} {$filterSql}
    ");
    $stmt->execute(array_merge($scopeParams, $tagParams));
    $kpis = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

    $total_clicks  = (int)($kpis['total_clicks']  ?? 0);
    $active_links  = (int)($kpis['active_links']  ?? 0);

    // Unique visitors
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT cl.visitor_hash)
        FROM click_logs cl
        JOIN links l ON cl.link_id = l.id
        WHERE 1=1 {$scopeSql} {$tagSql} {$filterSql}
    ");
    $stmt->execute(array_merge($scopeParams, $tagParams));
    $unique_visitors = (int)$stmt->fetchColumn();

    // Links table
    $stmt = $pdo->prepare("
        SELECT l.*,
               GROUP_CONCAT(DISTINCT t.name SEPARATOR ',') AS tags,
               u.email AS owner_email
        FROM links l
        LEFT JOIN link_tags lt ON l.id = lt.link_id
        LEFT JOIN tags t ON lt.tag_id = t.id
        LEFT JOIN users u ON l.user_id = u.id
        WHERE 1=1 {$scopeSql} {$tagSql} {$filterSql}
        GROUP BY l.id
        ORDER BY l.created_at DESC
        LIMIT 50
    ");
    $stmt->execute(array_merge($scopeParams, $tagParams));
    $recent_links = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $activeTag = $tag;
    $baseURL = "http://" . $_SERVER['HTTP_HOST'] . str_replace('/index.php', '', $_SERVER['PHP_SELF']);
    require BASE_PATH . '/resources/views/links.php';
}
}