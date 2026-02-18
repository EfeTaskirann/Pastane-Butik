<?php

declare(strict_types=1);

namespace Pastane\Middleware;

/**
 * CORS Middleware
 *
 * Cross-Origin Resource Sharing headers.
 *
 * @package Pastane\Middleware
 * @since 1.0.0
 */
class CorsMiddleware implements MiddlewareInterface
{
    /**
     * @var array Allowed origins
     */
    protected array $allowedOrigins;

    /**
     * @var array Allowed methods
     */
    protected array $allowedMethods;

    /**
     * @var array Allowed headers
     */
    protected array $allowedHeaders;

    /**
     * @var bool Allow credentials
     */
    protected bool $allowCredentials;

    /**
     * @var int Max age in seconds
     */
    protected int $maxAge;

    /**
     * Constructor
     *
     * @param array|null $config
     */
    public function __construct(?array $config = null)
    {
        $config = $config ?? config('security.cors', []);

        // GÜVENLİK: Wildcard '*' yerine .env'den APP_URL kullanılır
        // Production'da mutlaka spesifik origin'ler tanımlanmalıdır
        $defaultOrigin = env('APP_URL', 'http://localhost/pastane');
        $this->allowedOrigins = $config['allowed_origins'] ?? [$defaultOrigin];
        $this->allowedMethods = $config['allowed_methods'] ?? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
        $this->allowedHeaders = $config['allowed_headers'] ?? ['Content-Type', 'Authorization', 'X-Requested-With'];
        $this->allowCredentials = $config['allow_credentials'] ?? true;
        $this->maxAge = $config['max_age'] ?? 86400;
    }

    /**
     * Handle the request
     *
     * Preflight (OPTIONS) istekleri burada cevaplanır ve sonlandırılır.
     * Normal isteklerde CORS header'ları set edilip $next çağrılır.
     *
     * @param callable $next
     * @return mixed
     */
    public function handle(callable $next): mixed
    {
        $this->setHeaders();

        // Handle preflight — OPTIONS isteğine 204 ile cevap ver
        // Bu durumda exit gerekli çünkü preflight'ın body'si olmamalı
        // ve router'a girmemeli. Bu bir "request termination", exception değil.
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            return '';
        }

        return $next();
    }

    /**
     * Set CORS headers
     *
     * @return void
     */
    protected function setHeaders(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // Check if origin is allowed
        // GÜVENLİK: Wildcard '*' fallback kaldırıldı.
        // Sadece açıkça izin verilen origin'lere Access-Control-Allow-Origin gönderilir.
        if ($this->isOriginAllowed($origin)) {
            header("Access-Control-Allow-Origin: {$origin}");
        }

        header('Access-Control-Allow-Methods: ' . implode(', ', $this->allowedMethods));
        header('Access-Control-Allow-Headers: ' . implode(', ', $this->allowedHeaders));
        header("Access-Control-Max-Age: {$this->maxAge}");

        if ($this->allowCredentials) {
            header('Access-Control-Allow-Credentials: true');
        }

        // Expose custom headers
        header('Access-Control-Expose-Headers: X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset');
    }

    /**
     * Check if origin is allowed
     *
     * @param string $origin
     * @return bool
     */
    protected function isOriginAllowed(string $origin): bool
    {
        if (empty($origin)) {
            return false;
        }

        foreach ($this->allowedOrigins as $allowed) {
            if ($allowed === $origin) {
                return true;
            }

            // Support wildcard subdomains (e.g., *.example.com)
            if (str_starts_with($allowed, '*.')) {
                $pattern = '/^https?:\/\/([a-z0-9-]+\.)?' . preg_quote(substr($allowed, 2), '/') . '$/i';
                if (preg_match($pattern, $origin)) {
                    return true;
                }
            }
        }

        return false;
    }
}
