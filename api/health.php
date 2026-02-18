<?php
/**
 * Health Check API Endpoint
 *
 * Sistem sağlık kontrolü endpoint'i.
 *
 * Endpoints:
 *   GET /api/health         - Full health check
 *   GET /api/health/live    - Liveness probe (for k8s)
 *   GET /api/health/ready   - Readiness probe (for k8s)
 *   GET /api/health/metrics - System metrics (auth required)
 *   GET /api/health/errors  - Recent errors (auth required)
 *
 * @package Pastane\API
 */

header('Content-Type: application/json; charset=utf-8');

// Bootstrap
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/HealthCheck.php';

// Get the check type from URL
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
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

$health = new HealthCheck();

try {
    switch ($type) {
        case 'live':
            $result = $health->liveness();
            break;

        case 'ready':
            $result = $health->readiness();
            if ($result['status'] !== 'ready') {
                http_response_code(503);
            }
            break;

        case 'metrics':
        case 'errors':
            // Require auth for sensitive endpoints
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
            $expectedToken = config('app.health_token', '');

            if ($expectedToken && $authHeader !== "Bearer {$expectedToken}") {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'error' => 'Yetkilendirme gerekli',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $result = ($type === 'metrics')
                ? $health->getMetrics()
                : $health->getRecentErrors();
            break;

        default:
            $result = $health->run();
            if ($result['status'] !== 'healthy') {
                http_response_code(503);
            }
            break;
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    // Hata logla — Logger varsa onu kullan
    try {
        if (class_exists('Logger', false)) {
            Logger::getInstance()->error('Health check error', [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        } else {
            error_log("Health check error: " . $e->getMessage());
        }
    } catch (Throwable) {
        error_log("Health check error (logger unavailable): " . $e->getMessage());
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'error' => 'Sistem sağlık kontrolü sırasında hata oluştu',
        'timestamp' => date('c'),
    ], JSON_UNESCAPED_UNICODE);
}
