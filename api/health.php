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
 *   GET /api/health/metrics - System metrics
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
            // Require auth for metrics
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
            $expectedToken = config('app.health_token', '');

            if ($expectedToken && $authHeader !== "Bearer {$expectedToken}") {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized']);
                exit;
            }

            $result = $health->getMetrics();
            break;

        case 'errors':
            // Require auth for error logs
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
            $expectedToken = config('app.health_token', '');

            if ($expectedToken && $authHeader !== "Bearer {$expectedToken}") {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized']);
                exit;
            }

            $result = $health->getRecentErrors();
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
    // GÜVENLİK: Exception detaylarını client'a gösterme
    if (function_exists('error_log')) {
        error_log("Health check error: " . $e->getMessage());
    }

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => 'Internal server error',
        'timestamp' => date('c'),
    ]);
}
