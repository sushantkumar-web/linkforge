<?php
namespace App\Controllers;

use App\Core\Database;
use PDO;

class ApiController {
    private $user_id;
    private $api_key_id;
    private $scopes = [];

    public function __construct() {
        header('Content-Type: application/json');
    }

    /**
     * Authenticates the incoming Bearer token.
     */
    private function authenticate() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $this->response(401, ['error' => 'Missing or invalid token. Format: Authorization: Bearer <your_key>']);
        }

        $token = $matches[1];
        $hashedToken = hash('sha256', trim($token));
        $pdo = Database::getInstance();

        // 1. Fetch the key details based on the hashed token
        $stmt = $pdo->prepare("SELECT id, user_id, scopes FROM api_keys WHERE hashed_key = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$hashedToken]);
        $apiKey = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$apiKey) {
            $this->response(401, ['error' => 'Unauthorized: Invalid or revoked API key']);
        }

        // 2. Set class properties for later use
        $this->user_id = (int)$apiKey['user_id'];
        $this->api_key_id = (int)$apiKey['id'];
        $this->scopes = array_map('trim', explode(',', $apiKey['scopes']));

        // 3. Update last_used_at and total_requests
        $updateStmt = $pdo->prepare("UPDATE api_keys SET last_used_at = NOW(), total_requests = total_requests + 1 WHERE id = ?");
        $updateStmt->execute([$this->api_key_id]);
    }

    /**
     * Checks if the current API key has the required scope.
     */
    private function checkScope(string $requiredScope) {
        if (!in_array($requiredScope, $this->scopes)) {
            $this->response(403, ['error' => "Forbidden: Your API key lacks the '$requiredScope' scope."]);
        }
    }

    /**
     * Internal JSON response helper with telemetry logging.
     */
    private function response(int $code, array $payload) {
        // Log the request to the telemetry table if we have a valid API key ID
        if ($this->api_key_id) {
            try {
                $pdo = Database::getInstance();
                $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                $method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
                $uri = $_SERVER['REQUEST_URI'] ?? 'UNKNOWN';
                
                $logStmt = $pdo->prepare("INSERT INTO api_request_logs (api_key_id, endpoint, http_method, status_code, ip_address) VALUES (?, ?, ?, ?, ?)");
                $logStmt->execute([$this->api_key_id, $uri, $method, $code, $ip]);
            } catch (\Exception $e) {
                // Fail silently for logging errors to not break the API response
            }
        }

        http_response_code($code);
        echo json_encode($payload);
        exit;
    }

    /**
     * Dispatcher for incoming /api/v1/ requests.
     */
    public function dispatch(string $method, string $uri) {
        // Authenticate before anything else
        $this->authenticate();

        $path = trim(substr($uri, strlen('/api/v1/')), '/');
        $parts = explode('/', $path);

        // /api/v1/links
        if ($parts[0] === 'links') {
            // GET /api/v1/links
            if (count($parts) === 1 && $method === 'GET') {
                $this->checkScope('links:read');
                $this->index();
            }
            // POST /api/v1/links
            elseif (count($parts) === 1 && $method === 'POST') {
                $this->checkScope('links:write');
                $this->store();
            }
            // /api/v1/links/{id}
            elseif (count($parts) === 2 && is_numeric($parts[1])) {
                $id = (int)$parts[1];
                if ($method === 'GET') {
                    $this->checkScope('links:read');
                    $this->show($id);
                }
                elseif ($method === 'PATCH') {
                    $this->checkScope('links:write');
                    $this->update($id);
                }
                elseif ($method === 'DELETE') {
                    $this->checkScope('links:write');
                    $this->destroy($id);
                }
            }
            // GET /api/v1/links/{id}/analytics
            elseif (count($parts) === 3 && is_numeric($parts[1]) && $parts[2] === 'analytics' && $method === 'GET') {
                $this->checkScope('links:read');
                $this->analytics((int)$parts[1]);
            }
        }

        $this->response(404, ['error' => 'API endpoint or method not found']);
    }

    /**
     * GET /api/v1/links
     */
    public function index() {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("SELECT id, title, short_code, destination_url, clicks, status, expires_at, created_at, updated_at FROM links WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$this->user_id]);
        $links = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $baseURL = "http://" . $_SERVER['HTTP_HOST'] . str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        foreach ($links as &$link) {
            $link['short_url'] = $baseURL . '/' . $link['short_code'];
        }

        $this->response(200, ['status' => 'success', 'count' => count($links), 'data' => $links]);
    }

    /**
     * GET /api/v1/links/{id}
     */
    public function show(int $id) {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("SELECT id, title, short_code, destination_url, clicks, status, expires_at, created_at, updated_at FROM links WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$id, $this->user_id]);
        $link = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$link) {
            $this->response(404, ['error' => 'Link not found']);
        }

        $baseURL = "http://" . $_SERVER['HTTP_HOST'] . str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        $link['short_url'] = $baseURL . '/' . $link['short_code'];

        $this->response(200, ['status' => 'success', 'data' => $link]);
    }

    /**
     * POST /api/v1/links
     */
    public function store() {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $url = filter_var($input['url'] ?? '', FILTER_SANITIZE_URL);
        $title = trim($input['title'] ?? '');
        $custom_slug = trim($input['slug'] ?? '');
        $expires_at = !empty($input['expires_at']) ? $input['expires_at'] : null;

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $this->response(400, ['error' => 'Invalid destination URL format']);
        }

        $slug = $custom_slug ?: substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);
        $pdo = Database::getInstance();

        try {
            $stmt = $pdo->prepare("INSERT INTO links (user_id, title, destination_url, short_code, expires_at) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$this->user_id, $title ?: null, $url, $slug, $expires_at]);
            $id = $pdo->lastInsertId();

            $baseURL = "http://" . $_SERVER['HTTP_HOST'] . str_replace('/index.php', '', $_SERVER['PHP_SELF']);

            $this->response(201, [
                'status' => 'success',
                'data' => [
                    'id' => (int)$id,
                    'title' => $title ?: null,
                    'short_url' => $baseURL . '/' . $slug,
                    'slug' => $slug,
                    'destination_url' => $url,
                    'expires_at' => $expires_at
                ]
            ]);
        } catch (\PDOException $e) {
            // Check for duplicate entry error code 23000
            if ($e->getCode() == 23000) {
                $this->response(409, ['error' => 'That short code is already taken']);
            }
            $this->response(500, ['error' => 'Database error: ' . $e->getMessage()]);
        }
    }

    /**
     * PATCH /api/v1/links/{id}
     */
    public function update(int $id) {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("SELECT id FROM links WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$id, $this->user_id]);
        if (!$stmt->fetch()) {
            $this->response(404, ['error' => 'Link not found']);
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $updates = [];
        $params = [];

        if (isset($input['title'])) {
            $updates[] = "title = ?";
            $params[] = trim($input['title']) ?: null;
        }
        if (isset($input['destination_url'])) {
            if (!filter_var($input['destination_url'], FILTER_VALIDATE_URL)) {
                $this->response(400, ['error' => 'Invalid destination URL']);
            }
            $updates[] = "destination_url = ?";
            $params[] = $input['destination_url'];
        }
        if (isset($input['status'])) {
            if (!in_array($input['status'], ['active', 'disabled'])) {
                $this->response(400, ['error' => 'Status must be active or disabled']);
            }
            $updates[] = "status = ?";
            $params[] = $input['status'];
        }
        if (array_key_exists('expires_at', $input)) {
            $updates[] = "expires_at = ?";
            $params[] = !empty($input['expires_at']) ? $input['expires_at'] : null;
        }

        if (empty($updates)) {
            $this->response(400, ['error' => 'No valid fields provided for update']);
        }

        $params[] = $id;
        $params[] = $this->user_id;
        $sql = "UPDATE links SET " . implode(', ', $updates) . " WHERE id = ? AND user_id = ?";
        $pdo->prepare($sql)->execute($params);

        $this->response(200, ['status' => 'success', 'message' => 'Link updated successfully']);
    }

    /**
     * DELETE /api/v1/links/{id}
     */
    public function destroy(int $id) {
        $pdo = Database::getInstance();
        // First delete associated analytics to maintain referential integrity
        $pdo->prepare("DELETE FROM click_logs WHERE link_id = ?")->execute([$id]);
        $stmt = $pdo->prepare("DELETE FROM links WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $this->user_id]);

        if ($stmt->rowCount() === 0) {
            $this->response(404, ['error' => 'Link not found']);
        }

        $this->response(200, ['status' => 'success', 'message' => 'Link and associated analytics deleted']);
    }

    /**
     * GET /api/v1/links/{id}/analytics
     */
    public function analytics(int $id) {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("SELECT id, short_code, destination_url, clicks FROM links WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$id, $this->user_id]);
        $link = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$link) {
            $this->response(404, ['error' => 'Link not found']);
        }

        // Unique visitors
        $uniqueStmt = $pdo->prepare("SELECT COUNT(DISTINCT visitor_hash) FROM click_logs WHERE link_id = ?");
        $uniqueStmt->execute([$id]);
        $uniqueVisitors = (int)$uniqueStmt->fetchColumn();

        // Referrers
        $refStmt = $pdo->prepare("SELECT referrer as name, COUNT(*) as count FROM click_logs WHERE link_id = ? GROUP BY referrer ORDER BY count DESC LIMIT 10");
        $refStmt->execute([$id]);

        // Devices
        $devStmt = $pdo->prepare("SELECT device_type as name, COUNT(*) as count FROM click_logs WHERE link_id = ? GROUP BY device_type ORDER BY count DESC");
        $devStmt->execute([$id]);

        // Browsers
        $browserStmt = $pdo->prepare("SELECT browser as name, COUNT(*) as count FROM click_logs WHERE link_id = ? GROUP BY browser ORDER BY count DESC");
        $browserStmt->execute([$id]);

        $this->response(200, [
            'status' => 'success',
            'data' => [
                'link_id' => (int)$link['id'],
                'short_code' => $link['short_code'],
                'total_clicks' => (int)$link['clicks'],
                'unique_visitors' => $uniqueVisitors,
                'breakdown' => [
                    'referrers' => $refStmt->fetchAll(PDO::FETCH_ASSOC),
                    'devices' => $devStmt->fetchAll(PDO::FETCH_ASSOC),
                    'browsers' => $browserStmt->fetchAll(PDO::FETCH_ASSOC)
                ]
            ]
        ]);
    }
}