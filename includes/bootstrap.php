<?php
/**
 * Application Bootstrap (Basitleştirilmiş)
 */

// Zaten yüklenmişse tekrar yükleme
if (defined('PASTANE_LOADED')) {
    return;
}

define('PASTANE_LOADED', true);

// Proje kök dizini
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

// Composer autoloader (Service sınıfları için gerekli)
if (file_exists(BASE_PATH . '/vendor/autoload.php')) {
    require_once BASE_PATH . '/vendor/autoload.php';
}

// Temel config ve veritabanı
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/db.php';

// Güvenlik fonksiyonları
if (file_exists(BASE_PATH . '/includes/security.php')) {
    require_once BASE_PATH . '/includes/security.php';
}

// Güvenlik sınıfları — Composer classmap ile autoload ediliyor (composer.json)
// Fallback: Composer autoload çalışmazsa manuel yükle
if (!class_exists('JWT', false)) {
    $securityClasses = [
        '/includes/JWT.php',
        '/includes/RateLimiter.php',
        '/includes/SecurityAudit.php',
        '/includes/TwoFactorAuth.php',
        '/includes/PasswordPolicy.php',
        '/includes/Logger.php',
        '/includes/Cache.php',
    ];
    foreach ($securityClasses as $classFile) {
        if (file_exists(BASE_PATH . $classFile)) {
            require_once BASE_PATH . $classFile;
        }
    }
}

// Helper fonksiyonlar (service fonksiyonları burada)
// helpers.php Composer files autoload ile de yüklenir ama require_once ile çift yükleme engellenir
require_once BASE_PATH . '/includes/helpers.php';
require_once BASE_PATH . '/includes/functions.php';

// Session'ı auth.php yönetecek, burada başlatmıyoruz
