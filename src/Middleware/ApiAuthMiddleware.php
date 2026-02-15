<?php

declare(strict_types=1);

namespace Pastane\Middleware;

use Pastane\Exceptions\HttpException;

/**
 * API Auth Middleware
 *
 * JWT-based API authentication.
 *
 * @package Pastane\Middleware
 * @since 1.0.0
 */
class ApiAuthMiddleware implements MiddlewareInterface
{
    /**
     * @var array|null JWT payload
     */
    protected ?array $payload = null;

    /**
     * Handle the request
     *
     * @param callable $next
     * @return mixed
     * @throws HttpException
     */
    public function handle(callable $next): mixed
    {
        $payload = \JWT::requireAuth();

        if ($payload === null) {
            throw HttpException::unauthorized('Geçersiz veya eksik token.');
        }

        $this->payload = $payload;

        return $next();
    }

    /**
     * Get JWT payload
     *
     * @return array|null
     */
    public function getPayload(): ?array
    {
        return $this->payload;
    }

    /**
     * Get user ID from payload
     *
     * @return int|null
     */
    public function getUserId(): ?int
    {
        return isset($this->payload['user_id']) ? (int)$this->payload['user_id'] : null;
    }

    /**
     * Check if user has scope
     *
     * @param string $scope
     * @return bool
     */
    public function hasScope(string $scope): bool
    {
        if (!isset($this->payload['scopes'])) {
            return false;
        }

        return in_array($scope, $this->payload['scopes']);
    }

    /**
     * Require specific scope
     *
     * @param string $scope
     * @throws HttpException
     */
    public function requireScope(string $scope): void
    {
        if (!$this->hasScope($scope)) {
            throw HttpException::forbidden("Bu işlem için '{$scope}' yetkisi gerekli.");
        }
    }
}
