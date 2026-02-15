<?php

declare(strict_types=1);

namespace Pastane\Middleware;

use Pastane\Exceptions\HttpException;

/**
 * Rate Limit Middleware
 *
 * İstek hız sınırlama.
 *
 * @package Pastane\Middleware
 * @since 1.0.0
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    /**
     * @var string Rate limit action name
     */
    protected string $action;

    /**
     * Constructor
     *
     * @param string $action
     */
    public function __construct(string $action = 'api')
    {
        $this->action = $action;
    }

    /**
     * Handle the request
     *
     * @param callable $next
     * @return mixed
     * @throws HttpException
     */
    public function handle(callable $next): mixed
    {
        $result = \RateLimiter::check($this->action);

        // Add rate limit headers
        header("X-RateLimit-Limit: {$result['limit']}");
        header("X-RateLimit-Remaining: {$result['remaining']}");
        header("X-RateLimit-Reset: {$result['reset']}");

        if (!$result['allowed']) {
            $retryAfter = $result['reset'] - time();
            header("Retry-After: {$retryAfter}");

            throw HttpException::tooManyRequests(
                'Çok fazla istek gönderdiniz. Lütfen bekleyin.',
                $retryAfter
            );
        }

        // Record the hit
        \RateLimiter::hit($this->action);

        return $next();
    }

    /**
     * Create middleware for specific action
     *
     * @param string $action
     * @return static
     */
    public static function for(string $action): static
    {
        return new static($action);
    }
}
