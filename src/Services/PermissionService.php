<?php

declare(strict_types=1);

namespace Pastane\Services;

use PDO;

/**
 * Permission Service — RBAC
 *
 * Kullanıcıların rol bazlı izinlerini değerlendirir.
 * Konfigürasyon: config/permissions.php
 *
 * Kullanım:
 *   $svc = new PermissionService();
 *   if ($svc->hasPermission($userId, 'product.create')) { ... }
 *
 * Performans: Kullanıcı rolü per-request hafızada cache'lenir; aynı request
 * boyunca tekrar DB sorgusu yapılmaz.
 *
 * @package Pastane\Services
 * @since 1.1.0
 */
class PermissionService
{
    /**
     * @var array<string, array<string>> Rol → izin listesi (expanded wildcard)
     */
    protected array $roleMap = [];

    /**
     * @var array<string> Tanımlı tüm permission'lar
     */
    protected array $allPermissions = [];

    /**
     * @var string Varsayılan rol
     */
    protected string $defaultRole = 'viewer';

    /**
     * @var array<int, string|null> Per-request user role cache (id => rol)
     */
    protected array $userRoleCache = [];

    /**
     * Constructor
     *
     * @param array|null $config Test için override (prod: null → config yüklenir)
     */
    public function __construct(?array $config = null)
    {
        $config = $config ?? $this->loadConfig();

        $this->allPermissions = $config['permissions'] ?? [];
        $this->defaultRole    = $config['default_role'] ?? 'viewer';

        $roles = $config['roles'] ?? [];
        foreach ($roles as $role => $permissions) {
            $this->roleMap[$role] = $this->expandWildcards($permissions);
        }
    }

    /**
     * Kullanıcının belirli bir izni var mı?
     *
     * Fail-safe: tanımsız kullanıcı veya rol → false.
     *
     * @param int $userId admin_kullanicilar.id
     * @param string $permission İzin adı (örn. 'product.create')
     * @return bool
     */
    public function hasPermission(int $userId, string $permission): bool
    {
        if ($userId <= 0 || $permission === '') {
            return false;
        }

        $role = $this->getUserRole($userId);
        if ($role === null) {
            return false;
        }

        return $this->roleHasPermission($role, $permission);
    }

    /**
     * Bir rolün belirli bir izni var mı?
     *
     * @param string $role
     * @param string $permission
     * @return bool
     */
    public function roleHasPermission(string $role, string $permission): bool
    {
        if (!isset($this->roleMap[$role])) {
            return false;
        }

        return in_array($permission, $this->roleMap[$role], true);
    }

    /**
     * Rolün tüm (expanded) izinlerini döndür
     *
     * @param string $role
     * @return array<string>
     */
    public function getRolePermissions(string $role): array
    {
        return $this->roleMap[$role] ?? [];
    }

    /**
     * Tanımlı tüm permission'ları döndür
     *
     * @return array<string>
     */
    public function getAllPermissions(): array
    {
        return $this->allPermissions;
    }

    /**
     * Kullanıcı rolünü DB'den çek (per-request cache)
     *
     * @param int $userId
     * @return string|null Rol veya null (kullanıcı yoksa)
     */
    protected function getUserRole(int $userId): ?string
    {
        if (array_key_exists($userId, $this->userRoleCache)) {
            return $this->userRoleCache[$userId];
        }

        try {
            $row = db()->fetch(
                'SELECT rol FROM admin_kullanicilar WHERE id = :id LIMIT 1',
                ['id' => $userId]
            );
        } catch (\Throwable $e) {
            // DB/kolon hatası → fail-safe: null
            $this->userRoleCache[$userId] = null;
            return null;
        }

        if (!$row) {
            $this->userRoleCache[$userId] = null;
            return null;
        }

        $role = $row['rol'] ?? null;
        if (!is_string($role) || $role === '') {
            $role = $this->defaultRole;
        }

        $this->userRoleCache[$userId] = $role;
        return $role;
    }

    /**
     * Wildcard permission listesini expand et.
     *
     *   '*'         → allPermissions
     *   'product.*' → product.view, product.create, product.update, product.delete
     *
     * @param array<string> $permissions
     * @return array<string>
     */
    protected function expandWildcards(array $permissions): array
    {
        $expanded = [];

        foreach ($permissions as $perm) {
            if ($perm === '*') {
                return $this->allPermissions; // tümü
            }

            if (str_ends_with($perm, '.*')) {
                $prefix = substr($perm, 0, -1); // 'product.' (trailing dot kalır)
                foreach ($this->allPermissions as $p) {
                    if (str_starts_with($p, $prefix)) {
                        $expanded[] = $p;
                    }
                }
                continue;
            }

            $expanded[] = $perm;
        }

        return array_values(array_unique($expanded));
    }

    /**
     * config/permissions.php dosyasını yükle
     *
     * @return array
     */
    protected function loadConfig(): array
    {
        $path = dirname(__DIR__, 2) . '/config/permissions.php';
        if (!file_exists($path)) {
            return ['permissions' => [], 'roles' => [], 'default_role' => 'viewer'];
        }

        $config = require $path;
        return is_array($config) ? $config : [];
    }

    /**
     * Cache temizle (test veya rol değişiminden sonra)
     *
     * @param int|null $userId null → tümünü temizle
     * @return void
     */
    public function clearCache(?int $userId = null): void
    {
        if ($userId === null) {
            $this->userRoleCache = [];
            return;
        }
        unset($this->userRoleCache[$userId]);
    }
}
