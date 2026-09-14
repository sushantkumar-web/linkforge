<?php
namespace App\Controllers;
use PDO;
use PDOException;

class InstallController {
    public function index() {
        require BASE_PATH . '/resources/views/install.php';
    }

    public function setup() {
    try {
        // 1. Test Database Connection
        $dsn = "mysql:host={$_POST['db_host']};dbname={$_POST['db_name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $_POST['db_user'], $_POST['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        // 2. Ensure Config Directory Exists & Write File
        $configDir = BASE_PATH . '/config';
        if (!is_dir($configDir)) {
            if (!mkdir($configDir, 0777, true)) {
                throw new \Exception("Cannot create the 'config' directory. Please create it manually inside the linkforge folder.");
            }
        }
        
        $configPath = $configDir . '/config.php';
        $configContent = "<?php\nreturn [\n"
            . "    'DB_HOST' => '" . addslashes($_POST['db_host']) . "',\n"
            . "    'DB_NAME' => '" . addslashes($_POST['db_name']) . "',\n"
            . "    'DB_USER' => '" . addslashes($_POST['db_user']) . "',\n"
            . "    'DB_PASS' => '" . addslashes($_POST['db_pass']) . "',\n"
            . "];\n";
            
        if (file_put_contents($configPath, $configContent) === false) {
            throw new \Exception("Failed to write config.php. Please check folder permissions.");
        }

        // 3. Run V1 Migrations
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                role VARCHAR(50) DEFAULT 'super_admin',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS links (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                destination_url TEXT NOT NULL,
                short_code VARCHAR(50) UNIQUE NOT NULL,
                clicks INT DEFAULT 0,
                status VARCHAR(20) DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // 4. Create Admin User (Ignore if already exists from your previous attempt)
        $stmt = $pdo->prepare("INSERT IGNORE INTO users (email, password) VALUES (?, ?)");
        $stmt->execute([$_POST['admin_email'], password_hash($_POST['admin_pass'], PASSWORD_DEFAULT)]);

        // 5. Redirect to Login
        $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        header("Location: {$baseURL}/login");
        exit;

    } catch (PDOException $e) {
        $error = "Database Error: " . $e->getMessage();
        require BASE_PATH . '/resources/views/install.php';
    } catch (\Exception $e) {
        $error = "System Error: " . $e->getMessage();
        require BASE_PATH . '/resources/views/install.php';
    }
}
}