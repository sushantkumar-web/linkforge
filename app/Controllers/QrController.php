<?php
namespace App\Controllers;

use App\Core\Database;

class QrController {

    public function render() {
        // 1. Kill the global minify_html buffer so it doesn't corrupt the PNG
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // 2. Silence the legacy phpqrcode library's PHP 8.2 deprecation warnings.
        //    They are cosmetic, but any output before headers breaks binary responses.
        $prevErrorReporting = error_reporting();
        $prevDisplayErrors  = ini_get('display_errors');
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
        ini_set('display_errors', '0');

        // 3. Buffer everything so any stray output is swallowed, not sent
        ob_start();

        try {
            $this->generate();
        } catch (\Throwable $e) {
            // Clean up any partial output and restore error settings
            while (ob_get_level() > 0) ob_end_clean();
            error_reporting($prevErrorReporting);
            ini_set('display_errors', $prevDisplayErrors);

            http_response_code(500);
            header('Content-Type: text/plain');
            exit('QR generation failed: ' . $e->getMessage());
        }

        $png = ob_get_clean();

        // 4. Restore error settings BEFORE sending the actual response
        error_reporting($prevErrorReporting);
        ini_set('display_errors', $prevDisplayErrors);

        // 5. Send headers + PNG
        header('Content-Type: image/png');
        header('Content-Length: ' . strlen($png));
        header('Cache-Control: public, max-age=86400');

        if (!empty($_GET['dl'])) {
            $filename = 'qr-' . ((int)($_GET['id'] ?? 0) ?: 'custom') . '.png';
            header('Content-Disposition: attachment; filename="' . $filename . '"');
        }

        echo $png;
        exit;
    }

    /**
     * The actual generation logic — runs inside a clean output buffer.
     */
    private function generate(): void {
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            exit('Unauthorized');
        }

        $linkId    = (int)($_GET['id'] ?? 0);
        $rawData   = trim($_GET['data'] ?? '');
        $pixelSize = max(2, min(20, (int)($_GET['size'] ?? 8)));

        // Resolve the data to encode
        if ($linkId) {
            $pdo    = Database::getInstance();
            $role   = $_SESSION['role'] ?? 'user';
            $userId = $_SESSION['user_id'];

            if ($role === 'super_admin') {
                $stmt = $pdo->prepare("SELECT short_code FROM links WHERE id = ?");
                $stmt->execute([$linkId]);
            } else {
                $stmt = $pdo->prepare("SELECT short_code FROM links WHERE id = ? AND user_id = ?");
                $stmt->execute([$linkId, $userId]);
            }
            $link = $stmt->fetch();

            if (!$link) {
                http_response_code(404);
                exit('Link not found');
            }

            $data = $this->buildShortUrl($link['short_code']);
        } elseif ($rawData) {
            $data = $rawData;
        } else {
            http_response_code(400);
            exit('Missing id or data parameter');
        }

        // Locate phpqrcode
        $libPath = BASE_PATH . '/app/Libraries/phpqrcode/qrlib.php';
        if (!file_exists($libPath)) {
            http_response_code(500);
            exit('QR library not installed at: ' . $libPath);
        }

        // Ensure cache dir exists (phpqrcode will use it if QR_CACHEABLE is true)
        $cacheDir = BASE_PATH . '/app/Libraries/phpqrcode/cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        // ⚠️ Do NOT define QR_CACHEABLE yourself — qrconfig.php does that.
        // Defining it here causes the "already defined" warning that leaks into output.
        require_once $libPath;

        if (!class_exists('QRcode')) {
            http_response_code(500);
            exit('QRcode class not found after loading qrlib.php');
        }

        // Generate directly into the active output buffer
        \QRcode::png(
            $data,
            false,          // output to buffer, not a file
            QR_ECLEVEL_M,   // 15% error correction
            $pixelSize,     // pixels per module
            2               // quiet zone
        );
    }

    /**
     * Build the absolute public short URL for a given short code.
     */
    private function buildShortUrl(string $shortCode): string {
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

        $basePath = str_replace('/index.php', '', $_SERVER['PHP_SELF']);
        $basePath = rtrim($basePath, '/');

        return $scheme . '://' . $host . $basePath . '/' . $shortCode;
    }
}