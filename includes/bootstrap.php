<?php
/**
 * Application Bootstrap
 *
 * Tüm entry point'ler bu dosya üzerinden yüklenir.
 * Yükleme sırası: config → db → security → classes → helpers → global error handler
 *
 * @package Pastane
 * @since 1.0.0
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

// Composer autoloader (PSR-4 + classmap sınıfları)
if (file_exists(BASE_PATH . '/vendor/autoload.php')) {
    require_once BASE_PATH . '/vendor/autoload.php';
}

// Temel config ve veritabanı
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/db.php';

// Güvenlik fonksiyonları (zorunlu — yoksa erken hata ver)
require_once BASE_PATH . '/includes/security.php';

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

// Helper fonksiyonlar
// helpers.php Composer files autoload ile de yüklenir ama require_once ile çift yükleme engellenir
require_once BASE_PATH . '/includes/helpers.php';
require_once BASE_PATH . '/includes/functions.php';

// ============================================
// GLOBAL ERROR/EXCEPTION HANDLER
// ============================================
// Tüm yakalanmamış exception'ları ve PHP error'larını yakalar.
// API context → JSON response, Admin/Frontend → HTML error page.
\Pastane\Exceptions\AppException::register(
    defined('DEBUG_MODE') && DEBUG_MODE
);

// Session'ı auth.php yönetecek, burada başlatmıyoruz
