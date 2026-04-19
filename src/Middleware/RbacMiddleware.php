<?php

declare(strict_types=1);

namespace Pastane\Middleware;

use Pastane\Exceptions\HttpException;
use Pastane\Services\PermissionService;

/**
 * RBAC Middleware
 *
 * Rol bazlı erişim kontrolü. Oturum açık olan kullanıcının
 * $requiredPermission iznine sahip olup olmadığını doğrular.
 *
 * Kullanım (router):
 *   $pipeline->pipe(new RbacMiddleware('product.create'));
 *
 * Kullanım (admin sayfaları, prosedürel):
 *   require_permission('product.view'); // includes/functions.php helper
 *
 * Session değeri beklentisi: $_SESSION['admin_id'] (int) — AuthMiddleware
 * ve admin/includes/auth.php ile uyumlu.
 *
 * @package Pastane\Middleware
 * @since 1.1.0
 */
class RbacMiddleware implements MiddlewareInterface
{
    /**
     * @var string Gereken izin (örn. 'product.create')
     */
    protected string $requiredPermission;

    /**
     * @var PermissionService
     */
    protected PermissionService $permissionService;

    /**
     * Constructor
     *
     * @param string $requiredPermission Gereken izin adı
     * @param PermissionService|null $permissionService DI (test için)
     */
    public function __construct(string $requiredPermission, ?PermissionService $permissionService = null)
    {
        $trimmed = trim($requiredPermission);
        if ($trimmed === '') {
            throw new \InvalidArgumentException('RbacMiddleware: requiredPermission boş olamaz.');
        }

        $this->requiredPermission = $trimmed;
        $this->permissionService = $permissionService ?? new PermissionService();
    }

    /**
     * Handle request
     *
     * @param callable $next
     * @return mixed
     * @throws HttpException 401 (oturum yok) veya 403 (yetki yok)
     */
    public function handle(callable $next): mixed
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : 0;
        if ($userId <= 0) {
            throw HttpException::unauthorized('Oturum açmanız gerekiyor.');
        }

        if (!$this->permissionService->hasPermission($userId, $this->requiredPermission)) {
            throw HttpException::forbidden(
                'Bu işlem için yetkiniz yok (' . $this->requiredPermission . ').'
            );
        }

        return $next();
    }

    /**
     * Gereken izin adı
     *
     * @return string
     */
    public function getRequiredPermission(): string
    {
        return $this->requiredPermission;
    }
}
