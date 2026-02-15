<?php
/**
 * Health Check & Monitoring
 *
 * Sistem sağlık kontrolü ve metrikler.
 *
 * @package Pastane
 * @since 1.0.0
 */

class HealthCheck
{
    /**
     * @var array Check results
     */
    private array $checks = [];

    /**
     * @var bool Overall healthy status
     */
    private bool $healthy = true;

    /**
     * Run all health checks
     *
     * @return array
     */
    public function run(): array
    {
        $startTime = microtime(true);

        // Run individual checks
        $this->checkDatabase();
        $this->checkDiskSpace();
        $this->checkMemory();
        $this->checkWritablePaths();
        $this->checkRequiredExtensions();
        $this->checkPhpVersion();

        $endTime = microtime(true);

        return [
            'status' => $this->healthy ? 'healthy' : 'unhealthy',
            'timestamp' => date('c'),
            'duration_ms' => round(($endTime - $startTime) * 1000, 2),
            'checks' => $this->checks,
        ];
    }

    /**
     * Quick liveness check
     *
     * @return array
     */
    public function liveness(): array
    {
        return [
            'status' => 'alive',
            'timestamp' => date('c'),
        ];
    }

    /**
     * Readiness check (can serve traffic)
     *
     * @return array
     */
    public function readiness(): array
    {
        $this->checkDatabase();

        return [
            'status' => $this->healthy ? 'ready' : 'not_ready',
            'timestamp' => date('c'),
            'database' => $this->checks['database'] ?? null,
        ];
    }

