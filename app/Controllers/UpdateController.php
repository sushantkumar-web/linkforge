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
        $ch = curl_init($downloadUrl);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'LinkForge-Updater');
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);

        // 3. Extract and copy
        $zip = new ZipArchive;
        if ($zip->open($tempZip) !== TRUE) {
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

        // 4. Run pending migrations via the shared runner
        try {
            $result = \App\Core\MigrationRunner::run();
            if (!empty($result['applied'])) {
                error_log('[LinkForge] Update applied migrations: ' . implode(', ', $result['applied']));
            }
        } catch (Exception $e) {
            error_log('[LinkForge] Migration error: ' . $e->getMessage());
            die("Migration failed: " . htmlspecialchars($e->getMessage()));
        }

        // 5. Redirect
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

    private function deleteDirectory($dir) {
        if (!is_dir($dir)) return;
        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}