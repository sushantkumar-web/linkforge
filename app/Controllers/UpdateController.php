<?php
namespace App\Controllers;

use App\Core\Database;
use PDO;
use ZipArchive;
use Exception;

class UpdateController {
    public function runMigrations() {
        if (!isset($_SESSION['user_id'])) {
            die("Unauthorized");
        }

        $repo = 'sushantkumar-web/linkforge';
        $apiUrl = "https://api.github.com/repos/{$repo}/releases/latest";

        // 1. Fetch Latest Release Details from GitHub API
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'LinkForge-Updater');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/vnd.github.v3+json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        curl_close($ch);

        $release = json_decode($response, true);
        $downloadUrl = null;

        // Look for the clean ZIP asset uploaded via build.sh
        if (!empty($release['assets'])) {
            foreach ($release['assets'] as $asset) {
                if (str_ends_with($asset['name'], '.zip')) {
                    $downloadUrl = $asset['browser_download_url'];
                    break;
                }
            }
        }

        // Fallback to source zipball if no release asset was uploaded
        if (!$downloadUrl && !empty($release['zipball_url'])) {
            $downloadUrl = $release['zipball_url'];
        }

        if (!$downloadUrl) {
            die("Error: No downloadable release package found on GitHub.");
        }

        // 2. Download ZIP Package
        $storageDir = BASE_PATH . '/storage';
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }

        $tempZip = $storageDir . '/latest_update.zip';
        $extractPath = $storageDir . '/update_extracted';

        $fp = fopen($tempZip, 'w+');
        $ch = curl_init($downloadUrl);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow AWS/GitHub redirects
        curl_setopt($ch, CURLOPT_USERAGENT, 'LinkForge-Updater');
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);

        // 3. Unpack and Overwrite Code Files
        $zip = new ZipArchive;
        if ($zip->open($tempZip) === TRUE) {
            // Wipe any previous extraction directory
            $this->deleteDirectory($extractPath);
            mkdir($extractPath, 0755, true);
            $zip->extractTo($extractPath);
            $zip->close();
            unlink($tempZip);

            // Determine if archive has a root wrapper folder (GitHub source zips do)
            $sourceFiles = $extractPath;
            $items = array_diff(scandir($extractPath), ['.', '..']);
            if (count($items) === 1 && is_dir($extractPath . '/' . reset($items))) {
                $sourceFiles = $extractPath . '/' . reset($items);
            }

            // Copy files over production codebase
            $this->copyFiles($sourceFiles, BASE_PATH);
            $this->deleteDirectory($extractPath);
        } else {
            die("Error: Failed to open downloaded ZIP package.");
        }

        // 4. Run Pending SQL Migrations
        $this->executePendingMigrations();

        // 5. Finished - Redirect back to settings with success
        $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        header("Location: {$baseURL}/settings?updated=1");
        exit;
    }

    private function copyFiles($src, $dst) {
        $dir = opendir($src);
        @mkdir($dst, 0755, true);

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') continue;

            $srcPath = $src . '/' . $file;
            $dstPath = $dst . '/' . $file;

            // NEVER overwrite config/config.php or .git directory
            if ($file === 'config.php' && basename($dst) === 'config') continue;
            if ($file === '.git') continue;

            if (is_dir($srcPath)) {
                $this->copyFiles($srcPath, $dstPath);
            } else {
                copy($srcPath, $dstPath);
            }
        }
        closedir($dir);
    }

    private function deleteDirectory($dir) {
        if (!is_dir($dir)) return;
        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function executePendingMigrations() {
        $pdo = Database::getInstance();
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS system_settings (
                setting_key VARCHAR(50) PRIMARY KEY,
                setting_value VARCHAR(255) NOT NULL
            );
        ");

        $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'db_version' LIMIT 1");
        $currentVersion = (int)($stmt->fetchColumn() ?: 0);

        $migrationsDir = BASE_PATH . '/database/migrations';
        if (!is_dir($migrationsDir)) return;

        $files = glob($migrationsDir . '/*.sql');
        sort($files);

        foreach ($files as $file) {
            $filename = basename($file);
            if (preg_match('/^(\d+)_\w+\.sql$/', $filename, $matches)) {
                $migrationVersion = (int)$matches[1];
                if ($migrationVersion > $currentVersion) {
                    $sql = file_get_contents($file);
                    $pdo->exec($sql);
                    $pdo->prepare("
                        INSERT INTO system_settings (setting_key, setting_value) 
                        VALUES ('db_version', ?) 
                        ON DUPLICATE KEY UPDATE setting_value = ?
                    ")->execute([$migrationVersion, $migrationVersion]);
                    $currentVersion = $migrationVersion;
                }
            }
        }
    }
}