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

        $this->allowedOrigins = $config['allowed_origins'] ?? ['*'];
        $this->allowedMethods = $config['allowed_methods'] ?? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
        $this->allowedHeaders = $config['allowed_headers'] ?? ['Content-Type', 'Authorization', 'X-Requested-With'];
        $this->allowCredentials = $config['allow_credentials'] ?? false;
        $this->maxAge = $config['max_age'] ?? 86400;
    }

    /**
     * Handle the request
     *
     * @param callable $next
     * @return mixed
     */
    public function handle(callable $next): mixed
    {
        $this->setHeaders();

        // Handle preflight
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
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
        if ($this->isOriginAllowed($origin)) {
            header("Access-Control-Allow-Origin: {$origin}");
        } elseif (in_array('*', $this->allowedOrigins)) {
            header('Access-Control-Allow-Origin: *');
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

        if (in_array('*', $this->allowedOrigins)) {
            return true;
        }

        foreach ($this->allowedOrigins as $allowed) {
            if ($allowed === $origin) {
                return true;
            }

            // Support wildcard subdomains
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
