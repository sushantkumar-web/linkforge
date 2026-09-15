<?php
namespace App\Controllers;

use App\Core\Database;

class ApiAuth {
    public static function authenticate() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (empty($authHeader) || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            self::respond(401, 'Missing or invalid Authorization header');
        }

        $rawKey = $matches[1];
        $hashedKey = hash('sha256', $rawKey);

        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM api_keys WHERE hashed_key = ? AND status = 'active'");
        $stmt->execute([$hashedKey]);
        $key = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$key) {
            self::respond(401, 'Invalid or revoked API key');
        }

        // Update last used and total requests
        $stmt = $db->prepare("UPDATE api_keys SET last_used_at = NOW(), total_requests = total_requests + 1 WHERE id = ?");
        $stmt->execute([$key['id']]);

        return $key; // Return key data (including scopes) to the controller
    }

    private static function respond($code, $message) {
        http_response_code($code);
        echo json_encode(['error' => $message]);
        exit;
    }
}