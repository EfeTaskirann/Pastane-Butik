<?php
declare(strict_types=1);

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

// Sentry — native client (library-free). SENTRY_DSN boşsa no-op.
if (!class_exists('Sentry', false) && file_exists(BASE_PATH . '/includes/Sentry.php')) {
    require_once BASE_PATH . '/includes/Sentry.php';
}
if (class_exists('Sentry', false)) {
    Sentry::init();
}

// Metrics — Prometheus uyumlu file-backed counter/gauge/histogram
if (!class_exists('Metrics', false) && file_exists(BASE_PATH . '/includes/Metrics.php')) {
    require_once BASE_PATH . '/includes/Metrics.php';
}

// Helper fonksiyonlar
// helpers.php Composer files autoload ile de yüklenir ama require_once ile çift yükleme engellenir
require_once BASE_PATH . '/includes/helpers.php';
require_once BASE_PATH . '/includes/functions.php';

// I18n — Composer files autoload ile de yuklenir; fallback require_once
if (!class_exists('I18n', false) && file_exists(BASE_PATH . '/includes/i18n.php')) {
    require_once BASE_PATH . '/includes/i18n.php';
}
// ?lang=xx yakala, aktif locale'i belleke yukle (session yoksa cookie/default kullanir)
if (class_exists('I18n', false)) {
    I18n::load();
}

// ============================================
// GLOBAL ERROR/EXCEPTION HANDLER
// ============================================
// Tüm yakalanmamış exception'ları ve PHP error'larını yakalar.
// API context → JSON response, Admin/Frontend → HTML error page.
\Pastane\Exceptions\AppException::register(
    defined('DEBUG_MODE') && DEBUG_MODE
);

// Session'ı auth.php yönetecek, burada başlatmıyoruz
