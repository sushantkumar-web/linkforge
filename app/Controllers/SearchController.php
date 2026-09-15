<?php
namespace App\Controllers;

use App\Core\Database;

class SearchController {

    /**
     * GET /search?q=...
     * Full-page search results.
     */
    public function index() {
        if (empty($_SESSION['user_id'])) {
            $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
            header("Location: {$baseURL}/login");
            exit;
        }

        $q = trim($_GET['q'] ?? '');
        $userId = $_SESSION['user_id'];
        $role = $_SESSION['role'] ?? 'user';
        $isSuperAdmin = ($role === 'super_admin');

        $results = [];
        if ($q !== '') {
            $pdo = Database::getInstance();
            $like = '%' . $q . '%';

            $sql = "
                SELECT DISTINCT l.id, l.title, l.short_code, l.destination_url,
                       l.clicks, l.status, l.expires_at, l.created_at,
                       u.email AS owner_email
                FROM links l
                LEFT JOIN link_tags lt ON l.id = lt.link_id
                LEFT JOIN tags t ON lt.tag_id = t.id
                LEFT JOIN users u ON l.user_id = u.id
                WHERE (
                    l.short_code LIKE ?
                    OR l.destination_url LIKE ?
                    OR l.title LIKE ?
                    OR t.name LIKE ?
                )
            ";
            $params = [$like, $like, $like, $like];

            if (!$isSuperAdmin) {
                $sql .= " AND l.user_id = ?";
                $params[] = $userId;
            }

            $sql .= " ORDER BY l.clicks DESC, l.created_at DESC LIMIT 100";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        $baseURL = "http://" . $_SERVER['HTTP_HOST'] . str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        require BASE_PATH . '/resources/views/search.php';
    }

    /**
     * GET /search/quick?q=...
     * JSON endpoint for the topbar live dropdown.
     */
    public function quick() {
        header('Content-Type: application/json');

        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $q = trim($_GET['q'] ?? '');
        if (mb_strlen($q) < 2) {
            echo json_encode(['results' => []]);
            exit;
        }

        $userId = $_SESSION['user_id'];
        $role = $_SESSION['role'] ?? 'user';
        $isSuperAdmin = ($role === 'super_admin');
        $like = '%' . $q . '%';

        $pdo = Database::getInstance();

        $sql = "
            SELECT DISTINCT l.id, l.title, l.short_code, l.destination_url, l.clicks, l.status
            FROM links l
            LEFT JOIN link_tags lt ON l.id = lt.link_id
            LEFT JOIN tags t ON lt.tag_id = t.id
            WHERE (
                l.short_code LIKE ?
                OR l.destination_url LIKE ?
                OR l.title LIKE ?
                OR t.name LIKE ?
            )
        ";
        $params = [$like, $like, $like, $like];

        if (!$isSuperAdmin) {
            $sql .= " AND l.user_id = ?";
            $params[] = $userId;
        }

        $sql .= " ORDER BY l.clicks DESC LIMIT 6";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Add absolute short URL for each result
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $basePath = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        $basePath = preg_replace('#/search.*$#', '', $basePath);

        foreach ($rows as &$r) {
            $r['short_url'] = $scheme . '://' . $host . $basePath . '/' . $r['short_code'];
        }

        echo json_encode(['results' => $rows]);
        exit;
    }
}