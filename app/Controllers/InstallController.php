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

        $configWritten = false;

        try {
            // 1. Verify connection
            $pdo = new PDO(
                "mysql:host={$host};dbname={$name};charset=utf8mb4",
                $user, $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            // 2. Write config.php FIRST so Database::getInstance() can find it
            $configDir = BASE_PATH . '/config';
            if (!is_dir($configDir)) {
                mkdir($configDir, 0755, true);
            }

            $configContent = "<?php\nreturn [\n"
                . "    'DB_HOST' => " . var_export($host, true) . ",\n"
                . "    'DB_NAME' => " . var_export($name, true) . ",\n"
                . "    'DB_USER' => " . var_export($user, true) . ",\n"
                . "    'DB_PASS' => " . var_export($pass, true) . ",\n"
                . "];\n";

            if (file_put_contents($configDir . '/config.php', $configContent) === false) {
                throw new Exception("Could not write config/config.php. Check folder permissions.");
            }
            $configWritten = true;

            // 3. Run all pending migrations
            $result = \App\Core\MigrationRunner::run();

            // 4. Create initial admin account
            $hash = password_hash($admin_pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (email, password, role, status) VALUES (?, ?, 'super_admin', 'active')");
            $stmt->execute([$admin_email, $hash]);

            // 5. Success → redirect to login
            $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
            header("Location: {$baseURL}/login");
            exit;

        } catch (Exception $e) {
            // Roll back config so install can be retried
            if ($configWritten) {
                @unlink(BASE_PATH . '/config/config.php');
            }
            die("<h3 style='color:red;'>Installation Failed:</h3><pre>" . htmlspecialchars($e->getMessage()) . "</pre>");
        }
    }
}