    /**
     * Check database connection
     *
     * @return void
     */
    private function checkDatabase(): void
    {
        try {
            $startTime = microtime(true);
            $result = db()->fetch("SELECT 1 as check_value");
            $endTime = microtime(true);

            $this->checks['database'] = [
                'status' => 'healthy',
                'response_time_ms' => round(($endTime - $startTime) * 1000, 2),
            ];
        } catch (Exception $e) {
            $this->healthy = false;
            $this->checks['database'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check disk space
     *
     * @return void
     */
    private function checkDiskSpace(): void
    {
        $path = dirname(__DIR__);

        $totalSpace = disk_total_space($path);
        $freeSpace = disk_free_space($path);
        $usedSpace = $totalSpace - $freeSpace;
        $usagePercent = ($usedSpace / $totalSpace) * 100;

        $status = 'healthy';
        if ($usagePercent > 90) {
            $status = 'critical';
            $this->healthy = false;
        } elseif ($usagePercent > 80) {
            $status = 'warning';
        }

        $this->checks['disk'] = [
            'status' => $status,
            'total_gb' => round($totalSpace / 1024 / 1024 / 1024, 2),
            'free_gb' => round($freeSpace / 1024 / 1024 / 1024, 2),
            'used_percent' => round($usagePercent, 2),
        ];
    }

    /**
     * Check memory usage
     *
     * @return void
     */
    private function checkMemory(): void
    {
        $memoryLimit = $this->parseBytes(ini_get('memory_limit'));
        $memoryUsage = memory_get_usage(true);
        $peakUsage = memory_get_peak_usage(true);
        $usagePercent = ($memoryUsage / $memoryLimit) * 100;

        $status = 'healthy';
        if ($usagePercent > 90) {
            $status = 'critical';
        } elseif ($usagePercent > 80) {
            $status = 'warning';
        }

        $this->checks['memory'] = [
            'status' => $status,
            'limit_mb' => round($memoryLimit / 1024 / 1024, 2),
            'usage_mb' => round($memoryUsage / 1024 / 1024, 2),
            'peak_mb' => round($peakUsage / 1024 / 1024, 2),
            'used_percent' => round($usagePercent, 2),
        ];
    }

    /**
     * Check writable paths
     *
     * @return void
     */
    private function checkWritablePaths(): void
    {
        $paths = [
            'storage/logs' => dirname(__DIR__) . '/storage/logs',
            'storage/cache' => dirname(__DIR__) . '/storage/cache',
            'storage/uploads' => dirname(__DIR__) . '/storage/uploads',
            'uploads' => dirname(__DIR__) . '/uploads',
        ];

        $status = 'healthy';
        $details = [];

        foreach ($paths as $name => $path) {
            if (!is_dir($path)) {
                $details[$name] = 'not_exists';
                $status = 'warning';
            } elseif (!is_writable($path)) {
                $details[$name] = 'not_writable';
                $status = 'unhealthy';
                $this->healthy = false;
            } else {
                $details[$name] = 'ok';
            }
        }

        $this->checks['writable_paths'] = [
            'status' => $status,
            'paths' => $details,
        ];
    }

    /**
     * Check required PHP extensions
     *
     * @return void
     */
    private function checkRequiredExtensions(): void
    {
        $required = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl', 'curl'];
        $missing = [];

        foreach ($required as $ext) {
            if (!extension_loaded($ext)) {
                $missing[] = $ext;
            }
        }

        $status = empty($missing) ? 'healthy' : 'unhealthy';
        if (!empty($missing)) {
            $this->healthy = false;
        }

        $this->checks['php_extensions'] = [
            'status' => $status,
            'required' => $required,
            'missing' => $missing,
        ];
    }

    /**
     * Check PHP version
     *
     * @return void
     */
    private function checkPhpVersion(): void
    {
        $requiredVersion = '8.1.0';
        $currentVersion = PHP_VERSION;
        $isCompatible = version_compare($currentVersion, $requiredVersion, '>=');

        $this->checks['php_version'] = [
            'status' => $isCompatible ? 'healthy' : 'unhealthy',
            'current' => $currentVersion,
            'required' => $requiredVersion,
        ];

        if (!$isCompatible) {
            $this->healthy = false;
        }
    }

    /**
     * Get system metrics
     *
     * @return array
     */
    public function getMetrics(): array
    {
        return [
            'timestamp' => date('c'),
            'php' => [
                'version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
            ],
            'memory' => [
                'usage_bytes' => memory_get_usage(true),
                'peak_bytes' => memory_get_peak_usage(true),
            ],
            'database' => $this->getDatabaseMetrics(),
            'application' => $this->getApplicationMetrics(),
        ];
    }

    /**
     * Get database metrics
     *
     * @return array
     */
    private function getDatabaseMetrics(): array
    {
        try {
            // Get table sizes
            $tables = db()->fetchAll("
                SELECT
                    TABLE_NAME as table_name,
                    TABLE_ROWS as row_count,
                    ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) as size_mb
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC
            ");

            // Get connection info
            $processlist = db()->fetch("SHOW STATUS LIKE 'Threads_connected'");

            return [
                'tables' => $tables,
                'connections' => (int)($processlist['Value'] ?? 0),
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get application metrics
     *
     * @return array
     */
    private function getApplicationMetrics(): array
    {
        try {
            $today = date('Y-m-d');
            $thisMonth = date('Y-m-01');

            // Orders today
            $ordersToday = db()->fetch(
                "SELECT COUNT(*) as count FROM siparisler WHERE DATE(created_at) = ?",
                [$today]
            );

            // Orders this month
            $ordersMonth = db()->fetch(
                "SELECT COUNT(*) as count FROM siparisler WHERE created_at >= ?",
                [$thisMonth]
            );

            // Active products
            $products = db()->fetch(
                "SELECT COUNT(*) as count FROM urunler WHERE aktif = 1"
            );

            // Unread messages
            $messages = db()->fetch(
                "SELECT COUNT(*) as count FROM mesajlar WHERE okundu = 0"
            );

            // Pending orders
            $pending = db()->fetch(
                "SELECT COUNT(*) as count FROM siparisler WHERE durum = 'beklemede'"
            );

            return [
                'orders_today' => (int)($ordersToday['count'] ?? 0),
                'orders_month' => (int)($ordersMonth['count'] ?? 0),
                'active_products' => (int)($products['count'] ?? 0),
                'unread_messages' => (int)($messages['count'] ?? 0),
                'pending_orders' => (int)($pending['count'] ?? 0),
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Parse bytes from PHP ini value
     *
     * @param string $value
     * @return int
     */
    private function parseBytes(string $value): int
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int)$value;

        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    /**
     * Get error log summary
     *
     * @param int $lines Number of recent lines
     * @return array
     */
    public function getRecentErrors(int $lines = 50): array
    {
        $logFile = dirname(__DIR__) . '/storage/logs/app-' . date('Y-m-d') . '.log';

        if (!file_exists($logFile)) {
            return ['message' => 'Log dosyası bulunamadı.'];
        }

        $content = file_get_contents($logFile);
        $allLines = explode("\n", $content);
        $recentLines = array_slice($allLines, -$lines);

        $errors = [];
        foreach ($recentLines as $line) {
            if (str_contains($line, 'ERROR') || str_contains($line, 'CRITICAL')) {
                $errors[] = $line;
            }
        }

        return [
            'total_lines' => count($allLines),
            'recent_errors' => array_slice($errors, -10),
            'error_count' => count($errors),
        ];
    }
}
