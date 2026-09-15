<?php
namespace App\Controllers;

use App\Core\Database;

class TagsController {

    public function __construct() {
        if (empty($_SESSION['user_id'])) {
            $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
            header("Location: {$baseURL}/login");
            exit;
        }
    }

    /**
     * GET /tags
     * List all tags with usage counts.
     */
    public function index() {
        $pdo = Database::getInstance();
        $userId = $_SESSION['user_id'];
        $role = $_SESSION['role'] ?? 'user';
        $isSuperAdmin = ($role === 'super_admin');

        // Base query — one row per tag with usage count
        $sql = "
            SELECT
                t.id,
                t.name,
                t.user_id,
                t.created_at,
                COUNT(DISTINCT lt.link_id) AS link_count,
                COALESCE(SUM(l.clicks), 0) AS total_clicks,
                u.email AS owner_email
            FROM tags t
            LEFT JOIN link_tags lt ON t.id = lt.tag_id
            LEFT JOIN links l ON lt.link_id = l.id
            LEFT JOIN users u ON t.user_id = u.id
        ";
        $params = [];

        if (!$isSuperAdmin) {
            $sql .= " WHERE t.user_id = ?";
            $params[] = $userId;
        }

        $sql .= " GROUP BY t.id ORDER BY link_count DESC, t.name ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $tags = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Summary metrics
        $totalTags = count($tags);
        $usedTags  = 0;
        $unusedTags = 0;
        foreach ($tags as $t) {
            if ((int)$t['link_count'] > 0) $usedTags++;
            else $unusedTags++;
        }

        $baseURL = "http://" . $_SERVER['HTTP_HOST'] . str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        require BASE_PATH . '/resources/views/tags.php';
    }

    /**
     * POST /tags/delete
     * Delete a tag and its link associations (links themselves stay).
     */
    public function delete() {
        $tagId = (int)($_POST['tag_id'] ?? 0);
        $userId = $_SESSION['user_id'];
        $role = $_SESSION['role'] ?? 'user';
        $isSuperAdmin = ($role === 'super_admin');

        $pdo = Database::getInstance();

        // Verify ownership (super admin can delete any tag)
        if ($isSuperAdmin) {
            $check = $pdo->prepare("SELECT name FROM tags WHERE id = ?");
            $check->execute([$tagId]);
        } else {
            $check = $pdo->prepare("SELECT name FROM tags WHERE id = ? AND user_id = ?");
            $check->execute([$tagId, $userId]);
        }
        $tag = $check->fetch();

        if (!$tag) {
            flash('error', 'Tag not found or you do not have permission to delete it.');
            $this->redirect('/tags');
        }

        // Delete link_tags associations first, then the tag
        $pdo->prepare("DELETE FROM link_tags WHERE tag_id = ?")->execute([$tagId]);
        $pdo->prepare("DELETE FROM tags WHERE id = ?")->execute([$tagId]);

        flash('success', 'Tag "' . $tag['name'] . '" deleted.');
        $this->redirect('/tags');
    }

    private function redirect(string $path): void {
        $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        header('Location: ' . $baseURL . $path);
        exit;
    }
}