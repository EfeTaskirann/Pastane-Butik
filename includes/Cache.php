<?php
/**
 * Cache Manager
 *
 * File-based and database caching system.
 *
 * @package Pastane
 * @since 1.0.0
 */

class Cache
{
    /**
     * @var self|null Singleton instance
     */
    private static ?self $instance = null;

    /**
     * @var string Cache driver (file, database, redis)
     */
    private string $driver;

    /**
     * @var string Cache path (for file driver)
     */
    private string $path;

    /**
     * @var int Default TTL in seconds
     */
    private int $defaultTtl;

    /**
     * @var string Cache key prefix
     */
    private string $prefix;

    /**
     * Private constructor (singleton)
     */
    private function __construct()
    {
        $this->driver = config('cache.driver', 'file');
        $this->path = config('cache.path', storage_path('cache'));
        $this->defaultTtl = config('cache.ttl', 3600);
        $this->prefix = config('cache.prefix', 'pastane_');

        // Ensure cache directory exists
        if ($this->driver === 'file' && !is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }

    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get item from cache
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $key = $this->prefixKey($key);

        return match ($this->driver) {
            'file' => $this->getFromFile($key, $default),
            'database' => $this->getFromDatabase($key, $default),
            default => $default,
        };
    }

    /**
     * Store item in cache
     *
     * @param string $key
     * @param mixed $value
     * @param int|null $ttl Time to live in seconds
     * @return bool
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $key = $this->prefixKey($key);
        $ttl = $ttl ?? $this->defaultTtl;
        $expiration = time() + $ttl;

        return match ($this->driver) {
            'file' => $this->setToFile($key, $value, $expiration),
            'database' => $this->setToDatabase($key, $value, $expiration),
            default => false,
        };
    }

    /**
     * Check if key exists in cache
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Remove item from cache
     *
     * @param string $key
     * @return bool
     */
    public function forget(string $key): bool
    {
        $key = $this->prefixKey($key);

        return match ($this->driver) {
            'file' => $this->forgetFromFile($key),
            'database' => $this->forgetFromDatabase($key),
            default => false,
        };
    }

    /**
     * Clear all cache
     *
     * @return bool
     */
    public function flush(): bool
    {
        return match ($this->driver) {
            'file' => $this->flushFile(),
            'database' => $this->flushDatabase(),
            default => false,
        };
    }

    /**
     * Get or set cache value
     *
     * @param string $key
     * @param callable $callback
     * @param int|null $ttl
     * @return mixed
     */
    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Get and delete
     *
     * @param string $key
     * @return mixed
     */
    public function pull(string $key): mixed
    {
        $value = $this->get($key);
        $this->forget($key);
        return $value;
    }

    /**
     * Increment numeric value
     *
     * @param string $key
     * @param int $amount
     * @return int|false
     */
    public function increment(string $key, int $amount = 1): int|false
    {
        $value = $this->get($key, 0);

        if (!is_numeric($value)) {
            return false;
        }

        $newValue = $value + $amount;
        $this->set($key, $newValue);

        return $newValue;
    }

    /**
     * Decrement numeric value
     *
     * @param string $key
     * @param int $amount
     * @return int|false
     */
    public function decrement(string $key, int $amount = 1): int|false
    {
        return $this->increment($key, -$amount);
    }

    // ========================================
    // FILE DRIVER METHODS
    // ========================================

    /**
     * Get from file cache
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    private function getFromFile(string $key, mixed $default): mixed
    {
        $file = $this->getFilePath($key);

        if (!file_exists($file)) {
            return $default;
        }

        $content = file_get_contents($file);
        $data = json_decode($content, true);

        if ($data === null || !isset($data['expiration']) || !isset($data['value'])) {
            return $default;
        }

        // Check expiration
        if ($data['expiration'] !== 0 && $data['expiration'] < time()) {
            $this->forgetFromFile($key);
            return $default;
        }

        return $data['value'];
    }

    /**
     * Set to file cache
     *
     * @param string $key
     * @param mixed $value
     * @param int $expiration
     * @return bool
     */
    private function setToFile(string $key, mixed $value, int $expiration): bool
    {
        $file = $this->getFilePath($key);
        $dir = dirname($file);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $data = json_encode([
            'value' => $value,
            'expiration' => $expiration,
        ], JSON_UNESCAPED_UNICODE);

        return file_put_contents($file, $data, LOCK_EX) !== false;
    }

    /**
     * Forget from file cache
     *
     * @param string $key
     * @return bool
     */
    private function forgetFromFile(string $key): bool
    {
        $file = $this->getFilePath($key);

        if (file_exists($file)) {
            return unlink($file);
        }

        return true;
    }

    /**
     * Flush file cache
     *
     * @return bool
     */
    private function flushFile(): bool
    {
        $files = glob($this->path . '/*.cache');

        foreach ($files as $file) {
            unlink($file);
        }

        return true;
    }

    /**
     * Get file path for key
     *
     * @param string $key
     * @return string
     */
    private function getFilePath(string $key): string
    {
        $hash = md5($key);
        return $this->path . '/' . $hash . '.cache';
    }

