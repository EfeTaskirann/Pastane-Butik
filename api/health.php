<?php
/**
 * Health Check API Endpoint (Sprint 1 — güçlendirildi)
 *
 * JSON response (şema):
 *   {
 *     "status": "ok" | "degraded" | "down",
 *     "timestamp": ISO-8601,
 *     "checks": {
 *       "database": {"status": "ok|down", "latency_ms": <float>},
 *       "disk":     {"status": "ok|warning|critical", "free_gb": <float>},
 *       "php":      {"version": "<php-version>"},
 *       "uptime_sec": <int>
 *     },
 *     "version": "1.0.0-sprint1"
 *   }
 *
 * Endpoint'ler:
 *   GET /api/health           → full check (detailed=true ile ekstra detay)
 *   GET /api/health/live      → k8s liveness (sadece PHP up)
 *   GET /api/health/ready     → k8s readiness (DB + cache + disk full check)
 *   GET /api/health/metrics   → detaylı metrikler (auth required)
 *   GET /api/health/errors    → son hatalar (auth required)
 *
 * Response code:
 *   ok        → 200
 *   degraded  → 200
 *   down      → 503
 *
 * @package Pastane\API
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/HealthCheck.php';

// URL'den endpoint tipini belirle
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$type = 'full';
if (str_ends_with($path, '/live') || str_ends_with($path, '/liveness')) {
    $type = 'live';
} elseif (str_ends_with($path, '/ready') || str_ends_with($path, '/readiness')) {
    $type = 'ready';
} elseif (str_ends_with($path, '/metrics')) {
    $type = 'metrics';
} elseif (str_ends_with($path, '/errors')) {
    $type = 'errors';
}

$detailed = isset($_GET['detailed']) && $_GET['detailed'] === 'true';
$VERSION = '1.0.0-sprint1';

/**
 * Uptime: ana process start zamanından saniye farkı.
 * Windows'da sys_getloadavg yok — $_SERVER['REQUEST_TIME_FLOAT'] kullanamayız (process start değil).
 * Pragmatik: /proc/uptime varsa onu kullan, yoksa app booted time'ı 0 göster.
 */
function pastane_uptime_seconds(): int
{
    if (@is_readable('/proc/uptime')) {
        $line = @file_get_contents('/proc/uptime');
        if (is_string($line)) {
            $parts = explode(' ', trim($line));
            return (int) floor((float) ($parts[0] ?? 0));
        }
    }
    // Fallback: bootstrap süresinden bu yana geçen süre
    return (int) (microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true)));
}

/**
 * Sprint 1 şemasına dönüştür.
 *
 * @param array<string,mixed> $raw HealthCheck::run() çıktısı
 * @return array{status:string,timestamp:string,checks:array<string,mixed>,version:string}
 */
function pastane_map_full(array $raw, string $version, bool $detailed): array
{
    $rawChecks = $raw['checks'] ?? [];

    $db = $rawChecks['database'] ?? ['status' => 'unknown'];
    $disk = $rawChecks['disk'] ?? ['status' => 'unknown'];

    // Status mapping:
    //   healthy      → ok
    //   warning      → degraded
    //   critical     → down (for disk); degraded (others)
    //   unhealthy    → down
    $anyUnhealthy = false;
    $anyWarning = false;

    foreach ($rawChecks as $c) {
        $s = $c['status'] ?? 'healthy';
        if (in_array($s, ['unhealthy', 'critical'], true)) {
            $anyUnhealthy = true;
        } elseif ($s === 'warning') {
            $anyWarning = true;
        }
    }

    $overall = $anyUnhealthy ? 'down' : ($anyWarning ? 'degraded' : 'ok');

    $checks = [
        'database' => [
            'status'     => (($db['status'] ?? '') === 'healthy') ? 'ok' : 'down',
            'latency_ms' => $db['response_time_ms'] ?? null,
        ],
        'disk' => [
            'status'  => match ($disk['status'] ?? 'healthy') {
                'healthy'  => 'ok',
                'warning'  => 'warning',
                'critical' => 'critical',
                default    => 'unknown',
            },
            'free_gb' => $disk['free_gb'] ?? null,
        ],
        'php' => [
            'version' => PHP_VERSION,
        ],
        'uptime_sec' => pastane_uptime_seconds(),
    ];

    if ($detailed) {
        $checks['memory'] = $rawChecks['memory'] ?? null;
        $checks['writable_paths'] = $rawChecks['writable_paths'] ?? null;
        $checks['php_extensions'] = $rawChecks['php_extensions'] ?? null;
    }

    return [
        'status'    => $overall,
        'timestamp' => date('c'),
        'checks'    => $checks,
        'version'   => $version,
    ];
}

$health = new HealthCheck();

try {
    switch ($type) {
        case 'live':
            // Liveness = sadece PHP çalışıyor mu? DB'ye bakma.
            echo json_encode([
                'status'    => 'ok',
                'timestamp' => date('c'),
                'php'       => PHP_VERSION,
                'version'   => $VERSION,
            ], JSON_UNESCAPED_UNICODE);
            exit;

        case 'ready':
            // Readiness = DB + cache + disk full check
            $raw = $health->run();
            $out = pastane_map_full($raw, $VERSION, false);

            // Ek: cache durumu
            $cachePath = BASE_PATH . '/' . env('CACHE_PATH', 'storage/cache');
            $cacheOk = is_dir($cachePath) && is_writable($cachePath);
            $out['checks']['cache'] = [
                'status' => $cacheOk ? 'ok' : 'down',
                'path'   => $cachePath,
            ];
            if (!$cacheOk) {
                $out['status'] = 'down';
            }

            // Readiness için "ok" dışındakiler 503
            if ($out['status'] === 'down') {
                http_response_code(503);
            }
            echo json_encode($out, JSON_UNESCAPED_UNICODE);
            exit;

        case 'metrics':
        case 'errors':
            // Sensitive: token gerekli
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
            $expectedToken = (string) env('HEALTH_TOKEN', '');
            if ($expectedToken === '' || $authHeader !== "Bearer {$expectedToken}") {
                http_response_code(401);
                echo json_encode([
                    'success'   => false,
                    'error'     => 'Yetkilendirme gerekli',
                    'timestamp' => date('c'),
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $result = ($type === 'metrics') ? $health->getMetrics() : $health->getRecentErrors();
            echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;

        default:
            $raw = $health->run();
            $out = pastane_map_full($raw, $VERSION, $detailed);

            // down → 503, ok/degraded → 200
            if ($out['status'] === 'down') {
                http_response_code(503);
            }
            echo json_encode($out, JSON_UNESCAPED_UNICODE);
            exit;
    }
} catch (Throwable $e) {
    try {
        if (class_exists('Logger', false)) {
            Logger::getInstance()->error('Health check error', [
                'exception' => $e->getMessage(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            ]);
        } else {
            error_log("Health check error: " . $e->getMessage());
        }
        if (class_exists('Sentry', false) && Sentry::isEnabled()) {
            Sentry::captureException($e, ['source' => 'api/health.php']);
        }
    } catch (Throwable) {
        // swallow
    }
    http_response_code(500);
    echo json_encode([
        'status'    => 'down',
        'timestamp' => date('c'),
        'error'     => 'Sistem sağlık kontrolü sırasında hata oluştu',
        'version'   => $VERSION,
    ], JSON_UNESCAPED_UNICODE);
}
