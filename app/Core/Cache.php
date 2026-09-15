<?php
namespace App\Core;

class Cache {
    private static string $cacheDir = BASE_PATH . '/storage/cache/links';

    private static function getFilePath(string $slug): string {
        // Generates consistent filename based on hash
        return self::$cacheDir . '/' . hash('xxh64', $slug) . '.php';
    }

    public static function get(string $slug): ?array {
        $file = self::getFilePath($slug);

        if (!file_exists($file)) {
            return null;
        }

        // OPcache loads compiled array directly into memory
        $data = @include $file;
        return is_array($data) ? $data : null;
    }

    public static function set(string $slug, array $data): bool {
        // Auto-create storage/cache/links if it doesn't exist yet
        if (!is_dir(self::$cacheDir)) {
            @mkdir(self::$cacheDir, 0777, true);
        }

        $file = self::getFilePath($slug);
        
        // Export array as valid executable PHP code for OPcache
        $content = "<?php\n// LinkForge OPcache Layer\nreturn " . var_export($data, true) . ";\n";
        
        $bytes = @file_put_contents($file, $content, LOCK_EX);

        if ($bytes !== false && function_exists('opcache_compile_file')) {
            @opcache_compile_file($file);
            return true;
        }

        return $bytes !== false;
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

    public static function flush(): void {
        if (!is_dir(self::$cacheDir)) {
            return;
        }

        $files = glob(self::$cacheDir . '/*.php');
        if ($files) {
            foreach ($files as $file) {
                if (function_exists('opcache_invalidate')) {
                    @opcache_invalidate($file, true);
                }
                @unlink($file);
            }
        }
    }
}