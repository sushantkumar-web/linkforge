<?php
namespace App\Core;

class Cache {
    private static function getDir(): string {
        $dir = BASE_PATH . '/storage/cache/links';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    private static function getFilePath(string $slug): string {
        return self::getDir() . '/' . hash('xxh64', $slug) . '.php';
    }

    public static function get(string $slug): ?array {
        $file = self::getFilePath($slug);
        if (file_exists($file)) {
            return include $file;
        }
        return null;
    }

    public static function set(string $slug, array $data): void {
        $file = self::getFilePath($slug);
        $content = "<?php\nreturn " . var_export($data, true) . ";\n";
        
        // Atomic write with exclusive lock
        file_put_contents($file, $content, LOCK_EX);

        // Pre-compile into PHP OPcache if active
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($file, true);
        }
    }

    public static function forget(string $slug): void {
        $file = self::getFilePath($slug);
        if (file_exists($file)) {
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($file, true);
            }
            @unlink($file);
        }
    }
}