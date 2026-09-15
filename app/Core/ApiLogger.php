<?php
namespace App\Core;

class ApiLogger {
    public static function log($apiKeyId, $endpoint, $method, $statusCode) {
        $db = Database::getInstance();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        $stmt = $db->prepare("INSERT INTO api_request_logs (api_key_id, endpoint, http_method, status_code, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$apiKeyId, $endpoint, $method, $statusCode, $ip]);
    }
}