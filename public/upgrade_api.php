<?php
require dirname(__DIR__) . '/config/config.php';
$config = require dirname(__DIR__) . '/config/config.php';

$pdo = new PDO("mysql:host={$config['DB_HOST']};dbname={$config['DB_NAME']};charset=utf8mb4", $config['DB_USER'], $config['DB_PASS']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("
    CREATE TABLE IF NOT EXISTS api_keys (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        api_key VARCHAR(64) UNIQUE NOT NULL,
        last_used_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(user_id)
    );
");

// Generate a secure API key for User ID 1
$new_key = 'lf_prod_' . bin2hex(random_bytes(16));
$stmt = $pdo->prepare("INSERT INTO api_keys (user_id, api_key) VALUES (1, ?)");
$stmt->execute([$new_key]);

echo "API Table created! Your first API Key is:<br><br>";
echo "<strong style='font-family: monospace; font-size: 18px;'>{$new_key}</strong><br><br>";
echo "Save this key, then delete this file.";