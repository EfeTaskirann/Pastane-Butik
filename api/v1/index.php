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

// Initialize router
$router = Router::getInstance();

// Apply global middleware
$cors = new CorsMiddleware();
$cors->handle(function() {});

// API Info endpoint
$router->get('/api/v1', function() {
    return json_response([
        'name' => 'Tatlı Düşler API',
        'version' => '1.0.0',
        'status' => 'active',
        'documentation' => url('/api/docs'),
        'endpoints' => [
            'products' => '/api/v1/urunler',
            'categories' => '/api/v1/kategoriler',
            'orders' => '/api/v1/siparisler',
            'reports' => '/api/v1/raporlar',
        ],
    ]);
});

// Include route files
require_once __DIR__ . '/routes/urunler.php';
require_once __DIR__ . '/routes/kategoriler.php';
require_once __DIR__ . '/routes/siparisler.php';
require_once __DIR__ . '/routes/auth.php';

// Get request path
$requestUri = $_SERVER['REQUEST_URI'];
$basePath = '/api/v1';

// Simple routing based on URI
$path = parse_url($requestUri, PHP_URL_PATH);
$path = str_replace($basePath, '', $path);
$method = $_SERVER['REQUEST_METHOD'];

// Rate limiting
$rateLimiter = new RateLimitMiddleware('api');
try {
    $rateLimiter->handle(function() use ($router, $method, $path) {
        // Dispatch request
        try {
            echo $router->dispatch($method, '/api/v1' . $path);
        } catch (\Pastane\Exceptions\HttpException $e) {
            http_response_code($e->getStatusCode());
            json_response($e->toArray(), $e->getStatusCode());
        }
    });
} catch (\Pastane\Exceptions\HttpException $e) {
    http_response_code($e->getStatusCode());
    json_response($e->toArray(), $e->getStatusCode());
}