    // ========================================
    // DATABASE DRIVER METHODS
    // ========================================

    /**
     * Get from database cache
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    private function getFromDatabase(string $key, mixed $default): mixed
    {
        try {
            $result = db()->fetch(
                "SELECT `value`, `expiration` FROM cache WHERE `key` = ?",
                [$key]
            );

            if (!$result) {
                return $default;
            }

            // Check expiration
            if ($result['expiration'] < time()) {
                $this->forgetFromDatabase($key);
                return $default;
            }

            return json_decode($result['value'], true);
        } catch (Exception $e) {
            $this->logCacheError('getFromDatabase failed', $e, ['key' => $key]);
            return $default;
        }
    }

    /**
     * Set to database cache
     *
     * @param string $key
     * @param mixed $value
     * @param int $expiration
     * @return bool
     */
    private function setToDatabase(string $key, mixed $value, int $expiration): bool
    {
        try {
            $jsonValue = json_encode($value, JSON_UNESCAPED_UNICODE);

            db()->query(
                "INSERT INTO cache (`key`, `value`, `expiration`)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE `value` = ?, `expiration` = ?",
                [$key, $jsonValue, $expiration, $jsonValue, $expiration]
            );

            return true;
        } catch (Exception $e) {
            $this->logCacheError('setToDatabase failed', $e, ['key' => $key]);
            return false;
        }
    }

    /**
     * Forget from database cache
     *
     * @param string $key
     * @return bool
     */
    private function forgetFromDatabase(string $key): bool
    {
        try {
            db()->delete('cache', '`key` = :key', ['key' => $key]);
            return true;
        } catch (Exception $e) {
            $this->logCacheError('forgetFromDatabase failed', $e, ['key' => $key]);
            return false;
        }
    }

    /**
     * Flush database cache
     *
     * @return bool
     */
    private function flushDatabase(): bool
    {
        try {
            db()->query("TRUNCATE TABLE cache");
            return true;
        } catch (Exception $e) {
            $this->logCacheError('flushDatabase failed', $e);
            return false;
        }
    }

    /**
     * Clean expired entries
     *
     * @return int Number of deleted entries
     */
    public function cleanup(): int
    {
        if ($this->driver === 'file') {
            $count = 0;
            $files = glob($this->path . '/*.cache');

            foreach ($files as $file) {
                $content = file_get_contents($file);
                $data = json_decode($content, true);

                if ($data && isset($data['expiration']) && $data['expiration'] < time()) {
                    unlink($file);
                    $count++;
                }
            }

            return $count;
        }

        if ($this->driver === 'database') {
            try {
                $result = db()->query(
                    "DELETE FROM cache WHERE expiration < ?",
                    [time()]
                );
                return $result->rowCount();
            } catch (Exception $e) {
                return 0;
            }
        }

        return 0;
    }

    /**
     * Prefix cache key
     *
     * @param string $key
     * @return string
     */
    private function prefixKey(string $key): string
    {
        return $this->prefix . $key;
    }

    /**
     * Get cache statistics
     *
     * @return array
     */
    public function stats(): array
    {
        if ($this->driver === 'file') {
            $files = glob($this->path . '/*.cache');
            $totalSize = 0;

            foreach ($files as $file) {
                $totalSize += filesize($file);
            }

            return [
                'driver' => 'file',
                'entries' => count($files),
                'size_bytes' => $totalSize,
                'size_human' => $this->formatBytes($totalSize),
            ];
        }

        if ($this->driver === 'database') {
            try {
                $result = db()->fetch("SELECT COUNT(*) as count FROM cache");
                return [
                    'driver' => 'database',
                    'entries' => (int)($result['count'] ?? 0),
                ];
            } catch (Exception $e) {
                return ['driver' => 'database', 'error' => $e->getMessage()];
            }
        }

        return ['driver' => $this->driver];
    }

    /**
     * Format bytes to human readable
     *
     * @param int $bytes
     * @return string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Log cache errors — Logger varsa onu kullan, yoksa error_log fallback
     *
     * Cache hataları kritik değil ama sessiz geçmemeli — debug sırasında önemli.
     *
     * @param string $message
     * @param Exception $e
     * @param array $context
     * @return void
     */
    private function logCacheError(string $message, Exception $e, array $context = []): void
    {
        $context['exception'] = $e->getMessage();
        $context['driver'] = $this->driver;

        try {
            if (class_exists('Logger', false)) {
                Logger::getInstance()->warning("Cache: {$message}", $context);
            } else {
                error_log("[Cache WARNING] {$message}: " . $e->getMessage());
            }
        } catch (Exception) {
            // Logger da fail ederse son çare
        }
    }
}

// Global cache helper function
if (!function_exists('cache')) {
    /**
     * Get cache instance or value
     *
     * @param string|null $key
     * @param mixed $default
     * @return Cache|mixed
     */
    function cache(?string $key = null, mixed $default = null): mixed
    {
        $cache = Cache::getInstance();

        if ($key === null) {
            return $cache;
        }

        return $cache->get($key, $default);
    }
}
