<?php
namespace App\Core;

class Cache {

    private static $driver = null;
    private static $ttl = 3600;
    private static $prefix = 'lf_link_';

    public static function init(): void {
        if (self::$driver !== null) return;

        if (extension_loaded('apcu') && apcu_enabled()) {
            self::$driver = 'apcu';
        } elseif (self::fileCacheWritable()) {
            self::$driver = 'file';
        } else {
            self::$driver = 'array';
        }
    }

    public static function get(string $key) {
        self::init();
        $key = self::normalizeKey($key);

        switch (self::$driver) {
            case 'apcu':
                $hit = false;
                $value = apcu_fetch($key, $hit);
                return $hit ? $value : null;

            case 'file':
                return self::fileGet($key);

            case 'array':
                return self::$arrayStore[$key] ?? null;
        }
        return null;
    }

    public static function set(string $key, $value, int $ttl = null): void {
        self::init();
        $key = self::normalizeKey($key);
        $ttl = $ttl ?? self::$ttl;

        switch (self::$driver) {
            case 'apcu':
                apcu_store($key, $value, $ttl);
                break;

            case 'file':
                self::fileSet($key, $value, $ttl);
                break;

            case 'array':
                self::$arrayStore[$key] = $value;
                break;
        }
    }

    public static function forget(string $key): void {
        self::init();
        $key = self::normalizeKey($key);

        switch (self::$driver) {
            case 'apcu':
                apcu_delete($key);
                break;

            case 'file':
                $file = self::filePath($key);
                if (file_exists($file)) @unlink($file);
                break;

            case 'array':
                unset(self::$arrayStore[$key]);
                break;
        }
    }

    // ---------- Helpers ----------

    private static $arrayStore = [];

    private static function normalizeKey(string $key): string {
    $safe = preg_replace('/[^a-z0-9_\-:]/i', '_', $key);
    return self::$prefix . $safe;
}

    private static function fileCacheWritable(): bool {
        $dir = BASE_PATH . '/storage/cache/links';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        return is_dir($dir) && is_writable($dir);
    }

    private static function filePath(string $key): string {
        $hash = md5($key);
        return BASE_PATH . '/storage/cache/links/' . $hash . '.php';
    }

    private static function fileGet(string $key) {
        $file = self::filePath($key);
        if (!file_exists($file)) return null;

        $data = @include $file;
        if (!is_array($data) || !isset($data['expires'])) return null;
        if ($data['expires'] < time()) {
            @unlink($file);
            return null;
        }
        return $data['value'] ?? null;
    }

    private static function fileSet(string $key, $value, int $ttl): void {
        $file = self::filePath($key);
        $payload = [
            'expires' => time() + $ttl,
            'value'   => $value,
        ];
        $content = '<?php return ' . var_export($payload, true) . ';';
        @file_put_contents($file, $content, LOCK_EX);
    }

    /**
     * Report which driver is active (for the health check page).
     */
    public static function getDriverName(): string {
        self::init();
        return self::$driver;
    }

    /**
     * Wipe the entire link cache.
     */
    public static function flushAll(): int {
        self::init();
        $count = 0;

        if (self::$driver === 'apcu') {
            $info = apcu_cache_info();
            foreach ($info['cache_list'] ?? [] as $entry) {
                if (str_starts_with($entry['info'] ?? '', self::$prefix)) {
                    apcu_delete($entry['info']);
                    $count++;
                }
            }
        } elseif (self::$driver === 'file') {
            $files = glob(BASE_PATH . '/storage/cache/links/*.php');
            foreach ($files as $f) {
                @unlink($f);
                $count++;
            }
        }
        return $count;
    }
}