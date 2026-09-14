<?php
namespace App\Controllers;

use PDO;
use Exception;

class InstallController {
    public function index() {
        if (file_exists(BASE_PATH . '/config/config.php')) {
            $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
            header("Location: {$baseURL}/");
            exit;
        }
        require BASE_PATH . '/resources/views/install.php';
    }

    public function setup() {
        if (file_exists(BASE_PATH . '/config/config.php')) {
            die("LinkForge is already installed.");
        }

        $host = trim($_POST['db_host'] ?? 'localhost');
        $name = trim($_POST['db_name'] ?? '');
        $user = trim($_POST['db_user'] ?? '');
        $pass = $_POST['db_pass'] ?? '';
        $admin_email = trim($_POST['admin_email'] ?? '');
        $admin_pass = $_POST['admin_pass'] ?? '';

        if (!$name || !$user || !$admin_email || !$admin_pass) {
            die("Please fill all required fields.");
        }

        try {
            // 1. Verify Database Connection
            $pdo = new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            // 2. Base Core Tables
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    email VARCHAR(191) UNIQUE NOT NULL,
                    password_hash VARCHAR(255) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );

                CREATE TABLE IF NOT EXISTS links (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    title VARCHAR(255) NULL,
                    destination_url TEXT NOT NULL,
                    short_code VARCHAR(50) UNIQUE NOT NULL,
                    clicks INT DEFAULT 0,
                    expires_at DATETIME NULL,
                    status ENUM('active', 'disabled') DEFAULT 'active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX(user_id),
                    INDEX(short_code)
                );

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

                CREATE TABLE IF NOT EXISTS api_keys (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    api_key VARCHAR(64) UNIQUE NOT NULL,
                    last_used_at TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX(user_id)
                );

                CREATE TABLE IF NOT EXISTS tags (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    name VARCHAR(50) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX(user_id)
                );

                CREATE TABLE IF NOT EXISTS link_tags (
                    link_id INT NOT NULL,
                    tag_id INT NOT NULL,
                    PRIMARY KEY (link_id, tag_id)
                );

                CREATE TABLE IF NOT EXISTS system_settings (
                    setting_key VARCHAR(50) PRIMARY KEY,
                    setting_value VARCHAR(255) NOT NULL
                );
            ");

            // 3. Mark DB as Version 2
            $pdo->exec("
                INSERT INTO system_settings (setting_key, setting_value) 
                VALUES ('db_version', '2') 
                ON DUPLICATE KEY UPDATE setting_value = '2';
            ");

            // 4. Create Initial Administrator Account
            $hash = password_hash($admin_pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (email, password_hash) VALUES (?, ?)");
            $stmt->execute([$admin_email, $hash]);
            $admin_id = $pdo->lastInsertId();

            // 5. Generate Initial API Key for Admin
            $initial_api_key = 'lf_prod_' . bin2hex(random_bytes(16));
            $pdo->prepare("INSERT INTO api_keys (user_id, api_key) VALUES (?, ?)")->execute([$admin_id, $initial_api_key]);

            // 6. Write config/config.php securely
            $configContent = "<?php\nreturn [\n"
                . "    'DB_HOST' => " . var_export($host, true) . ",\n"
                . "    'DB_NAME' => " . var_export($name, true) . ",\n"
                . "    'DB_USER' => " . var_export($user, true) . ",\n"
                . "    'DB_PASS' => " . var_export($pass, true) . ",\n"
                . "];\n";

            file_put_contents(BASE_PATH . '/config/config.php', $configContent);

            $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
            header("Location: {$baseURL}/login");
            exit;

        } catch (Exception $e) {
            die("<h3 style='color:red;'>Installation Failed:</h3><pre>" . htmlspecialchars($e->getMessage()) . "</pre>");
        }
    }
}