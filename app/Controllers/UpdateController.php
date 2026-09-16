<?php
namespace App\Controllers;

use App\Core\Database;
use ZipArchive;
use Exception;

class UpdateController {

    public function runMigrations() {
        if (!isset($_SESSION['user_id'])) {
            die("Unauthorized");
        }

        // Only super_admin / admin can run updates
        if (!in_array($_SESSION['role'] ?? 'user', ['super_admin', 'admin'])) {
            http_response_code(403);
            die("Forbidden: You do not have permission to run updates.");
        }

        $repo = 'sushantkumar-web/linkforge';
        $apiUrl = "https://api.github.com/repos/{$repo}/releases/latest";

        // 1. Fetch latest release
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

        if (!empty($release['assets'])) {
            foreach ($release['assets'] as $asset) {
                if (str_ends_with($asset['name'], '.zip')) {
                    $downloadUrl = $asset['browser_download_url'];
                    break;
                }
            }
        }
        if (!$downloadUrl && !empty($release['zipball_url'])) {
            $downloadUrl = $release['zipball_url'];
        }
        if (!$downloadUrl) {
            die("Error: No downloadable release package found on GitHub.");
        }

        // 2. Download ZIP
        $storageDir = BASE_PATH . '/storage';
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }
        $tempZip = $storageDir . '/latest_update.zip';
        $extractPath = $storageDir . '/update_extracted';

        $fp = fopen($tempZip, 'w+');
        if ($fp === false) {
            die("Error: Could not open " . htmlspecialchars($tempZip) . " for writing. Check folder permissions.");
        }

        $ch = curl_init($downloadUrl);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'LinkForge-Updater');
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if ($httpCode !== 200 || !file_exists($tempZip) || filesize($tempZip) < 1024) {
            @unlink($tempZip);
            die("Error: Failed to download update package (HTTP {$httpCode}). " . htmlspecialchars($curlErr));
        }

        // 3. Extract and copy
        $zip = new ZipArchive;
        if ($zip->open($tempZip) !== TRUE) {
            @unlink($tempZip);
            die("Error: Failed to open downloaded ZIP package.");
        }

        $this->deleteDirectory($extractPath);
        mkdir($extractPath, 0755, true);
        $zip->extractTo($extractPath);
        $zip->close();
        unlink($tempZip);

        $sourceFiles = $extractPath;
        $items = array_diff(scandir($extractPath), ['.', '..']);
        if (count($items) === 1 && is_dir($extractPath . '/' . reset($items))) {
            $sourceFiles = $extractPath . '/' . reset($items);
        }

        $this->copyFiles($sourceFiles, BASE_PATH);
        $this->deleteDirectory($extractPath);

        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

        // 4. Back up the database BEFORE running migrations.
        //    This is a best-effort operation — if the host disables exec()
        //    or mysqldump, we skip and continue.
        $backupFile = $this->backupDatabase();
        if ($backupFile) {
            error_log('[LinkForge] DB backup written to: ' . $backupFile);
        } else {
            error_log('[LinkForge] DB backup skipped or unavailable on this host.');
        }

        // 5. Run pending migrations via the shared runner
        try {
            $result = \App\Core\MigrationRunner::run();
            if (!empty($result['applied'])) {
                error_log('[LinkForge] Update applied migrations: ' . implode(', ', $result['applied']));
            }
        } catch (Exception $e) {
            error_log('[LinkForge] Migration error: ' . $e->getMessage());
            die("Migration failed: " . htmlspecialchars($e->getMessage())
                . ($backupFile ? "<br><br>A backup of your database was saved to: <code>"
                    . htmlspecialchars(basename($backupFile)) . "</code>" : ""));
        }

        // 6. Redirect back to settings with a success flag
        $baseURL = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        header("Location: {$baseURL}/settings?updated=1");
        exit;
    }

    /**
     * Recursively copy files from src to dst, preserving user config.
     */
    private function copyFiles($src, $dst) {
        $dir = opendir($src);
        @mkdir($dst, 0755, true);

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') continue;
            $srcPath = $src . '/' . $file;
            $dstPath = $dst . '/' . $file;

            // NEVER overwrite user's config or .git
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

    /**
     * Recursively delete a directory.
     */
    private function deleteDirectory($dir) {
        if (!is_dir($dir)) return;
        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Create a mysqldump backup of the current database.
     * Returns the absolute path to the backup file, or false on failure.
     *
     * Silently skips on shared hosts where exec() or mysqldump is disabled.
     * Keeps only the 5 most recent backups to prevent storage bloat.
     */
    private function backupDatabase() {
        // Bail if exec() is unavailable or blocked
        if (!function_exists('exec')) return false;

        $disabled = ini_get('disable_functions') ?: '';
        if ($disabled && stripos($disabled, 'exec') !== false) return false;

        // Load DB credentials
        $configFile = BASE_PATH . '/config/config.php';
        if (!file_exists($configFile)) return false;
        $config = require $configFile;

        if (empty($config['DB_NAME']) || empty($config['DB_USER'])) return false;

        // Prepare backup directory
        $backupDir = BASE_PATH . '/storage/backups';
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0755, true);
        }
        if (!is_writable($backupDir)) return false;

        $file = $backupDir . '/pre-update-' . date('Y-m-d_His') . '.sql';

        // Build mysqldump command
        // --single-transaction + --skip-lock-tables = no table locks on InnoDB
        $cmd = sprintf(
            'mysqldump --single-transaction --skip-lock-tables --host=%s --user=%s %s %s > %s 2>/dev/null',
            escapeshellarg($config['DB_HOST']),
            escapeshellarg($config['DB_USER']),
            !empty($config['DB_PASS']) ? '-p' . escapeshellarg($config['DB_PASS']) : '',
            escapeshellarg($config['DB_NAME']),
            escapeshellarg($file)
        );

        exec($cmd, $output, $code);

        // Validate the backup actually has content
        if ($code !== 0 || !file_exists($file) || filesize($file) < 1024) {
            @unlink($file);
            return false;
        }

        // Prune old backups — keep only the 5 most recent
        $files = glob($backupDir . '/pre-update-*.sql');
        if (is_array($files) && count($files) > 5) {
            usort($files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            $toDelete = array_slice($files, 0, count($files) - 5);
            foreach ($toDelete as $old) {
                @unlink($old);
            }
        }

        return $file;
    }
}