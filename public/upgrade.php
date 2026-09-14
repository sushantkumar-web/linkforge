<?php
require dirname(__DIR__) . '/config/config.php';
$config = require dirname(__DIR__) . '/config/config.php';

$pdo = new PDO("mysql:host={$config['DB_HOST']};dbname={$config['DB_NAME']};charset=utf8mb4", $config['DB_USER'], $config['DB_PASS']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("
    CREATE TABLE IF NOT EXISTS click_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        link_id INT NOT NULL,
        visitor_hash VARCHAR(64) NOT NULL,
        referrer VARCHAR(255) DEFAULT 'Direct',
        browser VARCHAR(50) DEFAULT 'Unknown',
        os VARCHAR(50) DEFAULT 'Unknown',
        device_type VARCHAR(20) DEFAULT 'Desktop',
        clicked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(link_id),
        INDEX(clicked_at)
    );
");
echo "Analytics table created successfully! Please delete this file.";