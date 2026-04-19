<?php
/**
 * Prometheus Metrics Endpoint
 *
 * GET /api/metrics
 * Header: Authorization: Bearer <METRICS_TOKEN>
 *
 * Response:
 *   text/plain; version=0.0.4; charset=utf-8
 *   Prometheus text exposition format
 *   HTTP 200 — başarılı
 *   HTTP 401 — auth token eksik/yanlış
 *   HTTP 503 — token konfigüre edilmemiş
 *
 * Cache:
 *   Cache-Control: public, max-age=15
 *   → Prometheus scrape interval ile uyumlu (15s)
 *
 * Metrikler:
 *   - pastane_http_requests_total{method,status}           counter
 *   - pastane_db_query_duration_seconds                    histogram (son 1 saat)
 *   - pastane_active_sessions                              gauge
 *   - pastane_orders_total{durum}                          counter
 *   - pastane_emails_sent_total{status}                    counter
 *   - pastane_php_errors_total{level}                      counter
 *   - pastane_uptime_seconds                               gauge
 *
 * @package Pastane\API
 * @since   1.2.0-sprint2
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/Metrics.php';

// Bearer token auth
$expected = (string) (function_exists('env') ? env('METRICS_TOKEN', '') : '');
if ($expected === '') {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => 'METRICS_TOKEN .env içinde tanımlı değil — endpoint devre dışı.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
// Apache bazen Authorization header'ını REDIRECT_HTTP_AUTHORIZATION'a koyuyor
if ($authHeader === '' && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
}

$provided = '';
if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
    $provided = trim($m[1]);
}

// Constant-time karşılaştırma
if ($provided === '' || !hash_equals($expected, $provided)) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    header('WWW-Authenticate: Bearer realm="pastane-metrics"');
    echo json_encode([
        'error' => 'Bearer token geçersiz veya eksik.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Active sessions gauge — mevcut session dizinine bak
try {
    $sessDir = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
    $sessDir .= DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions';
    if (is_dir($sessDir)) {
        $files = glob($sessDir . DIRECTORY_SEPARATOR . 'sess_*') ?: [];
        $cutoff = time() - 3600; // 1 saatten yeni olanlar aktif
        $active = 0;
        foreach ($files as $f) {
            if (@filemtime($f) >= $cutoff) {
                $active++;
            }
        }
        Metrics::set('pastane_active_sessions', $active);
    }
} catch (Throwable) {
    // sessiz
}

// Prometheus content type
header('Content-Type: text/plain; version=0.0.4; charset=utf-8');
header('Cache-Control: public, max-age=15');

echo Metrics::render();
