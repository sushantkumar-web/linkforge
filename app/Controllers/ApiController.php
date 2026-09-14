<?php
namespace App\Controllers;

use App\Core\Database;

class ApiController {
    public function createLink() {
        header('Content-Type: application/json');
        
        // 1. Authenticate API Key
        $headers = getallheaders();
        $auth = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        if (!preg_match('/Bearer\s(\S+)/', $auth, $matches)) {
            http_response_code(401);
            echo json_encode(['error' => 'Missing or invalid token. Format: Authorization: Bearer <your_key>']);
            exit;
        }
        
        $token = $matches[1];
        $pdo = Database::getInstance();
        
        $stmt = $pdo->prepare("SELECT user_id FROM api_keys WHERE api_key = ? LIMIT 1");
        $stmt->execute([$token]);
        $key = $stmt->fetch();
        
        if (!$key) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid API key']);
            exit;
        }
        
        // 2. Update Last Used
        $pdo->prepare("UPDATE api_keys SET last_used_at = CURRENT_TIMESTAMP WHERE api_key = ?")->execute([$token]);
        
        // 3. Parse JSON Request
        $input = json_decode(file_get_contents('php://input'), true);
        $url = filter_var($input['url'] ?? '', FILTER_SANITIZE_URL);
        $custom_slug = trim($input['slug'] ?? '');
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid or missing destination URL']);
            exit;
        }
        
        $slug = $custom_slug ?: substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);
        
        // 4. Create Link and Return JSON
        try {
            $stmt = $pdo->prepare("INSERT INTO links (user_id, destination_url, short_code) VALUES (?, ?, ?)");
            $stmt->execute([$key['user_id'], $url, $slug]);
            
            $baseURL = "http://" . $_SERVER['HTTP_HOST'] . str_replace('/index.php', '', $_SERVER['PHP_SELF']);
            
            http_response_code(201);
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'short_url' => $baseURL . '/' . $slug,
                    'slug' => $slug,
                    'destination' => $url
                ]
            ]);
        } catch (\PDOException $e) {
            http_response_code(409);
            echo json_encode(['error' => 'That short code is already taken']);
        }
    }
}