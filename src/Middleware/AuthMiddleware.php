<?php

declare(strict_types=1);

namespace Pastane\Middleware;

use Pastane\Exceptions\HttpException;

/**
 * Auth Middleware
 *
 * Session-based authentication kontrolü.
 *
 * @package Pastane\Middleware
 * @since 1.0.0
 */
class AuthMiddleware implements MiddlewareInterface
{
    /**
     * @var array|null Authenticated user
     */
    protected ?array $user = null;

    /**
     * Handle the request
     *
     * @param callable $next
     * @return mixed
     * @throws HttpException
     */
    public function handle(callable $next): mixed
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!$this->isAuthenticated()) {
            throw HttpException::unauthorized('Oturum açmanız gerekiyor.');
        }

        return $next();
    }

    /**
     * Check if user is authenticated
     *
     * @return bool
     */
    protected function isAuthenticated(): bool
    {
        return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
    }

    /**
     * Get authenticated user
     *
     * @return array|null
     */
    public function getUser(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        if ($this->user === null) {
            $this->user = $this->loadUser($_SESSION['admin_id']);
        }

        return $this->user;
    }

    /**
     * Load user from database
     *
     * @param int $id
     * @return array|null
     */
    protected function loadUser(int $id): ?array
    {
        $result = db()->fetch(
            "SELECT id, kullanici_adi, email, rol, created_at FROM admin_kullanicilar WHERE id = ?",
            [$id]
        );

        return $result ?: null;
    }

    /**
     * Require specific role
     *
     * @param string|array $roles
     * @return void
     * @throws HttpException
     */
    public function requireRole(string|array $roles): void
    {
        $user = $this->getUser();

        if (!$user) {
            throw HttpException::unauthorized();
        }

        $roles = is_array($roles) ? $roles : [$roles];

        if (!in_array($user['rol'], $roles)) {
            throw HttpException::forbidden('Bu işlem için yetkiniz yok.');
        }
    }

    /**
     * Static helper for quick auth check
     *
     * @return bool
     */
    public static function check(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
    }

    /**
     * Static helper to get current user ID
     *
     * @return int|null
     */
    public static function id(): ?int
    {
        if (!self::check()) {
            return null;
        }

        return (int)$_SESSION['admin_id'];
    }
}
