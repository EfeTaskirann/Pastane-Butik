<?php

/**
 * RBAC — Rol ve İzin Konfigürasyonu
 *
 * Uygulama genelinde kullanılan permission (izin) listesi ve
 * rol → permission haritası.
 *
 * ROL ENUM'u (migration 2024_01_01_000001_create_initial_tables.php):
 *   admin_kullanicilar.rol ENUM('admin', 'editor', 'viewer') DEFAULT 'admin'
 *
 * Permission adlandırma kuralı: `kaynak.eylem` (örn. `product.create`).
 *
 * @package Pastane\Config
 * @since 1.1.0
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Tüm Permission'lar
    |--------------------------------------------------------------------------
    |
    | Uygulamada tanımlı izinlerin tamamı. PermissionService burada listelenen
    | izinleri tanır; liste dışı bir izin istendiğinde hasPermission() `false`
    | döner (fail-safe).
    */
    'permissions' => [
        // Ürün yönetimi
        'product.view',
        'product.create',
        'product.update',
        'product.delete',

        // Kategori yönetimi
        'category.view',
        'category.create',
        'category.update',
        'category.delete',

        // Sipariş yönetimi
        'order.view',
        'order.create',
        'order.update',
        'order.approve',
        'order.cancel',
        'order.delete',

        // Masa / QR menü yönetimi
        'table.view',
        'table.manage',

        // Raporlar
        'report.view',
        'report.export',

        // Kullanıcı yönetimi
        'user.view',
        'user.manage',

        // Ayarlar
        'setting.view',
        'setting.edit',

        // Güvenlik denetimi
        'audit.view',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rol → Permission Haritası
    |--------------------------------------------------------------------------
    |
    | 'admin'  => tüm izinler (wildcard '*').
    | 'editor' => içerik yönetimi (ürün/kategori/sipariş) + raporlar.
    | 'viewer' => salt-okunur (görüntüleme izinleri).
    |
    | Wildcard destekli:
    |   - '*'            => tüm izinler
    |   - 'product.*'    => product.view, product.create, product.update, product.delete
    */
    'roles' => [
        'admin' => ['*'],

        'editor' => [
            'product.*',
            'category.*',
            'order.view',
            'order.create',
            'order.update',
            'order.approve',
            'order.cancel',
            'table.view',
            'table.manage',
            'report.view',
        ],

        'viewer' => [
            'product.view',
            'category.view',
            'order.view',
            'table.view',
            'report.view',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Varsayılan Rol
    |--------------------------------------------------------------------------
    |
    | Kullanıcının rol kolonu boş veya tanımsız ise kullanılacak rol.
    | Fail-safe: 'viewer' (en az yetkili). Mevcut sistem geriye uyumluluk için
    | migration default'unu ('admin') korur ama yeni kullanıcılar için
    | 'viewer' önerilir.
    */
    'default_role' => 'viewer',
];
