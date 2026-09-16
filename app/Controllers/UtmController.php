<?php
namespace App\Controllers;

use App\Core\Database;

class UtmController {

    public function __construct() {
        if (empty($_SESSION['user_id'])) {
            $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
            header("Location: {$baseURL}/login");
            exit;
        }
    }

    /**
     * GET /utm-presets
     * List all presets for the current user.
     */
    public function index() {
        $pdo = Database::getInstance();
        $userId = $_SESSION['user_id'];

        $stmt = $pdo->prepare("
            SELECT p.*,
                   (SELECT COUNT(*) FROM links WHERE user_id = p.user_id AND destination_url LIKE CONCAT('%utm_campaign=', p.utm_campaign, '%')) AS usage_count
            FROM utm_presets p
            WHERE p.user_id = ?
            ORDER BY p.is_default DESC, p.created_at DESC
        ");
        $stmt->execute([$userId]);
        $presets = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        require BASE_PATH . '/resources/views/utm-presets.php';
    }

    public function store() {
        $userId = $_SESSION['user_id'];

        $name         = trim($_POST['name'] ?? '');
        $utm_source   = trim($_POST['utm_source'] ?? '');
        $utm_medium   = trim($_POST['utm_medium'] ?? '');
        $utm_campaign = trim($_POST['utm_campaign'] ?? '');
        $utm_term     = trim($_POST['utm_term'] ?? '');
        $utm_content  = trim($_POST['utm_content'] ?? '');
        $is_default   = !empty($_POST['is_default']) ? 1 : 0;

        if ($name === '' || $utm_source === '' || $utm_medium === '') {
            flash('error', 'Name, Source, and Medium are required.');
            $this->back();
        }

        // Sanitize: lowercase, no spaces (URL-safe)
        $utm_source   = $this->slugify($utm_source);
        $utm_medium   = $this->slugify($utm_medium);
        $utm_campaign = $this->slugify($utm_campaign);
        $utm_term     = $this->slugify($utm_term);
        $utm_content  = $this->slugify($utm_content);

        $pdo = Database::getInstance();

        // If this is marked as default, unset any other default
        if ($is_default) {
            $pdo->prepare("UPDATE utm_presets SET is_default = 0 WHERE user_id = ?")->execute([$userId]);
        }

        $stmt = $pdo->prepare("
            INSERT INTO utm_presets (user_id, name, utm_source, utm_medium, utm_campaign, utm_term, utm_content, is_default)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId, $name, $utm_source, $utm_medium,
            $utm_campaign ?: null, $utm_term ?: null, $utm_content ?: null,
            $is_default
        ]);

        flash('success', 'UTM preset "' . $name . '" created.');
        $this->back();
    }

    public function update() {
        $userId = $_SESSION['user_id'];
        $id     = (int)($_POST['preset_id'] ?? 0);

        if (!$id) { $this->back(); }

        $pdo = Database::getInstance();
        $check = $pdo->prepare("SELECT id FROM utm_presets WHERE id = ? AND user_id = ?");
        $check->execute([$id, $userId]);
        if (!$check->fetch()) {
            flash('error', 'Preset not found.');
            $this->back();
        }

        $name         = trim($_POST['name'] ?? '');
        $utm_source   = $this->slugify(trim($_POST['utm_source'] ?? ''));
        $utm_medium   = $this->slugify(trim($_POST['utm_medium'] ?? ''));
        $utm_campaign = $this->slugify(trim($_POST['utm_campaign'] ?? ''));
        $utm_term     = $this->slugify(trim($_POST['utm_term'] ?? ''));
        $utm_content  = $this->slugify(trim($_POST['utm_content'] ?? ''));
        $is_default   = !empty($_POST['is_default']) ? 1 : 0;

        if ($name === '' || $utm_source === '' || $utm_medium === '') {
            flash('error', 'Name, Source, and Medium are required.');
            $this->back();
        }

        if ($is_default) {
            $pdo->prepare("UPDATE utm_presets SET is_default = 0 WHERE user_id = ?")->execute([$userId]);
        }

        $stmt = $pdo->prepare("
            UPDATE utm_presets
            SET name = ?, utm_source = ?, utm_medium = ?, utm_campaign = ?,
                utm_term = ?, utm_content = ?, is_default = ?
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([
            $name, $utm_source, $utm_medium,
            $utm_campaign ?: null, $utm_term ?: null, $utm_content ?: null,
            $is_default, $id, $userId
        ]);

        flash('success', 'Preset updated.');
        $this->back();
    }

    public function delete() {
        $userId = $_SESSION['user_id'];
        $id     = (int)($_POST['preset_id'] ?? 0);

        $pdo = Database::getInstance();
        $pdo->prepare("DELETE FROM utm_presets WHERE id = ? AND user_id = ?")->execute([$id, $userId]);

        flash('success', 'Preset deleted.');
        $this->back();
    }

    /**
     * GET /api/utm-presets
     * JSON list — used by the create-link modal's preset dropdown.
     */
    public function apiList() {
        header('Content-Type: application/json');
        $pdo = Database::getInstance();
        $userId = $_SESSION['user_id'];

        $stmt = $pdo->prepare("
            SELECT id, name, utm_source, utm_medium, utm_campaign, utm_term, utm_content, is_default
            FROM utm_presets
            WHERE user_id = ?
            ORDER BY is_default DESC, name ASC
        ");
        $stmt->execute([$userId]);

        echo json_encode(['presets' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
        exit;
    }

    /**
     * Convert a string into a URL-safe slug for UTM values.
     * "Summer Sale 2026" → "summer_sale_2026"
     */
    private function slugify(string $text): string {
        if ($text === '') return '';
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\-\s]/', '', $text);
        $text = preg_replace('/[\s\-]+/', '_', $text);
        return trim($text, '_');
    }

    private function back() {
        $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        header('Location: ' . $baseURL . '/utm-presets');
        exit;
    }
}