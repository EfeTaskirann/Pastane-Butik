<?php
/**
 * API v1 Entry Point
 *
 * RESTful API version 1.
 *
 * @package Pastane\API
 * @version 1.0.0
 */

declare(strict_types=1);

// Load bootstrap (Composer autoload + config + security)
require_once __DIR__ . '/../../includes/bootstrap.php';

// PSR-4 autoloaded via Composer
use Pastane\Middleware\CorsMiddleware;
use Pastane\Middleware\RateLimitMiddleware;
use Pastane\Router\Router;

// Apply CORS headers early (before any output)
// OPTIONS preflight → boş string döner, sonlandır
$cors = new CorsMiddleware();
$corsResult = $cors->handle(function() { return null; });
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit; // Preflight handled, body yok
}

// Initialize router
$router = Router::getInstance();

// API Info endpoint
$router->get('/api/v1', function() {
    json_success([
        'name' => 'Tatlı Düşler API',
        'version' => '1.0.0',
        'status' => 'active',
        'endpoints' => [
            'products' => '/api/v1/urunler',
            'categories' => '/api/v1/kategoriler',
            'orders' => '/api/v1/siparisler',
            'auth' => '/api/v1/auth',
            'reports' => '/api/v1/raporlar',
        ],
    ]);
});

// Include route files
require_once __DIR__ . '/routes/urunler.php';
require_once __DIR__ . '/routes/kategoriler.php';
require_once __DIR__ . '/routes/siparisler.php';
require_once __DIR__ . '/routes/auth.php';

// Parse request
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

// Pastane base path'i çıkar (örn: /pastane/api/v1/urunler → /api/v1/urunler)
$basePath = config('app.base_path', '/pastane');
if ($basePath && str_starts_with($path, $basePath)) {
    $path = substr($path, strlen($basePath));
}

$method = $_SERVER['REQUEST_METHOD'];

// Rate limiting + Dispatch
$rateLimiter = new RateLimitMiddleware('api');
try {
    $rateLimiter->handle(function() use ($router, $method, $path) {
        try {
            echo $router->dispatch($method, $path);
        } catch (\Pastane\Exceptions\ValidationException $e) {
            json_error($e->getMessage(), 422, $e->getErrors());
        } catch (\Pastane\Exceptions\HttpException $e) {
            json_error($e->getMessage(), $e->getStatusCode());
        }
    });
} catch (\Pastane\Exceptions\ValidationException $e) {
    json_error($e->getMessage(), 422, $e->getErrors());
} catch (\Pastane\Exceptions\HttpException $e) {
    json_error($e->getMessage(), $e->getStatusCode());
} catch (\Throwable $e) {
    // Beklenmeyen hatalar — logla ve production'da detay gösterme
    try {
        if (class_exists('Logger', false)) {
            Logger::getInstance()->error('API v1 unhandled exception', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'path' => $path ?? 'unknown',
                'method' => $method ?? 'unknown',
            ]);
        }
    } catch (\Throwable) {
        // Logger fail — global handler yakalayacak
    }

    $message = (defined('DEBUG_MODE') && DEBUG_MODE)
        ? $e->getMessage()
        : 'Sunucu hatası oluştu.';
    json_error($message, 500);
}
