<?php
namespace App\Controllers;

use App\Core\Database;
use PDO;
use Exception;

class UpdateController {
    public function runMigrations() {
        if (!isset($_SESSION['user_id'])) die("Unauthorized");
        
        // CSRF Protection
        $token = $_POST['csrf_token'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            die("403 Forbidden: Invalid CSRF Token.");
        }
        
        $pdo = Database::getInstance();
        
        // 1. Check current DB version safely
        $current_version = 0;
        try {
            $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'db_version'");
            if ($row = $stmt->fetch()) {
                $current_version = (int)$row['setting_value'];
            }
        } catch (Exception $e) {
            // If system_settings doesn't exist, we are at version 0
            $current_version = 0;
        }
        
        // 2. Scan the migrations directory
        $migration_dir = BASE_PATH . '/database/migrations';
        $files = glob($migration_dir . '/*.sql');
        sort($files); // Ensure sequential execution (001, 002, etc.)
        
        $migrations_run = 0;
        
        // 3. Execute new migrations
        foreach ($files as $file) {
            $filename = basename($file);
            // Extract the number from the filename (e.g., "001" -> 1)
            preg_match('/^(\d+)_/', $filename, $matches);
            if (!isset($matches[1])) continue;
            
            $file_version = (int)$matches[1];
            
            if ($file_version > $current_version) {
                $sql = file_get_contents($file);
                
                try {
                    $pdo->exec($sql);
                    
                    // Update the tracker
                    $update = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('db_version', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                    $update->execute([(string)$file_version, (string)$file_version]);
                    
                    $current_version = $file_version;
                    $migrations_run++;
                } catch (Exception $e) {
                    die("Migration failed on {$filename}: " . $e->getMessage());
                }
            }
        }
        
        // 4. Redirect back to settings with a success flag
        $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        header("Location: {$baseURL}/settings?updated=" . $migrations_run);
        exit;
    }
}